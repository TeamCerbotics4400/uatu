<?php

namespace App\Services;

use App\Models\Matches;
use App\Models\MxTask;
use App\Models\ServiceTask;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TelegramBot
{
    private const FLOW_TTL = 1800;

    private const MATCH_SLOTS = ['blue_1', 'blue_2', 'blue_3', 'red_1', 'red_2', 'red_3'];

    private const MX_TYPES = [
        'PIT' => 'Pit',
        'MATCH' => 'Match',
        'CHECKLIST' => 'Checklist',
        'PIT_CHECKLIST' => 'Pit + Checklist',
    ];

    private const SERVICES = ['MECHANICAL', 'PROGRAMMING', 'BOTH', 'NONE'];

    private const PARTICIPANT_LABELS = [
        'ASSIGNED' => 'asignado',
        'ACTIVE' => 'activo',
        'SUSPENDED' => 'suspendido',
        'DONE' => 'terminado',
    ];

    public function __construct(
        private TelegramService $telegram,
        private TaskStateMachine $tasks,
    ) {
    }

    public function handleUpdate(array $update): void
    {
        if (isset($update['callback_query'])) {
            $this->handleCallback($update['callback_query']);
        } elseif (isset($update['inline_query'])) {
            $this->handleInlineQuery($update['inline_query']);
        } elseif (isset($update['message'])) {
            $this->handleMessage($update['message']);
        }
    }

    // -------------------------------------------------------------------
    // Entrada
    // -------------------------------------------------------------------

    private function handleMessage(array $message): void
    {
        $chatId = (string) $message['chat']['id'];
        $text = trim($message['text'] ?? '');

        if ($text === '/start') {
            $this->askWho($chatId);
            return;
        }

        $user = $this->currentUser($chatId);

        if (!$user) {
            $this->askWho($chatId);
            return;
        }

        if ($text === '/menu') {
            $this->showMenu($chatId, $user);
            return;
        }

        $flow = $this->flow($chatId);

        if ($flow && $flow['flow'] === 'match' && $flow['step'] === 'number') {
            $this->matchNumberReceived($chatId, $text);
            return;
        }

        if ($flow && $flow['flow'] === 'match' && $flow['step'] === 'teams') {
            $this->adminOnly($user, $chatId, fn () => $this->matchTemplateReceived($chatId, $flow, $text));
            return;
        }

        if ($flow && $flow['flow'] === 'st' && $flow['step'] === 'match_number') {
            $this->adminOnly($user, $chatId, fn () => $this->stMatchNumberReceived($chatId, $flow, $text));
            return;
        }

        if (isset($message['via_bot']) && preg_match('/^Equipo: (.+)$/u', $text, $m)) {
            $this->inlineTeamChosen($chatId, $user, $flow, trim($m[1]));
            return;
        }

        $this->send($chatId, 'No entendi ese mensaje. Usa /menu para ver las opciones o /start para cambiar de usuario.');
    }

    // -------------------------------------------------------------------
    // Busqueda inline de equipos (@bot texto)
    // -------------------------------------------------------------------

    private function handleInlineQuery(array $query): void
    {
        $chatId = (string) $query['from']['id'];
        $text = trim($query['query'] ?? '');
        $flow = $this->flow($chatId);

        $teams = $this->teamCandidates($flow)
            ->when($text !== '', fn ($q) => $q->where('name', 'ilike', '%' . str_replace(['%', '_'], ['\%', '\_'], $text) . '%'))
            ->orderBy('name')
            ->limit(50)
            ->get();

        $results = $teams->map(fn (Team $t) => [
            'type' => 'article',
            'id' => $t->id,
            'title' => $t->name,
            'description' => 'Seleccionar este equipo',
            'input_message_content' => ['message_text' => "Equipo: {$t->name}"],
        ])->values()->all();

        $this->telegram->answerInlineQuery($query['id'], $results);
    }

    private function teamCandidates(?array $flow)
    {
        $query = Team::query();

        if (!$flow) {
            return $query;
        }

        if ($flow['flow'] === 'st' && $flow['step'] === 'team' && ($match = Matches::find($flow['data']['match_id'] ?? 0))) {
            return $query->whereIn('id', array_filter([$match->blue_1, $match->blue_2, $match->blue_3, $match->red_1, $match->red_2, $match->red_3]));
        }

        return $query;
    }

    private function inlineTeamChosen(string $chatId, User $user, ?array $flow, string $name): void
    {
        $team = $this->teamCandidates($flow)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();

        if (!$flow || !$team) {
            $this->send($chatId, 'Ese equipo no aplica en este paso. Usa /menu para empezar de nuevo.');
            return;
        }

        if ($flow['flow'] === 'st' && $flow['step'] === 'team') {
            $this->adminOnly($user, $chatId, fn () => $this->stTeamSelected($chatId, $flow, $team));
            return;
        }

        $this->send($chatId, 'Ese equipo no aplica en este paso. Usa /menu para empezar de nuevo.');
    }

    private function searchTeamButton(): array
    {
        return ['text' => 'Buscar equipo', 'switch_inline_query_current_chat' => ''];
    }

    private function handleCallback(array $callback): void
    {
        $chatId = (string) ($callback['message']['chat']['id'] ?? $callback['from']['id']);
        $data = $callback['data'] ?? '';

        $this->telegram->answerCallbackQuery($callback['id']);

        $parts = explode(':', $data);
        $action = array_shift($parts);

        if ($action === 'who') {
            $this->identify($chatId, $parts[0] ?? '', $callback['from']['username'] ?? null);
            return;
        }

        $user = $this->currentUser($chatId);

        if (!$user) {
            $this->askWho($chatId);
            return;
        }

        match ($action) {
            'menu' => $this->showMenu($chatId, $user),
            'who_menu' => $this->askWho($chatId),
            'cancel' => $this->cancelFlow($chatId, $user),
            'status' => $this->adminOnly($user, $chatId, fn () => $this->checkStatus($chatId)),
            'match' => $this->adminOnly($user, $chatId, fn () => $this->matchFlow($chatId, $parts)),
            'task' => $this->adminOnly($user, $chatId, fn () => $this->chooseTaskType($chatId)),
            'mx' => $this->adminOnly($user, $chatId, fn () => $this->mxFlow($chatId, $parts)),
            'st' => $this->adminOnly($user, $chatId, fn () => $this->stFlow($chatId, $parts)),
            'my' => $this->myTasks($chatId, $user, $parts[0] ?? 'st'),
            'map' => $this->pitMap($chatId),
            'act' => $this->taskAction($chatId, $user, $parts),
            default => $this->showMenu($chatId, $user),
        };
    }

    // -------------------------------------------------------------------
    // Identidad y menus
    // -------------------------------------------------------------------

    private function askWho(string $chatId): void
    {
        $users = $this->selectableUsers()->get();

        $buttons = $users
            ->map(fn (User $u) => ['text' => $u->name, 'callback_data' => "who:{$u->id}"])
            ->chunk(3)
            ->map(fn ($row) => $row->values()->all())
            ->values()
            ->all();

        $this->send($chatId, 'Quien eres?', $buttons);
    }

    private function identify(string $chatId, string $userId, ?string $username): void
    {
        $user = User::find($userId);

        if (!$user) {
            $this->askWho($chatId);
            return;
        }

        User::where('telegram_chat_id', $chatId)
            ->where('id', '!=', $user->id)
            ->update(['telegram_chat_id' => null]);

        $user->update([
            'telegram_chat_id' => $chatId,
            'telegram_username' => $username,
        ]);

        $this->clearFlow($chatId);

        $role = $user->is_admin ? 'ADMIN' : 'USUARIO';
        $this->send($chatId, "Hola {$this->h($user->name)}. Entraste como <b>{$role}</b>.");
        $this->showMenu($chatId, $user);
    }

    private function showMenu(string $chatId, User $user): void
    {
        $this->clearFlow($chatId);

        if ($user->is_admin) {
            $this->send($chatId, "<b>MENU ADMIN</b>\nQue deseas hacer?", [
                [
                    ['text' => 'Crear Match', 'callback_data' => 'match:new'],
                    ['text' => 'Checar Estado', 'callback_data' => 'status'],
                ],
                [
                    ['text' => 'Crear Tarea', 'callback_data' => 'task'],
                    ['text' => 'Mis Tareas', 'callback_data' => 'my:st'],
                ],
                [['text' => 'Cambiar Usuario', 'callback_data' => 'who_menu']],
            ]);
            return;
        }

        $this->send($chatId, "<b>MENU</b>\nQue deseas ver?", [
            [
                ['text' => 'Mis ServiceTasks', 'callback_data' => 'my:st'],
                ['text' => 'Mis MxTasks', 'callback_data' => 'my:mx'],
            ],
            [
                ['text' => 'Mapa de Pits', 'callback_data' => 'map'],
                ['text' => 'Cambiar Usuario', 'callback_data' => 'who_menu'],
            ],
        ]);
    }

    private function adminOnly(User $user, string $chatId, callable $fn): void
    {
        if (!$user->is_admin) {
            $this->send($chatId, 'Esta opcion es solo para admins.');
            $this->showMenu($chatId, $user);
            return;
        }

        $fn();
    }

    private function cancelFlow(string $chatId, User $user): void
    {
        $this->clearFlow($chatId);
        $this->send($chatId, 'Operacion cancelada.');
        $this->showMenu($chatId, $user);
    }

    // -------------------------------------------------------------------
    // Admin: checar estado
    // -------------------------------------------------------------------

    private function checkStatus(string $chatId): void
    {
        $lines = User::orderBy('name')->get()->map(function (User $u) {
            return "{$this->h($u->name)} - {$u->status} - {$this->h($u->getCurrentTaskDisplay())}";
        });

        $this->send($chatId, "<b>ESTADO DE USUARIOS</b>\n\n" . $lines->implode("\n"), [
            [['text' => 'Volver al Menu', 'callback_data' => 'menu']],
        ]);
    }

    // -------------------------------------------------------------------
    // Admin: crear match
    // -------------------------------------------------------------------

    private function matchFlow(string $chatId, array $parts): void
    {
        $step = $parts[0] ?? '';

        if ($step === 'new') {
            $this->setFlow($chatId, ['flow' => 'match', 'step' => 'number', 'data' => []]);
            $this->send($chatId, "<b>CREAR MATCH</b>\nEscribe el numero del match.", [
                [['text' => 'Cancelar', 'callback_data' => 'cancel']],
            ]);
            return;
        }

        if ($step === 'retry') {
            $flow = $this->flow($chatId);

            if (!$flow || $flow['flow'] !== 'match' || $flow['step'] !== 'teams') {
                $this->send($chatId, 'El flujo de crear match expiro. Empieza de nuevo.');
                $this->showMenu($chatId, $this->currentUser($chatId));
                return;
            }

            $this->sendMatchTemplate($chatId, $flow);
        }
    }

    private function matchNumberReceived(string $chatId, string $text): void
    {
        if (!ctype_digit($text)) {
            $this->send($chatId, 'El numero del match debe ser un entero. Intenta de nuevo.', [
                [['text' => 'Cancelar', 'callback_data' => 'cancel']],
            ]);
            return;
        }

        if (Matches::where('number', (int) $text)->exists()) {
            $this->send($chatId, "Ya existe el match #{$text}. Escribe otro numero.", [
                [['text' => 'Cancelar', 'callback_data' => 'cancel']],
            ]);
            return;
        }

        $flow = ['flow' => 'match', 'step' => 'teams', 'data' => ['number' => (int) $text]];
        $this->setFlow($chatId, $flow);
        $this->sendMatchTemplate($chatId, $flow);
    }

    private function sendMatchTemplate(string $chatId, array $flow): void
    {
        $template = implode("\n", array_map(
            fn ($slot) => $this->slotLabel($slot) . ': ',
            self::MATCH_SLOTS
        ));

        $this->send(
            $chatId,
            "<b>Match #{$flow['data']['number']}</b>\n"
            . "Copia la plantilla, escribe el nombre de cada equipo despues de los dos puntos y mandala de vuelta.\n\n"
            . "<pre>{$template}</pre>",
            [[['text' => 'Cancelar', 'callback_data' => 'cancel']]]
        );
    }

    private function matchTemplateReceived(string $chatId, array $flow, string $text): void
    {
        $values = [];

        foreach (preg_split('/\R/u', $text) as $line) {
            if (preg_match('/^\s*(blue|red)\s*[_ ]?\s*([123])\s*[:\-]\s*(.*?)\s*$/iu', $line, $m)) {
                $values[strtolower($m[1]) . '_' . $m[2]] = $m[3];
            }
        }

        if (!$values) {
            $this->send($chatId, 'No reconoci la plantilla. Copiala tal cual y llena cada linea.', $this->retryButtons());
            return;
        }

        $errors = [];
        $teamIds = [];
        $usedNames = [];
        $corrections = [];

        foreach (self::MATCH_SLOTS as $slot) {
            $label = $this->slotLabel($slot);
            $name = trim($values[$slot] ?? '');

            if ($name === '') {
                $errors[] = "{$label}: falta el equipo";
                continue;
            }

            $resolved = $this->resolveTeam($name);

            if ($resolved['error']) {
                $errors[] = "{$label}: {$resolved['error']}";
                continue;
            }

            $team = $resolved['team'];

            if ($resolved['corrected']) {
                $corrections[] = "{$label}: {$this->h($name)} -> {$this->h($team->name)}";
            }

            if (isset($usedNames[$team->id])) {
                $errors[] = "{$label}: {$this->h($team->name)} ya esta en {$usedNames[$team->id]}";
                continue;
            }

            $usedNames[$team->id] = $label;
            $teamIds[$slot] = $team->id;
        }

        if ($errors) {
            $this->send(
                $chatId,
                "<b>No se pudo crear el match</b>\n\n" . implode("\n", $errors)
                . "\n\nCorrige y manda la plantilla de nuevo.",
                $this->retryButtons()
            );
            return;
        }

        $match = Matches::create(['number' => $flow['data']['number']] + $teamIds);

        $summary = $this->matchSummary($match);

        if ($corrections) {
            $summary .= "\n\n<i>Correcciones automaticas:</i>\n" . implode("\n", $corrections);
        }

        $this->clearFlow($chatId);
        $this->send($chatId, "<b>Match creado</b>\n\n" . $summary, [
            [
                ['text' => 'Crear otro Match', 'callback_data' => 'match:new'],
                ['text' => 'Volver al Menu', 'callback_data' => 'menu'],
            ],
        ]);
    }

    /**
     * Devuelve ['team' => Team|null, 'error' => string|null, 'corrected' => bool].
     * Acepta nombre exacto, fragmento unico, o el equipo mas parecido cuando
     * la diferencia es pequena (errores de dedo, acentos, mayusculas).
     */
    private function resolveTeam(string $name): array
    {
        $key = $this->normalizeName($name);
        $teams = Team::orderBy('name')->get();
        $ok = fn (Team $t) => ['team' => $t, 'error' => null, 'corrected' => strcasecmp($t->name, $name) !== 0];
        $ambiguous = fn ($list) => ['team' => null, 'error' => "\"{$this->h($name)}\" es ambiguo (" . $list->map(fn (Team $t) => $this->h($t->name))->implode(', ') . ')', 'corrected' => false];

        $exact = $teams->first(fn (Team $t) => $this->normalizeName($t->name) === $key);
        if ($exact) {
            return $ok($exact);
        }

        $partial = $teams->filter(fn (Team $t) => str_contains($this->normalizeName($t->name), $key));
        if ($partial->count() === 1) {
            return $ok($partial->first());
        }
        if ($partial->count() > 1) {
            return $ambiguous($partial->take(4));
        }

        $maxDistance = max(1, (int) floor(strlen($key) * 0.34));
        $scored = $teams
            ->map(fn (Team $t) => ['team' => $t, 'distance' => levenshtein($key, $this->normalizeName($t->name))])
            ->filter(fn ($row) => $row['distance'] <= $maxDistance)
            ->sortBy('distance')
            ->values();

        if ($scored->isEmpty()) {
            return ['team' => null, 'error' => "\"{$this->h($name)}\" no existe", 'corrected' => false];
        }

        $best = $scored->first()['distance'];
        $ties = $scored->filter(fn ($row) => $row['distance'] === $best);

        if ($ties->count() > 1) {
            return $ambiguous($ties->pluck('team')->take(4));
        }

        return $ok($scored->first()['team']);
    }

    private function normalizeName(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', Str::ascii(mb_strtolower(trim($value))));
    }

    private function retryButtons(): array
    {
        return [[
            ['text' => 'Intentar nuevamente', 'callback_data' => 'match:retry'],
            ['text' => 'Cancelar', 'callback_data' => 'cancel'],
        ]];
    }

    private function slotLabel(string $slot): string
    {
        return strtoupper(str_replace('_', ' ', $slot));
    }

    // -------------------------------------------------------------------
    // Admin: crear tarea
    // -------------------------------------------------------------------

    private function chooseTaskType(string $chatId): void
    {
        $this->clearFlow($chatId);
        $this->send($chatId, "<b>CREAR TAREA</b>\nQue tipo de tarea?", [
            [
                ['text' => 'MxTask', 'callback_data' => 'mx:new'],
                ['text' => 'ServiceTask', 'callback_data' => 'st:new'],
            ],
            [['text' => 'Cancelar', 'callback_data' => 'cancel']],
        ]);
    }

    private function mxFlow(string $chatId, array $parts): void
    {
        $step = $parts[0] ?? '';

        if ($step === 'new') {
            $this->setFlow($chatId, ['flow' => 'mx', 'step' => 'type', 'data' => ['users' => []]]);

            $buttons = collect(self::MX_TYPES)
                ->map(fn ($label, $type) => ['text' => $label, 'callback_data' => "mx:type:{$type}"])
                ->values()
                ->chunk(2)
                ->map(fn ($row) => $row->values()->all())
                ->all();
            $buttons[] = [['text' => 'Cancelar', 'callback_data' => 'cancel']];

            $this->send($chatId, "<b>CREAR MXTASK</b>\nSelecciona el tipo:", $buttons);
            return;
        }

        $flow = $this->flow($chatId);

        if (!$flow || $flow['flow'] !== 'mx') {
            $this->send($chatId, 'El flujo de crear MxTask expiro. Empieza de nuevo.');
            $this->showMenu($chatId, $this->currentUser($chatId));
            return;
        }

        if ($step === 'type') {
            $type = $parts[1] ?? '';

            if (!isset(self::MX_TYPES[$type])) {
                return;
            }

            $flow['data']['type'] = $type;
            $flow['step'] = 'user_1';
            $this->setFlow($chatId, $flow);
            $this->askMxUser($chatId, $flow, 1);
            return;
        }

        if ($step === 'user') {
            $n = (int) ($parts[1] ?? 0);
            $choice = $parts[2] ?? '';

            if ($n < 1 || $n > 4) {
                return;
            }

            if ($choice !== 'skip') {
                $flow['data']['users'][$n] = $choice;
            }

            if ($n < 4) {
                $flow['step'] = 'user_' . ($n + 1);
                $this->setFlow($chatId, $flow);
                $this->askMxUser($chatId, $flow, $n + 1);
                return;
            }

            $this->createMxTask($chatId, $flow['data']);
        }
    }

    private function askMxUser(string $chatId, array $flow, int $n): void
    {
        $chosen = array_values($flow['data']['users']);

        $buttons = $this->selectableUsers()
            ->whereNotIn('id', $chosen)
            ->get()
            ->map(fn (User $u) => ['text' => "{$u->name} ({$u->status})", 'callback_data' => "mx:user:{$n}:{$u->id}"])
            ->chunk(2)
            ->map(fn ($row) => $row->values()->all())
            ->values()
            ->all();

        $last = [['text' => 'Cancelar', 'callback_data' => 'cancel']];
        if ($n > 1) {
            array_unshift($last, ['text' => 'Skip', 'callback_data' => "mx:user:{$n}:skip"]);
        }
        $buttons[] = $last;

        $label = self::MX_TYPES[$flow['data']['type']];
        $this->send($chatId, "MxTask <b>{$label}</b>\nAsigna Usuario {$n}:", $buttons);
    }

    private function createMxTask(string $chatId, array $data): void
    {
        $users = $data['users'];

        $task = MxTask::create([
            'type' => $data['type'],
            'status' => 'PENDING',
            'assigned_user_1' => $users[1] ?? null,
            'assigned_user_2' => $users[2] ?? null,
            'assigned_user_3' => $users[3] ?? null,
            'assigned_user_4' => $users[4] ?? null,
        ]);

        User::whereIn('id', array_values($users))->update(['status' => 'BUSY']);

        $this->clearFlow($chatId);
        $this->send($chatId, "<b>MxTask creada</b>\n\n" . $this->mxTaskCard($task), [
            [
                ['text' => 'Crear otra Tarea', 'callback_data' => 'task'],
                ['text' => 'Volver al Menu', 'callback_data' => 'menu'],
            ],
        ]);

    }

    private function stFlow(string $chatId, array $parts): void
    {
        $step = $parts[0] ?? '';

        if ($step === 'new') {
            $numbers = Matches::orderBy('number')->pluck('number');

            if ($numbers->isEmpty()) {
                $this->clearFlow($chatId);
                $this->send($chatId, 'No hay matches creados. Crea uno primero.', [
                    [
                        ['text' => 'Crear Match', 'callback_data' => 'match:new'],
                        ['text' => 'Volver al Menu', 'callback_data' => 'menu'],
                    ],
                ]);
                return;
            }

            $this->setFlow($chatId, ['flow' => 'st', 'step' => 'match_number', 'data' => []]);

            $this->send(
                $chatId,
                "<b>CREAR SERVICETASK</b>\nEscribe el numero del match.\n\nRegistrados: " . $numbers->implode(', '),
                [[['text' => 'Cancelar', 'callback_data' => 'cancel']]]
            );
            return;
        }

        $flow = $this->flow($chatId);

        if (!$flow || $flow['flow'] !== 'st') {
            $this->send($chatId, 'El flujo de crear ServiceTask expiro. Empieza de nuevo.');
            $this->showMenu($chatId, $this->currentUser($chatId));
            return;
        }

        if ($step === 'match') {
            $match = Matches::find($parts[1] ?? 0);

            if ($match) {
                $this->stMatchSelected($chatId, $flow, $match);
            }
            return;
        }

        if ($step === 'team') {
            $team = Team::find($parts[1] ?? '');

            if ($team) {
                $this->stTeamSelected($chatId, $flow, $team);
            }
            return;
        }

        if ($step === 'prio') {
            $p = (int) ($parts[1] ?? 0);

            if ($p < 1 || $p > 7) {
                return;
            }

            $flow['data']['priority'] = (string) $p;
            $flow['step'] = 'service';
            $this->setFlow($chatId, $flow);

            $buttons = array_map(
                fn ($s) => [['text' => $s, 'callback_data' => "st:svc:{$s}"]],
                self::SERVICES
            );
            $buttons[] = [['text' => 'Cancelar', 'callback_data' => 'cancel']];

            $this->send($chatId, 'Servicio requerido:', $buttons);
            return;
        }

        if ($step === 'svc') {
            $svc = $parts[1] ?? '';

            if (!in_array($svc, self::SERVICES, true)) {
                return;
            }

            $flow['data']['required_service'] = $svc;
            $flow['data']['users'] = [];
            $flow['step'] = 'user_1';
            $this->setFlow($chatId, $flow);
            $this->askStUser($chatId, $flow, 1);
            return;
        }

        if ($step === 'user') {
            $n = (int) ($parts[1] ?? 0);
            $choice = $parts[2] ?? '';

            if ($n < 1 || $n > 3 || $flow['step'] !== "user_{$n}") {
                return;
            }

            if ($choice === 'none') {
                if ($n === 1) {
                    $this->createServiceTask($chatId, $flow['data']);
                }
                return;
            }

            if ($choice !== 'skip') {
                $flow['data']['users'][$n] = $choice;
            }

            if ($n < 3) {
                $flow['step'] = 'user_' . ($n + 1);
                $this->setFlow($chatId, $flow);
                $this->askStUser($chatId, $flow, $n + 1);
                return;
            }

            $this->createServiceTask($chatId, $flow['data']);
        }
    }

    private function askStUser(string $chatId, array $flow, int $n): void
    {
        $chosen = array_values($flow['data']['users']);

        $buttons = $this->selectableUsers()
            ->whereNotIn('id', $chosen)
            ->get()
            ->map(fn (User $u) => ['text' => "{$u->name} ({$u->status})", 'callback_data' => "st:user:{$n}:{$u->id}"])
            ->chunk(2)
            ->map(fn ($row) => $row->values()->all())
            ->values()
            ->all();

        $buttons[] = [
            $n === 1
                ? ['text' => 'Sin asignar', 'callback_data' => 'st:user:1:none']
                : ['text' => 'Skip', 'callback_data' => "st:user:{$n}:skip"],
            ['text' => 'Cancelar', 'callback_data' => 'cancel'],
        ];

        $this->send($chatId, "Asigna Usuario {$n} de 3:", $buttons);
    }

    private function stMatchNumberReceived(string $chatId, array $flow, string $text): void
    {
        $retry = [[['text' => 'Cancelar', 'callback_data' => 'cancel']]];
        $number = ltrim(trim($text), '#');

        if (!ctype_digit($number)) {
            $this->send($chatId, 'Escribe solo el numero del match.', $retry);
            return;
        }

        $match = Matches::where('number', (int) $number)->first();

        if (!$match) {
            $registered = Matches::orderBy('number')->pluck('number')->implode(', ');
            $this->send($chatId, "No existe el match #{$number}.\nRegistrados: {$registered}", $retry);
            return;
        }

        $this->stMatchSelected($chatId, $flow, $match);
    }

    private function stMatchSelected(string $chatId, array $flow, Matches $match): void
    {
        $flow['data']['match_id'] = $match->id;
        $flow['step'] = 'team';
        $this->setFlow($chatId, $flow);

        $teamIds = array_filter([$match->blue_1, $match->blue_2, $match->blue_3, $match->red_1, $match->red_2, $match->red_3]);
        $names = Team::whereIn('id', $teamIds)->pluck('name', 'id');

        $buttons = [];
        foreach (self::MATCH_SLOTS as $slot) {
            $id = $match->{$slot};
            if ($id && isset($names[$id])) {
                $buttons[] = [['text' => "{$names[$id]} ({$this->slotLabel($slot)})", 'callback_data' => "st:team:{$id}"]];
            }
        }

        if (!$buttons) {
            $this->clearFlow($chatId);
            $this->send($chatId, "El match #{$match->number} no tiene equipos registrados.", [
                [['text' => 'Volver al Menu', 'callback_data' => 'menu']],
            ]);
            return;
        }

        $buttons[] = [$this->searchTeamButton(), ['text' => 'Cancelar', 'callback_data' => 'cancel']];

        $this->send($chatId, "<b>Match #{$match->number}</b>\nSelecciona el equipo:", $buttons);
    }

    private function stTeamSelected(string $chatId, array $flow, Team $team): void
    {
        $flow['data']['team_id'] = $team->id;
        $flow['step'] = 'priority';
        $this->setFlow($chatId, $flow);

        $buttons = array_map(
            fn ($row) => array_map(fn ($p) => ['text' => (string) $p, 'callback_data' => "st:prio:{$p}"], $row),
            [[1, 2, 3, 4], [5, 6, 7]]
        );
        $buttons[] = [['text' => 'Cancelar', 'callback_data' => 'cancel']];

        $this->send($chatId, "Equipo <b>{$this->h($team->name)}</b>\nPrioridad (1 critica, 7 muy baja):", $buttons);
    }

    private function createServiceTask(string $chatId, array $data): void
    {
        $users = $data['users'] ?? [];

        // Sin eventos: el observer notificaria con estado PENDING antes de que
        // toAssigned corra. Aqui se notifica a mano con el estado final.
        $task = ServiceTask::withoutEvents(fn () => ServiceTask::create([
            'status' => 'PENDING',
            'assigned_team' => $data['team_id'],
            'match_id' => $data['match_id'],
            'priority' => $data['priority'],
            'required_service' => $data['required_service'],
            'assigned_user_1' => $users[1] ?? null,
            'assigned_user_2' => $users[2] ?? null,
            'assigned_user_3' => $users[3] ?? null,
        ]));

        if ($users) {
            $this->tasks->toAssigned($task);
            $task->refresh();
            $this->notifyAssigned($task, array_values($users));
        }

        $this->clearFlow($chatId);
        $this->send($chatId, "<b>ServiceTask creada</b>\n\n" . $this->serviceTaskCard($task), [
            [
                ['text' => 'Crear otra Tarea', 'callback_data' => 'task'],
                ['text' => 'Volver al Menu', 'callback_data' => 'menu'],
            ],
        ]);
    }

    // -------------------------------------------------------------------
    // Usuario: mis tareas y acciones
    // -------------------------------------------------------------------

    private function myTasks(string $chatId, User $user, string $kind): void
    {
        $this->clearFlow($chatId);

        if ($kind === 'mx') {
            $tasks = MxTask::whereIn('status', ['PENDING', 'IN_PROGRESS', 'BLOCKED'])
                ->where(function ($q) use ($user) {
                    $q->where('assigned_user_1', $user->id)
                        ->orWhere('assigned_user_2', $user->id)
                        ->orWhere('assigned_user_3', $user->id)
                        ->orWhere('assigned_user_4', $user->id);
                })
                ->orderBy('created_at')
                ->get();

            if ($tasks->isEmpty()) {
                $this->send($chatId, 'No tienes MxTasks activas.', [[['text' => 'Volver al Menu', 'callback_data' => 'menu']]]);
                return;
            }

            $this->send($chatId, "<b>TUS MXTASKS</b> ({$tasks->count()})");
            foreach ($tasks as $task) {
                $this->send($chatId, $this->mxTaskCard($task), $this->mxTaskButtons($task));
            }
            return;
        }

        $tasks = ServiceTask::with(['team', 'match', 'participants.user'])
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id)->where('status', '!=', 'DONE'))
            ->whereNotIn('status', ['COMPLETED', 'CANCELLED'])
            ->orderBy('priority')
            ->get();

        if ($tasks->isEmpty()) {
            $this->send($chatId, 'No tienes ServiceTasks pendientes.', [[['text' => 'Volver al Menu', 'callback_data' => 'menu']]]);
            return;
        }

        $this->send($chatId, "<b>TUS SERVICETASKS</b> ({$tasks->count()})");
        foreach ($tasks as $task) {
            $this->send($chatId, $this->serviceTaskCard($task, $user), $this->serviceTaskButtons($task, $user));
        }
    }

    private function taskAction(string $chatId, User $user, array $parts): void
    {
        [$kind, $id, $op] = array_pad($parts, 3, '');

        if ($kind === 'st') {
            $task = ServiceTask::with(['team', 'match', 'participants.user'])->find($id);

            if (!$task || !$task->participantFor($user)) {
                $this->send($chatId, 'Esa tarea no existe o no esta asignada a ti.');
                return;
            }

            $this->serviceTaskAction($chatId, $user, $task, $op);
            return;
        }

        if ($kind === 'mx') {
            $task = MxTask::find($id);
            $mine = $task && in_array($user->id, [$task->assigned_user_1, $task->assigned_user_2, $task->assigned_user_3, $task->assigned_user_4], true);

            if (!$mine) {
                $this->send($chatId, 'Esa tarea no existe o no esta asignada a ti.');
                return;
            }

            $this->mxTaskAction($chatId, $user, $task, $op);
        }
    }

    private function serviceTaskAction(string $chatId, User $user, ServiceTask $task, string $op): void
    {
        $back = ['text' => 'Volver a la tarea', 'callback_data' => "act:st:{$task->id}:show"];

        switch ($op) {
            case 'show':
                break;

            case 'start':
                $active = $this->tasks->activeParticipationFor($user);

                if ($active && $active->service_task_id !== $task->id) {
                    $team = $this->h($active->task?->team?->name ?? '-');
                    $this->send(
                        $chatId,
                        "Ya estas activo en la ServiceTask #{$active->service_task_id} ({$team}). Suspendela primero para poder trabajar en esta.",
                        [[
                            ['text' => "Ver tarea #{$active->service_task_id}", 'callback_data' => "act:st:{$active->service_task_id}:show"],
                            $back,
                        ]]
                    );
                    return;
                }

                if (!$this->requireTransition($chatId, $this->tasks->userStart($task, $user), 'iniciar')) {
                    return;
                }
                break;

            case 'suspend':
                if (!$this->requireTransition($chatId, $this->tasks->userSuspend($task, $user), 'suspender')) {
                    return;
                }
                $this->send($chatId, 'Suspendiste tu parte. Quedas AVAILABLE; puedes reanudarla cuando quieras desde Mis ServiceTasks.');
                break;

            case 'done':
                $participant = $task->participantFor($user);
                $this->send($chatId, "Terminar tu parte de la ServiceTask #{$task->id}?\nTu tiempo: " . ($this->tasks->getParticipantElapsedTime($participant) ?? '-'), [
                    [
                        ['text' => 'Confirmar', 'callback_data' => "act:st:{$task->id}:done_ok"],
                        $back,
                    ],
                ]);
                return;

            case 'done_ok':
                if (!$this->requireTransition($chatId, $this->tasks->userComplete($task, $user), 'terminar')) {
                    return;
                }

                $task->refresh()->load(['team', 'match', 'participants.user']);

                if ($task->status === 'COMPLETED') {
                    $this->send($chatId, "<b>Tarea completada por todos</b>\n\n" . $this->serviceTaskCard($task, $user), $this->afterTaskButtons('st'));
                    return;
                }

                $pending = $task->participants
                    ->where('status', '!=', 'DONE')
                    ->map(fn ($p) => $this->h($p->user?->name ?? '?') . ' (' . self::PARTICIPANT_LABELS[$p->status] . ')')
                    ->implode(', ');

                $this->send($chatId, "<b>Terminaste tu parte</b>\nLa tarea sigue abierta para: {$pending}.\n\n" . $this->serviceTaskCard($task, $user), $this->afterTaskButtons('st'));
                return;

            case 'block':
                $this->requireTransition($chatId, $this->tasks->toBlocked($task), 'bloquear');
                break;

            case 'unblock':
                $this->requireTransition($chatId, $this->tasks->toUnblocked($task), 'desbloquear');
                break;

            case 'cancel':
                $this->send($chatId, "Seguro que quieres cancelar la ServiceTask #{$task->id} para todos?\nEsta accion no se puede deshacer.", [
                    [
                        ['text' => 'Confirmar cancelacion', 'callback_data' => "act:st:{$task->id}:cancel_ok"],
                        $back,
                    ],
                ]);
                return;

            case 'cancel_ok':
                if (!$this->requireTransition($chatId, $this->tasks->toCancelled($task), 'cancelar')) {
                    return;
                }
                $this->send($chatId, "<b>Tarea cancelada</b>\n\n" . $this->serviceTaskCard($task->refresh(), $user), $this->afterTaskButtons('st'));
                return;

            case 'help':
                $this->requestHelp($chatId, $user, "ServiceTask #{$task->id} ({$this->h($task->team?->name ?? '-')}, Match #{$task->match?->number})");
                break;

            default:
                return;
        }

        $task->refresh()->load(['team', 'match', 'participants.user']);
        $this->send($chatId, $this->serviceTaskCard($task, $user), $this->serviceTaskButtons($task, $user));
    }

    private function mxTaskAction(string $chatId, User $user, MxTask $task, string $op): void
    {
        $back = [['text' => 'Volver a la tarea', 'callback_data' => "act:mx:{$task->id}:show"]];

        switch ($op) {
            case 'show':
                break;

            case 'start':
                $this->requireTransition($chatId, $this->tasks->mxStart($task), 'iniciar');
                break;

            case 'block':
                $this->requireTransition($chatId, $this->tasks->mxBlock($task), 'bloquear');
                break;

            case 'unblock':
                $this->requireTransition($chatId, $this->tasks->mxUnblock($task), 'reanudar');
                break;

            case 'complete':
                $this->send($chatId, "Completar la MxTask #{$task->id}?\nDuracion: {$this->mxElapsed($task)}", [
                    [
                        ['text' => 'Confirmar', 'callback_data' => "act:mx:{$task->id}:complete_ok"],
                        $back[0],
                    ],
                ]);
                return;

            case 'complete_ok':
                if (!$this->requireTransition($chatId, $this->tasks->mxComplete($task), 'completar')) {
                    return;
                }
                $this->send($chatId, "<b>Tarea completada</b>\n\n" . $this->mxTaskCard($task->refresh()), $this->afterTaskButtons('mx'));
                return;

            case 'cancel':
                $this->send($chatId, "Seguro que quieres cancelar la MxTask #{$task->id}?\nEsta accion no se puede deshacer.", [
                    [
                        ['text' => 'Confirmar cancelacion', 'callback_data' => "act:mx:{$task->id}:cancel_ok"],
                        $back[0],
                    ],
                ]);
                return;

            case 'cancel_ok':
                if (!$this->requireTransition($chatId, $this->tasks->mxCancel($task), 'cancelar')) {
                    return;
                }
                $this->send($chatId, "<b>Tarea cancelada</b>\n\n" . $this->mxTaskCard($task->refresh()), $this->afterTaskButtons('mx'));
                return;

            case 'help':
                $this->requestHelp($chatId, $user, "MxTask #{$task->id} ({$task->type})");
                break;

            default:
                return;
        }

        $this->send($chatId, $this->mxTaskCard($task->refresh()), $this->mxTaskButtons($task));
    }

    private function requireTransition(string $chatId, mixed $result, string $verb): bool
    {
        if ($result === false) {
            $this->send($chatId, "No se puede {$verb} la tarea en su estado actual.");
            return false;
        }

        return true;
    }

    private function requestHelp(string $chatId, User $user, string $taskLabel): void
    {
        $user->update(['status' => 'NEEDS_HELP']);

        $admins = User::where('is_admin', true)
            ->whereNotNull('telegram_chat_id')
            ->where('id', '!=', $user->id)
            ->get();

        foreach ($admins as $admin) {
            $this->telegram->sendToUser($admin, "<b>{$this->h($user->name)} pide ayuda</b> en {$taskLabel}.");
        }

        $count = $admins->count();
        $this->send($chatId, $count > 0
            ? "Se aviso a {$count} admin(s). Tu estado paso a NEEDS_HELP."
            : 'Ningun admin tiene Telegram vinculado todavia. Tu estado paso a NEEDS_HELP.');
    }

    private function pitMap(string $chatId): void
    {
        $path = public_path(config('telegram.pit_map'));
        $buttons = [[['text' => 'Volver al Menu', 'callback_data' => 'menu']]];

        if (!is_file($path)) {
            $this->send($chatId, 'Todavia no hay mapa de pits cargado. Sube la imagen a public/' . config('telegram.pit_map') . '.', $buttons);
            return;
        }

        $this->telegram->sendPhoto($chatId, $path, 'Mapa de Pits', $buttons);
    }

    // -------------------------------------------------------------------
    // Notificaciones a asignados
    // -------------------------------------------------------------------

    public function notifyAssigned(ServiceTask|MxTask $task, array $userIds): void
    {
        $recipients = User::whereIn('id', $userIds)->whereNotNull('telegram_chat_id')->get();

        if ($recipients->isEmpty()) {
            return;
        }

        if ($task instanceof ServiceTask) {
            $task->load(['team', 'match', 'participants.user']);

            foreach ($recipients as $user) {
                $this->send($user->telegram_chat_id, "<b>Te asignaron una ServiceTask</b>\n\n" . $this->serviceTaskCard($task, $user), $this->serviceTaskButtons($task, $user));
            }

            return;
        }

        $text = "<b>Te asignaron una MxTask</b>\n\n" . $this->mxTaskCard($task);
        $buttons = $this->mxTaskButtons($task);

        foreach ($recipients as $user) {
            $this->send($user->telegram_chat_id, $text, $buttons);
        }
    }

    // -------------------------------------------------------------------
    // Tarjetas y botones
    // -------------------------------------------------------------------

    private function serviceTaskCard(ServiceTask $task, ?User $viewer = null): string
    {
        $task->loadMissing(['team', 'match', 'participants.user']);

        $people = $task->participants
            ->map(fn ($p) => $this->h($p->user?->name ?? '?') . ' (' . (self::PARTICIPANT_LABELS[$p->status] ?? $p->status) . ')')
            ->implode(', ');

        $lines = [
            "<b>ServiceTask #{$task->id}</b>",
            "Match: #" . ($task->match?->number ?? '-'),
            "Equipo: {$this->h($task->team?->name ?? '-')}",
            "Prioridad: {$task->priority}",
            "Servicio: {$task->required_service}",
            "Usuarios: " . ($people !== '' ? $people : 'sin asignar'),
            "Estado: <b>{$task->status}</b>",
            "Inicio: " . ($task->started_at?->format('H:i:s') ?? '-'),
            "Fin: " . ($task->completed_at?->format('H:i:s') ?? '-'),
            "Duracion: {$this->elapsed($task)}",
        ];

        $me = $viewer ? $task->participantFor($viewer) : null;

        if ($me) {
            $lines[] = '';
            $lines[] = "Tu estado: <b>" . self::PARTICIPANT_LABELS[$me->status] . "</b>";
            $lines[] = "Tu tiempo: " . ($this->tasks->getParticipantElapsedTime($me) ?? '-');
        }

        return implode("\n", $lines);
    }

    private function serviceTaskButtons(ServiceTask $task, ?User $viewer = null): array
    {
        $id = $task->id;
        $me = $viewer ? $task->participantFor($viewer) : null;
        $rows = [];

        if ($me && !in_array($task->status, ['COMPLETED', 'CANCELLED'])) {
            $cancel = ['text' => 'Cancelar tarea', 'callback_data' => "act:st:{$id}:cancel"];
            $help = ['text' => 'Pedir ayuda', 'callback_data' => "act:st:{$id}:help"];

            if ($task->status === 'BLOCKED') {
                $rows[] = [['text' => 'Desbloquear', 'callback_data' => "act:st:{$id}:unblock"], $cancel];
                if ($me->status === 'ACTIVE') {
                    $rows[] = [['text' => 'Suspender', 'callback_data' => "act:st:{$id}:suspend"], $help];
                } else {
                    $rows[] = [$help];
                }
            } elseif ($me->status === 'ASSIGNED') {
                $rows[] = [['text' => 'Iniciar', 'callback_data' => "act:st:{$id}:start"], $cancel];
                $rows[] = [$help];
            } elseif ($me->status === 'ACTIVE') {
                $rows[] = [
                    ['text' => 'Suspender', 'callback_data' => "act:st:{$id}:suspend"],
                    ['text' => 'Terminar mi parte', 'callback_data' => "act:st:{$id}:done"],
                ];
                $rows[] = [['text' => 'Bloquear', 'callback_data' => "act:st:{$id}:block"], $cancel];
                $rows[] = [$help];
            } elseif ($me->status === 'SUSPENDED') {
                $rows[] = [
                    ['text' => 'Reanudar', 'callback_data' => "act:st:{$id}:start"],
                    ['text' => 'Terminar mi parte', 'callback_data' => "act:st:{$id}:done"],
                ];
                $rows[] = [$cancel, $help];
            }
        }

        $rows[] = [['text' => 'Volver al Menu', 'callback_data' => 'menu']];

        return $rows;
    }

    private function mxTaskCard(MxTask $task): string
    {
        $ids = [$task->assigned_user_1, $task->assigned_user_2, $task->assigned_user_3, $task->assigned_user_4];
        $names = User::whereIn('id', array_filter($ids))->pluck('name', 'id');
        $assigned = collect($ids)->map(fn ($id) => $id ? $this->h($names[$id] ?? '?') : '-')->implode(', ');

        return implode("\n", [
            "<b>MxTask #{$task->id}</b>",
            "Tipo: " . (self::MX_TYPES[$task->type] ?? $task->type),
            "Usuarios: {$assigned}",
            "Estado: <b>{$task->status}</b>",
            "Inicio: " . ($task->started_at?->format('H:i:s') ?? '-'),
            "Fin: " . ($task->completed_at?->format('H:i:s') ?? '-'),
            "Duracion: {$this->mxElapsed($task)}",
        ]);
    }

    private function mxTaskButtons(MxTask $task): array
    {
        $id = $task->id;

        $rows = match ($task->status) {
            'PENDING' => [[
                ['text' => 'Iniciar', 'callback_data' => "act:mx:{$id}:start"],
                ['text' => 'Cancelar', 'callback_data' => "act:mx:{$id}:cancel"],
            ]],
            'IN_PROGRESS' => [
                [
                    ['text' => 'Completar', 'callback_data' => "act:mx:{$id}:complete"],
                    ['text' => 'Bloquear', 'callback_data' => "act:mx:{$id}:block"],
                ],
                [
                    ['text' => 'Cancelar', 'callback_data' => "act:mx:{$id}:cancel"],
                    ['text' => 'Pedir ayuda', 'callback_data' => "act:mx:{$id}:help"],
                ],
            ],
            'BLOCKED' => [
                [
                    ['text' => 'Reanudar', 'callback_data' => "act:mx:{$id}:unblock"],
                    ['text' => 'Completar', 'callback_data' => "act:mx:{$id}:complete"],
                ],
                [
                    ['text' => 'Cancelar', 'callback_data' => "act:mx:{$id}:cancel"],
                    ['text' => 'Pedir ayuda', 'callback_data' => "act:mx:{$id}:help"],
                ],
            ],
            default => [],
        };

        $rows[] = [['text' => 'Volver al Menu', 'callback_data' => 'menu']];

        return $rows;
    }

    private function afterTaskButtons(string $kind): array
    {
        return [[
            ['text' => 'Ver mis tareas', 'callback_data' => "my:{$kind}"],
            ['text' => 'Volver al Menu', 'callback_data' => 'menu'],
        ]];
    }

    private function matchSummary(Matches $match): string
    {
        $ids = array_filter([$match->blue_1, $match->blue_2, $match->blue_3, $match->red_1, $match->red_2, $match->red_3]);
        $names = Team::whereIn('id', $ids)->pluck('name', 'id');
        $n = fn ($id) => $this->h($names[$id] ?? '-');

        return implode("\n", [
            "<b>MATCH #{$match->number}</b>",
            "AZUL: {$n($match->blue_1)} | {$n($match->blue_2)} | {$n($match->blue_3)}",
            "ROJO: {$n($match->red_1)} | {$n($match->red_2)} | {$n($match->red_3)}",
        ]);
    }

    private function elapsed(ServiceTask $task): string
    {
        return $this->tasks->getElapsedTime($task) ?? '-';
    }

    private function mxElapsed(MxTask $task): string
    {
        return $this->tasks->getMxTaskElapsedTime($task) ?? '-';
    }

    // -------------------------------------------------------------------
    // Utilidades
    // -------------------------------------------------------------------

    private function currentUser(string $chatId): ?User
    {
        return User::where('telegram_chat_id', $chatId)->first();
    }

    private function selectableUsers()
    {
        return User::orderBy('name')->whereNotIn('name', config('telegram.hidden_users'));
    }

    private function send(string $chatId, string $text, array $buttons = []): void
    {
        $this->telegram->sendWithInlineKeyboard($chatId, $text, $buttons);
    }

    private function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function flow(string $chatId): ?array
    {
        return Cache::get("tg:flow:{$chatId}");
    }

    private function setFlow(string $chatId, array $flow): void
    {
        Cache::put("tg:flow:{$chatId}", $flow, self::FLOW_TTL);
    }

    private function clearFlow(string $chatId): void
    {
        Cache::forget("tg:flow:{$chatId}");
    }
}

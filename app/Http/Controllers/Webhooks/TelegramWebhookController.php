<?php

namespace App\Http\Controllers\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController
{
    public function handle(Request $request)
    {
        $update = $request->all();

        Log::info('Telegram webhook recibido:', $update);

        if (isset($update['callback_query'])) {
            return $this->handleButtonClick($update['callback_query']);
        }

        if (isset($update['message'])) {
            return $this->handleMessage($update['message']);
        }

        return response()->json(['ok' => true]);
    }

    private function handleButtonClick(array $callback)
    {
        $chatId = $callback['from']['id'];
        $callbackData = $callback['data'];
        $callbackQueryId = $callback['id'];

        if (str_starts_with($callbackData, 'user_')) {
            $response = $this->userSelectedResponse(substr($callbackData, 5));
        } else {
            $response = $this->responses()[$callbackData] ?? null;
        }

        if ($response) {
            $this->sendMessage($chatId, $response['text'], $response['buttons']);
            $this->answerCallbackQuery($callbackQueryId, 'Opcion seleccionada');
        } else {
            $this->sendMessage(
                $chatId,
                "PRUEBA: presionaste un boton sin respuesta configurada.\n\ncallback_data recibido: {$callbackData}",
                [[['text' => 'Volver al inicio', 'callback_data' => 'back']]]
            );
            $this->answerCallbackQuery($callbackQueryId, 'Opcion no reconocida');
        }

        return response()->json(['ok' => true]);
    }

    private const ADMINS = ['Raul', 'Ivan', 'David', 'Mike', 'Hugo'];

    private const USERS = ['Santiago', 'Barbie', 'Martha', 'Isabella', 'Danna', 'Ernesto', 'Abril', 'Enevi'];

    private function userSelectedResponse(string $slug): array
    {
        $name = ucfirst($slug);
        $isAdmin = in_array($name, self::ADMINS, true);

        if ($isAdmin) {
            return [
                'text' => "PRUEBA: Hola {$name}.\n\nEres ADMIN, se activa el menu de administrador.",
                'buttons' => [[['text' => 'Ir al Menu Admin', 'callback_data' => 'admin_menu']]],
            ];
        }

        return [
            'text' => "PRUEBA: Hola {$name}.\n\nEres USUARIO, se activa el menu normal.",
            'buttons' => [[['text' => 'Ir al Menu Usuario', 'callback_data' => 'user_menu']]],
        ];
    }

    private function responses(): array
    {
        return [
            'admin_menu' => [
                'text' => "PRUEBA: MENU ADMIN\n\nQue deseas hacer?",
                'buttons' => [
                    [
                        ['text' => 'Crear Match', 'callback_data' => 'create_match'],
                        ['text' => 'Checar Estado', 'callback_data' => 'check_status'],
                    ],
                    [
                        ['text' => 'Crear Tarea', 'callback_data' => 'create_task'],
                        ['text' => 'Cambiar Usuario', 'callback_data' => 'back'],
                    ],
                ],
            ],
            'user_menu' => [
                'text' => "PRUEBA: MENU USUARIO\n\nQue deseas ver?",
                'buttons' => [
                    [
                        ['text' => 'Mis ServiceTasks', 'callback_data' => 'my_tasks'],
                        ['text' => 'Mis MxTasks', 'callback_data' => 'my_mxtasks'],
                    ],
                    [
                        ['text' => 'Mapa de Pits', 'callback_data' => 'pit_map'],
                        ['text' => 'Cambiar Usuario', 'callback_data' => 'back'],
                    ],
                ],
            ],
            'create_match' => [
                'text' => "PRUEBA: CREAR MATCH\n\nAqui se pediria el numero del match y luego blue1, blue2, blue3, red1, red2, red3.",
                'buttons' => [
                    [
                        ['text' => 'Simular Match creado', 'callback_data' => 'match_created'],
                        ['text' => 'Cancelar', 'callback_data' => 'admin_menu'],
                    ],
                ],
            ],
            'match_created' => [
                'text' => "PRUEBA: Match creado.\n\nMATCH #42\nAZUL: Team A | Team B | Team C\nROJO: Team D | Team E | Team F",
                'buttons' => [
                    [
                        ['text' => 'Crear otro Match', 'callback_data' => 'create_match'],
                        ['text' => 'Volver al Menu', 'callback_data' => 'admin_menu'],
                    ],
                ],
            ],
            'check_status' => [
                'text' => "PRUEBA: ESTADO DE USUARIOS\n\nRaul - BUSY - Match #42 Team A\nIvan - BUSY - Pit Checklist\nDavid - AVAILABLE - sin tarea\nSantiago - BUSY - ServiceTask #5\nBarbie - BLOCKED - Match #41 Team B",
                'buttons' => [[['text' => 'Volver al Menu', 'callback_data' => 'admin_menu']]],
            ],
            'create_task' => [
                'text' => "PRUEBA: CREAR TAREA\n\nQue tipo de tarea quieres crear?",
                'buttons' => [
                    [
                        ['text' => 'MxTask', 'callback_data' => 'create_mxtask'],
                        ['text' => 'ServiceTask', 'callback_data' => 'create_servicetask'],
                    ],
                    [['text' => 'Cancelar', 'callback_data' => 'admin_menu']],
                ],
            ],
            'create_mxtask' => [
                'text' => "PRUEBA: CREAR MXTASK\n\nSelecciona el tipo:",
                'buttons' => [
                    [
                        ['text' => 'Pit', 'callback_data' => 'mx_type_pit'],
                        ['text' => 'Match', 'callback_data' => 'mx_type_match'],
                    ],
                    [
                        ['text' => 'Checklist', 'callback_data' => 'mx_type_checklist'],
                        ['text' => 'Pit + Checklist', 'callback_data' => 'mx_type_pit_checklist'],
                    ],
                    [['text' => 'Cancelar', 'callback_data' => 'create_task']],
                ],
            ],
            'mx_type_pit' => $this->mxTypeSelected('PIT'),
            'mx_type_match' => $this->mxTypeSelected('MATCH'),
            'mx_type_checklist' => $this->mxTypeSelected('CHECKLIST'),
            'mx_type_pit_checklist' => $this->mxTypeSelected('PIT + CHECKLIST'),
            'mx_created' => [
                'text' => "PRUEBA: MxTask creada.\n\nTipo: PIT\nEstado: PENDING\nUsuarios: Raul, Ivan, -, -",
                'buttons' => [
                    [
                        ['text' => 'Crear otra Tarea', 'callback_data' => 'create_task'],
                        ['text' => 'Volver al Menu', 'callback_data' => 'admin_menu'],
                    ],
                ],
            ],
            'create_servicetask' => [
                'text' => "PRUEBA: CREAR SERVICETASK\n\nSelecciona el Match:",
                'buttons' => [
                    [
                        ['text' => 'Match #42', 'callback_data' => 'st_match_42'],
                        ['text' => 'Match #43', 'callback_data' => 'st_match_43'],
                    ],
                    [['text' => 'Cancelar', 'callback_data' => 'create_task']],
                ],
            ],
            'st_match_42' => $this->serviceTaskMatchSelected(42),
            'st_match_43' => $this->serviceTaskMatchSelected(43),
            'st_team_selected' => [
                'text' => "PRUEBA: Equipo seleccionado: Team A\n\nPrioridad: 2 (ALTA)\nServicio requerido: Pit Scout + Drive Check\n\nAsigna un usuario:",
                'buttons' => [
                    [
                        ['text' => 'Santiago', 'callback_data' => 'st_created'],
                        ['text' => 'Barbie', 'callback_data' => 'st_created'],
                    ],
                    [
                        ['text' => 'Sin asignar', 'callback_data' => 'st_created'],
                        ['text' => 'Cancelar', 'callback_data' => 'create_task'],
                    ],
                ],
            ],
            'st_created' => [
                'text' => "PRUEBA: ServiceTask creada.\n\nMatch: #42\nEquipo: Team A\nPrioridad: 2 (ALTA)\nUsuario: Santiago\nEstado: PENDING",
                'buttons' => [
                    [
                        ['text' => 'Crear otra Tarea', 'callback_data' => 'create_task'],
                        ['text' => 'Volver al Menu', 'callback_data' => 'admin_menu'],
                    ],
                ],
            ],
            'my_tasks' => [
                'text' => "PRUEBA: TUS SERVICETASKS\n\n#456 - Match #42 - Team A - Prioridad ALTA - ASSIGNED\n#457 - Match #42 - Team B - Prioridad MEDIA - COMPLETED",
                'buttons' => [
                    [
                        ['text' => 'Iniciar #456', 'callback_data' => 'task_start'],
                        ['text' => 'Cancelar #456', 'callback_data' => 'task_cancel_confirm'],
                    ],
                    [['text' => 'Volver al Menu', 'callback_data' => 'user_menu']],
                ],
            ],
            'my_mxtasks' => [
                'text' => "PRUEBA: TUS MXTASKS\n\n#123 - PIT - IN_PROGRESS - Raul, Ivan - 00:15:20\n#124 - MATCH - PENDING - David, Mike, Santiago",
                'buttons' => [
                    [
                        ['text' => 'Iniciar #124', 'callback_data' => 'task_start'],
                        ['text' => 'Volver al Menu', 'callback_data' => 'user_menu'],
                    ],
                ],
            ],
            'pit_map' => [
                'text' => "PRUEBA: MAPA DE PITS\n\nAqui se enviaria la imagen del mapa.",
                'buttons' => [[['text' => 'Volver al Menu', 'callback_data' => 'user_menu']]],
            ],
            'task_start' => [
                'text' => "PRUEBA: TAREA INICIADA\n\n#456 - Match #42 - Team A\nEstado: IN_PROGRESS\nInicio: 14:40:00\nDuracion: 00:00 (contando)",
                'buttons' => $this->inProgressButtons(),
            ],
            'task_block' => [
                'text' => "PRUEBA: TAREA BLOQUEADA\n\n#456\nEstado: BLOCKED\nDuracion hasta bloqueo: 00:02:10",
                'buttons' => [
                    [
                        ['text' => 'Reanudar', 'callback_data' => 'task_resume'],
                        ['text' => 'Completar', 'callback_data' => 'task_complete_confirm'],
                    ],
                    [
                        ['text' => 'Cancelar', 'callback_data' => 'task_cancel_confirm'],
                        ['text' => 'Pedir ayuda', 'callback_data' => 'task_help'],
                    ],
                ],
            ],
            'task_resume' => [
                'text' => "PRUEBA: TAREA REANUDADA\n\n#456\nEstado: IN_PROGRESS\nTiempo detenido: 00:02:45",
                'buttons' => $this->inProgressButtons(),
            ],
            'task_help' => [
                'text' => "PRUEBA: PEDIR AYUDA\n\nSe notificaria a los admins que necesitas apoyo en la tarea #456.",
                'buttons' => [[['text' => 'Volver a la tarea', 'callback_data' => 'task_start']]],
            ],
            'task_edit' => [
                'text' => "PRUEBA: EDITAR TAREA\n\nAqui se permitiria modificar datos de la tarea #456.",
                'buttons' => [[['text' => 'Volver a la tarea', 'callback_data' => 'task_start']]],
            ],
            'task_complete_confirm' => [
                'text' => "PRUEBA: Completar esta tarea?\n\n#456\nDuracion: 00:05:32",
                'buttons' => [
                    [
                        ['text' => 'Confirmar', 'callback_data' => 'task_completed'],
                        ['text' => 'Cancelar', 'callback_data' => 'task_start'],
                    ],
                ],
            ],
            'task_completed' => [
                'text' => "PRUEBA: TAREA COMPLETADA\n\n#456\nEstado: COMPLETED\nInicio: 14:40:00\nFin: 14:45:32\nDuracion: 00:05:32\nUsuario: Santiago pasa a AVAILABLE",
                'buttons' => [
                    [
                        ['text' => 'Ver mis tasks', 'callback_data' => 'my_tasks'],
                        ['text' => 'Volver al Menu', 'callback_data' => 'user_menu'],
                    ],
                ],
            ],
            'task_cancel_confirm' => [
                'text' => "PRUEBA: Seguro que quieres cancelar la tarea #456?\n\nEsta accion no se puede deshacer.",
                'buttons' => [
                    [
                        ['text' => 'Confirmar cancelacion', 'callback_data' => 'task_cancelled'],
                        ['text' => 'No, volver', 'callback_data' => 'task_start'],
                    ],
                ],
            ],
            'task_cancelled' => [
                'text' => "PRUEBA: TAREA CANCELADA\n\n#456\nEstado: CANCELLED\nInicio: 14:40:00\nCancelada: 14:43:15\nDuracion: 00:03:15\nUsuario: Santiago pasa a AVAILABLE",
                'buttons' => [
                    [
                        ['text' => 'Ver mis tasks', 'callback_data' => 'my_tasks'],
                        ['text' => 'Volver al Menu', 'callback_data' => 'user_menu'],
                    ],
                ],
            ],
            'back' => [
                'text' => "Quien eres?",
                'buttons' => $this->userSelectionButtons(),
            ],
            'cancel' => [
                'text' => 'Cancelado. Escribe /start para comenzar de nuevo.',
                'buttons' => [],
            ],
        ];
    }

    private function mxTypeSelected(string $type): array
    {
        return [
            'text' => "PRUEBA: Tipo seleccionado: {$type}\n\nAsigna Usuario 1 (o Skip):",
            'buttons' => [
                [
                    ['text' => 'Raul', 'callback_data' => 'mx_created'],
                    ['text' => 'Ivan', 'callback_data' => 'mx_created'],
                ],
                [
                    ['text' => 'Skip', 'callback_data' => 'mx_created'],
                    ['text' => 'Cancelar', 'callback_data' => 'create_task'],
                ],
            ],
        ];
    }

    private function serviceTaskMatchSelected(int $number): array
    {
        return [
            'text' => "PRUEBA: Match #{$number} seleccionado.\n\nSelecciona el equipo:",
            'buttons' => [
                [
                    ['text' => 'Team A (Blue 1)', 'callback_data' => 'st_team_selected'],
                    ['text' => 'Team B (Blue 2)', 'callback_data' => 'st_team_selected'],
                ],
                [
                    ['text' => 'Team D (Red 1)', 'callback_data' => 'st_team_selected'],
                    ['text' => 'Cancelar', 'callback_data' => 'create_task'],
                ],
            ],
        ];
    }

    private function inProgressButtons(): array
    {
        return [
            [
                ['text' => 'Completar', 'callback_data' => 'task_complete_confirm'],
                ['text' => 'Bloquear', 'callback_data' => 'task_block'],
            ],
            [
                ['text' => 'Cancelar', 'callback_data' => 'task_cancel_confirm'],
                ['text' => 'Editar', 'callback_data' => 'task_edit'],
            ],
            [
                ['text' => 'Pedir ayuda', 'callback_data' => 'task_help'],
                ['text' => 'Volver', 'callback_data' => 'my_tasks'],
            ],
        ];
    }

    private function userSelectionButtons(): array
    {
        $names = array_merge(self::ADMINS, self::USERS);
        $buttons = [];

        foreach (array_chunk($names, 3) as $row) {
            $buttons[] = array_map(
                fn (string $name) => ['text' => $name, 'callback_data' => 'user_' . strtolower($name)],
                $row
            );
        }

        return $buttons;
    }

    private function handleMessage(array $message)
    {
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';

        if ($text === '/start') {
            $this->sendMessage($chatId, 'Hola! Quien eres?', $this->userSelectionButtons());
        } else {
            $this->sendMessage(
                $chatId,
                "PRUEBA: recibi tu mensaje: \"{$text}\"\n\nEscribe /start para ver el menu.",
                []
            );
        }

        return response()->json(['ok' => true]);
    }

    private function sendMessage(string $chatId, string $text, array $buttons)
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode([
                'inline_keyboard' => $buttons
            ])
        ];

        $token = config('telegram.bot_token');
        $url = "https://api.telegram.org/bot{$token}/sendMessage";

        $client = new \GuzzleHttp\Client();
        $client->post($url, ['json' => $payload]);
    }

    private function answerCallbackQuery(string $callbackQueryId, string $text)
    {
        $payload = [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => false
        ];

        $token = config('telegram.bot_token');
        $url = "https://api.telegram.org/bot{$token}/answerCallbackQuery";

        $client = new \GuzzleHttp\Client();
        $client->post($url, ['json' => $payload]);
    }
}

<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class TelegramWebhookCommand extends Command
{
    protected $signature = 'telegram:webhook
                            {action : set, delete, info o me}
                            {--url= : URL publica del webhook (solo con set)}';

    protected $description = 'Registra, borra o consulta el webhook del bot de Telegram';

    public function handle(TelegramService $telegram): int
    {
        return match ($this->argument('action')) {
            'set' => $this->setWebhook($telegram),
            'delete' => $this->render($telegram->deleteWebhook()),
            'info' => $this->render($telegram->getWebhookInfo()),
            'me' => $this->render($telegram->getMe()),
            default => $this->invalidAction(),
        };
    }

    private function setWebhook(TelegramService $telegram): int
    {
        $url = $this->option('url') ?: rtrim(config('app.url'), '/') . '/webhooks/telegram';

        if (!str_starts_with($url, 'https://')) {
            $this->error("Telegram solo acepta webhooks por HTTPS. Recibido: {$url}");
            $this->line('Para desarrollo local usa un tunel (ngrok, cloudflared) y pasa --url.');

            return self::FAILURE;
        }

        if (empty(config('telegram.webhook_secret'))) {
            $this->error('Falta TELEGRAM_WEBHOOK_SECRET en el .env.');

            return self::FAILURE;
        }

        $this->line("Registrando webhook en {$url}");

        return $this->render($telegram->setWebhook($url));
    }

    private function render(array $result): int
    {
        $ok = ($result['ok'] ?? false) === true;

        if (!$ok) {
            $this->error($result['description'] ?? 'La llamada a Telegram fallo');

            return self::FAILURE;
        }

        $this->info('OK');
        $this->line(json_encode($result['result'] ?? true, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }

    private function invalidAction(): int
    {
        $this->error('Accion no valida. Usa: set, delete, info o me');

        return self::FAILURE;
    }
}

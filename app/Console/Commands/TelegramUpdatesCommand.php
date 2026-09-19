<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class TelegramUpdatesCommand extends Command
{
    protected $signature = 'telegram:updates {--limit=20 : Cuantos updates leer}';

    protected $description = 'Lee los mensajes recientes del bot para descubrir chat_ids';

    public function handle(TelegramService $telegram): int
    {
        $result = $telegram->getUpdates((int) $this->option('limit'));

        if (($result['ok'] ?? false) !== true) {
            $this->error($result['description'] ?? 'La llamada a Telegram fallo');

            // El error tipico cuando ya hay webhook registrado.
            if (str_contains($result['description'] ?? '', 'webhook is active')) {
                $this->line('Hay un webhook activo. Borralo con: php artisan telegram:webhook delete');
            }

            return self::FAILURE;
        }

        $updates = $result['result'] ?? [];

        if (empty($updates)) {
            $this->warn('No hay mensajes.');
            $this->line('Abre tu bot en Telegram y mandale cualquier texto, luego repite este comando.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($updates as $update) {
            $message = $update['message'] ?? $update['callback_query']['message'] ?? [];
            $from = $update['message']['from'] ?? $update['callback_query']['from'] ?? [];

            $rows[] = [
                $message['chat']['id'] ?? '-',
                $from['first_name'] ?? '-',
                isset($from['username']) ? '@' . $from['username'] : '-',
                mb_strimwidth($message['text'] ?? '-', 0, 40, '...'),
            ];
        }

        $this->table(['chat_id', 'nombre', 'usuario', 'texto'], $rows);
        $this->line('Guarda el chat_id que te corresponde: lo necesitas para telegram:send.');

        return self::SUCCESS;
    }
}

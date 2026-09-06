<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class TelegramSendCommand extends Command
{
    protected $signature = 'telegram:send
                            {--chat= : chat_id destino}
                            {--user= : id del User de Uatu (usa su telegram_chat_id)}
                            {--text=Prueba de Uatu : Texto a enviar}';

    protected $description = 'Envia un mensaje de prueba por Telegram';

    public function handle(TelegramService $telegram): int
    {
        $chat = $this->option('chat');
        $userId = $this->option('user');
        $text = $this->option('text');

        if (!$chat && !$userId) {
            $this->error('Indica --chat o --user.');
            $this->line('Si no conoces tu chat_id, corre: php artisan telegram:updates');

            return self::FAILURE;
        }

        if ($userId) {
            $user = User::find($userId);

            if (!$user) {
                $this->error("No existe el usuario {$userId}");

                return self::FAILURE;
            }

            if (!$user->canReceiveTelegram()) {
                $this->error("{$user->name} no tiene telegram_chat_id. Debe abrir el bot y mandar /start primero.");

                return self::FAILURE;
            }

            $message = $telegram->sendToUser($user, $text);
        } else {
            $message = $telegram->sendToChat($chat, $text);
        }

        if ($message->status !== 'SENT') {
            $this->error("Fallo el envio: {$message->error}");

            return self::FAILURE;
        }

        $this->info("Enviado. telegram_message_id={$message->telegram_message_id}");
        $this->line("Registrado en telegram_messages con id {$message->id}");

        return self::SUCCESS;
    }
}

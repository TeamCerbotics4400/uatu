<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class TelegramMenuCommand extends Command
{
    protected $signature = 'telegram:menu {--chat= : chat_id destino}';
    protected $description = 'Envía un menú con botones';

    public function handle(TelegramService $telegram): int
    {
        $chat = $this->option('chat');

        if (!$chat) {
            $this->error('Necesitas: --chat=TU_CHAT_ID');
            return self::FAILURE;
        }

        $buttons = [
            [
                ['text' => 'Menu Admin', 'callback_data' => 'admin_menu'],
                ['text' => 'Menu Usuario', 'callback_data' => 'user_menu']
            ],
            [
                ['text' => 'Crear Match', 'callback_data' => 'create_match'],
                ['text' => 'Ver Tasks', 'callback_data' => 'view_tasks']
            ],
            [
                ['text' => 'Cancelar', 'callback_data' => 'cancel']
            ]
        ];

        $result = $telegram->sendWithInlineKeyboard(
            $chat,
            "Que deseas hacer?",
            $buttons
        );

        if (($result['ok'] ?? false) === true) {
            $this->info('Menu enviado');
            return self::SUCCESS;
        }

        $this->error('Error: ' . ($result['description'] ?? 'desconocido'));
        return self::FAILURE;
    }
}

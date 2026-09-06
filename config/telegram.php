<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Token del bot
    |--------------------------------------------------------------------------
    | Se obtiene de @BotFather en Telegram con el comando /newbot.
    | Formato: 123456789:AAEhBOweik6ad6PsVMRjKhFhKGRGF2Zt7ss
    */
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Secret token del webhook
    |--------------------------------------------------------------------------
    | Cadena propia que se manda al registrar el webhook. Telegram la devuelve
    | en el header X-Telegram-Bot-Api-Secret-Token en cada update, y así se
    | verifica que la peticion viene de Telegram y no de un tercero.
    */
    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),

    'api_url' => env('TELEGRAM_API_URL', 'https://api.telegram.org'),

    /*
    |--------------------------------------------------------------------------
    | Formato de los mensajes
    |--------------------------------------------------------------------------
    | Telegram no usa plantillas pre-aprobadas como WhatsApp: el texto se
    | manda libre. parse_mode acepta 'HTML' o 'MarkdownV2'.
    */
    'parse_mode' => env('TELEGRAM_PARSE_MODE', 'HTML'),

    'timeout' => (int) env('TELEGRAM_TIMEOUT', 30),
];

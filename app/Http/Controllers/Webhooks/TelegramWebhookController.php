<?php

namespace App\Http\Controllers\Webhooks;

use App\Services\TelegramBot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController
{
    public function handle(Request $request, TelegramBot $bot): JsonResponse
    {
        $secret = config('telegram.webhook_secret');

        if ($secret && $request->header('X-Telegram-Bot-Api-Secret-Token') !== $secret) {
            abort(403);
        }

        try {
            $bot->handleUpdate($request->all());
        } catch (\Throwable $e) {
            // Siempre 200: si Telegram recibe un error reintenta el mismo update
            // indefinidamente y bloquea los que siguen.
            Log::error('Telegram: error procesando update', [
                'error' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
                'update' => $request->all(),
            ]);
        }

        return response()->json(['ok' => true]);
    }
}

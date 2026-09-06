<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    /**
     * Recibe los updates de Telegram.
     *
     * A diferencia de WhatsApp no hay verificacion por GET con hub.challenge:
     * la URL se registra con setWebhook y Telegram acompana cada peticion con
     * el header X-Telegram-Bot-Api-Secret-Token.
     */
    public function handle(Request $request)
    {
        if (!$this->hasValidSecret($request)) {
            Log::warning('Telegram webhook: secret token invalido', [
                'ip' => $request->ip(),
            ]);

            return response('Forbidden', 403);
        }

        $update = $request->all();

        Log::info('Telegram webhook: update recibido', [
            'update_id' => $update['update_id'] ?? null,
        ]);

        if (isset($update['message'])) {
            $this->handleMessage($update['message']);
        }

        // Telegram reintenta si no recibe 200 rapido.
        return response('OK', 200);
    }

    private function hasValidSecret(Request $request): bool
    {
        $expected = config('telegram.webhook_secret');

        if (empty($expected)) {
            Log::error('Telegram webhook: falta TELEGRAM_WEBHOOK_SECRET, se rechaza el update');

            return false;
        }

        $received = $request->header('X-Telegram-Bot-Api-Secret-Token', '');

        return hash_equals($expected, $received);
    }

    private function handleMessage(array $message): void
    {
        $chatId = (string) ($message['chat']['id'] ?? '');
        $text = trim($message['text'] ?? '');
        $username = $message['from']['username'] ?? null;

        if ($chatId === '') {
            return;
        }

        Log::info('Telegram: mensaje entrante', [
            'chat_id' => $chatId,
            'text' => $text,
        ]);

        if (str_starts_with($text, '/start')) {
            $this->handleStart($chatId, $text, $username);
        }
    }

    /**
     * /start <user_id> vincula la cuenta de Telegram con un User de Uatu.
     *
     * El enlace se arma como https://t.me/<bot>?start=<user_id> y es la unica
     * forma de conocer el chat_id: Telegram no permite escribirle primero a
     * alguien que no haya iniciado la conversacion con el bot.
     */
    private function handleStart(string $chatId, string $text, ?string $username): void
    {
        $parts = preg_split('/\s+/', $text, 2);
        $token = isset($parts[1]) ? trim($parts[1]) : '';

        if ($token === '') {
            Log::info('Telegram: /start sin parametro de vinculacion', ['chat_id' => $chatId]);

            return;
        }

        $user = User::find($token);

        if (!$user) {
            Log::warning('Telegram: /start con identificador desconocido', [
                'chat_id' => $chatId,
            ]);

            return;
        }

        $user->update([
            'telegram_chat_id' => $chatId,
            'telegram_username' => $username,
        ]);

        Log::info('Telegram: cuenta vinculada', [
            'user_id' => $user->id,
            'chat_id' => $chatId,
        ]);
    }
}

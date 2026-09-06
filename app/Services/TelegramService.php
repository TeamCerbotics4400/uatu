<?php

namespace App\Services;

use App\Models\TelegramMessage;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    /**
     * Envia un mensaje a un usuario y deja registro en telegram_messages.
     * Devuelve el registro creado; su campo status dice si salio o fallo.
     */
    public function sendToUser(User $user, string $text): TelegramMessage
    {
        $message = TelegramMessage::create([
            'user_id' => $user->id,
            'chat_id' => (string) $user->telegram_chat_id,
            'status' => 'PENDING',
            'body' => $text,
        ]);

        if (!$user->canReceiveTelegram()) {
            return $this->markFailed($message, 'El usuario no tiene telegram_chat_id registrado');
        }

        return $this->dispatch($message);
    }

    /**
     * Envia a un chat_id suelto (grupo, canal o alguien sin User asociado).
     */
    public function sendToChat(string $chatId, string $text): TelegramMessage
    {
        $message = TelegramMessage::create([
            'chat_id' => $chatId,
            'status' => 'PENDING',
            'body' => $text,
        ]);

        return $this->dispatch($message);
    }

    /**
     * Registra la URL del webhook en Telegram. Se corre una sola vez por
     * ambiente (o cuando cambia la URL publica).
     */
    public function setWebhook(string $url): array
    {
        return $this->call('setWebhook', [
            'url' => $url,
            'secret_token' => config('telegram.webhook_secret'),
            'allowed_updates' => ['message', 'callback_query'],
        ]);
    }

    public function deleteWebhook(): array
    {
        return $this->call('deleteWebhook');
    }

    public function getWebhookInfo(): array
    {
        return $this->call('getWebhookInfo');
    }

    /**
     * Comprueba que el token es valido. Devuelve los datos del bot.
     */
    public function getMe(): array
    {
        return $this->call('getMe');
    }

    /**
     * Lee los updates pendientes por polling. Sirve para descubrir chat_ids
     * durante las primeras pruebas, cuando todavia no hay webhook (Telegram
     * no permite usar getUpdates y webhook al mismo tiempo).
     */
    public function getUpdates(int $limit = 20): array
    {
        return $this->call('getUpdates', ['limit' => $limit]);
    }

    private function dispatch(TelegramMessage $message): TelegramMessage
    {
        $payload = [
            'chat_id' => $message->chat_id,
            'text' => $message->body,
            'parse_mode' => config('telegram.parse_mode'),
        ];

        $message->update(['payload' => $payload]);

        $result = $this->call('sendMessage', $payload);

        if (($result['ok'] ?? false) !== true) {
            return $this->markFailed(
                $message,
                $result['description'] ?? 'Telegram rechazo el envio',
                $result
            );
        }

        $message->update([
            'status' => 'SENT',
            'telegram_message_id' => $result['result']['message_id'] ?? null,
            'response' => $result,
            'error' => null,
        ]);

        return $message->refresh();
    }

    /**
     * Llama a un metodo de la Bot API. Nunca lanza: devuelve el cuerpo de la
     * respuesta, o un arreglo con ok=false describiendo el fallo.
     */
    private function call(string $method, array $params = []): array
    {
        $token = config('telegram.bot_token');

        if (empty($token)) {
            Log::error('Telegram: falta TELEGRAM_BOT_TOKEN');

            return ['ok' => false, 'description' => 'Falta TELEGRAM_BOT_TOKEN'];
        }

        $url = rtrim(config('telegram.api_url'), '/') . "/bot{$token}/{$method}";

        try {
            $response = Http::timeout(config('telegram.timeout'))
                ->asJson()
                ->post($url, $params);
        } catch (ConnectionException $e) {
            Log::error('Telegram: fallo de conexion', [
                'method' => $method,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'description' => 'Fallo de conexion: ' . $e->getMessage()];
        }

        $body = $response->json() ?? [];

        // El token va en la URL, asi que se registra el metodo, nunca la URL.
        Log::info('Telegram: respuesta de la API', [
            'method' => $method,
            'http_status' => $response->status(),
            'ok' => $body['ok'] ?? false,
        ]);

        return $body;
    }

    private function markFailed(TelegramMessage $message, string $error, ?array $response = null): TelegramMessage
    {
        $message->update([
            'status' => 'FAILED',
            'error' => $error,
            'response' => $response,
        ]);

        Log::warning('Telegram: envio fallido', [
            'message_id' => $message->id,
            'chat_id' => $message->chat_id,
            'error' => $error,
        ]);

        return $message->refresh();
    }
}

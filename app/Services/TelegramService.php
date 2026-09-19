<?php

namespace App\Services;

use App\Models\TelegramMessage;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
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

    public function sendToChat(string $chatId, string $text): TelegramMessage
    {
        $message = TelegramMessage::create([
            'chat_id' => $chatId,
            'status' => 'PENDING',
            'body' => $text,
        ]);

        return $this->dispatch($message);
    }

    public function sendWithInlineKeyboard(string $chatId, string $text, array $buttons = []): array
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => config('telegram.parse_mode'),
        ];

        if ($buttons) {
            $payload['reply_markup'] = json_encode(['inline_keyboard' => $buttons]);
        }

        return $this->call('sendMessage', $payload);
    }

    public function sendPhoto(string $chatId, string $path, string $caption = '', array $buttons = []): array
    {
        $payload = [
            'chat_id' => $chatId,
            'caption' => $caption,
            'parse_mode' => config('telegram.parse_mode'),
        ];

        if ($buttons) {
            $payload['reply_markup'] = json_encode(['inline_keyboard' => $buttons]);
        }

        return $this->call('sendPhoto', $payload, $path);
    }

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null): array
    {
        $payload = ['callback_query_id' => $callbackQueryId];

        if ($text !== null) {
            $payload['text'] = $text;
        }

        return $this->call('answerCallbackQuery', $payload);
    }

    public function setWebhook(string $url): array
    {
        return $this->call('setWebhook', [
            'url' => $url,
            'secret_token' => config('telegram.webhook_secret'),
            'allowed_updates' => ['message', 'callback_query', 'inline_query'],
        ]);
    }

    public function answerInlineQuery(string $inlineQueryId, array $results): array
    {
        return $this->call('answerInlineQuery', [
            'inline_query_id' => $inlineQueryId,
            'results' => json_encode($results),
            'cache_time' => 0,
            'is_personal' => true,
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

    public function getMe(): array
    {
        return $this->call('getMe');
    }

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

    private function call(string $method, array $params = [], ?string $photoPath = null): array
    {
        $token = config('telegram.bot_token');

        if (empty($token)) {
            Log::error('Telegram: falta TELEGRAM_BOT_TOKEN');

            return ['ok' => false, 'description' => 'Falta TELEGRAM_BOT_TOKEN'];
        }

        $url = rtrim(config('telegram.api_url'), '/') . "/bot{$token}/{$method}";

        try {
            $request = Http::timeout(config('telegram.timeout'));

            if ($photoPath !== null) {
                $response = $request
                    ->attach('photo', file_get_contents($photoPath), basename($photoPath))
                    ->post($url, $params);
            } else {
                $response = $request->asJson()->post($url, $params);
            }
        } catch (ConnectionException $e) {
            Log::error('Telegram: fallo de conexion', [
                'method' => $method,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'description' => 'Fallo de conexion: ' . $e->getMessage()];
        }

        $body = $response->json() ?? [];

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

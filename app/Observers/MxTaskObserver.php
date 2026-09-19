<?php

namespace App\Observers;

use App\Models\MxTask;
use App\Services\TelegramBot;
use Illuminate\Support\Facades\Log;

class MxTaskObserver
{
    private const SLOTS = ['assigned_user_1', 'assigned_user_2', 'assigned_user_3', 'assigned_user_4'];

    public function created(MxTask $task): void
    {
        $this->notify($task, $this->users($task->getAttributes()));
    }

    public function updated(MxTask $task): void
    {
        $added = array_diff($this->users($task->getAttributes()), $this->users($task->getOriginal()));

        if ($added) {
            $this->notify($task, $added);
        }
    }

    private function users(array $attributes): array
    {
        return array_values(array_filter(array_map(fn ($slot) => $attributes[$slot] ?? null, self::SLOTS)));
    }

    private function notify(MxTask $task, array $userIds): void
    {
        if (!$userIds) {
            return;
        }

        try {
            app(TelegramBot::class)->notifyAssigned($task, $userIds);
        } catch (\Throwable $e) {
            Log::error('Telegram: no se pudo notificar asignacion de MxTask', [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

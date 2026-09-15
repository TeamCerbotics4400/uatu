<?php

namespace App\Filament\Resources\MxTaskResource\Pages;

use App\Filament\Resources\MxTaskResource;
use App\Models\User;
use App\Services\TaskStateMachine;
use Filament\Resources\Pages\CreateRecord;

class CreateMxTask extends CreateRecord
{
    protected static string $resource = MxTaskResource::class;

    protected function afterCreate(): void
    {
        $stateMachine = new TaskStateMachine();
        $task = $this->record;

        // Mark assigned users as BUSY
        $userIds = collect([
            $task->assigned_user_1,
            $task->assigned_user_2,
            $task->assigned_user_3,
            $task->assigned_user_4,
        ])->filter()->toArray();

        foreach ($userIds as $userId) {
            $user = User::find($userId);
            if ($user) {
                $user->update(['status' => 'BUSY']);
            }
        }
    }
}
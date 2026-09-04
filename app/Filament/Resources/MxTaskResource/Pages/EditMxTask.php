<?php

namespace App\Filament\Resources\MxTaskResource\Pages;

use App\Filament\Resources\MxTaskResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;

class EditMxTask extends EditRecord
{
    protected static string $resource = MxTaskResource::class;

    protected function afterSave(): void
    {
        $task = $this->record;

        // Get original (before update) assigned users from database
        $originalTask = $task::find($task->id);
        
        $originalUserIds = collect([
            $originalTask->assigned_user_1,
            $originalTask->assigned_user_2,
            $originalTask->assigned_user_3,
            $originalTask->assigned_user_4,
        ])->filter()->toArray();

        $newUserIds = collect([
            $task->assigned_user_1,
            $task->assigned_user_2,
            $task->assigned_user_3,
            $task->assigned_user_4,
        ])->filter()->toArray();

        // Users that were removed: set to AVAILABLE
        $removedUserIds = array_diff($originalUserIds, $newUserIds);
        foreach ($removedUserIds as $userId) {
            $user = User::find($userId);
            if ($user && $user->status === 'BUSY') {
                $user->update(['status' => 'AVAILABLE']);
            }
        }

        // Users that were added: set to BUSY (only if task status is not DONE or CANCELLED)
        if (!in_array($task->status, ['DONE', 'CANCELLED'])) {
            $addedUserIds = array_diff($newUserIds, $originalUserIds);
            foreach ($addedUserIds as $userId) {
                $user = User::find($userId);
                if ($user) {
                    $user->update(['status' => 'BUSY']);
                }
            }
        }
    }
}
<?php

namespace App\Filament\Resources\ServiceTaskResource\Pages;

use App\Filament\Resources\ServiceTaskResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Services\TaskStateMachine;
use App\Models\User;
use Filament\Notifications\Notification;

class EditServiceTask extends EditRecord
{
    protected static string $resource = ServiceTaskResource::class;

    protected ?string $newAssignedUser = null;
    protected bool $unassignUser = false;


    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $originalUser = $this->record->getOriginal('assigned_user');
        $submittedUser = $data['assigned_user'] ?? null;

        if ($submittedUser !== $originalUser) {
            if ($submittedUser) {
                $this->newAssignedUser = $submittedUser;
            } else {
                $this->unassignUser = true;
            }
            // Prevent direct save, let state machine handle it
            $data['assigned_user'] = $originalUser;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $stateMachine = new TaskStateMachine();

        if ($this->unassignUser) {
            $stateMachine->toPending($this->record);
            Notification::make()
                ->title('Task Unassigned')
                ->body('The task is now pending.')
                ->success()
                ->send();
        }

        if ($this->newAssignedUser) {
            // If it's not pending, transition to pending first to free up the previous user if any
            if ($this->record->status !== 'PENDING') {
                $stateMachine->toPending($this->record);
            }

            $user = User::find($this->newAssignedUser);
            if ($user) {
                $result = $stateMachine->toAssigned($this->record, $user);

                if (!$result) {
                    Notification::make()
                        ->title('Assignment Failed')
                        ->body("Could not assign to {$user->name} because they are busy. Task is now PENDING.")
                        ->warning()
                        ->send();
                } else {
                    Notification::make()
                        ->title('Task Assigned')
                        ->body("Task successfully assigned to {$user->name}.")
                        ->success()
                        ->send();
                }
            }
        }
    }
}

<?php

namespace App\Filament\Resources\ServiceTaskResource\Pages;

use App\Filament\Resources\ServiceTaskResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Services\TaskStateMachine;
use App\Models\User;
use Filament\Notifications\Notification;

class CreateServiceTask extends CreateRecord
{
    protected static string $resource = ServiceTaskResource::class;

    protected ?string $pendingAssignedUser = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = 'PENDING';

        if (isset($data['assigned_user'])) {
            $this->pendingAssignedUser = $data['assigned_user'];
            $data['assigned_user'] = null; // We assign it via state machine
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->pendingAssignedUser) {
            $stateMachine = new TaskStateMachine();
            $user = User::find($this->pendingAssignedUser);

            if ($user) {
                // Ensure the database record is refreshed to have the 'status' updated before transition
                $this->record->refresh();
                $result = $stateMachine->toAssigned($this->record, $user);

                if (!$result) {
                    Notification::make()
                        ->title('Assignment Failed')
                        ->body("The task was created, but user {$user->name} could not be assigned because they are busy.")
                        ->warning()
                        ->send();
                } else {
                    Notification::make()
                        ->title('Task Assigned')
                        ->body("Task was automatically assigned to {$user->name}.")
                        ->success()
                        ->send();
                }
            }
        }
    }
}

<?php

namespace App\Filament\Resources\ServiceTaskResource\Pages;

use App\Filament\Resources\ServiceTaskResource;
use App\Services\TaskStateMachine;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateServiceTask extends CreateRecord
{
    protected static string $resource = ServiceTaskResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = 'PENDING';

        return $data;
    }

    protected function afterCreate(): void
    {
        $stateMachine = new TaskStateMachine();

        if ($stateMachine->hasAssignedUsers($this->record) && $stateMachine->toAssigned($this->record)) {
            Notification::make()
                ->title('Task Assigned')
                ->body("Task assigned to {$this->record->getAssignedUserNames()}.")
                ->success()
                ->send();
        }
    }
}

<?php

namespace App\Filament\Resources\ServiceTaskResource\Pages;

use App\Filament\Resources\ServiceTaskResource;
use App\Services\TaskStateMachine;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditServiceTask extends EditRecord
{
    protected static string $resource = ServiceTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $stateMachine = new TaskStateMachine();

        if ($this->record->status === 'PENDING' && $stateMachine->hasAssignedUsers($this->record)) {
            $stateMachine->toAssigned($this->record);
            return;
        }

        if (!in_array($this->record->status, ['COMPLETED', 'CANCELLED'])) {
            $stateMachine->syncParticipants($this->record);
        }
    }
}

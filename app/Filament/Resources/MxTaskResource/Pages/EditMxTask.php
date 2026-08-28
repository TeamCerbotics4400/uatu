<?php

namespace App\Filament\Resources\MxTaskResource\Pages;

use App\Filament\Resources\MxTaskResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMxTask extends EditRecord
{
    protected static string $resource = MxTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

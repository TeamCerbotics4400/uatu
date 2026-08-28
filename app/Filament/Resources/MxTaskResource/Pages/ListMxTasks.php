<?php

namespace App\Filament\Resources\MxTaskResource\Pages;

use App\Filament\Resources\MxTaskResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMxTasks extends ListRecords
{
    protected static string $resource = MxTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

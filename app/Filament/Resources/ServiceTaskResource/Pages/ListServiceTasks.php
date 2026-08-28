<?php

namespace App\Filament\Resources\ServiceTaskResource\Pages;

use App\Filament\Resources\ServiceTaskResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListServiceTasks extends ListRecords
{
    protected static string $resource = ServiceTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

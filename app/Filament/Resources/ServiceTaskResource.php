<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceTaskResource\Pages;
use App\Models\ServiceTask;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ServiceTaskResource extends Resource
{
    protected static ?string $model = ServiceTask::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('status')
                    ->options([
                        'PENDING' => 'Pending',
                        'ASSIGNED' => 'Assigned',
                        'IN_PROGRESS' => 'In Progress',
                        'COMPLETED' => 'Completed',
                    ])
                    ->required()
                    ->label('Status'),
                Forms\Components\Select::make('assigned_team')
                    ->relationship('team', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->label('Assigned Team'),
                Forms\Components\Select::make('assigned_user')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->label('Assigned User'),
                Forms\Components\DateTimePicker::make('started_at')
                    ->nullable()
                    ->label('Started At'),
                Forms\Components\DateTimePicker::make('completed_at')
                    ->nullable()
                    ->label('Completed At'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'PENDING' => 'danger',
                        'ASSIGNED' => 'warning',
                        'IN_PROGRESS' => 'info',
                        'COMPLETED' => 'success',
                    })
                    ->label('Status'),
                Tables\Columns\TextColumn::make('team.name')
                    ->searchable()
                    ->label('Assigned Team'),
                Tables\Columns\TextColumn::make('user.name')
                    ->searchable()
                    ->label('Assigned User'),
                Tables\Columns\TextColumn::make('started_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Started'),
                Tables\Columns\TextColumn::make('completed_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Completed'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'PENDING' => 'Pending',
                        'ASSIGNED' => 'Assigned',
                        'IN_PROGRESS' => 'In Progress',
                        'COMPLETED' => 'Completed',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServiceTasks::route('/'),
            'create' => Pages\CreateServiceTask::route('/create'),
            'edit' => Pages\EditServiceTask::route('/{record}/edit'),
        ];
    }
}
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TeamResource\Pages;
use App\Models\Team;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TeamResource extends Resource
{
    protected static ?string $model = Team::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->label('Team Name'),
                Forms\Components\Select::make('priority')
                    ->options([
                        '1' => 'Priority 1',
                        '2' => 'Priority 2',
                        '3' => 'Priority 3',
                        '4' => 'Priority 4',
                        '5' => 'Priority 5',
                        '6' => 'Priority 6',
                        '7' => 'Priority 7',
                    ])
                    ->required()
                    ->label('Priority'),
                Forms\Components\Select::make('required_service')
                    ->options([
                        'MECHANICAL' => 'Mechanical',
                        'PROGRAMMING' => 'Programming',
                        'BOTH' => 'Both',
                        'NONE' => 'None',
                    ])
                    ->required()
                    ->label('Required Service'),
                Forms\Components\Select::make('current_service_status')
                    ->options([
                        'IN_PROGRESS' => 'In Progress',
                        'DONE' => 'Done',
                        'NOT_HELPED' => 'Not Helped',
                        'PAUSE' => 'Pause',
                    ])
                    ->required()
                    ->default('NOT_HELPED')
                    ->label('Current Service Status'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->label('Team Name'),
                Tables\Columns\TextColumn::make('priority')
                    ->sortable()
                    ->label('Priority'),
                Tables\Columns\TextColumn::make('required_service')
                    ->badge()
                    ->label('Required Service'),
                Tables\Columns\TextColumn::make('current_service_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'IN_PROGRESS' => 'warning',
                        'DONE' => 'success',
                        'NOT_HELPED' => 'danger',
                        'PAUSE' => 'info',
                    })
                    ->label('Service Status'),
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
                Tables\Filters\SelectFilter::make('priority')
                    ->options([
                        '1' => 'Priority 1',
                        '2' => 'Priority 2',
                        '3' => 'Priority 3',
                        '4' => 'Priority 4',
                        '5' => 'Priority 5',
                        '6' => 'Priority 6',
                        '7' => 'Priority 7',
                    ]),
                Tables\Filters\SelectFilter::make('current_service_status')
                    ->options([
                        'IN_PROGRESS' => 'In Progress',
                        'DONE' => 'Done',
                        'NOT_HELPED' => 'Not Helped',
                        'PAUSE' => 'Pause',
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
            'index' => Pages\ListTeams::route('/'),
            'create' => Pages\CreateTeam::route('/create'),
            'edit' => Pages\EditTeam::route('/{record}/edit'),
        ];
    }
}
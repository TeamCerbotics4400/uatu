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
            ->modifyQueryUsing(fn ($query) => $query->with('serviceTasks'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->label('Team Name'),
                Tables\Columns\TextColumn::make('required_service')
                    ->label('Required Service')
                    ->badge()
                    ->getStateUsing(fn (Team $record): string => 
                        $record->getActiveRequiredServices()
                    )
                    ->color(fn (string $state): string => match ($state) {
                        'MECHANICAL' => 'info',
                        'PROGRAMMING' => 'warning',
                        'BOTH' => 'danger',
                        'NONE' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('current_service_status')
                    ->badge()
                    ->getStateUsing(fn (Team $record): string => 
                        $record->calculateServiceStatus()
                    )
                    ->color(fn (string $state): string => match ($state) {
                        'IN_PROGRESS' => 'warning',
                        'DONE' => 'success',
                        'NOT_HELPED' => 'danger',
                        'PAUSE' => 'info',
                        default => 'gray',
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
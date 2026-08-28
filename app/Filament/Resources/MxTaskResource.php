<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MxTaskResource\Pages;
use App\Models\MxTask;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MxTaskResource extends Resource
{
    protected static ?string $model = MxTask::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->options([
                        'PIT' => 'Pit',
                        'MATCH' => 'Match',
                    ])
                    ->required()
                    ->label('Task Type'),
                Forms\Components\Select::make('status')
                    ->options([
                        'IN_PROGRESS' => 'In Progress',
                    ])
                    ->required()
                    ->default('IN_PROGRESS')
                    ->label('Status'),
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
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'PIT' => 'info',
                        'MATCH' => 'warning',
                    })
                    ->label('Type'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'IN_PROGRESS' => 'warning',
                    })
                    ->label('Status'),
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
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'PIT' => 'Pit',
                        'MATCH' => 'Match',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'IN_PROGRESS' => 'In Progress',
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
            'index' => Pages\ListMxTasks::route('/'),
            'create' => Pages\CreateMxTask::route('/create'),
            'edit' => Pages\EditMxTask::route('/{record}/edit'),
        ];
    }
}
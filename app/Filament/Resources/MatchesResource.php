<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MatchesResource\Pages;
use App\Models\Matches;
use App\Models\Team;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MatchesResource extends Resource
{
    protected static ?string $model = Matches::class;

    protected static ?string $navigationIcon = 'heroicon-o-swatch';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('number')
                    ->numeric()
                    ->required()
                    ->label('Match Number'),
                Forms\Components\Section::make('Blue Alliance')
                    ->schema([
                        Forms\Components\Select::make('blue_1')
                            ->relationship('blue1Team', 'name')
                            ->searchable()
                            ->preload()
                            ->label('Blue Team 1'),
                        Forms\Components\Select::make('blue_2')
                            ->relationship('blue2Team', 'name')
                            ->searchable()
                            ->preload()
                            ->label('Blue Team 2'),
                        Forms\Components\Select::make('blue_3')
                            ->relationship('blue3Team', 'name')
                            ->searchable()
                            ->preload()
                            ->label('Blue Team 3'),
                    ])->columns(3),
                Forms\Components\Section::make('Red Alliance')
                    ->schema([
                        Forms\Components\Select::make('red_1')
                            ->relationship('red1Team', 'name')
                            ->searchable()
                            ->preload()
                            ->label('Red Team 1'),
                        Forms\Components\Select::make('red_2')
                            ->relationship('red2Team', 'name')
                            ->searchable()
                            ->preload()
                            ->label('Red Team 2'),
                        Forms\Components\Select::make('red_3')
                            ->relationship('red3Team', 'name')
                            ->searchable()
                            ->preload()
                            ->label('Red Team 3'),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->sortable()
                    ->searchable()
                    ->label('Match #'),

                // Blue Alliance
                Tables\Columns\TextColumn::make('blue1Team.name')
                    ->label('Blue 1')
                    ->badge()
                    ->color(fn (Matches $record): string => match ($record->getTeamServiceStatus($record->blue_1)) {
                        'completed' => 'success',
                        'in_progress' => 'warning',
                        default => 'danger',
                    }),

                Tables\Columns\TextColumn::make('blue2Team.name')
                    ->label('Blue 2')
                    ->badge()
                    ->color(fn (Matches $record): string => match ($record->getTeamServiceStatus($record->blue_2)) {
                        'completed' => 'success',
                        'in_progress' => 'warning',
                        default => 'danger',
                    }),

                Tables\Columns\TextColumn::make('blue3Team.name')
                    ->label('Blue 3')
                    ->badge()
                    ->color(fn (Matches $record): string => match ($record->getTeamServiceStatus($record->blue_3)) {
                        'completed' => 'success',
                        'in_progress' => 'warning',
                        default => 'danger',
                    }),

                // Red Alliance
                Tables\Columns\TextColumn::make('red1Team.name')
                    ->label('Red 1')
                    ->badge()
                    ->color(fn (Matches $record): string => match ($record->getTeamServiceStatus($record->red_1)) {
                        'completed' => 'success',
                        'in_progress' => 'warning',
                        default => 'danger',
                    }),

                Tables\Columns\TextColumn::make('red2Team.name')
                    ->label('Red 2')
                    ->badge()
                    ->color(fn (Matches $record): string => match ($record->getTeamServiceStatus($record->red_2)) {
                        'completed' => 'success',
                        'in_progress' => 'warning',
                        default => 'danger',
                    }),

                Tables\Columns\TextColumn::make('red3Team.name')
                    ->label('Red 3')
                    ->badge()
                    ->color(fn (Matches $record): string => match ($record->getTeamServiceStatus($record->red_3)) {
                        'completed' => 'success',
                        'in_progress' => 'warning',
                        default => 'danger',
                    }),

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
                //
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
            'index' => Pages\ListMatches::route('/'),
            'create' => Pages\CreateMatches::route('/create'),
            'edit' => Pages\EditMatches::route('/{record}/edit'),
        ];
    }
}
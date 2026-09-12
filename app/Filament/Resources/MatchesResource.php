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

    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Match Info')
                            ->description('Enter the match number and participating teams.')
                            ->schema([
                                Forms\Components\TextInput::make('number')
                                    ->required()
                                    ->numeric()
                                    ->unique(Matches::class, 'number', ignoreRecord: true)
                                    ->label('Match Number'),
                            ]),

                        Forms\Components\Section::make('Blue Alliance')
                            ->description('Select the three blue alliance teams.')
                            ->schema([
                                Forms\Components\Select::make('blue_1')
                                    ->label('Blue Team 1')
                                    ->options(fn () => Team::pluck('name', 'id')->toArray())
                                    ->searchable()
                                    ->preload()
                                    ->nullable(),

                                Forms\Components\Select::make('blue_2')
                                    ->label('Blue Team 2')
                                    ->options(fn () => Team::pluck('name', 'id')->toArray())
                                    ->searchable()
                                    ->preload()
                                    ->nullable(),

                                Forms\Components\Select::make('blue_3')
                                    ->label('Blue Team 3')
                                    ->options(fn () => Team::pluck('name', 'id')->toArray())
                                    ->searchable()
                                    ->preload()
                                    ->nullable(),
                            ])
                            ->columns(1),

                        Forms\Components\Section::make('Red Alliance')
                            ->description('Select the three red alliance teams.')
                            ->schema([
                                Forms\Components\Select::make('red_1')
                                    ->label('Red Team 1')
                                    ->options(fn () => Team::pluck('name', 'id')->toArray())
                                    ->searchable()
                                    ->preload()
                                    ->nullable(),

                                Forms\Components\Select::make('red_2')
                                    ->label('Red Team 2')
                                    ->options(fn () => Team::pluck('name', 'id')->toArray())
                                    ->searchable()
                                    ->preload()
                                    ->nullable(),

                                Forms\Components\Select::make('red_3')
                                    ->label('Red Team 3')
                                    ->options(fn () => Team::pluck('name', 'id')->toArray())
                                    ->searchable()
                                    ->preload()
                                    ->nullable(),
                            ])
                            ->columns(1),
                    ])
                    ->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Timestamps')
                            ->schema([
                                Forms\Components\Placeholder::make('created_at')
                                    ->label('Created At')
                                    ->content(fn (?Matches $record): string => 
                                        $record?->created_at?->format('Y-m-d H:i:s') ?? '—'
                                    ),

                                Forms\Components\Placeholder::make('updated_at')
                                    ->label('Updated At')
                                    ->content(fn (?Matches $record): string => 
                                        $record?->updated_at?->format('Y-m-d H:i:s') ?? '—'
                                    ),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->searchable()
                    ->sortable()
                    ->label('Match #'),

                Tables\Columns\TextColumn::make('blue_1')
                    ->label('Blue 1')
                    ->badge()
                    ->color(fn (Matches $record) => match (Matches::getTeamServiceStatus($record->blue_1)) {
                        'completed' => 'success',
                        'in_progress' => 'warning',
                        'pending' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => 
                        Team::find($state)?->name ?? '—'
                    ),

                Tables\Columns\TextColumn::make('blue_2')
                    ->label('Blue 2')
                    ->badge()
                    ->color(fn (Matches $record) => match (Matches::getTeamServiceStatus($record->blue_2)) {
                        'completed' => 'success',
                        'in_progress' => 'warning',
                        'pending' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => 
                        Team::find($state)?->name ?? '—'
                    ),

                Tables\Columns\TextColumn::make('blue_3')
                    ->label('Blue 3')
                    ->badge()
                    ->color(fn (Matches $record) => match (Matches::getTeamServiceStatus($record->blue_3)) {
                        'completed' => 'success',
                        'in_progress' => 'warning',
                        'pending' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => 
                        Team::find($state)?->name ?? '—'
                    ),

                Tables\Columns\TextColumn::make('red_1')
                    ->label('Red 1')
                    ->badge()
                    ->color(fn (Matches $record) => match (Matches::getTeamServiceStatus($record->red_1)) {
                        'completed' => 'success',
                        'in_progress' => 'warning',
                        'pending' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => 
                        Team::find($state)?->name ?? '—'
                    ),

                Tables\Columns\TextColumn::make('red_2')
                    ->label('Red 2')
                    ->badge()
                    ->color(fn (Matches $record) => match (Matches::getTeamServiceStatus($record->red_2)) {
                        'completed' => 'success',
                        'in_progress' => 'warning',
                        'pending' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => 
                        Team::find($state)?->name ?? '—'
                    ),

                Tables\Columns\TextColumn::make('red_3')
                    ->label('Red 3')
                    ->badge()
                    ->color(fn (Matches $record) => match (Matches::getTeamServiceStatus($record->red_3)) {
                        'completed' => 'success',
                        'in_progress' => 'warning',
                        'pending' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => 
                        Team::find($state)?->name ?? '—'
                    ),

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
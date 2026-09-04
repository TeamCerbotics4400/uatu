<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MxTaskResource\Pages;
use App\Models\MxTask;
use App\Models\User;
use App\Services\TaskStateMachine;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;

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
                        'CHECKLIST' => 'CheckList',
                        'PIT_CHECKLIST' => 'Pit + CheckList',
                    ])
                    ->required()
                    ->label('Task Type'),

                Forms\Components\Select::make('status')
                    ->options([
                        'PENDING' => 'Pending',
                        'IN_PROGRESS' => 'In Progress',
                        'BLOCKED' => 'Blocked',
                        'DONE' => 'Done',
                        'CANCELLED' => 'Cancelled',
                    ])
                    ->required()
                    ->default('PENDING')
                    ->disabled()
                    ->dehydrated()
                    ->label('Status'),

                Forms\Components\Section::make('Assigned Users')
                    ->description('Select up to 4 users (all optional)')
                    ->schema([
                        Forms\Components\Select::make('assigned_user_1')
                            ->relationship('assignedUser1', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->label('Assigned User 1'),

                        Forms\Components\Select::make('assigned_user_2')
                            ->relationship('assignedUser2', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->label('Assigned User 2'),

                        Forms\Components\Select::make('assigned_user_3')
                            ->relationship('assignedUser3', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->label('Assigned User 3'),

                        Forms\Components\Select::make('assigned_user_4')
                            ->relationship('assignedUser4', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->label('Assigned User 4'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Timeline')
                    ->schema([
                        Forms\Components\Placeholder::make('started_at')
                            ->label('Started At')
                            ->content(fn (?MxTask $record): string => $record?->started_at ? $record->started_at->format('M d, Y H:i') : '-')
                            ->hidden(fn (?MxTask $record): bool => $record === null),

                        Forms\Components\Placeholder::make('completed_at')
                            ->label('Completed At')
                            ->content(fn (?MxTask $record): string => $record?->completed_at ? $record->completed_at->format('M d, Y H:i') : '-')
                            ->hidden(fn (?MxTask $record): bool => $record === null),
                    ]),
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
                        'CHECKLIST' => 'success',
                        'PIT_CHECKLIST' => 'primary',
                    })
                    ->label('Type'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'PENDING' => 'gray',
                        'IN_PROGRESS' => 'warning',
                        'BLOCKED' => 'danger',
                        'DONE' => 'success',
                        'CANCELLED' => 'info',
                    })
                    ->label('Status'),

                Tables\Columns\TextColumn::make('assignedUser1.name')
                    ->searchable()
                    ->label('User 1')
                    ->default('—'),

                Tables\Columns\TextColumn::make('assignedUser2.name')
                    ->searchable()
                    ->label('User 2')
                    ->default('—'),

                Tables\Columns\TextColumn::make('assignedUser3.name')
                    ->searchable()
                    ->label('User 3')
                    ->default('—'),

                Tables\Columns\TextColumn::make('assignedUser4.name')
                    ->searchable()
                    ->label('User 4')
                    ->default('—'),

                Tables\Columns\TextColumn::make('started_at')
                    ->dateTime('M d, H:i')
                    ->sortable()
                    ->label('Started')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('completed_at')
                    ->dateTime('M d, H:i')
                    ->sortable()
                    ->label('Completed')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('elapsed_time')
                    ->label('Elapsed Time')
                    ->state(function (MxTask $record): ?string {
                        $stateMachine = new TaskStateMachine();
                        return $stateMachine->getMxTaskElapsedTime($record);
                    })
                    ->default('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'PIT' => 'Pit',
                        'MATCH' => 'Match',
                        'CHECKLIST' => 'CheckList',
                        'PIT_CHECKLIST' => 'Pit + CheckList',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'PENDING' => 'Pending',
                        'IN_PROGRESS' => 'In Progress',
                        'BLOCKED' => 'Blocked',
                        'DONE' => 'Done',
                        'CANCELLED' => 'Cancelled',
                    ]),
            ])
            ->actions([
                Action::make('start')
                    ->label('Start')
                    ->icon('heroicon-o-play')
                    ->visible(fn (MxTask $record): bool => $record->status === 'PENDING')
                    ->action(function (MxTask $record): void {
                        $stateMachine = new TaskStateMachine();
                        $result = $stateMachine->mxStart($record);
                        if ($result) {
                            \Filament\Notifications\Notification::make()
                                ->title('Success')
                                ->body('Task started')
                                ->success()
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->title('Error')
                                ->body('Cannot start task in current state')
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('block')
                    ->label('Block')
                    ->icon('heroicon-o-hand-raised')
                    ->visible(fn (MxTask $record): bool => $record->status === 'IN_PROGRESS')
                    ->color('warning')
                    ->action(function (MxTask $record): void {
                        $stateMachine = new TaskStateMachine();
                        $result = $stateMachine->mxBlock($record);
                        if ($result) {
                            \Filament\Notifications\Notification::make()
                                ->title('Success')
                                ->body('Task blocked')
                                ->warning()
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->title('Error')
                                ->body('Cannot block task in current state')
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('unblock')
                    ->label('Unblock')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (MxTask $record): bool => $record->status === 'BLOCKED')
                    ->color('info')
                    ->action(function (MxTask $record): void {
                        $stateMachine = new TaskStateMachine();
                        $result = $stateMachine->mxUnblock($record);
                        if ($result) {
                            \Filament\Notifications\Notification::make()
                                ->title('Success')
                                ->body('Task unblocked, resumed')
                                ->success()
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->title('Error')
                                ->body('Cannot resume task')
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('complete')
                    ->label('Complete')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (MxTask $record): bool => in_array($record->status, ['IN_PROGRESS', 'BLOCKED']))
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (MxTask $record): void {
                        $stateMachine = new TaskStateMachine();
                        $result = $stateMachine->mxComplete($record);
                        if ($result) {
                            \Filament\Notifications\Notification::make()
                                ->title('Success')
                                ->body('Task completed. Users set to AVAILABLE.')
                                ->success()
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->title('Error')
                                ->body('Cannot complete task in current state')
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->visible(fn (MxTask $record): bool => !in_array($record->status, ['DONE', 'CANCELLED']))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (MxTask $record): void {
                        $stateMachine = new TaskStateMachine();
                        $result = $stateMachine->mxCancel($record);
                        if ($result) {
                            \Filament\Notifications\Notification::make()
                                ->title('Success')
                                ->body('Task cancelled. Users set to AVAILABLE.')
                                ->success()
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->title('Error')
                                ->body('Cannot cancel task in current state')
                                ->danger()
                                ->send();
                        }
                    }),

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
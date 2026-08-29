<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceTaskResource\Pages;
use App\Models\ServiceTask;
use App\Models\Team;
use App\Models\User;
use App\Services\TaskStateMachine;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Forms\Components;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ServiceTaskResource extends Resource
{
    protected static ?string $model = ServiceTask::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Service Tasks';
    protected static ?string $modelLabel = 'Service Task';
    protected static ?string $pluralModelLabel = 'Service Tasks';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Task Information')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'PENDING' => 'Pending',
                                'ASSIGNED' => 'Assigned',
                                'IN_PROGRESS' => 'In Progress',
                                'BLOCKED' => 'Blocked',
                                'COMPLETED' => 'Completed',
                                'CANCELLED' => 'Cancelled',
                            ])
                            ->required()
                            ->disabled()
                            ->label('Status'),

                        Forms\Components\Select::make('assigned_team')
                            ->relationship('team', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->label('Team'),

                        Forms\Components\Select::make('assigned_user')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->label('Assigned User'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Timestamps')
                    ->schema([
                        Forms\Components\DateTimePicker::make('started_at')
                            ->label('Started At')
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('completed_at')
                            ->label('Completed At')
                            ->disabled(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable()
                    ->limit(10),

                TextColumn::make('team.name')
                    ->label('Team')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('user.name')
                    ->label('Assigned User')
                    ->sortable()
                    ->searchable()
                    ->default('—'),

                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'gray' => 'PENDING',
                        'info' => 'ASSIGNED',
                        'warning' => 'IN_PROGRESS',
                        'danger' => 'BLOCKED',
                        'success' => 'COMPLETED',
                        'secondary' => 'CANCELLED',
                    ])
                    ->sortable(),

                TextColumn::make('started_at')
                    ->label('Started')
                    ->dateTime('M d, H:i')
                    ->sortable()
                    ->default('—'),

                TextColumn::make('completed_at')
                    ->label('Completed')
                    ->dateTime('M d, H:i')
                    ->sortable()
                    ->default('—'),

                TextColumn::make('elapsed_time')
                    ->label('Elapsed Time')
                    ->state(function (ServiceTask $record): ?string {
                        $stateMachine = new TaskStateMachine();
                        return $stateMachine->getElapsedTime($record);
                    })
                    ->default('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'PENDING' => 'Pending',
                        'ASSIGNED' => 'Assigned',
                        'IN_PROGRESS' => 'In Progress',
                        'BLOCKED' => 'Blocked',
                        'COMPLETED' => 'Completed',
                        'CANCELLED' => 'Cancelled',
                    ]),

                Tables\Filters\SelectFilter::make('assigned_team')
                    ->relationship('team', 'name'),
            ])
            ->actions([
                Action::make('assign')
                    ->label('Assign')
                    ->icon('heroicon-o-check')
                    ->visible(fn (ServiceTask $record): bool => $record->status === 'PENDING')
                    ->form([
                        Forms\Components\Select::make('assigned_user')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->label('Select User'),
                    ])
                    ->action(function (ServiceTask $record, array $data): void {
                        $stateMachine = new TaskStateMachine();
                        $user = User::find($data['assigned_user']);

                        if ($user) {
                            $result = $stateMachine->toAssigned($record, $user);
                            if ($result) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Success')
                                    ->body("Task assigned to {$user->name}")
                                    ->success()
                                    ->send();
                            } else {
                                \Filament\Notifications\Notification::make()
                                    ->title('Error')
                                    ->body('Cannot assign task. User may have active tasks.')
                                    ->danger()
                                    ->send();
                            }
                        }
                    }),

                Action::make('start')
                    ->label('Start')
                    ->icon('heroicon-o-play')
                    ->visible(fn (ServiceTask $record): bool => $record->status === 'ASSIGNED')
                    ->action(function (ServiceTask $record): void {
                        $stateMachine = new TaskStateMachine();
                        $result = $stateMachine->toInProgress($record);

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
                    ->visible(fn (ServiceTask $record): bool => $record->status === 'IN_PROGRESS')
                    ->color('warning')
                    ->action(function (ServiceTask $record): void {
                        $stateMachine = new TaskStateMachine();
                        $result = $stateMachine->toBlocked($record);

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
                    ->visible(fn (ServiceTask $record): bool => $record->status === 'BLOCKED')
                    ->color('info')
                    ->action(function (ServiceTask $record): void {
                        $stateMachine = new TaskStateMachine();
                        $result = $stateMachine->toInProgress($record);

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
                    ->visible(fn (ServiceTask $record): bool => in_array($record->status, ['IN_PROGRESS', 'BLOCKED']))
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (ServiceTask $record): void {
                        $stateMachine = new TaskStateMachine();
                        $result = $stateMachine->toCompleted($record);

                        if ($result) {
                            \Filament\Notifications\Notification::make()
                                ->title('Success')
                                ->body('Task completed. User set to AVAILABLE.')
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
                    ->visible(fn (ServiceTask $record): bool => !in_array($record->status, ['COMPLETED', 'CANCELLED']))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (ServiceTask $record): void {
                        $stateMachine = new TaskStateMachine();
                        $result = $stateMachine->toCancelled($record);

                        if ($result) {
                            \Filament\Notifications\Notification::make()
                                ->title('Success')
                                ->body('Task cancelled. User set to AVAILABLE.')
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
            'index' => Pages\ListServiceTasks::route('/'),
            'create' => Pages\CreateServiceTask::route('/create'),
            'edit' => Pages\EditServiceTask::route('/{record}/edit'),
        ];
    }
}
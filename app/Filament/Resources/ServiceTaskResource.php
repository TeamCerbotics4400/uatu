<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceTaskResource\Pages;
use App\Models\ServiceTask;
use App\Models\Team;
use App\Models\User;
use App\Models\Matches;
use App\Services\TaskStateMachine;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
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
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Task Details')
                            ->description('Assign a team and match to this task.')
                            ->schema([
                                Forms\Components\Select::make('assigned_team')
                                    ->relationship('team', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->label('Assigned Team')
                                    ->live()
                                    ->columnSpanFull(),

                                Forms\Components\Select::make('match_id')
                                    ->label('Match')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->options(function ($get) {
                                        $teamId = $get('assigned_team');
                                        if (!$teamId) {
                                            return [];
                                        }
                                        return Matches::getMatchesForTeam($teamId)
                                            ->pluck('number', 'id')
                                            ->mapWithKeys(fn ($number, $id) => [$id => "Match #$number"])
                                            ->toArray();
                                    })
                                    ->columnSpanFull(),

                                Forms\Components\Select::make('priority')
                                    ->options([
                                        '1' => 'Priority 1 (Critical)',
                                        '2' => 'Priority 2 (High)',
                                        '3' => 'Priority 3 (High-Medium)',
                                        '4' => 'Priority 4 (Medium)',
                                        '5' => 'Priority 5 (Medium-Low)',
                                        '6' => 'Priority 6 (Low)',
                                        '7' => 'Priority 7 (Very Low)',
                                    ])
                                    ->required()
                                    ->default('4')
                                    ->label('Priority')
                                    ->columnSpanFull(),

                                Forms\Components\Select::make('required_service')
                                    ->options([
                                        'MECHANICAL' => 'Mechanical',
                                        'PROGRAMMING' => 'Programming',
                                        'BOTH' => 'Both',
                                        'NONE' => 'None',
                                    ])
                                    ->required()
                                    ->default('NONE')
                                    ->label('Required Service')
                                    ->columnSpanFull(),

                                Forms\Components\Select::make('assigned_user')
                                    ->relationship('user', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->nullable()
                                    ->label('Assigned User')
                                    ->hint('Selecting a user automatically assigns the task.')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Team Info')
                            ->schema([
                                Forms\Components\Placeholder::make('team_required_service')
                                    ->label('Required Service')
                                    ->content(fn (?ServiceTask $record): string => 
                                        $record?->team?->required_service ?? '—'
                                    ),

                                Forms\Components\Placeholder::make('team_status')
                                    ->label('Team Current Status')
                                    ->content(fn (?ServiceTask $record): string => 
                                        $record?->team?->current_service_status ?? '—'
                                    ),
                            ]),

                        Forms\Components\Section::make('Status & Timeline')
                            ->description('Status is automatically set to PENDING when created. Use action buttons to change state.')
                            ->schema([
                                Forms\Components\Placeholder::make('status')
                                    ->label('Status')
                                    ->content(fn (?ServiceTask $record): string => 
                                        $record?->status ?? 'PENDING'
                                    ),

                                Forms\Components\Placeholder::make('started_at')
                                    ->label('Started At')
                                    ->content(fn (?ServiceTask $record): string => 
                                        $record?->started_at?->format('Y-m-d H:i:s') ?? '—'
                                    ),

                                Forms\Components\Placeholder::make('completed_at')
                                    ->label('Completed At')
                                    ->content(fn (?ServiceTask $record): string => 
                                        $record?->completed_at?->format('Y-m-d H:i:s') ?? '—'
                                    ),
                            ]),

                        Forms\Components\Section::make('Timestamps')
                            ->schema([
                                Forms\Components\Placeholder::make('created_at')
                                    ->label('Created At')
                                    ->content(fn (?ServiceTask $record): string => 
                                        $record?->created_at?->format('Y-m-d H:i:s') ?? '—'
                                    ),

                                Forms\Components\Placeholder::make('updated_at')
                                    ->label('Updated At')
                                    ->content(fn (?ServiceTask $record): string => 
                                        $record?->updated_at?->format('Y-m-d H:i:s') ?? '—'
                                    ),
                            ])
                            ->collapsed(),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->searchable()
                    ->label('Task ID')
                    ->toggleable(isToggledHiddenByDefault: true),

                BadgeColumn::make('status')
                    ->colors([
                        'danger' => 'PENDING',
                        'info' => 'ASSIGNED',
                        'warning' => 'IN_PROGRESS',
                        'danger' => 'BLOCKED',
                        'success' => 'COMPLETED',
                        'gray' => 'CANCELLED',
                    ])
                    ->label('Status'),

                Tables\Columns\TextColumn::make('team.name')
                    ->searchable()
                    ->label('Team'),

                BadgeColumn::make('priority')
                    ->colors([
                        'danger' => ['1', '2'],
                        'warning' => ['3', '4'],
                        'success' => ['5', '6', '7'],
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        '1' => 'Priority 1 (Critical)',
                        '2' => 'Priority 2 (High)',
                        '3' => 'Priority 3 (High-Medium)',
                        '4' => 'Priority 4 (Medium)',
                        '5' => 'Priority 5 (Medium-Low)',
                        '6' => 'Priority 6 (Low)',
                        '7' => 'Priority 7 (Very Low)',
                        default => 'Unknown',
                    })
                    ->label('Priority'),

                Tables\Columns\TextColumn::make('required_service')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'MECHANICAL' => 'info',
                        'PROGRAMMING' => 'warning',
                        'BOTH' => 'danger',
                        'NONE' => 'gray',
                        default => 'gray',
                    })
                    ->label('Required Service'),

                Tables\Columns\TextColumn::make('user.name')
                    ->searchable()
                    ->label('Assigned User'),

                Tables\Columns\TextColumn::make('match.number')
                    ->label('Match #'),

                Tables\Columns\TextColumn::make('started_at')
                    ->dateTime()
                    ->label('Started At')
                    ->sortable(),

                Tables\Columns\TextColumn::make('completed_at')
                    ->dateTime()
                    ->label('Completed At')
                    ->sortable(),

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
                        'BLOCKED' => 'Blocked',
                        'COMPLETED' => 'Completed',
                        'CANCELLED' => 'Cancelled',
                    ]),

                Tables\Filters\SelectFilter::make('priority')
                    ->options([
                        '1' => 'Priority 1 (Critical)',
                        '2' => 'Priority 2 (High)',
                        '3' => 'Priority 3 (High-Medium)',
                        '4' => 'Priority 4 (Medium)',
                        '5' => 'Priority 5 (Medium-Low)',
                        '6' => 'Priority 6 (Low)',
                        '7' => 'Priority 7 (Very Low)',
                    ]),

                Tables\Filters\SelectFilter::make('required_service')
                    ->options([
                        'MECHANICAL' => 'Mechanical',
                        'PROGRAMMING' => 'Programming',
                        'BOTH' => 'Both',
                        'NONE' => 'None',
                    ]),

                Tables\Filters\SelectFilter::make('assigned_team')
                    ->relationship('team', 'name'),

                Tables\Filters\SelectFilter::make('match_id')
                    ->relationship('match', 'number')
                    ->label('Match'),
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
<?php

namespace App\Filament\Dashboard\Resources\Resumes\Tables;

use App\Jobs\GenerateResumeJob;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ResumesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('job_title')
                    ->label('Job Title')
                    ->searchable()
                    ->sortable()
                    ->limit(50),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('content')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => $state ? 'success' : 'warning')
                    ->formatStateUsing(fn (string $state): string => $state ? 'Generated' : 'Pending')
                    ->toggleable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('regenerate')
                    ->label('Regenerate')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Regenerate Resume')
                    ->modalDescription('This will regenerate the resume content using the latest job details and profile information. The existing content will be replaced.')
                    ->modalSubmitActionLabel('Regenerate')
                    ->action(function ($record) {
                        GenerateResumeJob::dispatch($record);

                        Notification::make()
                            ->success()
                            ->title('Resume Regeneration Started')
                            ->body('Your resume is being regenerated with the latest information.')
                            ->send();
                    })
                    ->visible(fn ($record) => !empty($record->content)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}

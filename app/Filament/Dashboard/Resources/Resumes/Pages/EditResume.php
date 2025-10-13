<?php

namespace App\Filament\Dashboard\Resources\Resumes\Pages;

use App\Filament\Dashboard\Resources\Resumes\ResumeResource;
use App\Jobs\GenerateResumeJob;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditResume extends EditRecord
{
    protected static string $resource = ResumeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        // Regenerate the resume content when job details are updated
        GenerateResumeJob::dispatch($this->record);

        Notification::make()
            ->success()
            ->title('Resume Updated')
            ->body('Your resume has been updated and is being regenerated with the new job details.')
            ->send();
    }
}

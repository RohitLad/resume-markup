<?php

namespace App\Filament\Dashboard\Resources\Resumes\Pages;

use App\Filament\Dashboard\Resources\Resumes\ResumeResource;
use App\Services\ResumeProcessingService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateResume extends CreateRecord
{
    protected static string $resource = ResumeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        // Initiate resume generation
        $processingService = app(ResumeProcessingService::class);
        $processingService->initiateResumeGeneration($this->record);

        // Show success notification
        Notification::make()
            ->success()
            ->title('Resume Created')
            ->body('Your resume has been created and is being generated. You will be notified when it\'s ready.')
            ->send();
    }
}

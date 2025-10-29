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
        $processingService = app(ResumeProcessingService::class);
        
        try {
            $processingService->initiateResumeGeneration($this->record);

            Notification::make()
                ->success()
                ->title('Resume Created')
                ->body('Your resume has been created and is being generated. You will be notified when it\'s ready.')
                ->send();
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Knowledge base needs to be updated')) {
                // Knowledge base needs to be updated first
                $processingService->initiateKnowledgeBaseGeneration($this->record->user_id);

                Notification::make()
                    ->info()
                    ->title('Knowledge Base Update Required')
                    ->body('Your knowledge base is being updated first. Resume generation will start automatically once complete.')
                    ->send();
            } else {
                throw $e;
            }
        }
    }
}

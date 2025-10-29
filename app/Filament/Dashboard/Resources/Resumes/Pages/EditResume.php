<?php

namespace App\Filament\Dashboard\Resources\Resumes\Pages;

use App\Filament\Dashboard\Resources\Resumes\ResumeResource;
use App\Services\ResumeProcessingService;
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
        $processingService = app(ResumeProcessingService::class);
        
        try {
            $processingService->initiateResumeGeneration($this->record);

            Notification::make()
                ->success()
                ->title('Resume Updated')
                ->body('Your resume has been updated and is being regenerated with the new job details.')
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

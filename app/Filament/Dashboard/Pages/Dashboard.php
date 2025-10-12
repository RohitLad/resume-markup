<?php

namespace App\Filament\Dashboard\Pages;

use App\Models\Profile;
use App\Services\OpenAIService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use BackedEnum;

class Dashboard extends Page implements HasForms
{
    use InteractsWithForms;
    protected string $view = 'filament.dashboard.pages.dashboard';
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-document-text';

    public ?array $data = [];
    
    public function mount(): void
    {
        $this->form->fill();
    }
    
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('resume')
                ->directory('resumes')
                ->previewable(true)
                ->storeFile(false)
                ->acceptedFileTypes(['application/pdf'])
                ->afterStateUpdated(function ($state, callable $set){
                    if ($state){
                        $this->processResumeFile($state, $set);
                    }
                })
            ])
            ->statePath('data');
    }

    protected function processResumeFile($file, callable $set): void
    {
        $openAIService = app(OpenAIService::class);
        $parsedData = $openAIService->parseResumePdf($file->getRealPath());

        Profile::updateOrCreate(
            ['user_id' => auth()->id()],
            ['data' => $parsedData]
        );
    }

    public function create(): void
    {
    }
}

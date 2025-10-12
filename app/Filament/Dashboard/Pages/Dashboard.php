<?php

namespace App\Filament\Dashboard\Pages;

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
            ])
            ->statePath('data');
    }

    public function create(): void
    {
    }
}

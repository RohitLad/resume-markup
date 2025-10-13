<?php

namespace App\Filament\Dashboard\Resources\Resumes\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ResumeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('job_title')
                    ->label('Job Title')
                    ->required()
                    ->placeholder('e.g., Senior Software Engineer, Data Scientist')
                    ->helperText('Enter the specific job title you\'re applying for'),

                Textarea::make('job_description')
                    ->label('Job Description')
                    ->required()
                    ->rows(8)
                    ->placeholder('Paste the full job description here...')
                    ->helperText('Copy and paste the complete job posting to help AI optimize your resume with relevant keywords and requirements')
                    ->columnSpanFull(),
            ]);
    }
}

<?php

namespace App\Filament\Dashboard\Resources\Resumes\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ResumeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Job Details')
                    ->schema([
                        TextEntry::make('job_title')
                            ->label('Target Job Title')
                            ->columnSpanFull(),

                        TextEntry::make('job_description')
                            ->label('Job Description')
                            ->columnSpanFull()
                            ->markdown()
                            ->limit(500),
                    ])
                    ->columns(1),

                Section::make('Generated Resume')
                    ->schema([
                        TextEntry::make('content')
                            ->label('')
                            ->columnSpanFull()
                            ->markdown()
                            ->visible(fn ($record) => !empty($record->content)),
                    ])
                    ->visible(fn ($record) => !empty($record->content))
                    ->description('This resume was generated specifically for the job above using AI optimization for ATS compatibility and keyword matching.'),

                Section::make('Resume Status')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),

                        TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime(),

                        TextEntry::make('content')
                            ->label('Generation Status')
                            ->formatStateUsing(fn ($state) => $state ? '✅ Generated' : '⏳ Pending Generation')
                            ->color(fn ($state) => $state ? 'success' : 'warning'),
                    ])
                    ->columns(3)
                    ->visible(fn ($record) => empty($record->content))
                    ->description('Resume is being generated. Please check back in a few moments.'),
            ]);
    }
}

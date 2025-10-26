<?php

namespace App\Filament\Dashboard\Resources\Resumes;

use App\Filament\Dashboard\Resources\Resumes\Pages\CreateResume;
use App\Filament\Dashboard\Resources\Resumes\Pages\EditResume;
use App\Filament\Dashboard\Resources\Resumes\Pages\ListResumes;
use App\Filament\Dashboard\Resources\Resumes\Pages\ViewResume;
use App\Filament\Dashboard\Resources\Resumes\Schemas\ResumeForm;
use App\Filament\Dashboard\Resources\Resumes\Schemas\ResumeInfolist;
use App\Filament\Dashboard\Resources\Resumes\Tables\ResumesTable;
use App\Models\Resume;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ResumeResource extends Resource
{
    protected static ?string $model = Resume::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-folder-plus';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('user_id', auth()->id());
    }

    public static function form(Schema $schema): Schema
    {
        return ResumeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ResumeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ResumesTable::configure($table);
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
            'index' => ListResumes::route('/'),
            'create' => CreateResume::route('/create'),
            'view' => ViewResume::route('/{record}'),
            'edit' => EditResume::route('/{record}/edit'),
        ];
    }
}

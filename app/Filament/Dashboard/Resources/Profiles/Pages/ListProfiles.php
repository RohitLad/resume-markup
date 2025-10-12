<?php

namespace App\Filament\Dashboard\Resources\Profiles\Pages;

use App\Filament\Dashboard\Resources\Profiles\ProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProfiles extends ListRecords
{
    protected static string $resource = ProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Dashboard\Resources\Profiles\Pages;

use App\Filament\Dashboard\Resources\Profiles\ProfileResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProfile extends EditRecord
{
    protected static string $resource = ProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

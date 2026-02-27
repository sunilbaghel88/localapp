<?php

namespace App\Filament\Resources\ShopTypeResource\Pages;

use App\Filament\Resources\ShopTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditShopType extends EditRecord
{
    protected static string $resource = ShopTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}


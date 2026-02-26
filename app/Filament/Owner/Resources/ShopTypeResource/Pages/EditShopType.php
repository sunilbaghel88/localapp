<?php

namespace App\Filament\Owner\Resources\ShopTypeResource\Pages;

use App\Filament\Owner\Resources\ShopTypeResource;
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

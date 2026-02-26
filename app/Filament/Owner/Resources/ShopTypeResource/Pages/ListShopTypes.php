<?php

namespace App\Filament\Owner\Resources\ShopTypeResource\Pages;

use App\Filament\Owner\Resources\ShopTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListShopTypes extends ListRecords
{
    protected static string $resource = ShopTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

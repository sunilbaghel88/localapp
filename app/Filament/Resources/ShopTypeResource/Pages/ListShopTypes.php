<?php

namespace App\Filament\Resources\ShopTypeResource\Pages;

use App\Filament\Resources\ShopTypeResource;
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


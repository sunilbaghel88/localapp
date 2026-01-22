<?php

namespace App\Filament\Owner\Resources\ProductVariantResource\Pages;

use App\Filament\Owner\Resources\ProductVariantResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateProductVariant extends CreateRecord
{
    protected static string $resource = ProductVariantResource::class;
}

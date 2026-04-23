<?php

namespace App\Filament\Resources\RewardRedemptionRequestResource\Pages;

use App\Filament\Resources\RewardRedemptionRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListRewardRedemptionRequests extends ListRecords
{
    protected static string $resource = RewardRedemptionRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

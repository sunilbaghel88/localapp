<?php

namespace App\Filament\Resources\ShopPeopleResource\Pages;

use App\Filament\Resources\ShopPeopleResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListShopPeople extends ListRecords
{
    protected static string $resource = ShopPeopleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add person'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'customers' => Tab::make('Customers')
                ->modifyQueryUsing(fn (Builder $query) => $query->customers()),
            'partners' => Tab::make('Partners')
                ->modifyQueryUsing(fn (Builder $query) => $query->partners()),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'customers';
    }
}

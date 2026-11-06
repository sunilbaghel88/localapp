<?php

namespace App\Filament\Resources\ShopPeopleResource\Pages;

use App\Filament\Resources\ShopPeopleResource;
use App\Models\User;
use App\Services\Users\ShopOwnerPeopleService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateShopPerson extends CreateRecord
{
    protected static string $resource = ShopPeopleResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();
        $service = app(ShopOwnerPeopleService::class);

        if (($data['kind'] ?? 'customer') === 'partner') {
            return $service->createPartner($actor, $data);
        }

        return $service->createCustomer($actor, $data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

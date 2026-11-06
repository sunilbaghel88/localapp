<?php

namespace App\Filament\Resources\ShopPeopleResource\Pages;

use App\Filament\Resources\ShopPeopleResource;
use App\Models\User;
use App\Services\Users\ShopOwnerPeopleService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditShopPerson extends EditRecord
{
    protected static string $resource = ShopPeopleResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $record */
        $record = $this->record;
        $actor = auth()->user();

        $data['kind'] = $record->isPartner() ? 'partner' : 'customer';
        $data['name'] = $record->name;
        $data['user_type_ids'] = $record->userTypes()->pluck('user_types.id')->all();
        $data['shop_ids'] = $actor instanceof User
            ? $record->partnerShops()->where('shops.user_id', $actor->id)->pluck('shops.id')->all()
            : [];

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();
        /** @var User $record */

        return app(ShopOwnerPeopleService::class)->updatePerson($actor, $record, $data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

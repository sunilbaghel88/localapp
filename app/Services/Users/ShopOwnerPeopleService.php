<?php

namespace App\Services\Users;

use App\Models\Shop;
use App\Models\ShopType;
use App\Models\User;
use App\Models\UserType;
use App\Services\Sms\SmsSender;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShopOwnerPeopleService
{
    public function authorizeActor(User $actor): void
    {
        abort_unless($actor->canAssignUserTypes(), 403);
    }

    public function peopleQuery(User $actor, string $kind, ?string $search = null): Builder
    {
        $this->authorizeActor($actor);

        $query = User::query()
            ->with(['userTypes', 'partnerShops' => fn ($q) => $q->where('shops.user_id', $actor->id)])
            ->withoutSuperAdmins()
            ->orderByName();

        if ($kind === 'partner') {
            $query->partners();
        } else {
            $query->customers();
        }

        $search = trim((string) $search);
        if ($search !== '') {
            $needle = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(function (Builder $builder) use ($needle) {
                $builder->whereNameLike($needle)
                    ->orWhere('email', 'like', $needle)
                    ->orWhere('phone', 'like', $needle);
            });
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(User $actor): array
    {
        $this->authorizeActor($actor);

        $typeIds = ShopType::rewardEligibleUserTypeIds();
        $types = $typeIds === []
            ? collect()
            : UserType::query()
                ->whereIn('id', $typeIds)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'slug']);

        $shops = $actor->shops()
            ->with(['shopType.rewardUserTypes'])
            ->orderBy('name')
            ->get();

        return [
            'partner_types' => $types->map(fn (UserType $type) => [
                'id' => $type->id,
                'name' => $type->name,
                'slug' => $type->slug,
            ])->values()->all(),
            'shops' => $shops->map(fn (Shop $shop) => [
                'id' => $shop->id,
                'name' => $shop->name,
                'supports_partners' => (bool) $shop->shopType?->supportsPartnerRewards(),
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createCustomer(User $actor, array $data): User
    {
        $this->authorizeActor($actor);

        $phone = $this->uniquePhone((string) ($data['phone'] ?? ''));
        $user = User::create([
            'name' => trim((string) ($data['name'] ?? '')),
            'email' => $this->phoneEmail($phone),
            'phone' => $phone,
            'password' => Hash::make(Str::random(32)),
            'is_active' => true,
        ]);

        app(SmsSender::class)->notifyCustomerCreated($user, $this->resolveShop($actor, $data['shop_id'] ?? null));

        return $user->fresh(['userTypes', 'partnerShops']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createPartner(User $actor, array $data): User
    {
        $this->authorizeActor($actor);

        $shop = $this->ownedShop($actor, (int) ($data['shop_id'] ?? 0));
        $shop->loadMissing(['shopType.rewardUserTypes']);
        $rewardTypeIds = $shop->shopType?->rewardUserTypeIds() ?? [];

        if (! $shop->shopType?->supportsPartnerRewards()) {
            throw ValidationException::withMessages([
                'shop_id' => [__('This shop does not support partner rewards.')],
            ]);
        }

        $typeId = isset($data['user_type_id'])
            ? (int) $data['user_type_id']
            : (int) ($rewardTypeIds[0] ?? 0);

        if (! in_array($typeId, $rewardTypeIds, true)) {
            throw ValidationException::withMessages([
                'user_type_id' => [__('Select a reward-eligible user type for this shop.')],
            ]);
        }

        $phone = $this->uniquePhone((string) ($data['phone'] ?? ''));
        $user = User::create([
            'name' => trim((string) ($data['name'] ?? '')),
            'email' => $this->phoneEmail($phone),
            'phone' => $phone,
            'password' => Hash::make(Str::random(32)),
            'is_active' => true,
        ]);
        $user->userTypes()->syncWithoutDetaching([$typeId]);
        $shop->partners()->syncWithoutDetaching([$user->id]);

        app(SmsSender::class)->notifyPartnerCreated($user, $shop);

        return $user->fresh(['userTypes', 'partnerShops']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updatePerson(User $actor, User $user, array $data): User
    {
        $this->authorizeActor($actor);
        abort_unless($actor->canAssignUserTypesTo($user), 403);

        $phone = $this->uniquePhone((string) ($data['phone'] ?? $user->phone), $user->id);
        $email = $user->email;
        if (str_ends_with((string) $email, '@phone.localapp') || blank($email)) {
            $email = $this->phoneEmail($phone);
        }

        $user->update([
            'name' => trim((string) ($data['name'] ?? $user->name)),
            'phone' => $phone,
            'email' => $email,
            'is_active' => array_key_exists('is_active', $data)
                ? (bool) $data['is_active']
                : $user->is_active,
        ]);

        if (array_key_exists('user_type_ids', $data)) {
            $allowed = ShopType::rewardEligibleUserTypeIds();
            $typeIds = collect($data['user_type_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => in_array($id, $allowed, true))
                ->unique()
                ->values()
                ->all();
            $user->userTypes()->sync($typeIds);
        }

        if (array_key_exists('shop_ids', $data)) {
            $this->syncOwnedShops($actor, $user, $data['shop_ids'] ?? []);
        }

        return $user->fresh(['userTypes', 'partnerShops']);
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(User $user, User $actor): array
    {
        $user->loadMissing(['userTypes']);
        $ownedShops = $user->partnerShops()
            ->where('shops.user_id', $actor->id)
            ->orderBy('shops.name')
            ->get(['shops.id', 'shops.name']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'is_active' => (bool) $user->is_active,
            'kind' => $user->isPartner() ? 'partner' : 'customer',
            'reward_points' => (int) ($user->reward_points ?? 0),
            'user_types' => $user->userTypesPayload(),
            'shops' => $ownedShops->map(fn (Shop $shop) => [
                'id' => $shop->id,
                'name' => $shop->name,
            ])->values()->all(),
        ];
    }

    public function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) > 10) {
            $digits = substr($digits, -10);
        }

        return $digits;
    }

    protected function uniquePhone(string $phone, ?int $ignoreUserId = null): string
    {
        $phone = $this->normalizePhone($phone);
        if (strlen($phone) < 10) {
            throw ValidationException::withMessages([
                'phone' => [__('Enter a valid 10-digit mobile number.')],
            ]);
        }

        $taken = User::query()
            ->when($ignoreUserId, fn ($q) => $q->where('id', '!=', $ignoreUserId))
            ->where('phone', $phone)
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'phone' => [__('An account already exists for this mobile number.')],
            ]);
        }

        $email = $this->phoneEmail($phone);
        $emailTaken = User::query()
            ->when($ignoreUserId, fn ($q) => $q->where('id', '!=', $ignoreUserId))
            ->where('email', $email)
            ->exists();

        if ($emailTaken) {
            throw ValidationException::withMessages([
                'phone' => [__('An account already exists for this mobile number.')],
            ]);
        }

        return $phone;
    }

    protected function phoneEmail(string $phone): string
    {
        return $phone.'@phone.localapp';
    }

    protected function ownedShop(User $actor, int $shopId): Shop
    {
        $shop = $actor->shops()->where('shops.id', $shopId)->first();
        if (! $shop) {
            throw ValidationException::withMessages([
                'shop_id' => [__('Select one of your shops.')],
            ]);
        }

        return $shop;
    }

    protected function resolveShop(User $actor, mixed $shopId): ?Shop
    {
        $id = (int) $shopId;
        if ($id > 0) {
            return $actor->shops()->where('shops.id', $id)->first();
        }

        return $actor->shops()->orderBy('name')->first();
    }

    /**
     * @param  list<mixed>  $shopIds
     */
    protected function syncOwnedShops(User $actor, User $user, array $shopIds): void
    {
        $ownedIds = $actor->shops()->pluck('id');
        $requested = collect($shopIds)
            ->map(fn ($id) => (int) $id)
            ->intersect($ownedIds)
            ->values();

        $currentOwned = $user->partnerShops()
            ->whereIn('shops.id', $ownedIds)
            ->pluck('shops.id');

        $user->partnerShops()->detach($currentOwned->diff($requested)->all());
        $user->partnerShops()->syncWithoutDetaching($requested->all());
    }
}

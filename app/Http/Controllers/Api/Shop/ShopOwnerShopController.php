<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ShopOwnerShopController extends Controller
{
    /**
     * @var array<string, array{directory: string, mimes: string}>
     */
    protected const DOCUMENT_FIELDS = [
        'shop_front_photo' => [
            'directory' => 'shop-documents/shop-front',
            'mimes' => 'jpeg,jpg,png,webp',
        ],
        'owner_photo' => [
            'directory' => 'shop-documents/owner-photos',
            'mimes' => 'jpeg,jpg,png,webp',
        ],
        'aadhar_card' => [
            'directory' => 'shop-documents/aadhar',
            'mimes' => 'jpeg,jpg,png,webp,pdf',
        ],
        'shop_license' => [
            'directory' => 'shop-documents/shop-license',
            'mimes' => 'jpeg,jpg,png,webp,pdf',
        ],
        'gst_certificate' => [
            'directory' => 'shop-documents/gst-certificate',
            'mimes' => 'jpeg,jpg,png,webp,pdf',
        ],
        'electricity_bill' => [
            'directory' => 'shop-documents/electricity-bill',
            'mimes' => 'jpeg,jpg,png,webp,pdf',
        ],
    ];

    public function show(Shop $shop): JsonResponse
    {
        $this->ensureOwned($shop);

        return response()->json([
            'shop' => $this->withType($shop),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Shop::class);

        $data = $this->validatedPayload($request, requireShopType: true);

        $shop = Shop::create([
            'user_id' => Auth::id(),
            'shop_type_id' => (int) $data['shop_type_id'],
            'name' => $data['name'],
            'slug' => $this->uniqueSlug(null, Str::slug((string) $data['name'])),
            'description' => $data['description'] ?? null,
            'phone' => $data['phone'] ?? null,
            'alternate_phone' => $data['alternate_phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address_line1' => $data['address_line1'] ?? null,
            'address_line2' => $data['address_line2'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'country' => $data['country'] ?? 'India',
            'postal_code' => $data['postal_code'] ?? null,
            'status' => $data['status'] ?? 'on',
        ]);

        return response()->json([
            'shop' => $this->withType($shop),
        ], 201);
    }

    public function update(Request $request, Shop $shop): JsonResponse
    {
        $this->ensureOwned($shop);
        $this->authorize('update', $shop);

        $data = $this->validatedPayload(
            $request,
            requireShopType: $shop->shop_type_id === null,
        );

        $shopTypeId = $shop->shop_type_id;
        if ($shopTypeId === null && ! empty($data['shop_type_id'])) {
            $shopTypeId = (int) $data['shop_type_id'];
        }

        $shop->update([
            'shop_type_id' => $shopTypeId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'phone' => $data['phone'] ?? null,
            'alternate_phone' => $data['alternate_phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address_line1' => $data['address_line1'] ?? null,
            'address_line2' => $data['address_line2'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'country' => $data['country'] ?? $shop->country,
            'postal_code' => $data['postal_code'] ?? null,
            'status' => $data['status'] ?? $shop->status,
        ]);

        return response()->json([
            'shop' => $this->withType($shop->fresh()),
        ]);
    }

    public function uploadDocument(Request $request, Shop $shop): JsonResponse
    {
        $this->ensureOwned($shop);
        $this->authorize('update', $shop);

        $request->validate([
            'field' => ['required', 'string', Rule::in(array_keys(self::DOCUMENT_FIELDS))],
        ]);

        $field = (string) $request->input('field');
        $config = self::DOCUMENT_FIELDS[$field];

        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:'.$config['mimes']],
        ]);

        $old = $shop->{$field};
        $path = $request->file('file')->store($config['directory'], 'public');

        if (is_string($old) && $old !== '' && $old !== $path) {
            Storage::disk('public')->delete($old);
        }

        $shop->update([$field => $path]);

        return response()->json([
            'shop' => $this->withType($shop->fresh()),
            'url' => $path,
        ]);
    }

    public function partners(Shop $shop): JsonResponse
    {
        $this->ensureOwned($shop);

        if (! $this->shopSupportsPartners($shop)) {
            return response()->json([
                'supports_partners' => false,
                'partners' => [],
            ]);
        }

        $rows = $shop->partners()
            ->with('userTypes')
            ->where('users.is_active', true)
            ->orderBy('users.first_name')
            ->orderBy('users.last_name')
            ->get();

        return response()->json([
            'supports_partners' => true,
            'partners' => $rows->map(fn (User $u) => $this->partnerPayload($u))->values(),
        ]);
    }

    public function searchPartners(Request $request, Shop $shop): JsonResponse
    {
        $this->ensureOwned($shop);
        $this->authorize('update', $shop);

        if (! $this->shopSupportsPartners($shop)) {
            return response()->json(['data' => []]);
        }

        $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $rewardTypeIds = $shop->shopType?->rewardUserTypeIds() ?? [];
        $attachedIds = $shop->partners()->pluck('users.id');
        $q = trim((string) $request->query('q', ''));

        $query = User::query()
            ->with('userTypes')
            ->withAnyUserTypeIds($rewardTypeIds)
            ->where('is_active', true)
            ->when(
                $attachedIds->isNotEmpty(),
                fn ($q) => $q->whereNotIn('id', $attachedIds),
            );

        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });
        }

        $rows = $query
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $rows->map(fn (User $u) => $this->partnerPayload($u))->values(),
        ]);
    }

    public function attachPartner(Request $request, Shop $shop): JsonResponse
    {
        $this->ensureOwned($shop);
        $this->authorize('update', $shop);

        if (! $this->shopSupportsPartners($shop)) {
            throw ValidationException::withMessages([
                'shop_id' => [__('This shop does not support partner rewards.')],
            ]);
        }

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $rewardTypeIds = $shop->shopType?->rewardUserTypeIds() ?? [];
        $user = User::query()
            ->with('userTypes')
            ->withAnyUserTypeIds($rewardTypeIds)
            ->where('is_active', true)
            ->find($data['user_id']);

        if (! $user) {
            throw ValidationException::withMessages([
                'user_id' => [__('Select a reward-eligible partner for this shop type.')],
            ]);
        }

        $shop->partners()->syncWithoutDetaching([$user->id]);

        return response()->json([
            'partner' => $this->partnerPayload($user),
        ]);
    }

    public function detachPartner(Shop $shop, User $user): JsonResponse
    {
        $this->ensureOwned($shop);
        $this->authorize('update', $shop);

        $shop->partners()->detach($user->id);

        return response()->json([
            'detached' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedPayload(Request $request, bool $requireShopType): array
    {
        $shopTypeRules = [
            Rule::exists('shop_types', 'id')->where('is_active', true),
        ];
        array_unshift($shopTypeRules, $requireShopType ? 'required' : 'nullable');

        return $request->validate([
            'shop_type_id' => $shopTypeRules,
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:20'],
            'alternate_phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', Rule::in(['on', 'off'])],
        ]);
    }

    protected function uniqueSlug(?int $ignoreId, string $baseSlug): string
    {
        $baseSlug = $baseSlug !== '' ? $baseSlug : 'shop';
        $slug = $baseSlug;
        $counter = 2;

        while (Shop::query()
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    protected function ensureOwned(Shop $shop): void
    {
        if ($shop->user_id !== Auth::id()) {
            abort(403);
        }
    }

    protected function withType(Shop $shop): Shop
    {
        return $shop->loadMissing(['shopType.rewardUserTypes']);
    }

    protected function shopSupportsPartners(Shop $shop): bool
    {
        $shop->loadMissing(['shopType.rewardUserTypes']);

        return (bool) $shop->shopType?->supportsPartnerRewards();
    }

    /**
     * @return array<string, mixed>
     */
    protected function partnerPayload(User $user): array
    {
        $user->loadMissing('userTypes');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'reward_points' => (int) ($user->reward_points ?? 0),
            'user_types' => $user->userTypes->pluck('name')->values()->all(),
            'label' => $user->name.($user->phone ? ' ('.$user->phone.')' : ''),
        ];
    }
}

<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Controller;
use App\Models\OfflineBill;
use App\Models\Shop;
use App\Models\User;
use App\Models\UserRewardGrant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ShopOfflineBillController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\Order::class);

        $perPage = min((int) $request->get('per_page', 15), 50);
        $bills = OfflineBill::query()
            ->whereHas('shop', fn ($q) => $q->where('user_id', Auth::id()))
            ->with(['shop:id,name,user_id', 'customer:id,first_name,last_name,email,phone', 'partner:id,first_name,last_name,email,phone'])
            ->latest()
            ->paginate($perPage);

        $bills->getCollection()->transform(fn (OfflineBill $bill) => $this->serialize($bill));

        return response()->json([
            'bills' => $bills,
        ]);
    }

    public function show(OfflineBill $offlineBill): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\Order::class);
        $this->ensureOwned($offlineBill);

        $offlineBill->load(['shop:id,name,user_id', 'customer:id,first_name,last_name,email,phone', 'partner:id,first_name,last_name,email,phone']);

        return response()->json([
            'bill' => $this->serialize($offlineBill),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', \App\Models\Order::class);

        $data = $request->validate([
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'type' => ['required', Rule::in(['debit', 'credit'])],
            'payment_mode' => [
                Rule::requiredIf(fn () => $request->input('type') === 'credit'),
                'nullable',
                Rule::in(['cash', 'upi', 'online', 'cheque']),
            ],
            'customer_id' => ['required', 'integer', 'exists:users,id'],
            'partner_id' => [
                Rule::requiredIf(fn () => $request->input('type') === 'debit' && (int) $request->input('reward_points', 0) > 0),
                'nullable',
                'integer',
                'exists:users,id',
            ],
            'reward_points' => [
                Rule::requiredIf(fn () => $request->input('type') === 'debit'),
                'nullable',
                'integer',
                'min:1',
            ],
            'amount' => ['required', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'image' => [
                Rule::requiredIf(fn () => $request->input('type') === 'debit'),
                'nullable',
                'file',
                'image',
                'max:15120',
                'mimes:jpeg,jpg,png,webp',
            ],
        ]);

        $user = $request->user();
        $shop = Shop::query()
            ->where('user_id', $user->id)
            ->where('id', (int) $data['shop_id'])
            ->firstOrFail();

        if ((int) $data['customer_id'] === (int) $user->id) {
            throw ValidationException::withMessages([
                'customer_id' => [__('Select the customer (not yourself).')],
            ]);
        }

        $isDebit = $data['type'] === 'debit';
        $partnerId = isset($data['partner_id']) ? (int) $data['partner_id'] : null;
        $points = $isDebit ? (int) ($data['reward_points'] ?? 0) : 0;
        $paymentMode = $isDebit ? null : ($data['payment_mode'] ?? null);

        if ($isDebit && $points > 0 && $partnerId === null) {
            throw ValidationException::withMessages([
                'partner_id' => [__('Select a partner to grant reward points.')],
            ]);
        }

        if ($partnerId !== null) {
            $this->assertValidPartner($shop, $partnerId);
        }

        $imagePath = null;
        if ($isDebit && $request->hasFile('image')) {
            $imagePath = $request->file('image')->store('offline-bills', 'public');
        }

        $bill = DB::transaction(function () use ($data, $user, $shop, $partnerId, $points, $imagePath, $paymentMode, $isDebit) {
            $bill = OfflineBill::create([
                'shop_id' => $shop->id,
                'created_by' => $user->id,
                'type' => $data['type'],
                'payment_mode' => $paymentMode,
                'customer_id' => (int) $data['customer_id'],
                'partner_id' => $partnerId,
                'reward_points' => $points,
                'amount' => $data['amount'],
                'remarks' => $data['remarks'] ?? null,
                'image_path' => $imagePath,
            ]);

            if ($isDebit && $partnerId !== null && $points > 0) {
                $partner = User::query()->findOrFail($partnerId);
                UserRewardGrant::create([
                    'user_id' => $partner->id,
                    'order_id' => null,
                    'offline_bill_id' => $bill->id,
                    'points' => $points,
                    'granted_by' => $user->id,
                    'notes' => 'Offline bill #'.$bill->id,
                ]);
                $partner->increment('reward_points', $points);
            }

            return $bill;
        });

        $bill->load(['shop:id,name,user_id', 'customer:id,first_name,last_name,email,phone', 'partner:id,first_name,last_name,email,phone']);

        return response()->json([
            'bill' => $this->serialize($bill),
        ], 201);
    }

    protected function ensureOwned(OfflineBill $bill): void
    {
        $owned = Shop::query()
            ->where('user_id', Auth::id())
            ->where('id', $bill->shop_id)
            ->exists();

        if (! $owned) {
            abort(403);
        }
    }

    protected function assertValidPartner(Shop $shop, int $partnerId): void
    {
        $shop->loadMissing(['shopType.rewardUserTypes']);
        $rewardTypeIds = $shop->shopType?->rewardUserTypeIds() ?? [];
        if (! ($shop->shopType?->supports_partner_rewards) || $rewardTypeIds === []) {
            throw ValidationException::withMessages([
                'partner_id' => [__('This shop does not support assigning a partner.')],
            ]);
        }

        $valid = $shop->partners()
            ->where('users.id', $partnerId)
            ->withAnyUserTypeIds($rewardTypeIds)
            ->where('users.is_active', true)
            ->exists();

        if (! $valid) {
            throw ValidationException::withMessages([
                'partner_id' => [__('The selected partner is not valid for this shop.')],
            ]);
        }
    }

    protected function serializeUser(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone ?? null,
        ];
    }

    protected function serialize(OfflineBill $bill): array
    {
        return [
            'id' => $bill->id,
            'shop_id' => $bill->shop_id,
            'shop' => $bill->shop ? ['id' => $bill->shop->id, 'name' => $bill->shop->name] : null,
            'type' => $bill->type,
            'payment_mode' => $bill->payment_mode,
            'customer_id' => $bill->customer_id,
            'customer' => $this->serializeUser($bill->customer),
            'partner_id' => $bill->partner_id,
            'partner' => $this->serializeUser($bill->partner),
            'reward_points' => (int) $bill->reward_points,
            'amount' => (float) $bill->amount,
            'remarks' => $bill->remarks,
            'image_path' => $bill->image_path,
            'image_url' => $bill->image_url,
            'created_at' => $bill->created_at?->toIso8601String(),
        ];
    }
}

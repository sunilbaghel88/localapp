<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Controller;
use App\Models\OfflineBill;
use App\Models\Shop;
use App\Models\User;
use App\Models\UserRewardGrant;
use App\Services\Sms\SmsSender;
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
        $query = OfflineBill::query()
            ->whereHas('shop', fn ($q) => $q->where('user_id', Auth::id()))
            ->with(['shop:id,name,user_id', 'customer:id,first_name,last_name,email,phone', 'partner:id,first_name,last_name,email,phone']);

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $needle = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
            $query->whereHas('customer', function ($customer) use ($needle) {
                $customer->where(function ($inner) use ($needle) {
                    $inner->whereNameLike($needle)
                        ->orWhere('email', 'like', $needle)
                        ->orWhere('phone', 'like', $needle);
                });
            });
        }

        $bills = $query->latest()->paginate($perPage);

        $balances = $this->customerClosingBalancesFor($bills->getCollection());

        $bills->getCollection()->transform(function (OfflineBill $bill) use ($balances) {
            $key = $bill->shop_id.':'.$bill->customer_id;

            return $this->serialize($bill, $balances[$key] ?? 0.0);
        });

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
            'bill' => $this->serialize($offlineBill, $this->customerClosingBalanceFor($offlineBill)),
        ]);
    }

    public function dues(Request $request): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\Order::class);

        return response()->json([
            'dues' => $this->dueCustomers($request)->map(fn (array $row) => $this->serializeDue($row))->values(),
        ]);
    }

    public function remindDues(Request $request): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\Order::class);

        $data = $request->validate([
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'customer_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $customerId = isset($data['customer_id']) ? (int) $data['customer_id'] : null;
        if ($customerId !== null && empty($data['shop_id'])) {
            throw ValidationException::withMessages([
                'shop_id' => [__('Select the shop for this customer.')],
            ]);
        }

        $targets = $this->dueCustomers($request);
        if ($targets->isEmpty()) {
            throw ValidationException::withMessages([
                'customer_id' => [$customerId !== null
                    ? __('This customer has no pending dues.')
                    : __('No customers have pending dues.')],
            ]);
        }

        $sms = app(SmsSender::class);
        $sent = 0;
        $failed = 0;
        $skipped = 0;
        $lastError = null;

        foreach ($targets as $row) {
            /** @var User $customer */
            $customer = $row['customer'];
            /** @var Shop $shop */
            $shop = $row['shop'];
            if (! $this->customerHasMobile($customer)) {
                $skipped++;
                $lastError = __('Customer has no mobile number.');

                continue;
            }

            try {
                $sms->sendOfflineBillDuesReminder($customer, $shop, (float) $row['balance']);
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                $lastError = $e->getMessage();
            }
        }

        if ($sent === 0) {
            throw ValidationException::withMessages([
                'sms' => [$lastError ?: __('Could not send the reminder SMS.')],
            ]);
        }

        $parts = [];
        if ($sent > 0) {
            $parts[] = $sent === 1 ? '1 reminder SMS sent' : $sent.' reminder SMS sent';
        }
        if ($failed > 0) {
            $parts[] = $failed.' failed';
        }
        if ($skipped > 0) {
            $parts[] = $skipped.' skipped (no mobile)';
        }

        return response()->json([
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
            'message' => $parts === [] ? 'No reminders sent.' : implode('. ', $parts).'.',
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
        $closingBalance = $this->customerClosingBalanceFor($bill);
        $sms = app(SmsSender::class);
        $sms->notifyOfflineBillCreated($bill, $closingBalance);
        if ($isDebit && $points > 0 && $bill->partner) {
            $partner = $bill->partner->fresh();
            $sms->notifyRewardPointsGranted(
                $partner,
                $shop,
                $points,
                (int) ($partner->reward_points ?? 0),
                [
                    'source' => 'Offline bill #'.$bill->id,
                    'customer' => trim((string) ($bill->customer?->name ?: '-')),
                    'amount' => number_format((float) $bill->amount, 2, '.', ''),
                ],
            );
        }

        return response()->json([
            'bill' => $this->serialize($bill, $closingBalance),
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

    /**
     * @return \Illuminate\Support\Collection<int, array{shop: Shop, customer: User, balance: float}>
     */
    protected function dueCustomers(Request $request)
    {
        $shopQuery = Shop::query()->where('user_id', Auth::id());
        $shopId = (int) $request->input('shop_id', $request->query('shop_id', 0));
        if ($shopId > 0) {
            $shopQuery->where('id', $shopId);
        }
        $shopIds = $shopQuery->pluck('id');
        if ($shopIds->isEmpty()) {
            return collect();
        }

        $query = OfflineBill::query()
            ->whereIn('shop_id', $shopIds)
            ->select('shop_id', 'customer_id')
            ->selectRaw("ROUND(SUM(CASE WHEN type = 'credit' THEN -amount ELSE amount END), 2) as balance")
            ->groupBy('shop_id', 'customer_id')
            ->havingRaw('ROUND(SUM(CASE WHEN type = \'credit\' THEN -amount ELSE amount END), 2) > 0');

        $q = trim((string) $request->query('q', $request->input('q', '')));
        if ($q !== '') {
            $needle = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
            $query->whereHas('customer', function ($customer) use ($needle) {
                $customer->where(function ($inner) use ($needle) {
                    $inner->whereNameLike($needle)
                        ->orWhere('email', 'like', $needle)
                        ->orWhere('phone', 'like', $needle);
                });
            });
        }

        $customerId = (int) $request->input('customer_id', $request->query('customer_id', 0));
        if ($customerId > 0) {
            $query->where('customer_id', $customerId);
        }

        $rows = $query->orderByDesc('balance')->limit(200)->get();
        if ($rows->isEmpty()) {
            return collect();
        }

        $shops = Shop::query()
            ->whereIn('id', $rows->pluck('shop_id')->unique())
            ->get(['id', 'name'])
            ->keyBy('id');
        $customers = User::query()
            ->whereIn('id', $rows->pluck('customer_id')->unique())
            ->get(['id', 'first_name', 'last_name', 'email', 'phone'])
            ->keyBy('id');

        return $rows->map(function ($row) use ($shops, $customers) {
            $shop = $shops->get((int) $row->shop_id);
            $customer = $customers->get((int) $row->customer_id);
            if (! $shop || ! $customer) {
                return null;
            }

            return [
                'shop' => $shop,
                'customer' => $customer,
                'balance' => round((float) $row->balance, 2),
            ];
        })->filter()->values();
    }

    /**
     * @param  array{shop: Shop, customer: User, balance: float}  $row
     */
    protected function serializeDue(array $row): array
    {
        $customer = $row['customer'];

        return [
            'shop_id' => $row['shop']->id,
            'shop' => ['id' => $row['shop']->id, 'name' => $row['shop']->name],
            'customer_id' => $customer->id,
            'customer' => $this->serializeUser($customer),
            'balance' => $row['balance'],
            'has_mobile' => $this->customerHasMobile($customer),
        ];
    }

    protected function customerHasMobile(User $user): bool
    {
        $digits = preg_replace('/\D+/', '', (string) $user->phone) ?? '';

        return strlen($digits) >= 10;
    }

    protected function signedAmount(OfflineBill $bill): float
    {
        $amount = (float) $bill->amount;

        return $bill->type === 'credit' ? -$amount : $amount;
    }

    /**
     * Current customer balance (debit adds, credit subtracts). Same value for every
     * entry of that customer in the shop.
     *
     * @param  \Illuminate\Support\Collection<int, OfflineBill>  $bills
     * @return array<string, float>
     */
    protected function customerClosingBalancesFor($bills): array
    {
        if ($bills->isEmpty()) {
            return [];
        }

        $pairs = $bills->map(fn (OfflineBill $bill) => [
            'shop_id' => (int) $bill->shop_id,
            'customer_id' => (int) $bill->customer_id,
        ])->unique(fn (array $row) => $row['shop_id'].':'.$row['customer_id']);

        $history = OfflineBill::query()
            ->where(function ($query) use ($pairs) {
                foreach ($pairs as $pair) {
                    $query->orWhere(function ($inner) use ($pair) {
                        $inner->where('shop_id', $pair['shop_id'])
                            ->where('customer_id', $pair['customer_id']);
                    });
                }
            })
            ->get(['shop_id', 'customer_id', 'type', 'amount']);

        $balances = [];
        foreach ($history as $row) {
            $key = $row->shop_id.':'.$row->customer_id;
            $balances[$key] = ($balances[$key] ?? 0) + $this->signedAmount($row);
        }

        return array_map(fn ($value) => round((float) $value, 2), $balances);
    }

    protected function customerClosingBalanceFor(OfflineBill $bill): float
    {
        $balances = $this->customerClosingBalancesFor(collect([$bill]));

        return $balances[$bill->shop_id.':'.$bill->customer_id] ?? round($this->signedAmount($bill), 2);
    }

    protected function serialize(OfflineBill $bill, ?float $closingBalance = null): array
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
            'closing_balance' => $closingBalance,
            'remarks' => $bill->remarks,
            'image_path' => $bill->image_path,
            'image_url' => $bill->image_url,
            'created_at' => $bill->created_at?->toIso8601String(),
        ];
    }
}

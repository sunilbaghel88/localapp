<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\RewardRedemptionRequest;
use App\Models\Shop;
use App\Models\UserRewardGrant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RewardPointsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $shopTable = (new Shop)->getTable();

        $shops = $user->partnerShops()
            ->orderBy($shopTable.'.name')
            ->get([$shopTable.'.id', $shopTable.'.name']);

        $grants = UserRewardGrant::query()
            ->with(['order.shop', 'grantedBy'])
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $redemptions = RewardRedemptionRequest::query()
            ->with(['shop', 'approvedBy'])
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(10, ['*'], 'redemptions_page')
            ->withQueryString();

        $totalGranted = (int) UserRewardGrant::query()
            ->where('user_id', $user->id)
            ->sum('points');

        return view('partner.rewards.index', [
            'user' => $user,
            'shops' => $shops,
            'grants' => $grants,
            'redemptions' => $redemptions,
            'totalGranted' => $totalGranted,
        ]);
    }

    public function storeRedemptionRequest(Request $request): RedirectResponse
    {
        $user = $request->user();
        $shopTable = (new Shop)->getTable();
        $allowedShopIds = $user->partnerShops()->pluck($shopTable.'.id');

        if ($allowedShopIds->isEmpty()) {
            return redirect()
                ->route('partner.rewards.index')
                ->with('error', __('You are not attached to any shop yet.'));
        }

        $validated = $request->validate([
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'requested_points' => ['required', 'integer', 'min:1'],
            'redemption_type' => ['required', 'string', 'in:cash,gift'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! $allowedShopIds->contains((int) $validated['shop_id'])) {
            abort(403, __('You cannot redeem points with this shop.'));
        }

        if ((int) $validated['requested_points'] > (int) $user->reward_points) {
            return redirect()
                ->route('partner.rewards.index')
                ->with('error', __('Requested points exceed your available balance.'));
        }

        RewardRedemptionRequest::create([
            'user_id' => $user->id,
            'shop_id' => (int) $validated['shop_id'],
            'requested_points' => (int) $validated['requested_points'],
            'redemption_type' => $validated['redemption_type'],
            'status' => 'pending',
            'note' => $validated['note'] ?? null,
        ]);

        return redirect()
            ->route('partner.rewards.index')
            ->with('success', __('Redemption request submitted. Shop owner approval is pending.'));
    }
}


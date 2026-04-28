<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Controller;
use App\Models\RewardRedemptionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShopRewardRedemptionController extends Controller
{
    protected function shopIdsForCurrentUser(): array
    {
        $user = request()->user();
        if (! $user) {
            return [];
        }

        return $user->shops()->pluck('id')->all();
    }

    public function index(Request $request): JsonResponse
    {
        $shopIds = $this->shopIdsForCurrentUser();
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));
        $status = (string) $request->query('status', '');

        $query = RewardRedemptionRequest::query()
            ->with(['user:id,name,email,phone', 'shop:id,name', 'approvedBy:id,name'])
            ->whereIn('shop_id', $shopIds)
            ->latest();

        if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $query->where('status', $status);
        }

        return response()->json($query->paginate($perPage));
    }

    public function approve(Request $request, RewardRedemptionRequest $rewardRedemptionRequest): JsonResponse
    {
        $shopIds = $this->shopIdsForCurrentUser();
        if (! in_array((int) $rewardRedemptionRequest->shop_id, $shopIds, true)) {
            abort(403);
        }

        $result = DB::transaction(function () use ($rewardRedemptionRequest, $request) {
            $locked = RewardRedemptionRequest::query()
                ->whereKey($rewardRedemptionRequest->id)
                ->lockForUpdate()
                ->first();

            if (! $locked || $locked->status !== 'pending') {
                return ['status' => 'already_processed'];
            }

            $user = $locked->user()->lockForUpdate()->first();
            if (! $user) {
                return ['status' => 'missing_user'];
            }

            if ((int) $user->reward_points < (int) $locked->requested_points) {
                return ['status' => 'insufficient_points'];
            }

            $user->decrement('reward_points', (int) $locked->requested_points);
            $locked->update([
                'status' => 'approved',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'rejection_reason' => null,
            ]);

            $locked->load(['user:id,name,email,phone', 'shop:id,name', 'approvedBy:id,name']);

            return [
                'status' => 'approved',
                'request' => $locked,
            ];
        });

        if ($result['status'] === 'approved') {
            return response()->json([
                'message' => __('Redemption approved and points deducted.'),
                'request' => $result['request'],
            ]);
        }

        if ($result['status'] === 'insufficient_points') {
            return response()->json([
                'message' => __('Electrician does not have enough points anymore.'),
            ], 422);
        }

        return response()->json([
            'message' => __('Request is already processed or unavailable.'),
        ], 422);
    }

    public function reject(Request $request, RewardRedemptionRequest $rewardRedemptionRequest): JsonResponse
    {
        $shopIds = $this->shopIdsForCurrentUser();
        if (! in_array((int) $rewardRedemptionRequest->shop_id, $shopIds, true)) {
            abort(403);
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $updated = RewardRedemptionRequest::query()
            ->whereKey($rewardRedemptionRequest->id)
            ->where('status', 'pending')
            ->update([
                'status' => 'rejected',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'rejection_reason' => $validated['rejection_reason'],
            ]);

        if (! $updated) {
            return response()->json([
                'message' => __('Request is already processed.'),
            ], 422);
        }

        $rewardRedemptionRequest->refresh();
        $rewardRedemptionRequest->load(['user:id,name,email,phone', 'shop:id,name', 'approvedBy:id,name']);

        return response()->json([
            'message' => __('Redemption request rejected.'),
            'request' => $rewardRedemptionRequest,
        ]);
    }
}

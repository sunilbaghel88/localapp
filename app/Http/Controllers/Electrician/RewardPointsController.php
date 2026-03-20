<?php

namespace App\Http\Controllers\Electrician;

use App\Http\Controllers\Controller;
use App\Models\UserRewardGrant;
use Illuminate\Http\Request;

class RewardPointsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $grants = UserRewardGrant::query()
            ->with(['order.shop', 'grantedBy'])
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $totalGranted = (int) UserRewardGrant::query()
            ->where('user_id', $user->id)
            ->sum('points');

        return view('electrician.rewards.index', [
            'user' => $user,
            'grants' => $grants,
            'totalGranted' => $totalGranted,
        ]);
    }
}


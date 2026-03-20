<?php

namespace App\Http\Controllers\Electrician;

use App\Http\Controllers\Controller;
use App\Models\UserRewardGrant;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $recentGrants = UserRewardGrant::query()
            ->with(['order.shop'])
            ->where('user_id', $user->id)
            ->latest()
            ->limit(10)
            ->get();

        $totalGranted = (int) UserRewardGrant::query()
            ->where('user_id', $user->id)
            ->sum('points');

        return view('electrician.dashboard', [
            'user' => $user,
            'recentGrants' => $recentGrants,
            'totalGranted' => $totalGranted,
        ]);
    }
}


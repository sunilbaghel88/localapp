<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserType;
use Illuminate\Http\JsonResponse;

class UserTypeController extends Controller
{
    public function index(): JsonResponse
    {
        $userTypes = UserType::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug']);

        return response()->json(['user_types' => $userTypes]);
    }
}

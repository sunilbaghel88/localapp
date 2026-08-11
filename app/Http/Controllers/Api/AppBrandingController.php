<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppBranding;

class AppBrandingController extends Controller
{
    public function show()
    {
        return response()->json(
            AppBranding::current()->toApiArray()
        );
    }
}

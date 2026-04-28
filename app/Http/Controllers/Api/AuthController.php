<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    protected const OTP_CACHE_PREFIX = 'auth_login_otp:';
    protected const OTP_TTL_MINUTES = 10;

    /**
     * Ensure the mobile client can do role/permission based UI.
     */
    protected function authUserPayload(User $user): array
    {
        $role = $user->roles()->pluck('name')->first();
        $permissions = $user->getAllPermissions()->pluck('name') ?? collect();

        return array_merge($user->toArray(), [
            'role' => $role,
            'permissions' => $permissions,
            'is_electrician' => $user->isElectrician(),
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'required|string|max:255',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        return response()->json([
            'token' => $user->createToken($request->device_name)->plainTextToken,
            'user' => $this->authUserPayload($user),
        ]);
    }

    public function requestEmailOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = strtolower((string) $request->email);
        $otp = (string) random_int(100000, 999999);
        $cacheKey = self::OTP_CACHE_PREFIX . $email;
        Cache::put($cacheKey, Hash::make($otp), now()->addMinutes(self::OTP_TTL_MINUTES));

        try {
            Mail::raw(
                "Your login OTP is {$otp}. It will expire in " . self::OTP_TTL_MINUTES . ' minutes.',
                function ($message) use ($email) {
                    $message->to($email)->subject('Your Login OTP');
                }
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to send login OTP email', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'If the email exists, an OTP has been sent.',
            'expires_in_minutes' => self::OTP_TTL_MINUTES,
        ]);
    }

    public function loginWithOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|digits:6',
            'device_name' => 'required|string|max:255',
        ]);

        $email = strtolower((string) $request->email);
        $cacheKey = self::OTP_CACHE_PREFIX . $email;
        $hashedOtp = Cache::get($cacheKey);

        if (! is_string($hashedOtp) || ! Hash::check((string) $request->otp, $hashedOtp)) {
            throw ValidationException::withMessages([
                'otp' => ['Invalid or expired OTP.'],
            ]);
        }

        $user = User::where('email', $email)->first();
        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['No account found for this email.'],
            ]);
        }

        Cache::forget($cacheKey);

        return response()->json([
            'token' => $user->createToken($request->device_name)->plainTextToken,
            'user' => $this->authUserPayload($user),
        ]);
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Password::defaults()],
            'device_name' => 'required|string|max:255',
            'user_type_id' => 'nullable|exists:user_types,id',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'user_type_id' => $request->user_type_id ?: null,
        ]);

        return response()->json([
            'token' => $user->createToken($request->device_name)->plainTextToken,
            'user' => $this->authUserPayload($user),
        ], 201);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }
}

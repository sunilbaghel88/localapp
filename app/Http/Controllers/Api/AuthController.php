<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SmsSetting;
use App\Models\User;
use App\Services\Sms\SmsSender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    protected const OTP_CACHE_PREFIX = 'auth_login_otp:';

    protected const SMS_OTP_CACHE_PREFIX = 'auth_sms_otp:';

    protected const OTP_TTL_MINUTES = 10;

    public function __construct(
        protected SmsSender $smsSender
    ) {}

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

    protected function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        // Keep last 10 digits for Indian mobiles when longer numbers include country code.
        if (strlen($digits) > 10) {
            $digits = substr($digits, -10);
        }

        return $digits;
    }

    protected function smsOtpTtlMinutes(): int
    {
        return max(1, (int) SmsSetting::current()->otp_ttl_minutes ?: self::OTP_TTL_MINUTES);
    }

    protected function storeSmsOtp(string $phone, string $otp): void
    {
        Cache::put(
            self::SMS_OTP_CACHE_PREFIX . $phone,
            Hash::make($otp),
            now()->addMinutes($this->smsOtpTtlMinutes())
        );
    }

    protected function verifySmsOtp(string $phone, string $otp): bool
    {
        $hashedOtp = Cache::get(self::SMS_OTP_CACHE_PREFIX . $phone);

        return is_string($hashedOtp) && Hash::check($otp, $hashedOtp);
    }

    protected function forgetSmsOtp(string $phone): void
    {
        Cache::forget(self::SMS_OTP_CACHE_PREFIX . $phone);
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

    public function requestSmsOtp(Request $request)
    {
        $request->validate([
            'phone' => ['required', 'string', 'min:10', 'max:15'],
            'purpose' => ['nullable', 'in:login,register'],
        ]);

        $phone = $this->normalizePhone((string) $request->phone);

        if (strlen($phone) < 10) {
            throw ValidationException::withMessages([
                'phone' => ['Enter a valid mobile number.'],
            ]);
        }

        $purpose = $request->input('purpose', 'login');
        $existing = User::where('phone', $phone)->first();

        if ($purpose === 'login' && ! $existing) {
            throw ValidationException::withMessages([
                'phone' => ['No account found for this mobile number. Please register first.'],
            ]);
        }

        if ($purpose === 'register' && $existing) {
            throw ValidationException::withMessages([
                'phone' => ['An account already exists for this mobile number. Please login.'],
            ]);
        }

        $otp = (string) random_int(100000, 999999);
        $this->storeSmsOtp($phone, $otp);

        try {
            $this->smsSender->sendOtp($phone, $otp);
        } catch (\Throwable $e) {
            $this->forgetSmsOtp($phone);

            throw ValidationException::withMessages([
                'phone' => [$e->getMessage() ?: 'Failed to send OTP SMS.'],
            ]);
        }

        return response()->json([
            'message' => 'OTP has been sent to your mobile number.',
            'expires_in_minutes' => $this->smsOtpTtlMinutes(),
        ]);
    }

    public function loginWithSmsOtp(Request $request)
    {
        $request->validate([
            'phone' => ['required', 'string', 'min:10', 'max:15'],
            'otp' => ['required', 'digits:6'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        $phone = $this->normalizePhone((string) $request->phone);

        if (! $this->verifySmsOtp($phone, (string) $request->otp)) {
            throw ValidationException::withMessages([
                'otp' => ['Invalid or expired OTP.'],
            ]);
        }

        $user = User::where('phone', $phone)->first();
        if (! $user) {
            throw ValidationException::withMessages([
                'phone' => ['No account found for this mobile number.'],
            ]);
        }

        if ($user->is_active === false) {
            throw ValidationException::withMessages([
                'phone' => ['This account is inactive.'],
            ]);
        }

        $this->forgetSmsOtp($phone);

        return response()->json([
            'token' => $user->createToken($request->device_name)->plainTextToken,
            'user' => $this->authUserPayload($user),
        ]);
    }

    public function registerWithSmsOtp(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'min:10', 'max:15'],
            'otp' => ['required', 'digits:6'],
            'device_name' => ['required', 'string', 'max:255'],
            'user_type_id' => ['nullable', 'exists:user_types,id'],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
        ]);

        $phone = $this->normalizePhone((string) $request->phone);

        if (! $this->verifySmsOtp($phone, (string) $request->otp)) {
            throw ValidationException::withMessages([
                'otp' => ['Invalid or expired OTP.'],
            ]);
        }

        if (User::where('phone', $phone)->exists()) {
            throw ValidationException::withMessages([
                'phone' => ['An account already exists for this mobile number.'],
            ]);
        }

        $email = $request->filled('email')
            ? strtolower((string) $request->email)
            : $phone.'@phone.localapp';

        $user = User::create([
            'name' => $request->name,
            'email' => $email,
            'phone' => $phone,
            'password' => Hash::make(Str::random(32)),
            'user_type_id' => $request->user_type_id ?: null,
        ]);

        $this->forgetSmsOtp($phone);

        return response()->json([
            'token' => $user->createToken($request->device_name)->plainTextToken,
            'user' => $this->authUserPayload($user),
        ], 201);
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

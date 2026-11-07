<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    /** Số lần đăng nhập sai tối đa trên mỗi cặp email + IP trước khi bị khoá tạm. */
    public const MAX_FAILED_ATTEMPTS = 5;

    /** Thời gian khoá (giây) sau khi vượt quá số lần sai. */
    public const LOCKOUT_SECONDS = 60;

    /** POST /api/auth/login — phát Sanctum token cho admin. */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = 'login:'.strtolower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_FAILED_ATTEMPTS)) {
            $wait = RateLimiter::availableIn($throttleKey);

            return response()->json(
                ['message' => "Đăng nhập sai quá nhiều lần. Thử lại sau {$wait} giây."],
                429,
                ['Retry-After' => $wait]
            );
        }

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($throttleKey, self::LOCKOUT_SECONDS);

            return response()->json(['message' => 'Sai email hoặc mật khẩu.'], 401);
        }

        RateLimiter::clear($throttleKey);

        return response()->json([
            'token' => $user->createToken('webgis')->plainTextToken,
            'user' => $user->only(['id', 'name', 'email', 'role']),
        ]);
    }
}

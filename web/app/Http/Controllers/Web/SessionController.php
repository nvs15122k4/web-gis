<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

/** Đăng nhập bằng phiên (session) cho các trang web cần phân quyền; API vẫn dùng token Sanctum. */
class SessionController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user()) {
            return redirect('/');
        }

        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Dùng chung khoá đăng nhập sai với API để không lách giới hạn qua form.
        $throttleKey = 'login:'.strtolower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, AuthController::MAX_FAILED_ATTEMPTS)) {
            $wait = RateLimiter::availableIn($throttleKey);

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => "Đăng nhập sai quá nhiều lần. Thử lại sau {$wait} giây."]);
        }

        if (! Auth::attempt($credentials)) {
            RateLimiter::hit($throttleKey, AuthController::LOCKOUT_SECONDS);

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Sai email hoặc mật khẩu.']);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        // Quay lại trang đang định vào (vd. /bao-cao) trước khi bị chuyển tới form đăng nhập.
        return redirect()->intended('/');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isAdmin()) {
            // Trang web: hiện trang 403 tiếng Việt; API và yêu cầu JSON: giữ phản hồi JSON.
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                abort(403);
            }

            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}

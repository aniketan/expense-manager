<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireLogin
{
    public const SESSION_KEY = 'single_user_authenticated';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get(self::SESSION_KEY) === true) {
            return $next($request);
        }

        // Page navigations (including Inertia visits) go to the login screen.
        // Everything else (fetch, JSON, SSE chat stream) gets a plain 401.
        if ($request->header('X-Inertia') || ($request->isMethod('GET') && ! $request->expectsJson())) {
            return redirect()->guest(route('login'));
        }

        return response()->json(['message' => 'Unauthenticated.'], 401);
    }
}

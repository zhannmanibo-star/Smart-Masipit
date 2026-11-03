<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountApproved
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->canAccessServices()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your account is not authorized to access this service.',
                ], 403);
            }

            return redirect()->route('account.status');
        }

        return $next($request);
    }
}
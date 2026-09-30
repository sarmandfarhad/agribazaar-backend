<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only lets users of the given type through, e.g. ->middleware('role:admin').
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! in_array($request->user()?->user_type, $roles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'This action is not available for your account type.',
            ], 403);
        }

        return $next($request);
    }
}

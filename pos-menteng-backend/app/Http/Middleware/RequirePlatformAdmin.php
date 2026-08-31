<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Bootstrap compatibility: the existing developer account is the
        // platform provisioning identity until a dedicated platform-admin
        // identity is introduced in a later phase.
        if ($user->role !== 'developer') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }
}

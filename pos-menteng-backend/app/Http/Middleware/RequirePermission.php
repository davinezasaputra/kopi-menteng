<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        $context = app(TenantContext::class);
        $membership = $context->membership();

        if (! $user || ! $membership) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $allowed = $membership->role()
            ->with('permissions')
            ->first()
            ?->permissions
            ->contains('name', $permission);

        if (! $allowed) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }
}

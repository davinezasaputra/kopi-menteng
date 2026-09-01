<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) return response()->json(['message'=>'Unauthenticated.'],401);
        try { app(TenantContext::class)->resolveFor($user,$request); }
        catch (\Throwable) { return response()->json(['message'=>'No active organization context is available.'],403); }
        return $next($request);
    }
}

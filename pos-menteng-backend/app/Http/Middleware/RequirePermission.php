<?php

namespace App\Http\Middleware;

use App\Domain\Audit\Services\AuditService;
use App\Support\Auth\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    public function __construct(private readonly PermissionService $permissions, private readonly AuditService $audit) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        if (! $user) return response()->json(['message'=>'Unauthenticated.'],401);
        if (! $this->permissions->hasPermission($user,$permission)) {
            $this->audit->record('permission_denied','authorization',$user,null,['permission'=>$permission,'route'=>$request->path(),'method'=>$request->method()],$request);
            return response()->json(['message'=>'Forbidden.'],403);
        }
        return $next($request);
    }
}

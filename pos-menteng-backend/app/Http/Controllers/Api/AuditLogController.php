<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\Models\AuditLog;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::query()->where('tenant_id', app(TenantContext::class)->tenantId());

        if ($request->filled('module')) {
            $query->where('module', $request->string('module')->toString());
        }
        if ($request->filled('event')) {
            $query->where('event', $request->string('event')->toString());
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->latest('created_at')->paginate(min($request->integer('per_page', 50), 100)),
        ]);
    }
}

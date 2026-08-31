<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index()
    {
        $tenantId = app(TenantContext::class)->tenantId();
        $customers = Customer::where('tenant_id', $tenantId)
            ->orderBy('points', 'desc')
            ->get();

        return response()->json(['status' => 'success', 'data' => $customers]);
    }

    public function store(Request $request)
    {
        $tenantId = app(TenantContext::class)->tenantId();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string',
            'tier' => 'required|in:silver,gold,vip',
        ]);

        if (Customer::where('tenant_id', $tenantId)->where('phone', $validated['phone'])->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Nomor HP sudah terdaftar pada tenant ini.',
            ], 422);
        }

        $customer = Customer::create([
            'tenant_id' => $tenantId,
            ...$validated,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Member baru berhasil didaftarkan.',
            'data' => $customer,
        ], 201);
    }

    public function search(Request $request)
    {
        $tenantId = app(TenantContext::class)->tenantId();
        $request->validate(['phone' => 'required|string']);

        $customer = Customer::where('tenant_id', $tenantId)
            ->where('phone', $request->phone)
            ->first();

        if (!$customer) {
            return response()->json(['status' => 'error', 'message' => 'Nomor HP tidak terdaftar.'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $customer]);
    }
}

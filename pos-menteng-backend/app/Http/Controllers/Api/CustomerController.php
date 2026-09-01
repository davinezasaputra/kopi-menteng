<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\Services\AuditService;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditService $audit,
    ) {}

    private function scopedQuery()
    {
        return Customer::query()
            ->where('tenant_id', $this->context->tenantId())
            ->where(function ($query) {
                $query->whereNull('company_id')->orWhere('company_id', $this->context->companyId());
            })
            ->where(function ($query) {
                $query->whereNull('branch_id')->orWhere('branch_id', $this->context->branchId());
            });
    }

    public function index(Request $request)
    {
        $customers = $this->scopedQuery()->orderByDesc('points')->paginate(min((int) $request->integer('per_page', 50), 100));
        return response()->json(['status' => 'success', 'data' => $customers]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:40',
            'tier' => 'required|in:silver,gold,vip',
        ]);

        if ($this->scopedQuery()->where('phone', $validated['phone'])->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Nomor HP sudah terdaftar pada context ini.'], 422);
        }

        $customer = Customer::create($validated + [
            'tenant_id' => $this->context->tenantId(),
            'company_id' => $this->context->companyId(),
            'branch_id' => $this->context->branchId(),
            'points' => 0,
        ]);
        $this->audit->record('created', 'crm.customer', $customer, null, $customer->toArray());
        return response()->json(['status' => 'success', 'message' => 'Member baru berhasil didaftarkan.', 'data' => $customer], 201);
    }

    public function search(Request $request)
    {
        $validated = $request->validate(['phone' => 'required|string|max:40']);
        $customer = $this->scopedQuery()->where('phone', $validated['phone'])->first();
        if (! $customer) return response()->json(['status' => 'error', 'message' => 'Nomor HP tidak terdaftar.'], 404);
        return response()->json(['status' => 'success', 'data' => $customer]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\Services\AuditService;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditService $audit,
    ) {
    }

    private function scopedQuery()
    {
        return Employee::query()
            ->where('tenant_id', $this->context->tenantId())
            ->where('company_id', $this->context->companyId())
            ->where('branch_id', $this->context->branchId());
    }

    public function index(Request $request)
    {
        $employees = $this->scopedQuery()
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->integer('per_page', 50), 100));

        return response()->json(['status' => 'success', 'data' => $employees]);
    }

    public function show(string $id)
    {
        $employee = $this->scopedQuery()->find($id);
        if (! $employee) {
            return response()->json(['status' => 'error', 'message' => 'Karyawan tidak ditemukan.'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $employee]);
    }

    public function search(Request $request)
    {
        $keyword = trim((string) $request->query('q', ''));
        $query = $this->scopedQuery();

        if ($keyword !== '') {
            $query->where(function ($builder) use ($keyword) {
                $builder->where('name', 'like', "%{$keyword}%")
                    ->orWhere('WA', 'like', "%{$keyword}%")
                    ->orWhere('position', 'like', "%{$keyword}%");
            });
        }

        $employees = $query->orderByDesc('created_at')->paginate(min((int) $request->integer('per_page', 50), 100));
        return response()->json(['status' => 'success', 'data' => $employees]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'tanggal_lahir' => 'nullable|date',
            'WA' => 'required|string|max:20',
            'position' => 'required|string|max:50',
            'join_date' => 'nullable|date',
            'base_sallary' => 'required|numeric|min:0',
            'status' => 'nullable|in:active,inactive',
        ]);

        $validated += [
            'tenant_id' => $this->context->tenantId(),
            'company_id' => $this->context->companyId(),
            'branch_id' => $this->context->branchId(),
        ];
        $validated['id'] = (string) Str::uuid();

        $employee = Employee::create($validated);
        $this->audit->record('created', 'hrm.employee', $employee, null, $employee->toArray());

        return response()->json(['status' => 'success', 'message' => 'Data karyawan berhasil ditambahkan.', 'data' => $employee], 201);
    }

    public function update(Request $request, string $id)
    {
        $employee = $this->scopedQuery()->find($id);
        if (! $employee) {
            return response()->json(['status' => 'error', 'message' => 'Karyawan tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'tanggal_lahir' => 'nullable|date',
            'WA' => 'sometimes|required|string|max:20',
            'position' => 'sometimes|required|string|max:50',
            'join_date' => 'nullable|date',
            'base_sallary' => 'sometimes|required|numeric|min:0',
            'status' => 'nullable|in:active,inactive',
        ]);

        $old = $employee->toArray();
        $employee->update($validated);
        $this->audit->record('updated', 'hrm.employee', $employee, $old, $employee->fresh()->toArray());

        return response()->json(['status' => 'success', 'message' => 'Data karyawan berhasil diperbarui.', 'data' => $employee->fresh()]);
    }

    public function destroy(string $id)
    {
        $employee = $this->scopedQuery()->find($id);
        if (! $employee) {
            return response()->json(['status' => 'error', 'message' => 'Karyawan tidak ditemukan.'], 404);
        }

        $old = $employee->toArray();
        $employee->delete();
        $this->audit->record('deleted', 'hrm.employee', $employee, $old, null);

        return response()->json(['status' => 'success', 'message' => 'Data karyawan berhasil dihapus.']);
    }
}

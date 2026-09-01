<?php

namespace App\Http\Controllers\Api;

use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Tenant;
use App\Domain\Organization\Models\TenantLicense;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DeveloperController extends Controller
{
    private const LICENSE_PLANS = [
        'starter' => ['name' => 'Starter', 'features' => ['pos', 'inventory'], 'max_users' => 5, 'max_branches' => 1],
        'business' => ['name' => 'Business', 'features' => ['pos', 'inventory', 'purchasing', 'sales', 'accounting'], 'max_users' => 20, 'max_branches' => 5],
        'professional' => ['name' => 'Professional', 'features' => ['pos', 'inventory', 'purchasing', 'sales', 'accounting', 'hrm'], 'max_users' => 50, 'max_branches' => 15],
        'enterprise' => ['name' => 'Enterprise', 'features' => ['pos', 'inventory', 'purchasing', 'sales', 'accounting', 'hrm', 'administration', 'audit', 'organization'], 'max_users' => null, 'max_branches' => null],
    ];

    private const FEATURE_PREFIXES = [
        'pos' => ['pos.'],
        'inventory' => ['inventory.'],
        'purchasing' => ['purchasing.'],
        'sales' => ['sales.'],
        'accounting' => ['accounting.'],
        'hrm' => ['hr.'],
        'administration' => ['users.', 'rbac.'],
        'audit' => ['audit.'],
        'organization' => ['organization.'],
    ];

    public function licenseCatalog(): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => collect(self::LICENSE_PLANS)->map(
            fn (array $plan, string $code) => ['code' => $code, ...$plan]
        )->values()->all()]);
    }

    public function tenants(): JsonResponse
    {
        $tenants = Tenant::query()
            ->with(['companies.branches'])
            ->withCount('companies')
            ->orderBy('name')
            ->get();

        $licenses = TenantLicense::query()->whereIn('tenant_id', $tenants->pluck('id'))->get()->keyBy('tenant_id');
        $data = $tenants->map(function (Tenant $tenant) use ($licenses): array {
            $license = $licenses->get($tenant->id);
            $companyCount = $tenant->companies->count();
            $branchCount = $tenant->companies->sum(fn (Company $company) => $company->branches->count());
            return [
                'id' => $tenant->id,
                'code' => $tenant->code,
                'name' => $tenant->name,
                'status' => $tenant->status,
                'timezone' => $tenant->timezone,
                'currency' => $tenant->currency,
                'company_count' => $companyCount,
                'branch_count' => $branchCount,
                'license' => $license,
                'companies' => $tenant->companies->map(fn (Company $company) => [
                    'id' => $company->id,
                    'code' => $company->code,
                    'name' => $company->name,
                    'status' => $company->status,
                    'branches' => $company->branches->map(fn (Branch $branch) => [
                        'id' => $branch->id,
                        'code' => $branch->code,
                        'name' => $branch->name,
                        'status' => $branch->status,
                    ])->values(),
                ])->values(),
            ];
        })->values();

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    public function tenantLicense(int $tenant): JsonResponse
    {
        $record = TenantLicense::query()->where('tenant_id', $tenant)->first();
        return response()->json(['status' => 'success', 'data' => $record]);
    }

    public function updateTenantLicense(Request $request, int $tenant): JsonResponse
    {
        Tenant::query()->findOrFail($tenant);
        $data = $request->validate([
            'plan_code' => ['required', 'string', Rule::in(array_keys(self::LICENSE_PLANS))],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', Rule::in(array_keys(self::FEATURE_PREFIXES))],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'max_branches' => ['nullable', 'integer', 'min:1'],
            'auto_renew' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $plan = self::LICENSE_PLANS[$data['plan_code']];
        $features = $data['features'] ?? $plan['features'];
        $license = TenantLicense::updateOrCreate(
            ['tenant_id' => $tenant],
            [
                'plan_code' => $data['plan_code'],
                'plan_name' => $plan['name'],
                'features' => array_values(array_unique($features)),
                'max_users' => $data['max_users'] ?? $plan['max_users'],
                'max_branches' => $data['max_branches'] ?? $plan['max_branches'],
                'starts_at' => $data['starts_at'] ?? now(),
                'expires_at' => $data['expires_at'] ?? null,
                'status' => 'active',
                'auto_renew' => (bool) ($data['auto_renew'] ?? false),
                'notes' => $data['notes'] ?? null,
            ]
        );

        return response()->json(['status' => 'success', 'data' => $license->fresh()]);
    }

    public function tenantAdmins(Request $request, int $tenant): JsonResponse
    {
        Tenant::query()->findOrFail($tenant);
        $memberships = Membership::query()
            ->where('tenant_id', $tenant)
            ->whereHas('role', fn ($query) => $query->where('code', 'tenant-admin'))
            ->with(['user', 'company', 'branch', 'role.permissions'])
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get();

        return response()->json(['status' => 'success', 'data' => $memberships->map(fn (Membership $membership) => [
            'membership_id' => $membership->id,
            'user_id' => $membership->user_id,
            'name' => $membership->user?->name,
            'email' => $membership->user?->email,
            'status' => $membership->status,
            'is_primary' => $membership->is_primary,
            'company_id' => $membership->company_id,
            'company_name' => $membership->company?->name,
            'branch_id' => $membership->branch_id,
            'branch_name' => $membership->branch?->name,
            'role' => $membership->role?->code,
            'permissions' => $membership->role?->permissions?->pluck('name')->values()->all() ?? [],
        ])->values()]);
    }

    public function updateTenantAdmin(Request $request, int $membershipId): JsonResponse
    {
        $membership = Membership::query()
            ->with(['user', 'role'])
            ->whereKey($membershipId)
            ->whereHas('role', fn ($query) => $query->where('code', 'tenant-admin'))
            ->firstOrFail();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($membership->user_id)],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'company_id' => ['required', 'integer'],
            'branch_id' => ['required', 'integer'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        $company = Company::query()->whereKey($data['company_id'])->where('tenant_id', $membership->tenant_id)->firstOrFail();
        $branch = Branch::query()->whereKey($data['branch_id'])->where('company_id', $company->id)->firstOrFail();
        $license = TenantLicense::query()->where('tenant_id', $membership->tenant_id)->first();
        $licensedPermissions = $this->licensedPermissionNames($license);
        $requestedPermissions = array_values(array_unique($data['permissions'] ?? []));
        $invalid = array_values(array_diff($requestedPermissions, $licensedPermissions));
        if ($invalid) {
            return response()->json(['status' => 'error', 'message' => 'Permission tidak termasuk fitur pada lisensi tenant.', 'errors' => ['permissions' => $invalid]], 422);
        }

        DB::transaction(function () use ($membership, $data, $company, $branch, $requestedPermissions): void {
            User::query()->whereKey($membership->user_id)->update(['name' => $data['name'], 'email' => $data['email']]);
            $membership->update(['status' => $data['status'], 'company_id' => $company->id, 'branch_id' => $branch->id]);
            $permissionIds = Permission::query()->whereIn('name', $requestedPermissions)->pluck('id')->all();
            $membership->role->permissions()->sync($permissionIds);
        });

        return response()->json(['status' => 'success', 'message' => 'Tenant admin berhasil diperbarui.', 'data' => $membership->fresh(['user', 'company', 'branch', 'role.permissions'])]);
    }

    private function licensedPermissionNames(?TenantLicense $license): array
    {
        if (! $license || ! $license->isActive()) return [];
        $features = $license->features ?? [];
        $prefixes = collect($features)->flatMap(fn (string $feature) => self::FEATURE_PREFIXES[$feature] ?? [])->unique()->values();
        if ($prefixes->isEmpty()) return [];

        return Permission::query()->pluck('name')->filter(function (string $name) use ($prefixes): bool {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($name, $prefix)) return true;
            }
            return false;
        })->values()->all();
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\Permission;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Tenant;
use App\Domain\Organization\Models\TenantLicense;
use App\Domain\Organization\Models\TenantLicenseEvent;
use App\Domain\Organization\Models\TenantSubscription;
use App\Http\Controllers\Controller;
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
        $tenants = Tenant::query()->with(['companies.branches'])->withCount('companies')->orderBy('name')->get();
        $licenses = TenantLicense::query()->whereIn('tenant_id', $tenants->pluck('id'))->get()->keyBy('tenant_id');
        $subscriptions = TenantSubscription::query()->whereIn('tenant_id', $tenants->pluck('id'))->orderByDesc('id')->get()->groupBy('tenant_id')->map(fn ($items) => $items->first());
        $data = $tenants->map(function (Tenant $tenant) use ($licenses, $subscriptions): array {
            $license = $licenses->get($tenant->id);
            return [
                'id' => $tenant->id,
                'code' => $tenant->code,
                'name' => $tenant->name,
                'status' => $tenant->status,
                'timezone' => $tenant->timezone,
                'currency' => $tenant->currency,
                'company_count' => $tenant->companies->count(),
                'branch_count' => $tenant->companies->sum(fn (Company $company) => $company->branches->count()),
                'license' => $license,
                'subscription' => $subscriptions->get($tenant->id),
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
        return response()->json(['status' => 'success', 'data' => TenantLicense::query()->where('tenant_id', $tenant)->first()]);
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
        $license = DB::transaction(function () use ($tenant, $data, $plan, $features): TenantLicense {
            $previous = TenantLicense::query()->where('tenant_id', $tenant)->first();
            $license = TenantLicense::updateOrCreate(['tenant_id' => $tenant], [
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
            ]);

            TenantLicenseEvent::create([
                'tenant_id' => $tenant,
                'tenant_license_id' => $license->id,
                'actor_user_id' => auth()->id(),
                'event' => $previous ? 'license_updated' : 'license_created',
                'from_plan_code' => $previous?->plan_code,
                'to_plan_code' => $license->plan_code,
                'from_status' => $previous?->status,
                'to_status' => $license->status,
                'metadata' => [
                    'features' => $license->features,
                    'max_users' => $license->max_users,
                    'max_branches' => $license->max_branches,
                    'expires_at' => optional($license->expires_at)->toIso8601String(),
                ],
            ]);

            return $license->fresh();
        });

        return response()->json(['status' => 'success', 'data' => $license]);
    }

    public function subscription(int $tenant): JsonResponse
    {
        Tenant::query()->findOrFail($tenant);
        return response()->json(['status' => 'success', 'data' => TenantSubscription::query()->where('tenant_id', $tenant)->latest('id')->first()]);
    }

    public function updateSubscription(Request $request, int $tenant): JsonResponse
    {
        $tenantRecord = Tenant::query()->findOrFail($tenant);
        $data = $request->validate([
            'subscription_no' => ['nullable', 'string', 'max:80'],
            'plan_code' => ['required', Rule::in(array_keys(self::LICENSE_PLANS))],
            'billing_cycle' => ['required', Rule::in(['monthly', 'quarterly', 'yearly'])],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'starts_at' => ['nullable', 'date'],
            'current_period_start' => ['nullable', 'date'],
            'current_period_end' => ['nullable', 'date', 'after_or_equal:current_period_start'],
            'trial_ends_at' => ['nullable', 'date'],
            'grace_until' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['trialing', 'active', 'past_due', 'suspended', 'cancelled'])],
            'auto_renew' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $subscription = TenantSubscription::updateOrCreate(
            ['tenant_id' => $tenantRecord->id],
            [
                'subscription_no' => $data['subscription_no'] ?: ('SUB-' . strtoupper($tenantRecord->code) . '-' . now()->format('Ym')),
                'plan_code' => $data['plan_code'],
                'billing_cycle' => $data['billing_cycle'],
                'amount' => $data['amount'],
                'currency' => strtoupper($data['currency']),
                'starts_at' => $data['starts_at'] ?? now(),
                'current_period_start' => $data['current_period_start'] ?? now()->startOfDay(),
                'current_period_end' => $data['current_period_end'] ?? null,
                'trial_ends_at' => $data['trial_ends_at'] ?? null,
                'grace_until' => $data['grace_until'] ?? null,
                'status' => $data['status'],
                'auto_renew' => (bool) ($data['auto_renew'] ?? false),
                'notes' => $data['notes'] ?? null,
            ]
        );

        return response()->json(['status' => 'success', 'data' => $subscription->fresh()]);
    }

    public function licenseEvents(int $tenant): JsonResponse
    {
        Tenant::query()->findOrFail($tenant);
        $events = TenantLicenseEvent::query()->where('tenant_id', $tenant)->with('actor')->latest()->limit(100)->get();
        return response()->json(['status' => 'success', 'data' => $events]);
    }

    public function tenantAdmins(Request $request, int $tenant): JsonResponse
    {
        Tenant::query()->findOrFail($tenant);
        $memberships = Membership::query()
            ->where('tenant_id', $tenant)
            ->whereHas('role', fn ($query) => $query->where('code', 'tenant-admin'))
            ->with(['user', 'company', 'branch', 'role.permissions', 'permissionOverrides.permission'])
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
            'permission_overrides' => $membership->permissionOverrides->pluck('permission.name')->filter()->values()->all(),
            'permissions' => $membership->permissionOverrides->isNotEmpty()
                ? $membership->permissionOverrides->pluck('permission.name')->filter()->values()->all()
                : ($membership->role?->permissions?->pluck('name')->values()->all() ?? []),
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
            $membership->permissionOverrides()->delete();
            if ($permissionIds) {
                $membership->permissionOverrides()->createMany(array_map(
                    fn (int $permissionId) => ['permission_id' => $permissionId],
                    $permissionIds
                ));
            }
        });

        return response()->json(['status' => 'success', 'message' => 'Tenant admin berhasil diperbarui.', 'data' => $membership->fresh(['user', 'company', 'branch', 'role.permissions', 'permissionOverrides.permission'])]);
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

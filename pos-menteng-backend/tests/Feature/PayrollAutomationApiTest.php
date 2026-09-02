<?php

namespace Tests\Feature;

use App\Domain\Accounting\Models\ErpAccount;
use App\Domain\Accounting\Models\FiscalPeriod;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Tenant;
use App\Domain\Organization\Models\Warehouse;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PayrollAutomationApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Company $company;
    private Branch $branch;
    private Warehouse $warehouse;
    private Employee $employee;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Payroll Tenant', 'code' => 'PAY', 'slug' => 'payroll-tenant']);
        $this->company = Company::create(['tenant_id' => $this->tenant->id, 'code' => 'PAY', 'name' => 'Payroll Company']);
        $this->branch = Branch::create(['tenant_id' => $this->tenant->id, 'company_id' => $this->company->id, 'code' => 'PAY-BR', 'name' => 'Payroll Branch']);
        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'code' => 'PAY-WH',
            'name' => 'Payroll Warehouse',
            'type' => 'main',
        ]);
        $this->employee = Employee::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'name' => 'Payroll Employee',
            'WA' => '081234567890',
            'position' => 'Barista',
            'base_sallary' => 5000000,
            'status' => 'active',
        ]);

        $this->user = User::create([
            'name' => 'Payroll Admin',
            'email' => 'payroll-admin@example.com',
            'password' => bcrypt('password'),
        ]);

        $role = Role::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Payroll Admin',
            'code' => 'payroll-admin',
            'is_system' => false,
        ]);

        $permissions = [];
        foreach (['hr.employee.view', 'hr.employee.manage'] as $name) {
            [$module, $resource, $action] = explode('.', $name);
            $permissions[] = Permission::firstOrCreate([
                'module' => $module,
                'resource' => $resource,
                'action' => $action,
                'name' => $name,
            ]);
        }
        $role->permissions()->attach($permissions);

        $this->user->memberships()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'role_id' => $role->id,
            'status' => 'active',
            'is_primary' => true,
        ]);
    }

    public function test_automation_config_is_scoped_to_active_tenant(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/api/hrm/payroll/automation/config');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.tenant_id', $this->tenant->id)
            ->assertJsonPath('data.enable_auto_fill', true)
            ->assertJsonPath('data.enable_whatsapp_notification', true);
    }

    public function test_auto_fill_uses_employee_salary_and_attendance_data_without_overwriting_manual_deduction(): void
    {
        $attendanceDate = '2026-09-01';
        Attendance::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $this->employee->id,
            'tanggal' => $attendanceDate,
            'clock_in' => $attendanceDate . ' 08:30:00',
            'status' => 'terlambat',
            'late_minute' => 30,
        ]);

        $payroll = Payroll::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $this->employee->id,
            'period' => '2026-09',
            'base_salary' => 1,
            'allowance' => 250000,
            'deduction' => 125000,
            'total_salary' => 126,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/hrm/payrolls/' . $payroll->id . '/generate-auto');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.base_salary', 5000000)
            ->assertJsonPath('data.manual_deduction', 125000)
            ->assertJsonPath('data.attendance_deduction', 0)
            ->assertJsonPath('data.total_salary', 5125000);

        $this->assertDatabaseHas('payrolls', [
            'id' => $payroll->id,
            'base_salary' => 5000000,
            'deduction' => 125000,
            'attendance_deduction' => 0,
            'total_salary' => 5125000,
        ]);
    }

    public function test_paid_payroll_queues_whatsapp_notification_and_keeps_payment_successful_when_provider_is_not_configured(): void
    {
        Queue::fake();
        Storage::fake('public');

        ErpAccount::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => '5200',
            'name' => 'Salary Expense',
            'type' => 'expense',
            'normal_balance' => 'debit',
            'is_postable' => true,
            'is_active' => true,
        ]);
        ErpAccount::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => '1000',
            'name' => 'Cash Payroll',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_postable' => true,
            'is_active' => true,
        ]);
        FiscalPeriod::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'year' => 2026,
            'month' => 9,
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-09-30',
            'status' => 'open',
        ]);

        $payroll = Payroll::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $this->employee->id,
            'period' => '2026-09',
            'base_salary' => 5000000,
            'allowance' => 0,
            'deduction' => 0,
            'attendance_deduction' => 0,
            'total_salary' => 5000000,
        ]);

        $response = $this->actingAs($this->user)
            ->putJson('/api/hrm/payrolls/' . $payroll->id . '/pay');

        $response->assertOk()->assertJsonPath('status', 'success');
        $this->assertDatabaseHas('payrolls', ['id' => $payroll->id, 'is_paid' => true]);
        $this->assertDatabaseCount('payroll_notifications', 1);
        Queue::assertPushed(\App\Jobs\SendPayrollNotificationJob::class);
        $this->assertDatabaseHas('payroll_notifications', [
            'payroll_id' => $payroll->id,
            'recipient_type' => 'employee',
            'recipient_phone' => '+6281234567890',
            'status' => 'pending',
        ]);
    }

    public function test_notifications_endpoint_does_not_expose_other_company_records(): void
    {
        $otherCompany = Company::create(['tenant_id' => $this->tenant->id, 'code' => 'PAY-2', 'name' => 'Other Company']);
        $otherBranch = Branch::create(['tenant_id' => $this->tenant->id, 'company_id' => $otherCompany->id, 'code' => 'PAY-2-BR', 'name' => 'Other Branch']);
        $otherEmployee = Employee::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $otherCompany->id,
            'branch_id' => $otherBranch->id,
            'name' => 'Other Employee',
            'WA' => '081111111111',
            'position' => 'Staff',
            'base_sallary' => 3000000,
            'status' => 'active',
        ]);
        $otherPayroll = Payroll::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $otherCompany->id,
            'branch_id' => $otherBranch->id,
            'employee_id' => $otherEmployee->id,
            'period' => '2026-09',
            'base_salary' => 3000000,
            'allowance' => 0,
            'deduction' => 0,
            'attendance_deduction' => 0,
            'total_salary' => 3000000,
            'is_paid' => true,
        ]);
        PayrollNotification::create([
            'payroll_id' => $otherPayroll->id,
            'recipient_type' => 'employee',
            'recipient_phone' => '+628111111111',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/hrm/payroll/notifications');

        $response->assertOk();
        $ids = collect(data_get($response->json(), 'data.data', []))->pluck('payroll.id');
        $this->assertNotContains($otherPayroll->id, $ids->all());
    }
}

<?php

namespace Database\Seeders;

use App\Domain\Accounting\Models\ErpAccount;
use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Tenant;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Purchasing\Models\PurchasingApprovalMatrixRule;
use App\Domain\Purchasing\Models\PurchasingBudget;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Sales\Models\SalesApprovalMatrixRule;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ErpMasterSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::updateOrCreate(
            ['code' => 'DEMO'],
            [
                'name' => 'Kopi Menteng Demo',
                'slug' => 'kopi-menteng-demo',
                'status' => 'active',
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
            ]
        );

        $company = Company::updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'KM'],
            [
                'name' => 'Kopi Menteng Indonesia',
                'status' => 'active',
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
            ]
        );

        $branch = Branch::updateOrCreate(
            ['company_id' => $company->id, 'code' => 'MTG'],
            [
                'name' => 'Kopi Menteng - Menteng',
                'type' => 'store',
                'status' => 'active',
            ]
        );

        $warehouse = Warehouse::updateOrCreate(
            ['branch_id' => $branch->id, 'code' => 'MAIN'],
            [
                'name' => 'Main Warehouse',
                'type' => 'store',
                'is_default' => true,
                'status' => 'active',
            ]
        );

        $permissions = [
            ['users','user','view'], ['users','user','create'], ['users','user','update'], ['users','user','delete'],
            ['rbac','role','view'], ['rbac','role','manage'],
            ['audit','audit_log','view'],
            ['organization','branch','view'], ['organization','branch','manage'],
            ['pos','sale','view'], ['pos','sale','create'],
            ['inventory','stock','view'], ['inventory','stock','adjust'],
            ['accounting','journal','view'], ['accounting','journal','create'], ['accounting','journal','post'],
            ['hr','employee','view'], ['hr','employee','manage'],
            ['purchasing','supplier','view'], ['purchasing','supplier','create'],
            ['purchasing','requisition','view'], ['purchasing','requisition','create'],
            ['purchasing','requisition','submit'], ['purchasing','requisition','cancel'],
            ['purchasing','order','view'], ['purchasing','order','create'], ['purchasing','order','submit'],
            ['purchasing','order','approve'], ['purchasing','order','cancel'],
            ['purchasing','receipt','view'], ['purchasing','receipt','create'],
            ['purchasing','ap','view'], ['purchasing','ap','create'], ['purchasing','ap','pay'],
            ['purchasing','reconciliation','view'],
            ['purchasing','reporting','view'],
            ['purchasing','return','view'], ['purchasing','return','create'],
            ['purchasing','credit_note','view'], ['purchasing','credit_note','create'],
            ['purchasing','budget','view'], ['purchasing','budget','create'],
            ['purchasing','approval_matrix','view'], ['purchasing','approval_matrix','create'],
            ['sales','order','view'], ['sales','order','create'], ['sales','order','submit'],
            ['sales','order','cancel'], ['sales','order','approve'],
            ['sales','approval_matrix','view'], ['sales','approval_matrix','create'],
        ];

        $permissionModels = [];
        foreach ($permissions as [$module, $resource, $action]) {
            $name = "{$module}.{$resource}.{$action}";
            $permissionModels[$name] = Permission::updateOrCreate(
                ['name' => $name],
                ['module'=>$module,'resource'=>$resource,'action'=>$action]
            );
        }

        $roleDefinitions = [
            'tenant-admin' => array_keys($permissionModels),
            'sales-manager' => [
                'sales.order.view','sales.order.create','sales.order.submit','sales.order.cancel',
                'sales.order.approve','sales.approval_matrix.view','sales.approval_matrix.create',
                'inventory.stock.view',
            ],
            'purchasing-manager' => [
                'purchasing.supplier.view','purchasing.supplier.create',
                'purchasing.requisition.view','purchasing.requisition.create','purchasing.requisition.submit','purchasing.requisition.cancel',
                'purchasing.order.view','purchasing.order.create','purchasing.order.submit','purchasing.order.approve','purchasing.order.cancel',
                'purchasing.receipt.view','purchasing.receipt.create',
                'purchasing.ap.view','purchasing.ap.create','purchasing.ap.pay',
                'purchasing.reconciliation.view','purchasing.reporting.view',
                'purchasing.return.view','purchasing.return.create',
                'purchasing.credit_note.view','purchasing.credit_note.create',
                'purchasing.budget.view','purchasing.budget.create',
                'purchasing.approval_matrix.view','purchasing.approval_matrix.create',
                'inventory.stock.view','inventory.stock.adjust',
                'accounting.journal.view',
            ],
            'branch-manager' => [
                'pos.sale.view','pos.sale.create','inventory.stock.view','inventory.stock.adjust',
                'organization.branch.view','hr.employee.view',
            ],
            'cashier' => ['pos.sale.view','pos.sale.create','sales.order.view','sales.order.create'],
            'accountant' => ['accounting.journal.view','accounting.journal.create','accounting.journal.post','purchasing.ap.view','purchasing.reconciliation.view'],
            'warehouse-manager' => ['inventory.stock.view','inventory.stock.adjust','purchasing.supplier.view','purchasing.requisition.view','purchasing.requisition.create'],
            'hr-manager' => ['hr.employee.view','hr.employee.manage'],
        ];

        $roles = [];
        foreach ($roleDefinitions as $code => $permissionNames) {
            $role = Role::updateOrCreate(
                ['tenant_id'=>$tenant->id,'code'=>$code],
                ['name'=>ucwords(str_replace('-',' ',$code)),'is_system'=>true]
            );

            $role->permissions()->sync(
                collect($permissionNames)->map(fn ($name) => $permissionModels[$name]->id)->all()
            );

            $roles[$code] = $role;
        }

        $users = [
            [
                'email' => 'davin-eza@mahasiswa.ubb.ac.id',
                'name' => 'Davin (Developer)',
                'legacy_role' => 'developer',
                'erp_role' => 'tenant-admin',
                'password_env' => 'SEED_DEVELOPER_PASSWORD',
            ],
            [
                'email' => 'sales.manager@menteng.test',
                'name' => 'Sales Manager',
                'legacy_role' => 'manager',
                'erp_role' => 'sales-manager',
                'password_env' => 'SEED_SALES_MANAGER_PASSWORD',
            ],
            [
                'email' => 'purchasing.manager@menteng.test',
                'name' => 'Purchasing Manager',
                'legacy_role' => 'manager',
                'erp_role' => 'purchasing-manager',
                'password_env' => 'SEED_PURCHASING_MANAGER_PASSWORD',
            ],
            [
                'email' => 'kasir1@menteng.com',
                'name' => 'Kasir Satu',
                'legacy_role' => 'kasir',
                'erp_role' => 'cashier',
                'password_env' => 'SEED_CASHIER_PASSWORD',
            ],
        ];

        foreach ($users as $definition) {
            $user = User::updateOrCreate(
                ['email'=>$definition['email']],
                [
                    'name'=>$definition['name'],
                    'password'=>Hash::make(env($definition['password_env'], 'change-me-immediately')),
                    'role'=>$definition['legacy_role'],
                    'pin'=>$definition['erp_role'] === 'cashier' ? env('SEED_CASHIER_PIN') : null,
                ]
            );

            Membership::updateOrCreate(
                ['tenant_id'=>$tenant->id,'user_id'=>$user->id,'company_id'=>$company->id,'branch_id'=>$branch->id],
                ['role_id'=>$roles[$definition['erp_role']]->id,'status'=>'active','is_primary'=>true]
            );

            if (Schema::hasColumn('users','default_tenant_id')) {
                $user->forceFill([
                    'default_tenant_id'=>$tenant->id,
                    'default_company_id'=>$company->id,
                    'default_branch_id'=>$branch->id,
                ])->saveQuietly();
            }
        }

        $accounts = [
            ['code'=>'1000','name'=>'Cash','type'=>'asset','normal_balance'=>'debit'],
            ['code'=>'1010','name'=>'Bank','type'=>'asset','normal_balance'=>'debit'],
            ['code'=>'1100','name'=>'Inventory Asset','type'=>'asset','normal_balance'=>'debit'],
            ['code'=>'2100','name'=>'Accounts Payable','type'=>'liability','normal_balance'=>'credit'],
            ['code'=>'4000','name'=>'Sales Revenue','type'=>'revenue','normal_balance'=>'credit'],
            ['code'=>'5000','name'=>'Cost of Goods Sold','type'=>'expense','normal_balance'=>'debit'],
        ];

        foreach ($accounts as $account) {
            ErpAccount::updateOrCreate(
                ['tenant_id'=>$tenant->id,'company_id'=>$company->id,'code'=>$account['code']],
                $account + ['is_postable'=>true,'is_active'=>true]
            );
        }

        $categories = [
            'Kopi',
            'Non-Kopi',
            'Makanan / Snack',
        ];

        $categoryModels = [];
        foreach ($categories as $name) {
            $categoryModels[$name] = Category::firstOrCreate(['name'=>$name]);
        }

        $products = [
            ['code'=>'KOPI-SUSU','category'=>'Kopi','name'=>'Kopi Susu Gula Aren','price'=>25000,'stock'=>100],
            ['code'=>'AMERICANO','category'=>'Kopi','name'=>'Americano','price'=>20000,'stock'=>100],
            ['code'=>'MATCHA','category'=>'Non-Kopi','name'=>'Matcha Latte','price'=>28000,'stock'=>50],
            ['code'=>'FRIES','category'=>'Makanan / Snack','name'=>'Kentang Goreng','price'=>18000,'stock'=>30],
        ];

        foreach ($products as $product) {
            $query = Product::query()->where('name',$product['name']);
            if (Schema::hasColumn('products','tenant_id')) {
                $query->where('tenant_id',$tenant->id);
            }

            $existing = $query->first();

            $attributes = [
                'category_id'=>$categoryModels[$product['category']]->id,
                'name'=>$product['name'],
                'description'=>'ERP demo master product',
                'price'=>$product['price'],
                'is_active'=>true,
            ];

            if (Schema::hasColumn('products','tenant_id')) {
                $attributes['tenant_id']=$tenant->id;
            }

            if ($existing) {
                $existing->fill($attributes)->save();
                $productModel=$existing;
            } else {
                $attributes['stock']=$product['stock'];
                $productModel=Product::create($attributes);
            }

            if (Schema::hasTable('inventory_balances')) {
                $exists = DB::table('inventory_balances')
                    ->where('tenant_id',$tenant->id)
                    ->where('company_id',$company->id)
                    ->where('branch_id',$branch->id)
                    ->where('warehouse_id',$warehouse->id)
                    ->where('product_id',$productModel->id)
                    ->exists();

                if (! $exists) {
                    DB::table('inventory_balances')->insert([
                        'tenant_id'=>$tenant->id,
                        'company_id'=>$company->id,
                        'branch_id'=>$branch->id,
                        'warehouse_id'=>$warehouse->id,
                        'product_id'=>$productModel->id,
                        'quantity'=>$product['stock'],
                        'reserved_quantity'=>0,
                        'average_cost'=>round($product['price']*0.40,4),
                        'last_cost'=>round($product['price']*0.40,4),
                        'created_at'=>now(),
                        'updated_at'=>now(),
                    ]);
                }
            }
        }

        $suppliers = [
            ['code'=>'SUP-001','name'=>'PT Supplier Kopi Indonesia','payment_terms_days'=>30],
            ['code'=>'SUP-002','name'=>'CV Bahan Baku Nusantara','payment_terms_days'=>14],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::updateOrCreate(
                ['tenant_id'=>$tenant->id,'company_id'=>$company->id,'code'=>$supplier['code']],
                [
                    'name'=>$supplier['name'],
                    'contact_name'=>'ERP Demo',
                    'phone'=>'081200000001',
                    'email'=>strtolower($supplier['code']).'@supplier.test',
                    'address'=>'Jakarta',
                    'payment_terms_days'=>$supplier['payment_terms_days'],
                    'status'=>'active',
                ]
            );
        }

        if (Schema::hasTable('customers')) {
            $customerData = [
                ['name'=>'Customer Retail 001','phone'=>'081300000001','tier'=>'silver','points'=>0],
                ['name'=>'Customer Gold 001','phone'=>'081300000002','tier'=>'gold','points'=>100],
            ];

            foreach ($customerData as $customer) {
                $query=Customer::query()->where('phone',$customer['phone']);

                if (Schema::hasColumn('customers','tenant_id')) {
                    $query->where('tenant_id',$tenant->id);
                    $customer['tenant_id']=$tenant->id;
                }

                $query->updateOrCreate(['phone'=>$customer['phone']],$customer);
            }
        }

        if (Schema::hasTable('purchasing_budgets')) {
            PurchasingBudget::updateOrCreate(
                ['tenant_id'=>$tenant->id,'company_id'=>$company->id,'branch_id'=>$branch->id,'budget_year'=>now()->year],
                [
                    'allocated_amount'=>100000000,
                    'committed_amount'=>0,
                    'spent_amount'=>0,
                    'is_active'=>true,
                    'created_by'=>$users[0] ? User::where('email',$users[0]['email'])->value('id') : null,
                    'updated_by'=>User::where('email',$users[0]['email'])->value('id'),
                    'notes'=>'ERP Demo purchasing budget',
                ]
            );
        }

        if (Schema::hasTable('purchasing_approval_matrix_rules')) {
            $roleId=$roles['purchasing-manager']->id;

            if (!PurchasingApprovalMatrixRule::query()
                ->where('tenant_id',$tenant->id)->where('company_id',$company->id)->where('branch_id',$branch->id)
                ->where('min_amount',0)->where('max_amount',1000000)->exists()) {
                PurchasingApprovalMatrixRule::create([
                    'tenant_id'=>$tenant->id,'company_id'=>$company->id,'branch_id'=>$branch->id,
                    'approver_role_id'=>$roleId,'document_type'=>'purchase_order',
                    'min_amount'=>0,'max_amount'=>1000000,'priority'=>1,'is_active'=>true,
                    'notes'=>'Demo purchasing approval up to Rp1 juta',
                ]);
            }

            if (!PurchasingApprovalMatrixRule::query()
                ->where('tenant_id',$tenant->id)->where('company_id',$company->id)->where('branch_id',$branch->id)
                ->where('min_amount',1000000)->where('max_amount',10000000)->exists()) {
                PurchasingApprovalMatrixRule::create([
                    'tenant_id'=>$tenant->id,'company_id'=>$company->id,'branch_id'=>$branch->id,
                    'approver_role_id'=>$roleId,'document_type'=>'purchase_order',
                    'min_amount'=>1000000,'max_amount'=>10000000,'priority'=>2,'is_active'=>true,
                    'notes'=>'Demo purchasing approval Rp1 juta-Rp10 juta',
                ]);
            }
        }

        if (Schema::hasTable('sales_approval_matrix_rules')) {
            $roleId=$roles['sales-manager']->id;

            $rules = [
                [0,1000000,1,'Demo sales approval up to Rp1 juta'],
                [1000000,10000000,2,'Demo sales approval Rp1 juta-Rp10 juta'],
            ];

            foreach ($rules as [$min,$max,$priority,$notes]) {
                if (!SalesApprovalMatrixRule::query()
                    ->where('tenant_id',$tenant->id)->where('company_id',$company->id)->where('branch_id',$branch->id)
                    ->where('min_amount',$min)->where('max_amount',$max)->exists()) {
                    SalesApprovalMatrixRule::create([
                        'tenant_id'=>$tenant->id,'company_id'=>$company->id,'branch_id'=>$branch->id,
                        'approver_role_id'=>$roleId,
                        'min_amount'=>$min,'max_amount'=>$max,'priority'=>$priority,'is_active'=>true,
                        'notes'=>$notes,
                    ]);
                }
            }
        }

        $this->command?->info('ERP master seed completed.');
        $this->command?->info('Demo users: Davin Developer, Sales Manager, Purchasing Manager, Cashier.');
        $this->command?->warn('Default passwords are change-me-immediately unless SEED_*_PASSWORD variables are configured.');
    }
}

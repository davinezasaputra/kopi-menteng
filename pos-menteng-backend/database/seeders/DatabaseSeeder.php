<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(['email'=>'davin-eza@mahasiswa.ubb.ac.id'],[
            'name'=>'Davin (Developer)','password'=>Hash::make(env('SEED_DEVELOPER_PASSWORD','change-me-immediately')),'role'=>'developer','pin'=>null,
        ]);
        $this->call([
            ErpMasterSeeder::class,
            OperationalExpenseAccountSeeder::class,
            EnterprisePermissionSyncSeeder::class,
            Phase1PermissionCatalogSeeder::class,
        ]);
    }
}

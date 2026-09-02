# ERP Seeders

## Master seeder

Use the main entry point:

    php artisan db:seed

or explicitly:

    php artisan db:seed --class=Database\\Seeders\\ErpMasterSeeder

`ErpMasterSeeder` is idempotent and intended for development/UAT master data.

It seeds or synchronizes:
- Tenant / Company / Branch / Warehouse
- ERP roles and permissions for the ERP foundation
- **Only the Developer account** and its primary tenant membership
- ERP Chart of Accounts
- Product categories and demo products
- Initial inventory balances only when a balance row does not exist
- Suppliers
- Customers when the current schema supports them
- Current-year Purchasing Budget
- Purchasing Approval Matrix
- Sales Approval Matrix

## Developer account

The only user account created by the seed is:

    davin-eza@mahasiswa.ubb.ac.id

Password is read from:

    SEED_DEVELOPER_PASSWORD

For a controlled local/UAT run, configure the value in the backend `.env`. The seeder keeps `change-me-immediately` only as a fallback for environments where the variable is omitted.

## Clean UAT reset

To start testing from a clean database:

    php artisan migrate:fresh --seed

**Warning:** this command destroys all existing database data. Use it only on a local/UAT database where the data can be reset.

After the reset, log in from the frontend Admin Login screen with the Developer account, then execute the UAT runbook in `docs/ERP_END_TO_END_UAT_RUNBOOK.md`.

## Important

The master seeder intentionally does not create PO, Goods Receipt, Supplier Invoice, Supplier Payment, Supplier Return, Credit Note, Sales Order, Reservation, Attendance, Payroll, or Journal transaction records. These should be created through the application/API during UAT so inventory and accounting behavior can be verified from a clean starting point.

Legacy ProductSeeder and ErpFoundationSeeder remain in the repository for backward compatibility, but `DatabaseSeeder` uses `ErpMasterSeeder` as the ERP seed entry point.

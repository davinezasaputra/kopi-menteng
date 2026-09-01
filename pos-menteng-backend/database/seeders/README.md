# ERP Seeders

## Master seeder

Use the main entry point:

    php artisan db:seed

or explicitly:

    php artisan db:seed --class=Database\\Seeders\\ErpMasterSeeder

ErpMasterSeeder is idempotent and intended for development/UAT master data.

It seeds or synchronizes:
- Tenant / Company / Branch / Warehouse
- ERP roles and permissions for Phase 2-4
- Demo users and memberships
- ERP Chart of Accounts
- Product categories and demo products
- Initial inventory balances only when a balance row does not exist
- Suppliers
- Customers when the current schema supports them
- Current-year Purchasing Budget
- Purchasing Approval Matrix
- Sales Approval Matrix

## Credentials

Configure these environment variables before seeding demo users:

    SEED_DEVELOPER_PASSWORD=
    SEED_SALES_MANAGER_PASSWORD=
    SEED_PURCHASING_MANAGER_PASSWORD=
    SEED_CASHIER_PASSWORD=
    SEED_CASHIER_PIN=

If omitted, the password defaults to change-me-immediately.

## Important

Do not use migrate:fresh --seed on a database containing data you need to preserve.

The master seeder intentionally does not create PO, Goods Receipt, Supplier Invoice,
Supplier Payment, Supplier Return, Credit Note, Sales Order, Reservation, or Journal
transactions. Those should be created through the APIs so inventory and accounting
remain deterministic for Postman/UAT tests.

Legacy ProductSeeder and ErpFoundationSeeder remain in the repository for backward
compatibility, but DatabaseSeeder now uses ErpMasterSeeder as the single ERP seed entry point.

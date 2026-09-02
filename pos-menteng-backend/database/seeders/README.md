# ERP Seeders

`DatabaseSeeder` is the single entry point for local/UAT ERP seed data.

Run:

```bash
php artisan db:seed
```

For a clean UAT database:

```bash
php artisan migrate:fresh --seed
```

**Warning:** `migrate:fresh` destroys existing database data. Use it only on a database that is safe to reset.

## Seeded account

The ERP seed creates only the Developer account:

```text
Email: davin-eza@mahasiswa.ubb.ac.id
Password: value of SEED_DEVELOPER_PASSWORD
```

Set the password in the backend `.env` before seeding. Do not commit the password.

The Developer receives the primary tenant membership and the `tenant-admin` ERP role. Other ERP roles remain available as role definitions for RBAC testing, but demo user accounts for those roles are not seeded.

## Seeded master data

The master seeder synchronizes:

- Tenant / Company / Branch / Warehouse
- ERP permissions and role definitions
- Developer account and membership
- ERP Chart of Accounts
- Product categories and demo products
- Initial inventory balances when a balance row does not already exist
- Suppliers
- Customers when the current schema supports them
- Current-year Purchasing Budget
- Purchasing Approval Matrix
- Sales Approval Matrix

## Transaction data

The master seeder does not create operational transactions such as PO, Goods Receipt, Supplier Invoice, Supplier Payment, Supplier Return, Credit Note, Sales Order, Reservation, Attendance, Payroll, or Journal transactions. Create those through the application/API during UAT so inventory and accounting can be validated from a known baseline.

## Related UAT documentation

See `docs/ERP_END_TO_END_UAT_RUNBOOK.md` for the complete manual test sequence from login through Purchasing, Sales, POS, Finance, HRM/Payroll, closing, security, and regression checks.

Legacy ProductSeeder and ErpFoundationSeeder remain for backward compatibility; use `DatabaseSeeder` for the normal local/UAT reset flow.

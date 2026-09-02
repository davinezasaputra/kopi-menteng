# ERP Feature Status — Phase HRM / POS / Inventory

## Status

| Request | Status | Notes |
|---|---|---|
| Payroll auto-fill | ✅ Existing + verified in source | Uses employee salary + attendance and attendance penalties. |
| WhatsApp PDF after payroll paid | ✅ Existing + verified in source | Generates PDF, queues notification, supports Twilio and status sync. |
| Off-duty per employee | ✅ Added | Attendance action + UI modal. |
| Export attendance | ✅ Added | Month/year CSV export with scoped data. |
| Attendance Hadir/Sakit/Late/Absence | ✅ Added | Manual action per attendance record. |
| Clock-in / Clock-out rules | ✅ Added | Configurable time + grace period. |
| Late / Absence penalties | ✅ Added | Fixed or percentage; scoped to tenant/company/branch. |
| Menu import Excel | ✅ Added | XLSX/XLS/CSV, template, results + failed rows. |
| Bill template edit | ✅ Existing + extended | Existing receipt template expanded with bill title/subtitle. |
| PPN setting | ✅ Added | Configurable and applied to POS checkout calculation. |
| Developer documentation | ✅ Added | `docs/DEVELOPER.md`. |
| User manual | ✅ Added | `docs/MANUAL_BOOK.md`. |
| Recovery / backdoor safety | ✅ Added | `docs/SECURITY_RECOVERY.md`; no secret bypass. |

## New UI routes

- `/hrm` — payroll and payroll automation.
- `/hrm/attendance` — attendance control, status actions, clock-in/out, off-duty, export.
- `/inventory/menu-import` — menu Excel import.
- `/admin/business-rules` — attendance rules, penalties, bill template, PPN.
- `/admin/pos/receipt-template` — existing detailed receipt template editor.

## Important production verification

After pulling the branch:

```bash
cd pos-menteng-backend
php artisan migrate
php artisan test
```

Then:

```bash
cd ../pos-menteng-frontend
npm install
npm run build
```

For WhatsApp queue processing:

```bash
cd ../pos-menteng-backend
php artisan queue:work
```

The previously verified baseline before these changes was 74 tests / 252 assertions. This new feature set changes backend schema, routes, services, and frontend routing, so the baseline must be rerun on the actual development environment.

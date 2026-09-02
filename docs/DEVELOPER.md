# Kopi Menteng ERP — Developer Documentation

## 1. Architecture

The backend is Laravel and the frontend is React/TypeScript. Organization context is resolved from the authenticated membership and is exposed through `TenantContext`.

Core ERP boundaries:
- `app/Domain/*` contains domain services and ERP business logic.
- `app/Http/Controllers/Api/*` contains HTTP orchestration.
- `app/Models/*` contains Eloquent models.
- `routes/api.php` contains the main API; `routes/api_master.php`, `routes/api_hrm_automation.php`, and `routes/api_features.php` contain isolated feature route groups loaded by `bootstrap/app.php`.
- Frontend pages live under `pos-menteng-frontend/src/pages` and navigation is centralized in `AdminSidebar.tsx`.

## 2. HRM automation

Payroll automation already supports automatic payroll calculation from employee salary and attendance data, PDF generation, and asynchronous WhatsApp notification dispatch after a payroll becomes paid.

Relevant backend classes:
- `app/Domain/Hrm/Services/PayrollAutomationService.php`
- `app/Jobs/SendPayrollNotificationJob.php`
- `app/Http/Controllers/Api/HrmController.php`

Relevant UI:
- `/hrm`
- `/hrm/attendance`
- `/admin/business-rules`

Required WhatsApp provider environment variables for the current Twilio implementation:
- `WHATSAPP_PROVIDER=twilio`
- `TWILIO_ACCOUNT_SID`
- `TWILIO_AUTH_TOKEN`
- `TWILIO_WHATSAPP_FROM`

## 3. Attendance

New attendance operations are exposed from `api_hrm_automation.php`:
- `GET/PATCH /hrm/attendance/settings`
- `GET/PUT /hrm/attendance/penalties`
- `POST /hrm/attendance/clock-in`
- `POST /hrm/attendance/clock-out`
- `POST /hrm/attendances/{id}/status`
- `POST /hrm/attendance/off-duty`
- `GET /hrm/attendances/export`

Attendance settings and penalties are scoped to tenant + company + branch.

## 4. Menu import

Menu import is intentionally tenant-scoped and uses the current `Product` schema rather than legacy columns.

API:
- `GET /api/inventory/menu-import/template`
- `POST /api/inventory/menu-import`

Template columns:
`name, category, price, description, is_active`

Existing products with the same name inside the active tenant are updated; otherwise a new product is created.

## 5. Bill template and PPN

Existing receipt-template functionality is extended with:
- `bill_title`
- `bill_subtitle`
- `ppn_rate`

The existing endpoint remains:
- `GET /api/pos/receipt-template`
- `PUT /api/pos/receipt-template`

The frontend business-rules workspace provides the operator UI at `/admin/business-rules`.

## 6. Organization and security rules

Never accept tenant/company/branch/location identifiers as authoritative authorization input. Controllers must resolve records through `TenantContext`, membership scope, and domain-specific scope helpers.

Mutations should be audited where the existing AuditService is available. Financial mutations must remain inside the relevant transaction and respect fiscal closing.

## 7. Local development

Backend:
```bash
cd pos-menteng-backend
php artisan migrate
php artisan test
```

Frontend:
```bash
cd pos-menteng-frontend
npm install
npm run build
```

Run the Laravel queue worker when WhatsApp payroll notifications are enabled:
```bash
php artisan queue:work
```

## 8. Regression baseline

The latest verified local baseline before these new features was:
`74 passed (252 assertions)`.

After applying this feature set, rerun the full suite locally because this assistant cannot execute the user's Windows/Laragon runtime.

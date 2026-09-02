# ERP UAT Results — 2026-09-02

## Scope

Initial manual UAT from a clean seeded database, beginning with Developer authentication.

## Results

| Case | Status | Finding |
|---|---|---|
| Baseline after reset | PASS | Developer account and ERP master baseline available as expected. |
| A1 Password login | PASS | Developer can log in and reach Dashboard. |
| A2 Invalid password | FAIL — fixed | HTTP 401 from password login was intercepted globally and redirected to the PIN login screen, so the Admin Login error was not visible. |
| A3 Logout | PARTIAL — fixed | Logout itself succeeded, but the application returned to the PIN login route with no direct Admin Login entry. |
| Tenant password login | FAIL — fixed in code | Password login persisted token/user but did not persist ERP organization context and permissions before Dashboard authorization. |
| Employee creation | BLOCKED | UAT could not continue reliably because the tenant session/RBAC context was not initialized correctly. Re-test after authentication fix. |

## Code fixes applied

- Password-login 401 responses no longer trigger the global session-expired redirect.
- Backoffice 401 redirects now return to Admin Login instead of the cashier PIN screen.
- PIN login screen now provides a direct `Login Admin / Developer` entry.
- Password login now persists ERP context, ERP role, and permissions returned by the backend.
- `/v1/me` bootstrap logic preserves already-persisted permissions instead of replacing them with an empty permission set during refresh.
- Developer-only seed baseline remains enforced: only `davin-eza@mahasiswa.ubb.ac.id` is seeded as a user.

## Re-test required

After pulling the latest branch:

1. `php artisan migrate:fresh --seed`
2. Configure `SEED_DEVELOPER_PASSWORD` locally.
3. Login from `/admin-login` with Developer.
4. Enter an invalid password and confirm the error remains on `/admin-login`.
5. Login successfully and refresh Dashboard.
6. Logout and confirm the PIN screen contains `Login Admin / Developer`.
7. Create a tenant from Developer Console, create a Tenant Admin, logout, and test Tenant Admin password login.
8. Confirm Tenant Admin reaches Dashboard and retains permissions after refresh.
9. Open Employees and create an employee; record the exact error if this still fails.

## Reporting format

For every remaining failure, record:

```text
CASE:
STATUS: PASS | FAIL
URL:
STEPS:
EXPECTED:
ACTUAL:
ERROR:
DATA IDS:
SCREENSHOT:
```

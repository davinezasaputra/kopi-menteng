# Frontend Enterprise UI Contract

## Objective

Upgrade the existing Kopi Menteng frontend to consume the ERP foundation without rewriting the established visual language or legacy POS/CRM/HRM workflows.

## Design preservation

Existing pages remain intact visually and are upgraded through shared infrastructure rather than a wholesale visual rewrite. POS remains a dedicated operational workspace; backoffice pages retain the existing stone/amber navigation and legacy page layouts.

## Access model

The frontend treats permissions as the source of UI authorization hints:

- `pos.sale.view` → POS
- `inventory.stock.view` → Inventory / raw materials
- `inventory.stock.adjust` → Raw material import / stock-changing UI
- `users.user.view` → Users
- `hr.employee.view` → Employees / HRM
- `accounting.journal.view`, `accounting.erp_account.view`, `accounting.report.view` → Accounting
- `sales.order.view` → CRM customers
- `rbac.role.view` → ERP Foundation administration
- reporting permissions → Dashboard / History

This is a UX boundary only. Backend middleware remains authoritative.

## Organization context

The frontend consumes `/api/v1/me` and `/api/v1/my-memberships` to maintain:

- tenant context
- company context
- branch context
- active role
- active permissions

Context is sent through `X-Tenant-ID`, `X-Company-ID`, and `X-Branch-ID` when an active membership has selected that scope.

## API client hardening

The global Axios client now adds:

- Bearer authentication from the current Sanctum token
- organization context headers
- `X-Request-ID` on every request
- valid unique `X-Idempotency-Key` on mutating requests except authentication/webhook routes
- centralized 401 session cleanup

Legacy pages can continue using their current API calls because the Axios instance is centralized, while new foundation calls use `/api/v1`.

## Navigation

Backoffice navigation is filtered from the current permission set. Unauthorized menu entries are not displayed. The original menu styling is retained.

## Route guards

Application routes no longer depend on legacy role strings as the primary UI gate. Route access is expressed through permissions or permission sets.

## Security expectations

The frontend must never be considered a security boundary. A hidden button or route guard does not grant security; the backend must continue to enforce authentication, tenant membership, and permissions.

## QA checklist

1. Login as tenant-admin and verify foundation context is loaded before protected pages render.
2. Login as cashier and verify only cashier-relevant navigation is visible.
3. Switch between valid memberships and verify tenant/company/branch headers change accordingly.
4. Attempt a cross-tenant context and verify the backend rejects it.
5. Verify mutating requests carry `X-Idempotency-Key` and all requests carry `X-Request-ID`.
6. Verify logout uses `/api/v1/auth/logout` and clears client context.
7. Run `npm run build` and the Master Postman regression collection.

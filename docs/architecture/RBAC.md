# RBAC

Authorization is determined by:

```text
User -> Membership -> Role -> Permission
```

## Principles

- `users.role` is legacy compatibility data, not the primary security boundary.
- Permission checks are scoped by active tenant/company/branch membership.
- Backend middleware and policies are authoritative.
- Frontend permission checks are UX controls only.
- System roles are protected from tenant-admin deletion/modification where policy requires it.

## Permission naming

Use `module.resource.action`, for example:

- `users.user.view`
- `users.user.create`
- `inventory.stock.adjust`
- `sales.order.create`
- `accounting.journal.post`
- `hr.employee.update`

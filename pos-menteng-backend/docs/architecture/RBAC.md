# RBAC

Authorization source of truth is `Membership -> Role -> Permission`.

Permission names use `module.resource.action`, for example `users.user.view`, `inventory.stock.adjust`, and `accounting.journal.post`.

Frontend checks are UX-only. Backend middleware/policies are authoritative.

System roles are protected from tenant deletion through `RolePolicy`.

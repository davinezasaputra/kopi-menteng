# ERP Foundation Architecture

```text
Tenant
  └── Company
       └── Branch
            └── Warehouse

User
  └── Membership
       └── Role
            └── Permission
```

A membership defines the user's organization scope and role. The authenticated request resolves an active primary membership into `TenantContext`.

## Authorization

Backend authorization is authoritative. Frontend permission checks are only a UX layer.

Permission naming convention:

`module.resource.action`

Examples:

- `users.user.view`
- `inventory.stock.adjust`
- `accounting.journal.post`

## Audit

`audit_logs` is append-only at the application model layer. It stores actor, organization context, event, entity, before/after values, request ID, IP address, and user agent.

## Multi-tenancy rollout

Phase 1 establishes the organization model and secures user administration. Existing business domains are migrated one at a time. No module is considered tenant-safe until both its writes and reads enforce tenant/company/branch scope.

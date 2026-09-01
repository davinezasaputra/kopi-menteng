# Tenancy

## Context source

The effective organization context comes from the authenticated user and an active `Membership`.

```text
User -> Membership -> Tenant -> Company -> Branch
```

`X-Tenant-ID`, `X-Company-ID`, and `X-Branch-ID` may be sent by the UI, but the backend must validate each requested context against membership scope.

## Isolation rules

- Cross-tenant resources are denied.
- Cross-company resources are denied unless membership permits the company.
- Cross-branch resources are denied unless membership permits the branch.
- Request payload organization IDs must never override the resolved context.

## Legacy compatibility

Existing business tables are migrated progressively. Scope requirements are maintained in `docs/database/ORGANIZATION_DATA_SCOPE.md`.

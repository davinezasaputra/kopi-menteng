# Tenancy

## Context hierarchy

`User -> Membership -> TenantContext -> tenant/company/branch`

The authenticated user is never trusted to select arbitrary organization IDs. A client may request a context through `X-Tenant-ID`, `X-Company-ID`, and `X-Branch-ID`; `ResolveTenantContext` accepts the context only when an active membership matches it.

## Rules

- Missing active membership returns `403` on tenant-protected endpoints.
- Cross-tenant context is denied.
- Company and branch must match the same membership.
- Default context uses the primary active membership.
- Existing business endpoints remain backward-compatible; their scope is hardened domain-by-domain.

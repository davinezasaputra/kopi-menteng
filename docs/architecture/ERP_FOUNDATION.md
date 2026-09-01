# ERP Foundation

## Baseline

Branch: `feat/erp-phase-5-finance-closing`

The platform uses a compatibility-preserving ERP foundation. Existing POS/CRM/HRM and ERP domains are retained; new organization, identity, authorization, audit, sequencing, and API-v1 capabilities are additive.

## Organization hierarchy

```text
Tenant
  └── Company
      └── Branch
          └── Warehouse

Company
  ├── Department
  └── Cost Center
```

## Authorization hierarchy

```text
User
  └── Membership
      └── Role
          └── Permission
```

Legacy `users.role` remains only for compatibility. Backend authorization is permission- and membership-based.

## Context

`TenantContext` resolves and validates tenant/company/branch from the authenticated user's active membership. Client-provided context headers are hints only and are never authoritative.

## API

New foundation endpoints are exposed under `/api/v1` while legacy `/api/*` routes remain during migration.

## Auditability

Important mutations and security events carry tenant context and request identifiers. Audit logs are append-only.

## Document numbering

Business document numbers are generated through concurrency-safe sequences rather than random identifiers.

## Compatibility

Historical migrations are not rewritten destructively. Existing tables and endpoints are migrated incrementally with explicit scope mapping and regression tests.

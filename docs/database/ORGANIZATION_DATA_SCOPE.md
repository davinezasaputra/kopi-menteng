# Organization Data Scope

This document records organization-scope expectations for transactional data. It is intentionally compatibility-preserving: a table is not marked tenant-safe merely because columns exist; read and write paths must enforce the scope.

| Domain/Table | Tenant | Company | Branch | Warehouse | Strategy |
|---|---|---|---|---|---|
| users / memberships | required via membership | optional in membership | optional in membership | n/a | resolve from authenticated membership |
| orders | required | required | required | where stock is affected | preserve legacy columns; enforce context |
| products | required | optional | optional | n/a | tenant scoped; company/branch only where business rules require |
| accounts | required | required | optional | n/a | accounting scope |
| audit_logs | required | optional | optional | n/a | immutable audit context |
| inventory balances/movements | required | required where modeled | required where modeled | required | ledger and warehouse scope |
| purchasing | required | required | optional | where receiving is modeled | transactional scope |
| sales | required | required | required | where fulfillment affects stock | transactional scope |
| HR | required | required | optional | n/a | employee/branch scope |

## Rules

1. Do not trust organization IDs supplied in request bodies.
2. Every scoped query must use indexed organization columns or an equivalent membership relationship.
3. New organization-scope migrations are additive.
4. Existing domain tables are migrated domain-by-domain with regression coverage.

# Phase 1 Impact Map

## Baseline

Branch: `feat/erp-phase-5-finance-closing`

Phase 1 was implemented on top of an existing POS/CRM/HRM application and later extended with ERP modules. This map records the foundation impact without treating later business modules as part of the original Phase 1 scope.

| Area | Current responsibility | Planned/implemented change | Risk | Migration strategy | Compatibility notes |
|---|---|---|---|---|---|
| Authentication | Sanctum login/PIN | secure lifecycle, audit, revocation | session breakage | additive | legacy login retained |
| User | identity + legacy role | membership/RBAC authorization | privilege drift | relationship-first | `role` retained temporarily |
| Organization | tenant/company/branch/warehouse | explicit hierarchy/context | cross-tenant leakage | additive | legacy data preserved |
| RBAC | role/permission | scoped permission service/policies | stale tenant permissions | sync seeders + tests | legacy role names preserved |
| Audit | business/security evidence | immutable append-only trail | audit gaps | additive | no update/delete API |
| Document sequence | business numbering | atomic sequence service | duplicate numbers | transactional lock | legacy identifiers preserved |
| API | legacy `/api/*` | additive `/api/v1/*` | client breakage | parallel migration | old routes remain |
| Frontend API | page-local Axios usage | central client and context headers | incomplete migration | incremental | existing UI retained |
| Frontend auth | role-based guards | permission-aware guards/gates | UX mismatch | incremental | backend remains authoritative |
| Database | existing integer-key ecosystem | new foundation tables/scopes | FK/type incompatibility | additive | do not destructively rewrite historical schema |

## Exit condition

A domain is considered tenant-safe only after its reads and writes are both scoped and covered by authorization/isolation regression tests.

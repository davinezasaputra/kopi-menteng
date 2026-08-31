# Phase 1 Impact Map

## Scope

ERP foundation only: organization hierarchy, identity/RBAC, tenant context, audit foundation, document numbering, and security boundaries.

| Area | Current | Phase 1 | Risk | Strategy |
|---|---|---|---|---|
| Users | legacy role enum | membership + role + permission | Medium | preserve legacy role for compatibility |
| User routes | public CRUD | Sanctum + tenant + permission | High | secure immediately |
| Organization | implicit single business | tenant/company/branch/warehouse | Medium | additive tables |
| RBAC | role string | roles + permissions | Medium | legacy role mapped by seeder |
| Audit | scattered observers | immutable audit log service | Medium | additive, domain-by-domain |
| Documents | random invoice reference | atomic sequence service | Medium | adopt per transaction domain |
| Existing business tables | mostly no tenant scope | scoped incrementally | High | do not claim full multi-tenancy until each domain is migrated |

## Important compatibility decision

Existing business endpoints remain under Sanctum during Phase 1. They are intentionally not given a tenant middleware until their underlying transactional tables receive tenant/company/branch scope and their queries are audited. This avoids creating a false sense of tenant isolation.

## Migration order

1. Foundation tables
2. User context fields
3. Seed organization/RBAC
4. Tenant context
5. Permission middleware
6. User administration isolation
7. Audit endpoint/service
8. Document sequence service
9. Domain-by-domain transaction scoping

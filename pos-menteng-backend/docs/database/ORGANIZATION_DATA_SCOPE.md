# Organization Data Scope

| Domain/table | Tenant | Company | Branch | Warehouse | Strategy |
|---|---|---|---|---|---|
| users | via memberships | via membership | via membership | no | membership context |
| products | required | optional | optional | no | tenant-scoped legacy product |
| customers | required | optional | optional | no | tenant/company/branch compatible |
| orders | required | required | required | operational | transactional scope |
| employees | required | required | required | no | HR scope |
| attendances | required | required | required | no | employee-derived |
| leaves | required | required | required | no | employee-derived |
| payrolls | required | required | required | no | employee-derived |
| raw_materials | required | required | required | no | inventory legacy scope |
| inventory_balances | required | required | required | required | stock scope |
| purchasing | required | required | required | where applicable | domain service scope |
| sales | required | required | required | where applicable | domain service scope |
| ERP accounting | required | required | branch-aware where needed | no | accounting scope |
| audit_logs | required | optional | optional | no | actor/context scope |
| document_sequences | required | optional | optional | no | atomic sequence scope |

Existing historical migrations are not rewritten. New migrations add fields/indexes and backfill legacy rows where required.

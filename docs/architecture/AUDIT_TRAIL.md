# Audit Trail

Audit records are append-only evidence of important business and security activity.

## Required fields

- tenant_id
- user_id (nullable)
- company_id (nullable)
- branch_id (nullable)
- event
- module
- entity_type
- entity_id
- old_values (nullable)
- new_values (nullable)
- ip_address (nullable)
- user_agent (nullable)
- request_id (nullable)
- created_at

## Event catalog

`created`, `updated`, `deleted`, `approved`, `rejected`, `posted`, `cancelled`, `reversed`, `paid`, `refunded`, `closed`, `reopened`, `login`, `logout`, `failed_login`, `permission_denied`.

Audit APIs are read-only. No update/delete endpoint is permitted.

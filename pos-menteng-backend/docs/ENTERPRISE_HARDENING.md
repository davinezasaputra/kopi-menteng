# Enterprise Hardening — Phase 6

## Implemented

### 6.1 Idempotency Registry
Write requests may provide:
    X-Idempotency-Key: <8-100 chars>

Completed responses are replayed for the same user, tenant, route, method and payload hash.
A changed payload with the same key is rejected.

Domain-level idempotency (X-Request-ID/request_id) remains active.

### 6.2 Concurrency
Transactional inventory and sales flows continue to use row locking.
The HTTP idempotency registry adds an additional protection against repeated write requests.

### 6.3 API Rate Limits
Named limiters:
- erp: 120 requests/minute by authenticated user (configurable with ERP_API_RATE_LIMIT)
- erp-login: 10 requests/minute by IP (configurable with ERP_LOGIN_RATE_LIMIT)

### 6.4 Readiness
GET /api/ready checks database connectivity and returns 200 when ready and 503 when the database check fails.

### 6.5 Security Headers
API responses include:
- X-Content-Type-Options: nosniff
- X-Frame-Options: DENY
- Referrer-Policy: strict-origin-when-cross-origin
- Permissions-Policy: camera=(), microphone=(), geolocation=()
- HSTS on HTTPS requests

### 6.6 Automated Coverage
Feature coverage added for readiness and security headers.

## Production notes

Configure:
    ERP_API_RATE_LIMIT=120
    ERP_LOGIN_RATE_LIMIT=10

Do not expose database credentials or seed passwords in source control.
Use environment-specific secrets for production.

Phase 6 does not claim complete production certification. Load testing, infrastructure failover,
backup/restore drills, and deployment verification still require the target environment.

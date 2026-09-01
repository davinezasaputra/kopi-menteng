# Authorization

## Request flow

```text
Sanctum authentication
  -> Tenant context
  -> Membership scope
  -> Permission middleware / Policy
  -> Controller / Action
```

## HTTP semantics

- `401 Unauthorized`: no valid authentication.
- `403 Forbidden`: authenticated but outside membership scope or missing permission.

## Security rules

Never authorize based solely on frontend visibility, role strings, request tenant IDs, or record ownership assumptions. All sensitive reads and writes must be enforced on the server.

## Frontend

`PermissionGate` and route guards exist to improve UX. They are not a security boundary.

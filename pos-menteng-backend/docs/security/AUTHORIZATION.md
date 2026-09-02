# Authorization Security Contract

1. Unauthenticated requests receive `401`.
2. Authenticated requests without the required permission receive `403`.
3. Tenant context is resolved from an active membership; frontend organization IDs are never trusted without membership validation.
4. System roles are protected by policy.
5. Audit logs are immutable and have no write API.
6. PINs are hashed into `users.pin_hash`; legacy plaintext `pin` is migrated to null.
7. Sanctum remains the token system. Logout revokes the current token.
8. Rate limiting applies to password and PIN login attempts.

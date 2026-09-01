# Audit Trail

`audit_logs` is append-only. There is no update/delete API and the model rejects update/delete operations.

Captured context:

- actor user
- tenant/company/branch
- event
- module
- entity type/id
- old/new values
- IP address
- user agent
- request ID
- creation timestamp

Supported lifecycle events include created, updated, deleted, approved, rejected, posted, cancelled, reversed, paid, refunded, closed, reopened, login, logout, failed_login, and permission_denied.

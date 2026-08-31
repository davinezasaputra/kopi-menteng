# Phase 2.5 Hardening

## Scope

This hardening pass strengthens the existing inventory reservation flow without changing the Phase 2.5 public reservation lifecycle.

## Changes

- Reservation numbers use the tenant/company/branch document sequence (`RES-YYYYMM-NNNNNN`).
- Reservation creation is idempotent when the request middleware supplies a `request_id`.
- A database unique constraint prevents duplicate reservation commands per tenant/request ID.
- Reservation stock release and fulfillment lock and scope `InventoryBalance` by tenant, company, branch, warehouse, and product.
- Duplicate product lines are normalized before reservation items are persisted.
- Expiration now releases reserved stock and records `expired` lifecycle state.
- Fulfillment automatically expires an overdue active reservation and releases its stock before returning validation failure.
- Reservation controller lookup operations are explicitly tenant/company/branch scoped.
- The reservation detail parameter is corrected from `opname` to `reservation`.
- An explicit expiration endpoint is available for administrative/worker-driven lifecycle processing.

## Verification

Run the existing Laravel test suite and execute the reservation Postman collection against the hardening branch before merging. The hardening branch does not claim runtime PASS until those tests are executed in the project environment.

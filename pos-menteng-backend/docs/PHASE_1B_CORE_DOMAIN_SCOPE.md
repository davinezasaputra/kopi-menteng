# Phase 1B — Core Domain Organization Scope

## Scope

This phase enforces organization context for three existing business domains:

- Product: tenant-scoped
- Customer: tenant-scoped
- Order: tenant + company + branch-scoped

## Existing data migration

Existing products, customers, and orders are backfilled to the first available tenant. Existing orders are also backfilled to the first available company and branch. The migration is additive and keeps the legacy data usable.

## API behavior

Authenticated users must have a valid ERP membership before accessing the core business routes below:

- GET/POST/PUT/DELETE `/api/products`
- GET/POST `/api/customers`
- POST `/api/customers/search`
- GET `/api/orders`
- GET `/api/orders/history`
- POST `/api/orders/checkout`

## Scope rules

### Product

Products are visible and mutable only inside the authenticated user's tenant.

### Customer

Customers are visible, searchable, and creatable only inside the authenticated user's tenant.

### Order

Orders are visible only inside the authenticated user's tenant/company/branch context.

Checkout resolves product and customer records through the authenticated tenant context and writes the order using the authenticated tenant/company/branch context. Client-supplied organization IDs are not used.

## Known next step

Inventory stock is still stored directly on `products.stock` and raw-material stock is still stored on raw-material records. A later inventory-engine phase must replace this with a ledger-driven stock model.

# Purchasing PO Audit Report

## Issues Identified

### 1. ✅ Backend Structure - GOOD
- Migration has `order_date` field (document date)
- Supplier model has proper relationships
- PurchaseOrder model has all required fields
- Service calculates totals properly

### 2. ❌ Frontend Response Handling - ISSUE
**Problem**: `labelOf(row, ['supplier_name','supplier','name','contact_name'])`
- Backend returns `supplier: { id, name, ... }` (object)
- Frontend tries `String(object)` → `[object Object]`
- Fix: Frontend needs to extract nested value OR backend needs to flatten response

### 3. ⚠️ Date Field - NEEDS VERIFICATION  
**Issue**: Frontend might be using `created_at` instead of `order_date`
- Backend properly sets `order_date` 
- Frontend uses date from response but might not be checking `order_date` explicitly

### 4. ⚠️ Total Calculation - NEEDS TESTING
**Issue**: Frontend showing `Rp 0` for nominal
- Backend calculates `grand_total = subtotal - discount + tax`
- Could be:
  - API not returning field
  - Frontend reading wrong field
  - Calculation issue on frontend

### 5. ⚠️ Product ID Type - SAFE
- Migration: `product_id` is UUID
- Service: No `Number()` conversion - GOOD
- Frontend: Should not convert UUID to number - VERIFY

## Next Steps

1. Test API endpoint directly to see response shape
2. Fix frontend `labelOf` to handle nested objects
3. Add explicit date handling for `order_date`
4. Add total calculation verification
5. Write comprehensive tests

## Files to Fix

### Backend
- No changes needed (structure is correct)

### Frontend
- `src/pages/PurchasingWorkspace.tsx` - Update labelOf or add resolver
- `src/pages/PurchaseOrders.tsx` - Verify total display logic
- Add response normalization for supplier/partner objects

### Tests
- Create comprehensive test suite for PO workflow
- Test API response shapes
- Test authorization/scope


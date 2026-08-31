<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\InventoryReservation;
use App\Domain\Inventory\Models\InventoryReservationItem;
use App\Models\Product;
use App\Domain\Organization\Models\Warehouse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryReservationService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditService $audit,
    ) {}

    public function reserve(Warehouse $warehouse, array $items, ?string $referenceType = null, ?string $referenceId = null, ?string $expiresAt = null, ?string $notes = null): InventoryReservation
    {
        $membership = $this->context->membership();
        if (! $membership) {
            throw ValidationException::withMessages(['context' => 'No active ERP context.']);
        }
        if ((int) $warehouse->branch_id !== (int) $membership->branch_id) {
            throw ValidationException::withMessages(['warehouse_id' => 'Warehouse is outside the active branch.']);
        }

        return DB::transaction(function () use ($membership, $warehouse, $items, $referenceType, $referenceId, $expiresAt, $notes) {
            $reservation = InventoryReservation::create([
                'tenant_id' => $membership->tenant_id,
                'company_id' => $membership->company_id,
                'branch_id' => $membership->branch_id,
                'warehouse_id' => $warehouse->id,
                'reservation_number' => 'RES-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(6)),
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'status' => 'active',
                'expires_at' => $expiresAt,
                'created_by' => auth()->id(),
                'request_id' => request()->attributes->get('request_id'),
                'notes' => $notes,
            ]);

            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);
                if ((int) $product->tenant_id !== (int) $membership->tenant_id) {
                    throw ValidationException::withMessages(['items' => 'One or more products are outside the active tenant.']);
                }

                $quantity = (float) $item['quantity'];
                if ($quantity <= 0) {
                    throw ValidationException::withMessages(['items' => 'Reservation quantity must be greater than zero.']);
                }

                $balance = InventoryBalance::query()
                    ->where('tenant_id', $membership->tenant_id)
                    ->where('company_id', $membership->company_id)
                    ->where('branch_id', $warehouse->branch_id)
                    ->where('warehouse_id', $warehouse->id)
                    ->where('product_id', $product->id)
                    ->lockForUpdate()
                    ->first();

                if (! $balance) {
                    throw ValidationException::withMessages(['items' => "No inventory balance exists for {$product->name} in this warehouse."]);
                }

                if ((float) $balance->available_quantity < $quantity) {
                    throw ValidationException::withMessages([
                        'items' => "Insufficient available stock for {$product->name}. Available: {$balance->available_quantity}.",
                    ]);
                }

                $balance->reserved_quantity = (float) $balance->reserved_quantity + $quantity;
                $balance->available_quantity = (float) $balance->quantity - (float) $balance->reserved_quantity;
                $balance->save();

                InventoryReservationItem::create([
                    'inventory_reservation_id' => $reservation->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'fulfilled_quantity' => 0,
                ]);
            }

            $reservation->load(['items.product','warehouse']);
            $this->audit->record('created', 'inventory_reservation', $reservation, null, $reservation->toArray());
            return $reservation;
        });
    }

    public function release(InventoryReservation $reservation): InventoryReservation
    {
        return DB::transaction(function () use ($reservation) {
            $this->assertContext($reservation);
            $reservation = InventoryReservation::query()->with('items')->lockForUpdate()->findOrFail($reservation->id);
            if ($reservation->status !== 'active') {
                throw ValidationException::withMessages(['status' => 'Only active reservations can be released.']);
            }

            foreach ($reservation->items as $item) {
                $balance = InventoryBalance::query()
                    ->where('warehouse_id', $reservation->warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $balance->reserved_quantity = max(0, (float) $balance->reserved_quantity - (float) $item->quantity);
                $balance->available_quantity = (float) $balance->quantity - (float) $balance->reserved_quantity;
                $balance->save();
            }

            $old = $reservation->only(['status']);
            $reservation->status = 'released';
            $reservation->released_by = auth()->id();
            $reservation->released_at = now();
            $reservation->save();
            $reservation->load(['items.product','warehouse']);
            $this->audit->record('released', 'inventory_reservation', $reservation, $old, ['status' => 'released']);
            return $reservation;
        });
    }

    public function fulfill(InventoryReservation $reservation): InventoryReservation
    {
        return DB::transaction(function () use ($reservation) {
            $this->assertContext($reservation);
            $reservation = InventoryReservation::query()->with('items')->lockForUpdate()->findOrFail($reservation->id);
            if ($reservation->status !== 'active') {
                throw ValidationException::withMessages(['status' => 'Only active reservations can be fulfilled.']);
            }
            if ($reservation->expires_at && $reservation->expires_at->isPast()) {
                throw ValidationException::withMessages(['expires_at' => 'Reservation has expired.']);
            }

            foreach ($reservation->items as $item) {
                $balance = InventoryBalance::query()
                    ->where('warehouse_id', $reservation->warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $qty = (float) $item->quantity;
                if ((float) $balance->reserved_quantity < $qty || (float) $balance->quantity < $qty) {
                    throw ValidationException::withMessages(['items' => 'Reserved quantity is no longer available for fulfillment.']);
                }
                $balance->reserved_quantity = (float) $balance->reserved_quantity - $qty;
                $balance->quantity = (float) $balance->quantity - $qty;
                $balance->available_quantity = (float) $balance->quantity - (float) $balance->reserved_quantity;
                $balance->save();
                $item->fulfilled_quantity = $qty;
                $item->save();
            }

            $old = $reservation->only(['status']);
            $reservation->status = 'fulfilled';
            $reservation->fulfilled_by = auth()->id();
            $reservation->fulfilled_at = now();
            $reservation->save();
            $reservation->load(['items.product','warehouse']);
            $this->audit->record('fulfilled', 'inventory_reservation', $reservation, $old, ['status' => 'fulfilled']);
            return $reservation;
        });
    }

    private function assertContext(InventoryReservation $reservation): void
    {
        $membership = $this->context->membership();
        if (! $membership || (int) $reservation->tenant_id !== (int) $membership->tenant_id || (int) $reservation->company_id !== (int) $membership->company_id || (int) $reservation->branch_id !== (int) $membership->branch_id) {
            throw ValidationException::withMessages(['reservation' => 'Reservation is outside the active ERP context.']);
        }
    }
}
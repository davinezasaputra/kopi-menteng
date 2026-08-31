<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Core\Services\DocumentNumberService;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\InventoryReservation;
use App\Domain\Inventory\Models\InventoryReservationItem;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Organization\Models\Warehouse;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryReservationService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditService $audit,
        private readonly DocumentNumberService $documentNumbers,
    ) {}

    public function reserve(
        Warehouse $warehouse,
        array $items,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $expiresAt = null,
        ?string $notes = null,
    ): InventoryReservation {
        $membership = $this->context->membership();
        if (! $membership) {
            throw ValidationException::withMessages(['context' => 'No active ERP context.']);
        }

        if ((int) $warehouse->branch_id !== (int) $membership->branch_id) {
            throw ValidationException::withMessages(['warehouse_id' => 'Warehouse is outside the active branch.']);
        }

        $requestId = request()->attributes->get('request_id');

        if ($requestId) {
            $existing = InventoryReservation::query()
                ->where('tenant_id', $membership->tenant_id)
                ->where('request_id', $requestId)
                ->first();

            if ($existing) {
                return $existing->load(['items.product', 'warehouse']);
            }
        }

        try {
            return DB::transaction(function () use ($membership, $warehouse, $items, $referenceType, $referenceId, $expiresAt, $notes, $requestId) {
                $reservation = InventoryReservation::create([
                    'tenant_id' => $membership->tenant_id,
                    'company_id' => $membership->company_id,
                    'branch_id' => $membership->branch_id,
                    'warehouse_id' => $warehouse->id,
                    'reservation_number' => $this->documentNumbers->next('inventory_reservation', 'RES'),
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'status' => 'active',
                    'expires_at' => $expiresAt,
                    'created_by' => auth()->id(),
                    'request_id' => $requestId,
                    'notes' => $notes,
                ]);

                foreach ($this->normalizeItems($items) as $item) {
                    $product = Product::query()
                        ->where('tenant_id', $membership->tenant_id)
                        ->findOrFail($item['product_id']);

                    $balance = InventoryBalance::query()
                        ->where('tenant_id', $membership->tenant_id)
                        ->where('company_id', $membership->company_id)
                        ->where('branch_id', $membership->branch_id)
                        ->where('warehouse_id', $warehouse->id)
                        ->where('product_id', $product->id)
                        ->lockForUpdate()
                        ->first();

                    if (! $balance) {
                        throw ValidationException::withMessages([
                            'items' => "No inventory balance exists for {$product->name} in this warehouse.",
                        ]);
                    }

                    $quantity = (float) $item['quantity'];
                    if ($quantity <= 0) {
                        throw ValidationException::withMessages(['items' => 'Reservation quantity must be greater than zero.']);
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

                $reservation->load(['items.product', 'warehouse']);
                $this->audit->record('created', 'inventory_reservation', $reservation, null, $reservation->toArray());

                return $reservation;
            });
        } catch (QueryException $exception) {
            if ($requestId && $this->isRequestIdConflict($exception)) {
                return InventoryReservation::query()
                    ->where('tenant_id', $membership->tenant_id)
                    ->where('request_id', $requestId)
                    ->firstOrFail()
                    ->load(['items.product', 'warehouse']);
            }

            throw $exception;
        }
    }

    public function release(InventoryReservation $reservation): InventoryReservation
    {
        return DB::transaction(function () use ($reservation) {
            $this->assertContext($reservation);
            $row = InventoryReservation::query()->with('items')->lockForUpdate()->findOrFail($reservation->id);

            if ($row->status !== 'active') {
                throw ValidationException::withMessages(['status' => 'Only active reservations can be released.']);
            }

            $this->releaseReservedStock($row);

            $old = $row->only(['status']);
            $row->status = 'released';
            $row->released_by = auth()->id();
            $row->released_at = now();
            $row->save();

            $row->load(['items.product', 'warehouse']);
            $this->audit->record('released', 'inventory_reservation', $row, $old, ['status' => 'released']);

            return $row;
        });
    }

    public function expire(InventoryReservation $reservation): InventoryReservation
    {
        return DB::transaction(function () use ($reservation) {
            $this->assertContext($reservation);
            $row = InventoryReservation::query()->with('items')->lockForUpdate()->findOrFail($reservation->id);

            if ($row->status !== 'active') {
                return $row->load(['items.product', 'warehouse']);
            }

            if (! $row->expires_at || $row->expires_at->isFuture()) {
                throw ValidationException::withMessages(['expires_at' => 'Reservation is not due for expiration.']);
            }

            $this->releaseReservedStock($row);
            $old = $row->only(['status']);
            $row->status = 'expired';
            $row->save();

            $row->load(['items.product', 'warehouse']);
            $this->audit->record('expired', 'inventory_reservation', $row, $old, ['status' => 'expired']);

            return $row;
        });
    }

    public function fulfill(InventoryReservation $reservation): InventoryReservation
    {
        return DB::transaction(function () use ($reservation) {
            $this->assertContext($reservation);
            $row = InventoryReservation::query()->with('items')->lockForUpdate()->findOrFail($reservation->id);

            if ($row->status !== 'active') {
                throw ValidationException::withMessages(['status' => 'Only active reservations can be fulfilled.']);
            }

            if ($row->expires_at && $row->expires_at->isPast()) {
                $this->releaseReservedStock($row);
                $old = $row->only(['status']);
                $row->status = 'expired';
                $row->save();
                $this->audit->record('expired', 'inventory_reservation', $row, $old, ['status' => 'expired']);
                throw ValidationException::withMessages(['expires_at' => 'Reservation has expired and its stock has been released.']);
            }

            foreach ($row->items as $item) {
                $balance = InventoryBalance::query()
                    ->where('tenant_id', $row->tenant_id)
                    ->where('company_id', $row->company_id)
                    ->where('branch_id', $row->branch_id)
                    ->where('warehouse_id', $row->warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $quantity = (float) $item->quantity;
                if ((float) $balance->reserved_quantity < $quantity || (float) $balance->quantity < $quantity) {
                    throw ValidationException::withMessages(['items' => 'Reserved quantity is no longer available for fulfillment.']);
                }

                $before = (float) $balance->quantity;
                $cost = (float) $balance->average_cost;
                $balance->reserved_quantity = (float) $balance->reserved_quantity - $quantity;
                $balance->quantity = $before - $quantity;
                $balance->available_quantity = (float) $balance->quantity - (float) $balance->reserved_quantity;
                $balance->save();

                $item->fulfilled_quantity = $quantity;
                $item->save();

                StockMovement::create([
                    'tenant_id' => $row->tenant_id,
                    'company_id' => $row->company_id,
                    'branch_id' => $row->branch_id,
                    'warehouse_id' => $row->warehouse_id,
                    'product_id' => $item->product_id,
                    'movement_type' => 'reservation_issue',
                    'quantity' => -$quantity,
                    'unit_cost' => $cost,
                    'balance_before' => $before,
                    'balance_after' => (float) $balance->quantity,
                    'reference_type' => 'inventory_reservation',
                    'reference_id' => (string) $row->id,
                    'created_by' => auth()->id(),
                    'request_id' => request()->attributes->get('request_id'),
                    'notes' => $row->notes,
                    'created_at' => now(),
                ]);

                $legacy = InventoryBalance::query()
                    ->where('tenant_id', $row->tenant_id)
                    ->where('product_id', $item->product_id)
                    ->sum('quantity');
                Product::whereKey($item->product_id)->update(['stock' => max(0, (int) round((float) $legacy))]);
            }

            $old = $row->only(['status']);
            $row->status = 'fulfilled';
            $row->fulfilled_by = auth()->id();
            $row->fulfilled_at = now();
            $row->save();

            $row->load(['items.product', 'warehouse']);
            $this->audit->record('fulfilled', 'inventory_reservation', $row, $old, ['status' => 'fulfilled']);

            return $row;
        });
    }

    private function releaseReservedStock(InventoryReservation $reservation): void
    {
        foreach ($reservation->items as $item) {
            $balance = InventoryBalance::query()
                ->where('tenant_id', $reservation->tenant_id)
                ->where('company_id', $reservation->company_id)
                ->where('branch_id', $reservation->branch_id)
                ->where('warehouse_id', $reservation->warehouse_id)
                ->where('product_id', $item->product_id)
                ->lockForUpdate()
                ->firstOrFail();

            $balance->reserved_quantity = max(0, (float) $balance->reserved_quantity - (float) $item->quantity);
            $balance->available_quantity = (float) $balance->quantity - (float) $balance->reserved_quantity;
            $balance->save();
        }
    }

    private function normalizeItems(array $items): array
    {
        $normalized = [];
        foreach ($items as $item) {
            $productId = $item['product_id'] ?? null;
            $quantity = (float) ($item['quantity'] ?? 0);
            if (! $productId || $quantity <= 0) {
                throw ValidationException::withMessages(['items' => 'Each reservation item requires a valid product and positive quantity.']);
            }
            $normalized[$productId] = ($normalized[$productId] ?? 0) + $quantity;
        }

        return array_map(
            fn ($productId, $quantity) => ['product_id' => $productId, 'quantity' => $quantity],
            array_keys($normalized),
            array_values($normalized),
        );
    }

    private function assertContext(InventoryReservation $reservation): void
    {
        $membership = $this->context->membership();
        if (! $membership
            || (int) $reservation->tenant_id !== (int) $membership->tenant_id
            || (int) $reservation->company_id !== (int) $membership->company_id
            || (int) $reservation->branch_id !== (int) $membership->branch_id) {
            throw ValidationException::withMessages(['reservation' => 'Reservation is outside the active ERP context.']);
        }
    }

    private function isRequestIdConflict(QueryException $exception): bool
    {
        return str_contains(strtolower($exception->getMessage()), 'inventory_reservations_tenant_request_unique');
    }
}

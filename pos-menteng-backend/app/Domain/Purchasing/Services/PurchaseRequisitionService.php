<?php

namespace App\Domain\Purchasing\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Core\Services\DocumentNumberService;
use App\Domain\Identity\Models\Membership;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Purchasing\Models\PurchaseRequisition;
use App\Domain\Purchasing\Models\PurchaseRequisitionItem;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseRequisitionService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly DocumentNumberService $numbers,
        private readonly AuditService $audit,
    ) {}

    public function create(
        Warehouse $warehouse,
        array $items,
        ?string $neededBy = null,
        ?string $reason = null,
        ?string $notes = null,
    ): PurchaseRequisition {
        $membership = $this->context->membership();

        if (! $membership) {
            throw ValidationException::withMessages(['context' => 'No active ERP context.']);
        }

        if ((int) $warehouse->branch_id !== (int) $membership->branch_id) {
            throw ValidationException::withMessages(['warehouse_id' => 'Warehouse is outside the active branch.']);
        }

        $normalized = $this->normalizeItems($items, $membership->tenant_id);

        return DB::transaction(function () use ($membership, $warehouse, $normalized, $neededBy, $reason, $notes) {
            $requisition = PurchaseRequisition::create([
                'tenant_id' => $membership->tenant_id,
                'company_id' => $membership->company_id,
                'branch_id' => $membership->branch_id,
                'warehouse_id' => $warehouse->id,
                'requisition_number' => $this->numbers->next('purchase_requisition', 'PR'),
                'status' => 'draft',
                'needed_by' => $neededBy,
                'reason' => $reason,
                'requested_by' => auth()->id(),
                'request_id' => request()->attributes->get('request_id'),
                'notes' => $notes,
            ]);

            foreach ($normalized as $item) {
                PurchaseRequisitionItem::create([
                    'purchase_requisition_id' => $requisition->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'estimated_unit_cost' => $item['estimated_unit_cost'],
                    'notes' => $item['notes'],
                ]);
            }

            $requisition->load(['warehouse','items.product','requester']);
            $this->audit->record('created', 'purchase_requisition', $requisition, null, $requisition->toArray());

            return $requisition;
        });
    }

    public function submit(PurchaseRequisition $requisition): PurchaseRequisition
    {
        return DB::transaction(function () use ($requisition) {
            $this->assertContext($requisition);
            $row = PurchaseRequisition::query()->with('items')->lockForUpdate()->findOrFail($requisition->id);

            if ($row->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Only draft requisitions can be submitted.']);
            }

            if ($row->items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Requisition must contain at least one item.']);
            }

            $old = $row->only(['status']);
            $row->status = 'submitted';
            $row->submitted_by = auth()->id();
            $row->submitted_at = now();
            $row->save();

            $row->load(['warehouse','items.product','requester','submitter']);
            $this->audit->record('submitted', 'purchase_requisition', $row, $old, ['status'=>'submitted']);

            return $row;
        });
    }

    public function cancel(PurchaseRequisition $requisition): PurchaseRequisition
    {
        return DB::transaction(function () use ($requisition) {
            $this->assertContext($requisition);
            $row = PurchaseRequisition::query()->lockForUpdate()->findOrFail($requisition->id);

            if (! in_array($row->status, ['draft','submitted'], true)) {
                throw ValidationException::withMessages(['status' => 'Only draft or submitted requisitions can be cancelled.']);
            }

            $old = $row->only(['status']);
            $row->status = 'cancelled';
            $row->cancelled_by = auth()->id();
            $row->cancelled_at = now();
            $row->save();

            $row->load(['warehouse','items.product','requester','canceller']);
            $this->audit->record('cancelled', 'purchase_requisition', $row, $old, ['status'=>'cancelled']);

            return $row;
        });
    }

    private function normalizeItems(array $items, int $tenantId): array
    {
        $result = [];

        foreach ($items as $item) {
            $product = Product::findOrFail($item['product_id']);

            if ((int) $product->tenant_id !== $tenantId) {
                throw ValidationException::withMessages(['items' => 'One or more products are outside the active tenant.']);
            }

            $quantity = (float) $item['quantity'];
            if ($quantity <= 0) {
                throw ValidationException::withMessages(['items' => 'Requisition quantity must be greater than zero.']);
            }

            $id = (string) $product->id;

            if (! isset($result[$id])) {
                $result[$id] = [
                    'product_id' => $product->id,
                    'quantity' => 0,
                    'estimated_unit_cost' => 0,
                    'notes' => $item['notes'] ?? null,
                ];
            }

            $result[$id]['quantity'] += $quantity;

            if (isset($item['estimated_unit_cost'])) {
                $result[$id]['estimated_unit_cost'] = (float) $item['estimated_unit_cost'];
            }
        }

        return array_values($result);
    }

    private function assertContext(PurchaseRequisition $requisition): void
    {
        $membership = $this->context->membership();

        if (
            ! $membership
            || (int) $requisition->tenant_id !== (int) $membership->tenant_id
            || (int) $requisition->company_id !== (int) $membership->company_id
            || (int) $requisition->branch_id !== (int) $membership->branch_id
        ) {
            throw ValidationException::withMessages([
                'requisition' => 'Purchase requisition is outside the active ERP context.',
            ]);
        }
    }
}

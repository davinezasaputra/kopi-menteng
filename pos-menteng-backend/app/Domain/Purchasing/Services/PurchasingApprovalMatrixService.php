<?php

namespace App\Domain\Purchasing\Services;

use App\Domain\Identity\Models\Membership;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\PurchasingApprovalMatrixRule;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchasingApprovalMatrixService
{
    public function __construct(private readonly TenantContext $context)
    {
    }

    public function createRule(
        int $approverRoleId,
        float $minAmount,
        ?float $maxAmount = null,
        int $priority = 1,
        ?string $notes = null,
    ): PurchasingApprovalMatrixRule {
        $membership=$this->context->membership();
        if(!$membership){
            throw ValidationException::withMessages(['context'=>'No active ERP context.']);
        }

        if($minAmount < 0){
            throw ValidationException::withMessages(['min_amount'=>'Minimum amount cannot be negative.']);
        }
        if($maxAmount !== null && $maxAmount <= $minAmount){
            throw ValidationException::withMessages(['max_amount'=>'Maximum amount must be greater than minimum amount.']);
        }
        if($priority < 1){
            throw ValidationException::withMessages(['priority'=>'Priority must be at least 1.']);
        }

        $role = \App\Domain\Identity\Models\Role::query()
            ->where('tenant_id',$membership->tenant_id)
            ->find($approverRoleId);

        if(!$role){
            throw ValidationException::withMessages(['approver_role_id'=>'Approver role must belong to the active tenant.']);
        }

        $query=PurchasingApprovalMatrixRule::query()
            ->where('tenant_id',$membership->tenant_id)
            ->where('company_id',$membership->company_id)
            ->where('branch_id',$membership->branch_id)
            ->where('document_type','purchase_order')
            ->where('is_active',true);

        $overlap = $query
            ->where('min_amount', '<', $maxAmount ?? PHP_FLOAT_MAX)
            ->where(function ($q) use ($minAmount) {
                $q->whereNull('max_amount')
                  ->orWhere('max_amount', '>', $minAmount);
            })
            ->exists();

        if($overlap){
            throw ValidationException::withMessages(['range'=>'Approval amount range overlaps an existing active rule.']);
        }

        return PurchasingApprovalMatrixRule::create([
            'tenant_id'=>$membership->tenant_id,
            'company_id'=>$membership->company_id,
            'branch_id'=>$membership->branch_id,
            'approver_role_id'=>$approverRoleId,
            'document_type'=>'purchase_order',
            'min_amount'=>$minAmount,
            'max_amount'=>$maxAmount,
            'priority'=>$priority,
            'is_active'=>true,
            'notes'=>$notes,
        ]);
    }

    public function listRules()
    {
        $membership=$this->context->membership();
        if(!$membership){
            throw ValidationException::withMessages(['context'=>'No active ERP context.']);
        }

        return PurchasingApprovalMatrixRule::query()
            ->with('approverRole:id,name,code')
            ->where('tenant_id',$membership->tenant_id)
            ->where('company_id',$membership->company_id)
            ->where('branch_id',$membership->branch_id)
            ->where('document_type','purchase_order')
            ->orderBy('min_amount')
            ->orderBy('priority')
            ->get();
    }

    public function resolveForAmount(float $amount): PurchasingApprovalMatrixRule
    {
        $membership=$this->context->membership();

        return PurchasingApprovalMatrixRule::query()
            ->with('approverRole:id,name,code')
            ->where('tenant_id',$membership->tenant_id)
            ->where('company_id',$membership->company_id)
            ->where('branch_id',$membership->branch_id)
            ->where('document_type','purchase_order')
            ->where('is_active',true)
            ->where('min_amount','<=',$amount)
            ->where(function($q) use($amount){
                $q->whereNull('max_amount')->orWhere('max_amount','>',$amount);
            })
            ->orderByDesc('priority')
            ->orderBy('min_amount')
            ->firstOrFail();
    }

    public function assertCanApprove(PurchaseOrder $order): PurchasingApprovalMatrixRule
    {
        $membership=$this->context->membership();
        if(!$membership){
            throw ValidationException::withMessages(['context'=>'No active ERP context.']);
        }

        $rule=$this->resolveForAmount((float)$order->grand_total);

        if((int)$membership->role_id !== (int)$rule->approver_role_id){
            throw ValidationException::withMessages([
                'approver'=>"This PO requires role {$rule->approverRole->code} to approve."
            ]);
        }

        if((int)$order->created_by === (int)auth()->id()){
            throw ValidationException::withMessages([
                'approver'=>'The PO creator cannot approve their own purchase order.'
            ]);
        }

        return $rule;
    }

    public function reject(PurchaseOrder $order, string $reason): PurchaseOrder
    {
        $membership=$this->context->membership();

        if(!$membership){
            throw ValidationException::withMessages(['context'=>'No active ERP context.']);
        }

        $this->assertCanApprove($order);

        if($order->status !== 'submitted'){
            throw ValidationException::withMessages(['status'=>'Only submitted purchase orders can be rejected.']);
        }

        return DB::transaction(function() use($order,$reason){
            $row=PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);
            $row->status='rejected';
            $row->rejected_by=auth()->id();
            $row->rejected_at=now();
            $row->rejection_reason=$reason;
            $row->save();

            return $row->fresh(['supplier','warehouse','items.product']);
        });
    }
}

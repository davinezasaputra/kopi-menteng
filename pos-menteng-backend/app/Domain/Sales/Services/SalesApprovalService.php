<?php

namespace App\Domain\Sales\Services;

use App\Domain\Sales\Models\{SalesApprovalMatrixRule,SalesOrder};
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesApprovalService
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
    ): SalesApprovalMatrixRule {
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

        $role=\App\Domain\Identity\Models\Role::query()
            ->where('tenant_id',$membership->tenant_id)
            ->find($approverRoleId);

        if(!$role){
            throw ValidationException::withMessages(['approver_role_id'=>'Approver role must belong to the active tenant.']);
        }

        $overlap=SalesApprovalMatrixRule::query()
            ->where('tenant_id',$membership->tenant_id)
            ->where('company_id',$membership->company_id)
            ->where('branch_id',$membership->branch_id)
            ->where('is_active',true)
            ->where('min_amount','<',$maxAmount ?? PHP_FLOAT_MAX)
            ->where(function($q) use($minAmount){
                $q->whereNull('max_amount')
                  ->orWhere('max_amount','>',$minAmount);
            })
            ->exists();

        if($overlap){
            throw ValidationException::withMessages(['range'=>'Approval amount range overlaps an existing active rule.']);
        }

        return SalesApprovalMatrixRule::create([
            'tenant_id'=>$membership->tenant_id,
            'company_id'=>$membership->company_id,
            'branch_id'=>$membership->branch_id,
            'approver_role_id'=>$approverRoleId,
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

        return SalesApprovalMatrixRule::query()
            ->with('approverRole:id,name,code')
            ->where('tenant_id',$membership->tenant_id)
            ->where('company_id',$membership->company_id)
            ->where('branch_id',$membership->branch_id)
            ->where('is_active',true)
            ->orderBy('min_amount')
            ->orderBy('priority')
            ->get();
    }

    public function resolveForAmount(float $amount): SalesApprovalMatrixRule
    {
        $membership=$this->context->membership();

        $rule=SalesApprovalMatrixRule::query()
            ->with('approverRole:id,name,code')
            ->where('tenant_id',$membership->tenant_id)
            ->where('company_id',$membership->company_id)
            ->where('branch_id',$membership->branch_id)
            ->where('is_active',true)
            ->where('min_amount','<=',$amount)
            ->where(function($q) use($amount){
                $q->whereNull('max_amount')->orWhere('max_amount','>',$amount);
            })
            ->orderByDesc('priority')
            ->orderBy('min_amount')
            ->first();

        if(!$rule){
            throw ValidationException::withMessages([
                'approval'=>'No active sales approval rule matches this order amount.'
            ]);
        }

        return $rule;
    }

    public function assertCanApprove(SalesOrder $order): SalesApprovalMatrixRule
    {
        $membership=$this->context->membership();
        if(!$membership){
            throw ValidationException::withMessages(['context'=>'No active ERP context.']);
        }

        if($order->status!=='submitted'){
            throw ValidationException::withMessages(['status'=>'Only submitted sales orders can be approved.']);
        }

        if((int)$order->created_by === (int)auth()->id()){
            throw ValidationException::withMessages([
                'approver'=>'The sales order creator cannot approve their own sales order.'
            ]);
        }

        $rule=$this->resolveForAmount((float)$order->grand_total);

        if((int)$membership->role_id !== (int)$rule->approver_role_id){
            throw ValidationException::withMessages([
                'approver'=>"This sales order requires role {$rule->approverRole->code} to approve."
            ]);
        }

        return $rule;
    }

    public function approve(SalesOrder $order): SalesOrder
    {
        return DB::transaction(function() use($order){
            $this->assertContext($order);
            $rule=$this->assertCanApprove($order);

            $row=SalesOrder::query()->lockForUpdate()->findOrFail($order->id);

            if($row->status!=='submitted'){
                throw ValidationException::withMessages(['status'=>'Only submitted sales orders can be approved.']);
            }

            $old=$row->only(['status']);
            $row->status='approved';
            $row->approval_matrix_rule_id=$rule->id;
            $row->approved_by=auth()->id();
            $row->approved_at=now();
            $row->save();

            $row->load(['customer','warehouse','items.product']);
            app(\App\Domain\Audit\Services\AuditService::class)
                ->record('approved','sales_order',$row,$old,['status'=>'approved','approval_matrix_rule_id'=>$rule->id]);

            return $row;
        });
    }

    public function reject(SalesOrder $order,string $reason): SalesOrder
    {
        return DB::transaction(function() use($order,$reason){
            $this->assertContext($order);
            $rule=$this->assertCanApprove($order);

            $row=SalesOrder::query()->lockForUpdate()->findOrFail($order->id);
            if($row->status!=='submitted'){
                throw ValidationException::withMessages(['status'=>'Only submitted sales orders can be rejected.']);
            }

            $old=$row->only(['status']);
            $row->status='rejected';
            $row->approval_matrix_rule_id=$rule->id;
            $row->rejected_by=auth()->id();
            $row->rejected_at=now();
            $row->rejection_reason=$reason;
            $row->save();

            app(\App\Domain\Audit\Services\AuditService::class)
                ->record('rejected','sales_order',$row,$old,['status'=>'rejected','reason'=>$reason]);

            return $row->fresh(['customer','warehouse','items.product']);
        });
    }

    private function assertContext(SalesOrder $order): void
    {
        $membership=$this->context->membership();
        if(
            !$membership ||
            (int)$order->tenant_id !== (int)$membership->tenant_id ||
            (int)$order->company_id !== (int)$membership->company_id ||
            (int)$order->branch_id !== (int)$membership->branch_id
        ){
            throw ValidationException::withMessages(['order'=>'Sales order is outside the active ERP context.']);
        }
    }
}

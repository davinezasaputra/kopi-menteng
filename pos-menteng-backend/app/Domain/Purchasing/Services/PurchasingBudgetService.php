<?php

namespace App\Domain\Purchasing\Services;

use App\Domain\Purchasing\Models\PurchasingBudget;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchasingBudgetService
{
    public function __construct(private readonly TenantContext $context)
    {
    }

    public function createOrUpdate(int $year,float $allocatedAmount,?string $notes=null): PurchasingBudget
    {
        $membership=$this->context->membership();
        if(!$membership){
            throw ValidationException::withMessages(['context'=>'No active ERP context.']);
        }

        if($year < 2000 || $year > 2100){
            throw ValidationException::withMessages(['budget_year'=>'Invalid budget year.']);
        }

        if($allocatedAmount < 0){
            throw ValidationException::withMessages(['allocated_amount'=>'Allocated budget cannot be negative.']);
        }

        return PurchasingBudget::updateOrCreate(
            [
                'tenant_id'=>$membership->tenant_id,
                'company_id'=>$membership->company_id,
                'branch_id'=>$membership->branch_id,
                'budget_year'=>$year,
            ],
            [
                'allocated_amount'=>$allocatedAmount,
                'is_active'=>true,
                'created_by'=>auth()->id(),
                'updated_by'=>auth()->id(),
                'notes'=>$notes,
            ],
        )->fresh();
    }

    public function summary(?int $year=null): PurchasingBudget
    {
        $membership=$this->context->membership();
        if(!$membership){
            throw ValidationException::withMessages(['context'=>'No active ERP context.']);
        }

        $year ??= now()->year;

        return PurchasingBudget::query()
            ->where('tenant_id',$membership->tenant_id)
            ->where('company_id',$membership->company_id)
            ->where('branch_id',$membership->branch_id)
            ->where('budget_year',$year)
            ->firstOrFail();
    }

    public function canCommit(PurchasingBudget $budget,float $amount): bool
    {
        $available=(float)$budget->allocated_amount
            -(float)$budget->committed_amount
            -(float)$budget->spent_amount;

        return ($available - $amount) >= -0.009;
    }

    public function commitForApprovedPurchase(PurchasingBudget $budget,float $amount): void
    {
        if(!$this->canCommit($budget,$amount)){
            $available=max(
                0,
                (float)$budget->allocated_amount
                -(float)$budget->committed_amount
                -(float)$budget->spent_amount
            );

            throw ValidationException::withMessages([
                'budget'=>"Insufficient purchasing budget. Available: {$available}. Requested: {$amount}."
            ]);
        }

        $budget->committed_amount=(float)$budget->committed_amount+$amount;
        $budget->updated_by=auth()->id();
        $budget->save();
    }
}

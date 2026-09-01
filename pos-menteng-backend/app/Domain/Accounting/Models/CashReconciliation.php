<?php
namespace App\Domain\Accounting\Models;
use App\Domain\Organization\Models\{Tenant,Company,Branch};
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CashReconciliation extends Model{
 protected $fillable=['tenant_id','company_id','branch_id','reconciliation_date','account_code','book_balance','statement_balance','adjustment_amount','difference','status','created_by','approved_by','approved_at','notes'];
 protected function casts():array{return ['reconciliation_date'=>'date','book_balance'=>'decimal:2','statement_balance'=>'decimal:2','adjustment_amount'=>'decimal:2','difference'=>'decimal:2','approved_at'=>'datetime'];}
 public function tenant():BelongsTo{return $this->belongsTo(Tenant::class);}
 public function company():BelongsTo{return $this->belongsTo(Company::class);}
 public function branch():BelongsTo{return $this->belongsTo(Branch::class);}
 public function creator():BelongsTo{return $this->belongsTo(User::class,'created_by');}
 public function approver():BelongsTo{return $this->belongsTo(User::class,'approved_by');}
}

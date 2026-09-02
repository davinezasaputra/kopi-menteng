<?php
namespace App\Domain\Accounting\Models;
use App\Domain\Organization\Models\{Tenant,Company};
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class FiscalPeriod extends Model{
 protected $fillable=['tenant_id','company_id','year','month','starts_on','ends_on','status','closed_by','closed_at','notes'];
 protected function casts():array{return ['starts_on'=>'date','ends_on'=>'date','closed_at'=>'datetime'];}
 public function tenant():BelongsTo{return $this->belongsTo(Tenant::class);}
 public function company():BelongsTo{return $this->belongsTo(Company::class);}
 public function closer():BelongsTo{return $this->belongsTo(User::class,'closed_by');}
}

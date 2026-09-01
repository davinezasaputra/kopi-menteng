<?php
namespace App\Domain\Sales\Models;
use App\Domain\Organization\Models\{Branch,Company,Tenant,Warehouse};
use App\Models\{Customer,User};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany};
use Illuminate\Support\Str;
class SalesReturn extends Model{
 protected $table='sales_returns';public $incrementing=false;protected $keyType='string';
 protected $fillable=['id','tenant_id','company_id','branch_id','warehouse_id','sales_invoice_id','customer_id','customer_name_snapshot','return_number','return_date','status','total_amount','created_by','request_id','reason'];
 protected static function booted():void{static::creating(fn(self $r)=>$r->id??=(string)Str::uuid());}
 protected function casts():array{return ['return_date'=>'date','total_amount'=>'decimal:2'];}
 public function tenant():BelongsTo{return $this->belongsTo(Tenant::class);} public function company():BelongsTo{return $this->belongsTo(Company::class);} public function branch():BelongsTo{return $this->belongsTo(Branch::class);} public function warehouse():BelongsTo{return $this->belongsTo(Warehouse::class);} public function invoice():BelongsTo{return $this->belongsTo(SalesInvoice::class,'sales_invoice_id');} public function customer():BelongsTo{return $this->belongsTo(Customer::class);} public function creator():BelongsTo{return $this->belongsTo(User::class,'created_by');} public function items():HasMany{return $this->hasMany(SalesReturnItem::class);}
}

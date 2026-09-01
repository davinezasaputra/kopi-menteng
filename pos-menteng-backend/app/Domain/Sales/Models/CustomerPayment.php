<?php
namespace App\Domain\Sales\Models;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
class CustomerPayment extends Model{
 protected $table='customer_payments'; public $incrementing=false; protected $keyType='string';
 protected $fillable=['id','tenant_id','company_id','branch_id','location_id','sales_invoice_id','customer_id','customer_name_snapshot','payment_number','payment_date','amount','method','reference','paid_by','request_id','notes'];
 protected static function booted():void{static::creating(fn(self $r)=>$r->id ??= (string)Str::uuid());}
 protected function casts():array{return ['payment_date'=>'date','amount'=>'decimal:2'];}
 public function invoice():BelongsTo{return $this->belongsTo(SalesInvoice::class,'sales_invoice_id');}
 public function customer():BelongsTo{return $this->belongsTo(Customer::class);}
 public function payer():BelongsTo{return $this->belongsTo(User::class,'paid_by');}
}

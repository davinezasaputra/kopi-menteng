<?php
namespace App\Domain\Sales\Models;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
class SalesReturnItem extends Model{
 protected $table='sales_return_items';public $incrementing=false;protected $keyType='string';
 protected $fillable=['id','sales_return_id','product_id','quantity','unit_price','line_total'];
 protected static function booted():void{static::creating(fn(self $r)=>$r->id??=(string)Str::uuid());}
 protected function casts():array{return ['quantity'=>'decimal:4','unit_price'=>'decimal:4','line_total'=>'decimal:2'];}
 public function salesReturn():BelongsTo{return $this->belongsTo(SalesReturn::class,'sales_return_id');}
 public function product():BelongsTo{return $this->belongsTo(Product::class);}
}

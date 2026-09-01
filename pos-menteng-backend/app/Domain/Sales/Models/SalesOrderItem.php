<?php

namespace App\Domain\Sales\Models;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SalesOrderItem extends Model
{
    protected $table='sales_order_items';
    public $incrementing=false;
    protected $keyType='string';

    protected $fillable=[
        'id','sales_order_id','product_id','quantity','unit_price',
        'discount_amount','tax_amount','line_total','notes',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $item) {
            $item->id ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'quantity'=>'decimal:4',
            'unit_price'=>'decimal:4',
            'discount_amount'=>'decimal:2',
            'tax_amount'=>'decimal:2',
            'line_total'=>'decimal:2',
        ];
    }

    public function salesOrder(): BelongsTo { return $this->belongsTo(SalesOrder::class,'sales_order_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}

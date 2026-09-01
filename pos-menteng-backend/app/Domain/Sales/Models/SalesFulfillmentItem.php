<?php

namespace App\Domain\Sales\Models;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SalesFulfillmentItem extends Model
{
    protected $table='sales_fulfillment_items';
    public $incrementing=false;
    protected $keyType='string';

    protected $fillable=[
        'id','sales_fulfillment_id','product_id','ordered_quantity',
        'reserved_quantity','picked_quantity','packed_quantity',
    ];

    protected static function booted(): void
    {
        static::creating(function(self $row){ $row->id ??= (string)Str::uuid(); });
    }

    protected function casts(): array
    {
        return [
            'ordered_quantity'=>'decimal:4',
            'reserved_quantity'=>'decimal:4',
            'picked_quantity'=>'decimal:4',
            'packed_quantity'=>'decimal:4',
        ];
    }

    public function fulfillment(): BelongsTo { return $this->belongsTo(SalesFulfillment::class,'sales_fulfillment_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}

<?php

namespace App\Domain\Inventory\Models;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryReservationItem extends Model
{
    protected $fillable = ['inventory_reservation_id','product_id','quantity','fulfilled_quantity'];

    protected $casts = [
        'quantity' => 'decimal:4',
        'fulfilled_quantity' => 'decimal:4',
    ];

    public function reservation(): BelongsTo { return $this->belongsTo(InventoryReservation::class, 'inventory_reservation_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory, HasUuids;
    

    protected $fillable = [
        'user_id',
        'shift_id',
        'subtotal',
        'tax',
        'discount',
        'total',
        'payment_method',
        'status',
        'invoice_number',
    ];
    
    public function users(){
        return $this->belongsTo(User::class);
    }
    public function shift(){
        return $this->belongsTo(Shift::class);
    }
    public function items(){
        return $this->hasMany(OrderItem::class);
    }
}

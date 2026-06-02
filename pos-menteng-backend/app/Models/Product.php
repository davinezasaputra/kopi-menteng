<?php

namespace App\Models;

use App\Models\Category;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'category_id',
        'name',
        'description',
        'price',
        'stock',
        'image',
        'is_active',
    ];

    protected $cast = [
        'price'=>'decimal:2',
        'is_active'=>'boolean',
    ];

    public function category(){
        return $this->belongsTo(Category::class);
    }
}

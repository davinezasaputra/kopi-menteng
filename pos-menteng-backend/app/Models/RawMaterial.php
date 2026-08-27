<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RawMaterial extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];
    protected $casts = [
        'is_requested' => 'boolean',
    ];
}

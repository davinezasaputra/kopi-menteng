<?php

namespace App\Domain\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Warehouse extends Model
{
    protected $fillable = ['branch_id','code','name','type','is_default','status'];
    protected function casts(): array { return ['is_default'=>'boolean']; }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
}

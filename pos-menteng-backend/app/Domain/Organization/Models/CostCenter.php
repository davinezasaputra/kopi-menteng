<?php

namespace App\Domain\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostCenter extends Model
{
    protected $fillable = ['company_id','code','name','description','status'];
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceSetting extends Model
{
    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'clock_in_time',
        'clock_in_grace_minutes',
        'clock_out_time',
        'clock_out_grace_minutes',
        'auto_absence_enabled',
    ];

    protected $casts = [
        'clock_in_grace_minutes' => 'integer',
        'clock_out_grace_minutes' => 'integer',
        'auto_absence_enabled' => 'boolean',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(\App\Domain\Organization\Models\Tenant::class); }
    public function company(): BelongsTo { return $this->belongsTo(\App\Domain\Organization\Models\Company::class); }
    public function branch(): BelongsTo { return $this->belongsTo(\App\Domain\Organization\Models\Branch::class); }
}

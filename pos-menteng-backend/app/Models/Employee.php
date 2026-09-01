<?php

namespace App\Models;

use App\Domain\Organization\Models\Location;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $guarded = [];
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'company_id',
        'branch_id',
        'location_id',
        'name',
        'tanggal_lahir',
        'WA',
        'position',
        'join_date',
        'base_sallary',
        'status',
    ];

    public function location(): BelongsTo { return $this->belongsTo(Location::class); }
    public function payrolls(): HasMany { return $this->hasMany(Payroll::class); }
    public function leaves(): HasMany { return $this->hasMany(Leave::class); }
    public function attendances(): HasMany { return $this->hasMany(Attendance::class); }
}

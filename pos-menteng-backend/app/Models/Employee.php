<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $guarded = [];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'tanggal_lahir',
        'WA',
        'position',
        'join_date',
        'base_sallary',
        'status',
    ];

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

}

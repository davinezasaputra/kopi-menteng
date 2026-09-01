<?php

namespace App\Models;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Location;
use App\Domain\Organization\Models\Tenant;
use App\Domain\Organization\Models\Warehouse;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id', 'company_id', 'branch_id', 'location_id', 'warehouse_id', 'user_id',
        'start_time', 'end_time', 'starting_cash', 'expected_ending_cash', 'actual_ending_cash', 'status',
    ];

    protected $casts = [
        'start_time' => 'datetime', 'end_time' => 'datetime',
        'starting_cash' => 'decimal:2', 'expected_ending_cash' => 'decimal:2', 'actual_ending_cash' => 'decimal:2',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function company() { return $this->belongsTo(Company::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function location() { return $this->belongsTo(Location::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
}

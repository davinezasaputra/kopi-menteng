<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Tenant;
use App\Domain\Organization\Models\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryReservation extends Model
{
    protected $fillable = [
        'tenant_id','company_id','branch_id','warehouse_id',
        'reservation_number','reference_type','reference_id','status',
        'expires_at','created_by','released_by','released_at',
        'fulfilled_by','fulfilled_at','request_id','notes',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'released_at' => 'datetime',
        'fulfilled_at' => 'datetime',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function releaser(): BelongsTo { return $this->belongsTo(User::class, 'released_by'); }
    public function fulfiller(): BelongsTo { return $this->belongsTo(User::class, 'fulfilled_by'); }
    public function items(): HasMany { return $this->hasMany(InventoryReservationItem::class); }
}
<?php

namespace App\Domain\Sales\Models;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Tenant;
use App\Domain\Organization\Models\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SalesFulfillment extends Model
{
    protected $table='sales_fulfillments';
    public $incrementing=false;
    protected $keyType='string';

    protected $fillable=[
        'id','tenant_id','company_id','branch_id','warehouse_id','sales_order_id',
        'fulfillment_number','status','created_by','picked_by','picked_at',
        'packed_by','packed_at','notes',
    ];

    protected static function booted(): void
    {
        static::creating(function(self $row){ $row->id ??= (string)Str::uuid(); });
    }

    protected function casts(): array
    {
        return ['picked_at'=>'datetime','packed_at'=>'datetime'];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function salesOrder(): BelongsTo { return $this->belongsTo(SalesOrder::class,'sales_order_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class,'created_by'); }
    public function picker(): BelongsTo { return $this->belongsTo(User::class,'picked_by'); }
    public function packer(): BelongsTo { return $this->belongsTo(User::class,'packed_by'); }
    public function items(): HasMany { return $this->hasMany(SalesFulfillmentItem::class); }
}

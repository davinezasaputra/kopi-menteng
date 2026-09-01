<?php

namespace App\Domain\Sales\Models;

use App\Domain\Inventory\Models\InventoryReservation;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Tenant;
use App\Domain\Organization\Models\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SalesShipment extends Model
{
    protected $table='sales_shipments';
    public $incrementing=false;
    protected $keyType='string';

    protected $fillable=[
        'id','tenant_id','company_id','branch_id','warehouse_id','sales_order_id',
        'sales_fulfillment_id','inventory_reservation_id','shipment_number','shipment_date',
        'status','carrier_name','tracking_number','shipped_by','shipped_at','request_id','notes',
    ];

    protected static function booted(): void
    {
        static::creating(fn(self $row)=>$row->id ??= (string) Str::uuid());
    }

    protected function casts(): array
    {
        return ['shipment_date'=>'date','shipped_at'=>'datetime'];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function salesOrder(): BelongsTo { return $this->belongsTo(SalesOrder::class,'sales_order_id'); }
    public function fulfillment(): BelongsTo { return $this->belongsTo(SalesFulfillment::class,'sales_fulfillment_id'); }
    public function inventoryReservation(): BelongsTo { return $this->belongsTo(InventoryReservation::class,'inventory_reservation_id'); }
    public function shipper(): BelongsTo { return $this->belongsTo(User::class,'shipped_by'); }
}

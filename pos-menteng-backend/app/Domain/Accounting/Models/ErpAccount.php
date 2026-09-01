<?php

namespace App\Domain\Accounting\Models;

use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ErpAccount extends Model
{
    protected $fillable = [
        'tenant_id','company_id','code','name','type','normal_balance',
        'parent_id','is_postable','is_active',
    ];

    protected function casts(): array
    {
        return ['is_postable'=>'boolean','is_active'=>'boolean'];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id'); }
    public function journalLines(): HasMany { return $this->hasMany(ErpJournalLine::class, 'account_id'); }
}

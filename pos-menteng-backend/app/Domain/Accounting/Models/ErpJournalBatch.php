<?php

namespace App\Domain\Accounting\Models;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ErpJournalBatch extends Model
{
    protected $fillable = [
        'tenant_id','company_id','branch_id','journal_number','journal_date',
        'status','source_type','source_id','description','total_debit','total_credit',
        'created_by','request_id',
    ];

    protected function casts(): array
    {
        return ['journal_date'=>'date','total_debit'=>'decimal:2','total_credit'=>'decimal:2'];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function lines(): HasMany { return $this->hasMany(ErpJournalLine::class, 'journal_batch_id'); }
}

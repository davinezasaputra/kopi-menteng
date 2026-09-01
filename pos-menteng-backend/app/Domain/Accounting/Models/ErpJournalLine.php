<?php

namespace App\Domain\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErpJournalLine extends Model
{
    protected $fillable = ['journal_batch_id','account_id','debit','credit','description'];

    protected function casts(): array
    {
        return ['debit'=>'decimal:2','credit'=>'decimal:2'];
    }

    public function batch(): BelongsTo { return $this->belongsTo(ErpJournalBatch::class, 'journal_batch_id'); }
    public function account(): BelongsTo { return $this->belongsTo(ErpAccount::class, 'account_id'); }
}

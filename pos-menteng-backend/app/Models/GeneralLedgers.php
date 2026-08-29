<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeneralLedger extends Model
{
    protected $fillable = [
        'transaction_date', 'description', 'account_category', 
        'debit', 'credit', 'reference_type', 'reference_id'
    ];

    // Membuka jalan pelacakan data ke tabel manapun yang menjadi sumber transaksi
    public function reference()
    {
        return $this->morphTo();
    }
}

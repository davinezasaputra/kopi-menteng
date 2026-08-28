<?php

namespace App\Models;

use App\Models\JournalEntry;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $guarded = [];

    public function journalEntries()
    {
        return $this->hasMany(JournalEntry::class);
    }
}


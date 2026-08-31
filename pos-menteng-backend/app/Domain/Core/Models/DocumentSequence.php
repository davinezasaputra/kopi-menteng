<?php

namespace App\Domain\Core\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentSequence extends Model
{
    protected $fillable = ['tenant_id','company_id','branch_id','document_type','prefix','period','next_number','padding'];
    protected function casts(): array { return ['next_number'=>'integer','padding'=>'integer']; }
}

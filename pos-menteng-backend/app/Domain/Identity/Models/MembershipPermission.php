<?php

namespace App\Domain\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipPermission extends Model
{
    protected $fillable = ['membership_id', 'permission_id'];

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }
}

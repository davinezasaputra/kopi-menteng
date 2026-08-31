<?php

namespace App\Domain\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $fillable = ['module','resource','action','name','description'];
    public function roles(): BelongsToMany { return $this->belongsToMany(Role::class, 'role_permissions'); }
}

<?php

namespace App\Domain\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = ['tenant_id','name','code','description','is_system'];
    protected function casts(): array { return ['is_system'=>'boolean']; }
    public function tenant(): BelongsTo { return $this->belongsTo(\App\Domain\Organization\Models\Tenant::class); }
    public function permissions(): BelongsToMany { return $this->belongsToMany(Permission::class, 'role_permissions'); }
    public function memberships(): HasMany { return $this->hasMany(Membership::class); }
}

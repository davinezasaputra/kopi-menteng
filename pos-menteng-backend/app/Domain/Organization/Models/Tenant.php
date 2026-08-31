<?php

namespace App\Domain\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = ['name', 'code', 'slug', 'status', 'timezone', 'currency', 'settings'];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    public function companies(): HasMany { return $this->hasMany(Company::class); }
    public function memberships(): HasMany { return $this->hasMany(\App\Domain\Identity\Models\Membership::class); }
}

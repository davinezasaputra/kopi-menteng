<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\ChecksFoundationPolicy;

class UserPolicy
{
    use ChecksFoundationPolicy;
    public function viewAny(User $user): bool { return $this->allows($user, 'users.user.view'); }
    public function view(User $user, User $model): bool { return $this->allows($user, 'users.user.view') && $model->memberships()->where('tenant_id', app(\App\Support\Tenancy\TenantContext::class)->tenantId())->exists(); }
    public function create(User $user): bool { return $this->allows($user, 'users.user.create'); }
    public function update(User $user, User $model): bool { return $this->view($user, $model) && $this->allows($user, 'users.user.update'); }
    public function delete(User $user, User $model): bool { return (int)$user->id !== (int)$model->id && $this->view($user, $model) && $this->allows($user, 'users.user.delete'); }
}

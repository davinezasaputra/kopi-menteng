<?php

namespace App\Policies;

use App\Domain\Organization\Models\Department;
use App\Models\User;
use App\Policies\Concerns\ChecksFoundationPolicy;

class DepartmentPolicy
{
    use ChecksFoundationPolicy;

    public function viewAny(User $user): bool { return $this->allows($user, 'organization.branch.view'); }
    public function view(User $user, Department $model): bool { return $this->allows($user, 'organization.branch.view', null, $model->company_id); }
    public function create(User $user): bool { return $this->allows($user, 'organization.branch.manage'); }
    public function update(User $user, Department $model): bool { return $this->view($user, $model) && $this->allows($user, 'organization.branch.manage'); }
    public function delete(User $user, Department $model): bool { return $this->update($user, $model); }
}

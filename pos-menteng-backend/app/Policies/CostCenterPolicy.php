<?php

namespace App\Policies;

use App\Domain\Organization\Models\CostCenter;
use App\Models\User;
use App\Policies\Concerns\ChecksFoundationPolicy;

class CostCenterPolicy
{
    use ChecksFoundationPolicy;

    public function viewAny(User $user): bool { return $this->allows($user, 'organization.branch.view'); }
    public function view(User $user, CostCenter $model): bool { return $this->allows($user, 'organization.branch.view', null, $model->company_id); }
    public function create(User $user): bool { return $this->allows($user, 'organization.branch.manage'); }
    public function update(User $user, CostCenter $model): bool { return $this->view($user, $model) && $this->allows($user, 'organization.branch.manage'); }
    public function delete(User $user, CostCenter $model): bool { return $this->update($user, $model); }
}

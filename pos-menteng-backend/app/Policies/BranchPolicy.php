<?php

namespace App\Policies;

use App\Models\User;
use App\Domain\Organization\Models\Branch;
use App\Policies\Concerns\ChecksFoundationPolicy;

class BranchPolicy
{
    use ChecksFoundationPolicy;
    public function viewAny(User $user): bool { return $this->allows($user,'organization.branch.view'); }
    public function view(User $user, Branch $model): bool { $company=$model->company; return $company && $this->allows($user,'organization.branch.view',$company->tenant_id,$company->id,$model->id); }
    public function create(User $user): bool { return $this->allows($user,'organization.branch.manage'); }
    public function update(User $user, Branch $model): bool { return $this->view($user,$model) && $this->allows($user,'organization.branch.manage'); }
    public function delete(User $user, Branch $model): bool { return $this->update($user,$model); }
}

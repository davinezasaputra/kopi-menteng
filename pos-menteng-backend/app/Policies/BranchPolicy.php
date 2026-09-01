<?php
namespace App\Policies;
use App\Models\User;
use App\Domain\Organization\Models\Branch;
use App\Policies\Concerns\ChecksFoundationPolicy;
class BranchPolicy { use ChecksFoundationPolicy; public function viewAny(User $u):bool{return $this->allows($u,'organization.branch.view');} public function view(User $u,Branch $m):bool{return $this->allows($u,'organization.branch.view',null,$m->company_id ? $m->company()->value('id') : null,$m->id);} public function create(User $u):bool{return $this->allows($u,'organization.branch.manage');} public function update(User $u,Branch $m):bool{return $this->view($u,$m)&&$this->allows($u,'organization.branch.manage');} public function delete(User $u,Branch $m):bool{return $this->update($u,$m);} }

<?php

namespace App\Policies;
use App\Models\User;
use App\Domain\Organization\Models\Company;
use App\Policies\Concerns\ChecksFoundationPolicy;
class CompanyPolicy { use ChecksFoundationPolicy; public function viewAny(User $u): bool{return $this->allows($u,'organization.branch.view');} public function view(User $u,Company $m): bool{return $this->allows($u,'organization.branch.view',$m->tenant_id,$m->id);} public function create(User $u):bool{return $this->allows($u,'organization.branch.manage');} public function update(User $u,Company $m):bool{return $this->view($u,$m)&&$this->allows($u,'organization.branch.manage',$m->tenant_id,$m->id);} public function delete(User $u,Company $m):bool{return $this->update($u,$m);} }

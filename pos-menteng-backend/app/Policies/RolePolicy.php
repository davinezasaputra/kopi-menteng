<?php
namespace App\Policies;
use App\Models\User;
use App\Domain\Identity\Models\Role;
use App\Policies\Concerns\ChecksFoundationPolicy;
class RolePolicy { use ChecksFoundationPolicy; public function viewAny(User $u):bool{return $this->allows($u,'rbac.role.view');} public function view(User $u,Role $m):bool{return $this->allows($u,'rbac.role.view',$m->tenant_id);} public function create(User $u):bool{return $this->allows($u,'rbac.role.manage');} public function update(User $u,Role $m):bool{return $this->view($u,$m)&&$this->allows($u,'rbac.role.manage',$m->tenant_id);} public function delete(User $u,Role $m):bool{return !$m->is_system && $this->update($u,$m);} }

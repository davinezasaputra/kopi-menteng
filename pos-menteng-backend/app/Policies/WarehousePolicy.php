<?php
namespace App\Policies;
use App\Models\User;
use App\Domain\Organization\Models\Warehouse;
use App\Policies\Concerns\ChecksFoundationPolicy;
class WarehousePolicy { use ChecksFoundationPolicy; public function viewAny(User $u):bool{return $this->allows($u,'inventory.stock.view');} public function view(User $u,Warehouse $m):bool{return $this->allows($u,'inventory.stock.view');} public function create(User $u):bool{return $this->allows($u,'organization.branch.manage');} public function update(User $u,Warehouse $m):bool{return $this->view($u,$m)&&$this->allows($u,'organization.branch.manage');} public function delete(User $u,Warehouse $m):bool{return $this->update($u,$m);} }

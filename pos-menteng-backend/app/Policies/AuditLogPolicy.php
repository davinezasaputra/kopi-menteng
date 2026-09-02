<?php
namespace App\Policies;
use App\Models\User;
use App\Domain\Audit\Models\AuditLog;
use App\Policies\Concerns\ChecksFoundationPolicy;
class AuditLogPolicy { use ChecksFoundationPolicy; public function viewAny(User $u):bool{return $this->allows($u,'audit.audit_log.view');} public function view(User $u,AuditLog $m):bool{return $this->allows($u,'audit.audit_log.view',$m->tenant_id,$m->company_id,$m->branch_id);} public function create(User $u):bool{return false;} public function update(User $u,AuditLog $m):bool{return false;} public function delete(User $u,AuditLog $m):bool{return false;} }

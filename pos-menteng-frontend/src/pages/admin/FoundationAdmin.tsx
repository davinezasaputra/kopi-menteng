import { useEffect, useState } from 'react';
import api from '../../core/api/client';
import PermissionGate from '../../components/PermissionGate';
import EnterpriseAdminLayout from '../../layouts/EnterpriseAdminLayout';

type Role = { id:number; name:string; code:string; permissions?: {name:string}[] };
type Permission = { id:number; module:string; resource:string; action:string; name:string };
type Audit = { id:number; event:string; module:string; entity_type?:string; entity_id?:string; created_at:string };

export default function FoundationAdmin(){
  const [roles,setRoles]=useState<Role[]>([]); const [permissions,setPermissions]=useState<Permission[]>([]); const [audits,setAudits]=useState<Audit[]>([]); const [loading,setLoading]=useState(true);
  useEffect(()=>{Promise.all([api.get('/v1/roles'),api.get('/v1/permissions'),api.get('/v1/audit-logs?per_page=10')]).then(([r,p,a])=>{setRoles(r.data?.data||[]);setPermissions(p.data?.data||[]);setAudits(a.data?.data?.data||[]);}).finally(()=>setLoading(false));},[]);
  return <EnterpriseAdminLayout>
    <main style={{padding:24,display:'grid',gap:24}}>
      <h1>ERP Foundation Administration</h1>
      <PermissionGate permission="rbac.role.view" fallback={<p>Access denied.</p>}>
        {loading?<p>Loading...</p>:<>
          <section><h2>Roles</h2><table><thead><tr><th>Name</th><th>Code</th><th>Permissions</th></tr></thead><tbody>{roles.map(r=><tr key={r.id}><td>{r.name}</td><td>{r.code}</td><td>{r.permissions?.length||0}</td></tr>)}</tbody></table></section>
          <section><h2>Permission Catalog</h2><p>{permissions.length} permissions</p></section>
          <section><h2>Recent Audit</h2><ul>{audits.map(a=><li key={a.id}>{a.event} · {a.module} · {a.entity_type||''} · {a.created_at}</li>)}</ul></section>
        </>}
      </PermissionGate>
    </main>
  </EnterpriseAdminLayout>;
}

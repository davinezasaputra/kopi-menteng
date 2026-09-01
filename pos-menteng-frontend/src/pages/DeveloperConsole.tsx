import { useEffect, useMemo, useState } from 'react';
import AdminSidebar from '../components/AdminSidebar';
import api from '../core/api/client';
import { isDeveloper } from '../core/auth/permissions';
import toast from 'react-hot-toast';

type Branch = { id: number; code: string; name: string; status: string };
type Company = { id: number; code: string; name: string; status: string; branches: Branch[] };
type License = { id?: number; plan_code: string; plan_name: string; features: string[]; max_users: number | null; max_branches: number | null; starts_at?: string | null; expires_at?: string | null; status?: string; auto_renew?: boolean; notes?: string | null };
type Tenant = { id: number; code: string; name: string; status: string; timezone?: string; currency?: string; company_count: number; branch_count: number; license?: License | null; companies: Company[] };
type Admin = { membership_id: number; user_id: number; name: string; email: string; status: string; is_primary: boolean; company_id: number; company_name: string; branch_id: number; branch_name: string; role: string; permissions: string[] };
type Permission = { id: number; module: string; resource: string; action: string; name: string; description?: string };
type Plan = { code: string; name: string; features: string[]; max_users: number | null; max_branches: number | null };

const featureLabels: Record<string, string> = { pos: 'POS', inventory: 'Inventory', purchasing: 'Purchasing', sales: 'Sales', accounting: 'Accounting', hrm: 'HRM', administration: 'Administration', audit: 'Audit', organization: 'Organization' };

function Card({ title, description, children }: { title: string; description?: string; children: React.ReactNode }) {
  return <section className="rounded-3xl border border-stone-200 bg-white p-5 shadow-sm"><h2 className="text-base font-black text-stone-900">{title}</h2>{description && <p className="mt-1 text-sm text-stone-500">{description}</p>}<div className="mt-4">{children}</div></section>;
}
function Select({ label, value, onChange, children, disabled = false }: { label: string; value: string; onChange: (value: string) => void; children: React.ReactNode; disabled?: boolean }) {
  return <label className="block"><span className="mb-1 block text-xs font-bold text-stone-500">{label}</span><select disabled={disabled} value={value} onChange={e => onChange(e.target.value)} className="w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm disabled:bg-stone-100"><option value="">Pilih {label.toLowerCase()}...</option>{children}</select></label>;
}
function Field({ label, value, onChange, type = 'text' }: { label: string; value: string; onChange: (value: string) => void; type?: string }) {
  return <label className="block"><span className="mb-1 block text-xs font-bold text-stone-500">{label}</span><input type={type} value={value} onChange={e => onChange(e.target.value)} className="w-full rounded-xl border border-stone-200 px-3 py-2.5 text-sm" /></label>;
}

export default function DeveloperConsole() {
  const developer = isDeveloper();
  const [tenants, setTenants] = useState<Tenant[]>([]);
  const [plans, setPlans] = useState<Plan[]>([]);
  const [permissions, setPermissions] = useState<Permission[]>([]);
  const [tenantId, setTenantId] = useState('');
  const [companyId, setCompanyId] = useState('');
  const [branchId, setBranchId] = useState('');
  const [admins, setAdmins] = useState<Admin[]>([]);
  const [selectedAdmin, setSelectedAdmin] = useState<Admin | null>(null);
  const [adminForm, setAdminForm] = useState({ name: '', email: '', status: 'active', company_id: '', branch_id: '', permissions: [] as string[] });
  const [licenseForm, setLicenseForm] = useState({ plan_code: '', features: [] as string[], expires_at: '', max_users: '', max_branches: '', auto_renew: false, notes: '' });
  const [createForm, setCreateForm] = useState({ name: '', email: '', password: '' });
  const selectedTenant = tenants.find(t => String(t.id) === tenantId) ?? null;
  const companies = selectedTenant?.companies ?? [];
  const selectedCompany = companies.find(c => String(c.id) === companyId) ?? null;
  const branches = selectedCompany?.branches ?? [];

  const licensedPermissionNames = useMemo(() => {
    const features = new Set(licenseForm.features);
    return permissions.filter(permission => {
      const prefix = permission.name.split('.')[0];
      const feature = prefix === 'hr' ? 'hrm' : prefix === 'users' || prefix === 'rbac' ? 'administration' : prefix;
      return features.has(feature);
    });
  }, [licenseForm.features, permissions]);

  const loadAll = async () => {
    try {
      const [tenantResponse, planResponse, permissionResponse] = await Promise.all([
        api.get('/v1/developer/tenants'), api.get('/v1/developer/license-catalog'), api.get('/v1/permissions'),
      ]);
      setTenants(tenantResponse.data?.data ?? []); setPlans(planResponse.data?.data ?? []); setPermissions(permissionResponse.data?.data ?? []);
    } catch { toast.error('Data Developer Console gagal dimuat.'); }
  };
  const loadAdmins = async (id: string) => { if (!id) return setAdmins([]); try { const response = await api.get(`/v1/developer/tenants/${id}/admins`); setAdmins(response.data?.data ?? []); } catch { toast.error('Akun tenant admin gagal dimuat.'); } };
  useEffect(() => { if (developer) void loadAll(); }, [developer]);
  useEffect(() => { void loadAdmins(tenantId); const current = tenants.find(t => String(t.id) === tenantId)?.license; setLicenseForm({ plan_code: current?.plan_code ?? '', features: current?.features ?? [], expires_at: current?.expires_at ? current.expires_at.slice(0, 10) : '', max_users: current?.max_users != null ? String(current.max_users) : '', max_branches: current?.max_branches != null ? String(current.max_branches) : '', auto_renew: Boolean(current?.auto_renew), notes: current?.notes ?? '' }); setCompanyId(''); setBranchId(''); }, [tenantId]);
  useEffect(() => { setBranchId(''); }, [companyId]);

  if (!developer) return <main className="min-h-screen bg-stone-50 p-10"><div className="mx-auto max-w-xl rounded-2xl border border-red-200 bg-white p-8 text-center"><div className="text-4xl">403</div><h1 className="mt-3 text-xl font-black">Developer Console</h1><p className="mt-2 text-sm text-stone-500">Console ini hanya tersedia untuk developer.</p></div></main>;

  const provisionAdmin = async () => {
    if (!tenantId || !companyId || !branchId || !createForm.name || !createForm.email || !createForm.password) return toast.error('Tenant, company, branch, nama, email, dan password wajib diisi.');
    try { await api.post('/v1/organizations/tenant-admins', { tenant_id: Number(tenantId), company_id: Number(companyId), branch_id: Number(branchId), ...createForm }); toast.success('Tenant admin berhasil dibuat.'); setCreateForm({ name: '', email: '', password: '' }); await loadAdmins(tenantId); } catch { toast.error('Tenant admin gagal dibuat.'); }
  };
  const saveLicense = async () => {
    if (!tenantId || !licenseForm.plan_code) return toast.error('Tenant dan plan wajib dipilih.');
    try { await api.put(`/v1/developer/tenants/${tenantId}/license`, { plan_code: licenseForm.plan_code, features: licenseForm.features, expires_at: licenseForm.expires_at || null, max_users: licenseForm.max_users ? Number(licenseForm.max_users) : null, max_branches: licenseForm.max_branches ? Number(licenseForm.max_branches) : null, auto_renew: licenseForm.auto_renew, notes: licenseForm.notes || null }); toast.success('Lisensi tenant berhasil diperbarui.'); await loadAll(); } catch { toast.error('Lisensi gagal diperbarui.'); }
  };
  const openAdmin = (admin: Admin) => { setSelectedAdmin(admin); setAdminForm({ name: admin.name, email: admin.email, status: admin.status, company_id: String(admin.company_id), branch_id: String(admin.branch_id), permissions: admin.permissions }); };
  const saveAdmin = async () => {
    if (!selectedAdmin) return;
    try { await api.put(`/v1/developer/tenant-admins/${selectedAdmin.membership_id}`, { ...adminForm, company_id: Number(adminForm.company_id), branch_id: Number(adminForm.branch_id) }); toast.success('Akun tenant admin diperbarui.'); await loadAdmins(tenantId); setSelectedAdmin(null); } catch { toast.error('Akun atau permission gagal diperbarui.'); }
  };
  const resetTenantSelection = (id: string) => { setTenantId(id); setCompanyId(''); setBranchId(''); };

  return <div className="flex min-h-screen bg-stone-50 text-stone-800"><AdminSidebar activePage="developer-console" /><main className="min-w-0 flex-1 overflow-auto p-6 lg:p-8"><div className="mx-auto max-w-7xl space-y-6">
    <header><div className="text-xs font-black uppercase tracking-[.18em] text-amber-700">Platform · Developer God Mode</div><h1 className="mt-1 text-3xl font-black text-stone-900">Developer Console</h1><p className="mt-1 text-sm text-stone-500">Kelola tenant, organisasi, lisensi, tenant-admin, dan permission dari satu tempat.</p></header>
    <div className="grid gap-4 md:grid-cols-4">{[['Tenant', String(tenants.length), '🏢'], ['Companies', String(tenants.reduce((n,t)=>n+t.company_count,0)), '🏷️'], ['Branches', String(tenants.reduce((n,t)=>n+t.branch_count,0)), '📍'], ['Mode', 'GOD MODE', '⚡']].map(([label,value,icon])=><div key={String(label)} className="rounded-2xl bg-stone-900 p-5 text-white"><div className="text-xl">{icon}</div><div className="mt-3 text-xs font-bold text-stone-400">{label}</div><div className="text-lg font-black">{value}</div></div>)}</div>

    <Card title="Tenant & Lisensi" description="Satu tenant memiliki company dan branch; lisensi menentukan fitur yang dapat diberikan ke akun tenant.">
      <div className="grid gap-4 lg:grid-cols-3"><Select label="Tenant" value={tenantId} onChange={resetTenantSelection}>{tenants.map(t=><option key={t.id} value={t.id}>{t.id} · {t.code} · {t.name}</option>)}</Select><Select label="Plan Lisensi" value={licenseForm.plan_code} onChange={value=>{const p=plans.find(x=>x.code===value);setLicenseForm(f=>({...f,plan_code:value,features:p?.features ?? f.features}))}} disabled={!tenantId}>{plans.map(p=><option key={p.code} value={p.code}>{p.name}</option>)}</Select><Field label="Berlaku sampai" type="date" value={licenseForm.expires_at} onChange={value=>setLicenseForm(f=>({...f,expires_at:value}))}/></div>
      <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">{Object.entries(featureLabels).map(([key,label])=><label key={key} className="flex items-center gap-2 rounded-xl border border-stone-200 px-3 py-2.5 text-sm"><input type="checkbox" checked={licenseForm.features.includes(key)} onChange={e=>setLicenseForm(f=>({...f,features:e.target.checked?[...f.features,key]:f.features.filter(x=>x!==key)}))}/><span className="font-semibold">{label}</span></label>)}</div>
      <div className="mt-4 grid gap-4 lg:grid-cols-3"><Field label="Maks User" type="number" value={licenseForm.max_users} onChange={value=>setLicenseForm(f=>({...f,max_users:value}))}/><Field label="Maks Branch" type="number" value={licenseForm.max_branches} onChange={value=>setLicenseForm(f=>({...f,max_branches:value}))}/><label className="flex items-center gap-2 rounded-xl border border-stone-200 px-3 py-2.5 text-sm"><input type="checkbox" checked={licenseForm.auto_renew} onChange={e=>setLicenseForm(f=>({...f,auto_renew:e.target.checked}))}/><span className="font-semibold">Auto renewal</span></label></div>
      <button onClick={saveLicense} disabled={!tenantId} className="mt-4 rounded-xl bg-stone-900 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-40">Simpan Lisensi</button>
    </Card>

    <Card title="Pembuatan Tenant Admin" description="Dropdown berantai memastikan company selalu milik tenant dan branch selalu milik company yang dipilih.">
      <div className="grid gap-4 lg:grid-cols-3"><Select label="Tenant ID" value={tenantId} onChange={resetTenantSelection}>{tenants.map(t=><option key={t.id} value={t.id}>{t.id} · {t.name}</option>)}</Select><Select label="Company ID" value={companyId} onChange={setCompanyId} disabled={!tenantId}>{companies.map(c=><option key={c.id} value={c.id}>{c.id} · {c.name}</option>)}</Select><Select label="Branch ID" value={branchId} onChange={setBranchId} disabled={!companyId}>{branches.map(b=><option key={b.id} value={b.id}>{b.id} · {b.name}</option>)}</Select></div>
      <div className="mt-4 grid gap-4 lg:grid-cols-3"><Field label="Nama" value={createForm.name} onChange={value=>setCreateForm(f=>({...f,name:value}))}/><Field label="Email" value={createForm.email} onChange={value=>setCreateForm(f=>({...f,email:value}))}/><Field label="Password" type="password" value={createForm.password} onChange={value=>setCreateForm(f=>({...f,password:value}))}/></div>
      <button onClick={provisionAdmin} className="mt-4 rounded-xl bg-amber-700 px-4 py-2.5 text-sm font-bold text-white">Buat Tenant Admin</button>
    </Card>

    <Card title="Data Tenant / Company / Branch" description="Tabel hierarchy untuk kebutuhan provisioning dan integrasi sewa lisensi.">
      <div className="overflow-x-auto"><table className="w-full min-w-[980px] text-sm"><thead><tr className="border-b border-stone-200 text-left text-xs uppercase tracking-wide text-stone-500"><th className="px-3 py-3">Tenant</th><th>Plan</th><th>Status</th><th>Company</th><th>Branch</th><th>License Limit</th></tr></thead><tbody>{tenants.flatMap(t=>t.companies.flatMap(c=>c.branches.length ? c.branches.map(b=><tr key={`${t.id}-${c.id}-${b.id}`} className="border-b border-stone-100"><td className="px-3 py-3"><b>{t.id} · {t.name}</b><div className="text-xs text-stone-400">{t.code}</div></td><td>{t.license?.plan_name ?? 'Unlicensed'}</td><td>{t.status}</td><td>{c.id} · {c.name}</td><td>{b.id} · {b.name}</td><td>{t.license?.max_users ?? '∞'} users · {t.license?.max_branches ?? '∞'} branches</td></tr>)) : [<tr key={`${t.id}-${c.id}`} className="border-b border-stone-100"><td className="px-3 py-3"><b>{t.id} · {t.name}</b></td><td>{t.license?.plan_name ?? 'Unlicensed'}</td><td>{t.status}</td><td>{c.id} · {c.name}</td><td>—</td><td>{t.license?.max_users ?? '∞'} users</td></tr>]))}</tbody></table></div>
    </Card>

    <Card title="Tenant Admin & Permission" description="Permission editor menampilkan hanya permission yang termasuk fitur lisensi aktif.">
      {!tenantId ? <p className="text-sm text-stone-500">Pilih tenant.</p> : admins.length === 0 ? <p className="text-sm text-stone-500">Belum ada tenant-admin.</p> : <div className="overflow-x-auto"><table className="w-full min-w-[780px] text-sm"><thead><tr className="border-b border-stone-200 text-left text-xs uppercase tracking-wide text-stone-500"><th className="px-3 py-3">Akun</th><th>Company</th><th>Branch</th><th>Status</th><th>Permissions</th><th></th></tr></thead><tbody>{admins.map(admin=><tr key={admin.membership_id} className="border-b border-stone-100"><td className="px-3 py-3"><b>{admin.name}</b><div className="text-xs text-stone-400">{admin.email}</div></td><td>{admin.company_name}</td><td>{admin.branch_name}</td><td>{admin.status}</td><td>{admin.permissions.length}</td><td><button onClick={()=>openAdmin(admin)} className="rounded-lg border border-stone-200 px-3 py-1.5 text-xs font-bold">Edit</button></td></tr>)}</tbody></table></div>}
    </Card>

    {selectedAdmin && <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"><div className="max-h-[90vh] w-full max-w-5xl overflow-auto rounded-3xl bg-white p-6 shadow-2xl"><div className="flex items-start justify-between"><div><div className="text-xs font-black uppercase tracking-[.15em] text-amber-700">Edit Tenant Admin</div><h2 className="mt-1 text-2xl font-black">{selectedAdmin.name}</h2></div><button onClick={()=>setSelectedAdmin(null)} className="rounded-xl border border-stone-200 px-3 py-2 font-bold">×</button></div><div className="mt-5 grid gap-4 lg:grid-cols-3"><Field label="Nama" value={adminForm.name} onChange={value=>setAdminForm(f=>({...f,name:value}))}/><Field label="Email" value={adminForm.email} onChange={value=>setAdminForm(f=>({...f,email:value}))}/><Select label="Status" value={adminForm.status} onChange={value=>setAdminForm(f=>({...f,status:value}))}><option value="active">Active</option><option value="inactive">Inactive</option></Select><Select label="Company" value={adminForm.company_id} onChange={value=>setAdminForm(f=>({...f,company_id:value,branch_id:''}))}>{companies.map(c=><option key={c.id} value={c.id}>{c.name}</option>)}</Select><Select label="Branch" value={adminForm.branch_id} onChange={value=>setAdminForm(f=>({...f,branch_id:value}))}>{(companies.find(c=>String(c.id)===adminForm.company_id)?.branches ?? []).map(b=><option key={b.id} value={b.id}>{b.name}</option>)}</Select></div><div className="mt-5"><div className="mb-2 text-sm font-black">Permission Berlisensi ({licensedPermissionNames.length})</div><div className="grid max-h-80 gap-2 overflow-auto rounded-2xl border border-stone-200 p-3 md:grid-cols-2">{licensedPermissionNames.map(permission=><label key={permission.name} className="flex items-start gap-2 rounded-xl border border-stone-100 px-3 py-2 text-xs"><input type="checkbox" checked={adminForm.permissions.includes(permission.name)} onChange={e=>setAdminForm(f=>({...f,permissions:e.target.checked?[...f.permissions,permission.name]:f.permissions.filter(x=>x!==permission.name)}))}/><span><b>{permission.name}</b>{permission.description && <span className="block text-stone-400">{permission.description}</span>}</span></label>)}</div></div><div className="mt-5 flex justify-end gap-2"><button onClick={()=>setSelectedAdmin(null)} className="rounded-xl border border-stone-200 px-4 py-2.5 text-sm font-bold">Batal</button><button onClick={saveAdmin} className="rounded-xl bg-stone-900 px-4 py-2.5 text-sm font-bold text-white">Simpan Perubahan</button></div></div></div>}
  </div></main></div>;
}

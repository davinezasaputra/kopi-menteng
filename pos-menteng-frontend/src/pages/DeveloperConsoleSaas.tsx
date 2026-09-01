import { useEffect, useMemo, useState, type ReactNode } from 'react';
import AdminSidebar from '../components/AdminSidebar';
import api from '../core/api/client';
import { isDeveloper } from '../core/auth/permissions';
import toast from 'react-hot-toast';

type Branch = { id: number; code: string; name: string; status: string };
type Company = { id: number; code: string; name: string; status: string; branches: Branch[] };
type License = { plan_code: string; plan_name: string; features: string[]; max_users: number | null; max_branches: number | null; starts_at?: string | null; expires_at?: string | null; status?: string; auto_renew?: boolean; notes?: string | null };
type Subscription = { id: number; subscription_no: string; plan_code: string; billing_cycle: string; amount: string | number; currency: string; starts_at?: string | null; current_period_start?: string | null; current_period_end?: string | null; trial_ends_at?: string | null; grace_until?: string | null; status: string; auto_renew: boolean; notes?: string | null };
type Tenant = { id: number; code: string; name: string; status: string; timezone?: string; currency?: string; company_count: number; branch_count: number; license?: License | null; subscription?: Subscription | null; companies: Company[] };
type Permission = { id: number; module: string; resource: string; action: string; name: string; description?: string };
type Admin = { membership_id: number; name: string; email: string; status: string; company_id: number; company_name: string; branch_id: number; branch_name: string; permissions: string[] };
type Plan = { code: string; name: string; features: string[]; max_users: number | null; max_branches: number | null };
type LicenseEvent = { id: number; event: string; from_plan_code?: string | null; to_plan_code?: string | null; from_status?: string | null; to_status?: string | null; metadata?: Record<string, unknown> | null; created_at: string; actor?: { name?: string | null } | null };

const featureLabels: Record<string, string> = { pos: 'POS', inventory: 'Inventory', purchasing: 'Purchasing', sales: 'Sales', accounting: 'Accounting', hrm: 'HRM', administration: 'Administration', audit: 'Audit', organization: 'Organization' };
const errorMessage = (e: unknown) => (e && typeof e === 'object' && 'response' in e ? String((e as { response?: { data?: { message?: string } } }).response?.data?.message ?? '') : '');

function Field({ label, value, onChange, type = 'text' }: { label: string; value: string; onChange: (value: string) => void; type?: string }) {
  return <label className="block"><span className="mb-1 block text-xs font-bold text-stone-500">{label}</span><input type={type} value={value} onChange={e => onChange(e.target.value)} className="w-full rounded-xl border border-stone-200 px-3 py-2.5 text-sm outline-none focus:border-amber-500" /></label>;
}
function Select({ label, value, onChange, disabled = false, children }: { label: string; value: string; onChange: (value: string) => void; disabled?: boolean; children: ReactNode }) {
  return <label className="block"><span className="mb-1 block text-xs font-bold text-stone-500">{label}</span><select value={value} disabled={disabled} onChange={e => onChange(e.target.value)} className="w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm disabled:bg-stone-100"><option value="">Pilih {label.toLowerCase()}...</option>{children}</select></label>;
}
function Card({ title, description, children }: { title: string; description?: string; children: ReactNode }) {
  return <section className="rounded-3xl border border-stone-200 bg-white p-5 shadow-sm"><div><h2 className="font-black text-stone-900">{title}</h2>{description && <p className="mt-1 text-sm text-stone-500">{description}</p>}</div><div className="mt-4">{children}</div></section>;
}

export default function DeveloperConsoleSaas() {
  const developer = isDeveloper();
  const [tenants, setTenants] = useState<Tenant[]>([]);
  const [plans, setPlans] = useState<Plan[]>([]);
  const [permissions, setPermissions] = useState<Permission[]>([]);
  const [admins, setAdmins] = useState<Admin[]>([]);
  const [events, setEvents] = useState<LicenseEvent[]>([]);
  const [tenantId, setTenantId] = useState('');
  const [companyId, setCompanyId] = useState('');
  const [branchId, setBranchId] = useState('');
  const [editingTenant, setEditingTenant] = useState<Tenant | null>(null);
  const [tenantForm, setTenantForm] = useState({ name: '', code: '', status: 'active', timezone: 'Asia/Jakarta', currency: 'IDR' });
  const [license, setLicense] = useState({ plan_code: '', features: [] as string[], starts_at: '', expires_at: '', max_users: '', max_branches: '', auto_renew: false, notes: '' });
  const [subscription, setSubscription] = useState({ subscription_no: '', plan_code: '', billing_cycle: 'monthly', amount: '0', currency: 'IDR', starts_at: '', current_period_start: '', current_period_end: '', trial_ends_at: '', grace_until: '', status: 'active', auto_renew: false, notes: '' });
  const [editingAdmin, setEditingAdmin] = useState<Admin | null>(null);
  const [adminForm, setAdminForm] = useState({ name: '', email: '', status: 'active', company_id: '', branch_id: '', permissions: [] as string[] });
  const [create, setCreate] = useState({ name: '', email: '', password: '' });

  const tenant = tenants.find(t => String(t.id) === tenantId);
  const companies = tenant?.companies ?? [];
  const company = companies.find(c => String(c.id) === companyId);
  const branches = company?.branches ?? [];
  const licenseFeatures = useMemo(() => new Set(license.features), [license.features]);
  const licensedPermissions = useMemo(() => permissions.filter(permission => {
    const root = permission.name.split('.')[0];
    const feature = root === 'hr' ? 'hrm' : root === 'users' || root === 'rbac' ? 'administration' : root;
    return licenseFeatures.has(feature);
  }), [permissions, licenseFeatures]);

  const load = async () => {
    try {
      const [tenantResponse, planResponse, permissionResponse] = await Promise.all([
        api.get('/v1/developer/tenants'),
        api.get('/v1/developer/license-catalog'),
        api.get('/v1/developer/permissions'),
      ]);
      setTenants(tenantResponse.data?.data ?? []);
      setPlans(planResponse.data?.data ?? []);
      setPermissions(permissionResponse.data?.data ?? []);
    } catch (e) { toast.error(errorMessage(e) || 'Data platform gagal dimuat.'); }
  };
  const loadTenantDetails = async (id: string) => {
    if (!id) { setAdmins([]); setEvents([]); return; }
    try {
      const [adminResponse, licenseResponse, subscriptionResponse, eventResponse] = await Promise.all([
        api.get(`/v1/developer/tenants/${id}/admins`),
        api.get(`/v1/developer/tenants/${id}/license`),
        api.get(`/v1/developer/tenants/${id}/subscription`),
        api.get(`/v1/developer/tenants/${id}/license-events`),
      ]);
      setAdmins(adminResponse.data?.data ?? []);
      setEvents(eventResponse.data?.data ?? []);
      const l = licenseResponse.data?.data as License | null;
      setLicense({ plan_code: l?.plan_code ?? '', features: l?.features ?? [], starts_at: l?.starts_at?.slice(0, 10) ?? '', expires_at: l?.expires_at?.slice(0, 10) ?? '', max_users: l?.max_users != null ? String(l.max_users) : '', max_branches: l?.max_branches != null ? String(l.max_branches) : '', auto_renew: Boolean(l?.auto_renew), notes: l?.notes ?? '' });
      const s = subscriptionResponse.data?.data as Subscription | null;
      setSubscription({ subscription_no: s?.subscription_no ?? '', plan_code: s?.plan_code ?? l?.plan_code ?? '', billing_cycle: s?.billing_cycle ?? 'monthly', amount: s?.amount != null ? String(s.amount) : '0', currency: s?.currency ?? tenant?.currency ?? 'IDR', starts_at: s?.starts_at?.slice(0, 16) ?? '', current_period_start: s?.current_period_start?.slice(0, 16) ?? '', current_period_end: s?.current_period_end?.slice(0, 16) ?? '', trial_ends_at: s?.trial_ends_at?.slice(0, 16) ?? '', grace_until: s?.grace_until?.slice(0, 16) ?? '', status: s?.status ?? 'active', auto_renew: Boolean(s?.auto_renew), notes: s?.notes ?? '' });
    } catch (e) { toast.error(errorMessage(e) || 'Detail tenant gagal dimuat.'); }
  };

  useEffect(() => { if (developer) void load(); }, [developer]);
  useEffect(() => { void loadTenantDetails(tenantId); setCompanyId(''); setBranchId(''); }, [tenantId]);

  if (!developer) return <main className="min-h-screen bg-stone-50 p-10"><div className="mx-auto max-w-xl rounded-2xl border border-red-200 bg-white p-8 text-center"><div className="text-4xl">403</div><h1 className="mt-3 font-black">Developer Console</h1></div></main>;

  const chooseTenant = (value: string) => setTenantId(value);
  const saveTenant = async () => {
    if (!editingTenant) return;
    try { await api.put(`/v1/developer/tenants/${editingTenant.id}`, tenantForm); toast.success('Tenant diperbarui'); setEditingTenant(null); await load(); }
    catch (e) { toast.error(errorMessage(e) || 'Tenant gagal diperbarui.'); }
  };
  const saveLicense = async () => {
    if (!tenantId) return toast.error('Pilih tenant terlebih dahulu.');
    try { await api.put(`/v1/developer/tenants/${tenantId}/license`, { ...license, max_users: license.max_users ? Number(license.max_users) : null, max_branches: license.max_branches ? Number(license.max_branches) : null, starts_at: license.starts_at || null, expires_at: license.expires_at || null, auto_renew: license.auto_renew, notes: license.notes || null }); toast.success('Lisensi disimpan'); await load(); await loadTenantDetails(tenantId); }
    catch (e) { toast.error(errorMessage(e) || 'Lisensi gagal disimpan.'); }
  };
  const saveSubscription = async () => {
    if (!tenantId) return toast.error('Pilih tenant terlebih dahulu.');
    try { await api.put(`/v1/developer/tenants/${tenantId}/subscription`, { ...subscription, amount: Number(subscription.amount || 0), starts_at: subscription.starts_at || null, current_period_start: subscription.current_period_start || null, current_period_end: subscription.current_period_end || null, trial_ends_at: subscription.trial_ends_at || null, grace_until: subscription.grace_until || null, auto_renew: subscription.auto_renew, notes: subscription.notes || null }); toast.success('Subscription disimpan'); await load(); await loadTenantDetails(tenantId); }
    catch (e) { toast.error(errorMessage(e) || 'Subscription gagal disimpan.'); }
  };
  const createAdmin = async () => {
    if (!tenantId || !companyId || !branchId) return toast.error('Tenant, company, dan branch wajib dipilih.');
    try { await api.post('/v1/organizations/tenant-admins', { tenant_id: Number(tenantId), company_id: Number(companyId), branch_id: Number(branchId), ...create }); toast.success('Tenant admin dibuat'); setCreate({ name: '', email: '', password: '' }); await loadTenantDetails(tenantId); }
    catch (e) { toast.error(errorMessage(e) || 'Tenant admin gagal dibuat.'); }
  };
  const openAdmin = (admin: Admin) => { setEditingAdmin(admin); setAdminForm({ name: admin.name, email: admin.email, status: admin.status, company_id: String(admin.company_id), branch_id: String(admin.branch_id), permissions: admin.permissions }); };
  const saveAdmin = async () => {
    if (!editingAdmin) return;
    try { await api.put(`/v1/developer/tenant-admins/${editingAdmin.membership_id}`, { ...adminForm, company_id: Number(adminForm.company_id), branch_id: Number(adminForm.branch_id) }); toast.success('Tenant admin diperbarui'); await loadTenantDetails(tenantId); setEditingAdmin(null); }
    catch (e) { toast.error(errorMessage(e) || 'Tenant admin gagal diperbarui.'); }
  };

  return <div className="flex min-h-screen bg-stone-50 text-stone-800"><AdminSidebar activePage="developer-console" /><main className="min-w-0 flex-1 overflow-auto p-6 lg:p-8"><div className="mx-auto max-w-7xl space-y-6">
    <header><div className="text-xs font-black uppercase tracking-[.18em] text-amber-700">Platform · God Mode</div><div className="flex flex-wrap items-end justify-between gap-3"><div><h1 className="mt-1 text-3xl font-black">Developer Console</h1><p className="mt-1 text-sm text-stone-500">Control plane SaaS untuk tenant, organisasi, lisensi, subscription, akun, dan permission.</p></div><div className="rounded-full bg-stone-900 px-4 py-2 text-xs font-black text-white">⚡ GOD MODE</div></div></header>
    <div className="grid gap-4 md:grid-cols-5">{[['Tenant', tenants.length, '🏢'], ['Company', tenants.reduce((n, t) => n + t.company_count, 0), '🏷️'], ['Branch', tenants.reduce((n, t) => n + t.branch_count, 0), '📍'], ['Active License', tenants.filter(t => t.license?.status === 'active').length, '✅'], ['Expiring', tenants.filter(t => t.license?.expires_at && new Date(t.license.expires_at).getTime() - Date.now() < 30 * 86400000).length, '⏳']].map(([label, value, icon]) => <div key={String(label)} className="rounded-2xl bg-stone-900 p-4 text-white"><div>{icon}</div><div className="mt-2 text-xs text-stone-400">{label}</div><div className="text-xl font-black">{value}</div></div>)}</div>

    <Card title="Pilih Tenant" description="Semua kontrol di bawah bekerja dalam konteks tenant yang dipilih."><div className="grid gap-4 md:grid-cols-[1fr_auto]"><Select label="Tenant" value={tenantId} onChange={chooseTenant}>{tenants.map(t => <option key={t.id} value={t.id}>{t.id} · {t.code} · {t.name}</option>)}</Select>{tenant && <button onClick={() => { setEditingTenant(tenant); setTenantForm({ name: tenant.name, code: tenant.code, status: tenant.status, timezone: tenant.timezone ?? 'Asia/Jakarta', currency: tenant.currency ?? 'IDR' }); }} className="self-end rounded-xl border border-stone-200 px-4 py-2.5 text-sm font-bold">Edit Tenant</button>}</div></Card>

    {tenant && <>
      <div className="grid gap-6 lg:grid-cols-2">
        <Card title="Tenant & License" description="Feature entitlement dan batas kapasitas tenant."><div className="grid gap-4 md:grid-cols-2"><Select label="Plan" value={license.plan_code} onChange={value => { const plan = plans.find(p => p.code === value); setLicense(f => ({ ...f, plan_code: value, features: plan?.features ?? f.features })); }}><>{plans.map(plan => <option key={plan.code} value={plan.code}>{plan.name}</option>)}</></Select><Field label="Expiry" type="date" value={license.expires_at} onChange={value => setLicense(f => ({ ...f, expires_at: value }))} /><Field label="Max User" type="number" value={license.max_users} onChange={value => setLicense(f => ({ ...f, max_users: value }))} /><Field label="Max Branch" type="number" value={license.max_branches} onChange={value => setLicense(f => ({ ...f, max_branches: value }))} /></div><div className="mt-4 grid grid-cols-2 gap-2">{Object.entries(featureLabels).map(([key, label]) => <label key={key} className="flex items-center gap-2 rounded-xl border border-stone-200 px-3 py-2 text-sm"><input type="checkbox" checked={license.features.includes(key)} onChange={e => setLicense(f => ({ ...f, features: e.target.checked ? [...new Set([...f.features, key])] : f.features.filter(x => x !== key) }))} />{label}</label>)}</div><label className="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" checked={license.auto_renew} onChange={e => setLicense(f => ({ ...f, auto_renew: e.target.checked }))} />Auto renewal</label><button onClick={saveLicense} className="mt-4 rounded-xl bg-stone-900 px-4 py-2.5 text-sm font-bold text-white">Simpan License</button></Card>
        <Card title="Subscription & Billing" description="Status langganan, periode, nominal, dan grace period."><div className="grid gap-4 md:grid-cols-2"><Field label="Subscription No" value={subscription.subscription_no} onChange={value => setSubscription(f => ({ ...f, subscription_no: value }))} /><Select label="Billing Cycle" value={subscription.billing_cycle} onChange={value => setSubscription(f => ({ ...f, billing_cycle: value }))}><option value="monthly">Monthly</option><option value="quarterly">Quarterly</option><option value="yearly">Yearly</option></Select><Field label="Amount" type="number" value={subscription.amount} onChange={value => setSubscription(f => ({ ...f, amount: value }))} /><Field label="Currency" value={subscription.currency} onChange={value => setSubscription(f => ({ ...f, currency: value.toUpperCase() }))} /><Select label="Status" value={subscription.status} onChange={value => setSubscription(f => ({ ...f, status: value }))}><option value="trialing">Trialing</option><option value="active">Active</option><option value="past_due">Past Due</option><option value="suspended">Suspended</option><option value="cancelled">Cancelled</option></Select><Field label="Grace Until" type="datetime-local" value={subscription.grace_until} onChange={value => setSubscription(f => ({ ...f, grace_until: value }))} /><Field label="Period Start" type="datetime-local" value={subscription.current_period_start} onChange={value => setSubscription(f => ({ ...f, current_period_start: value }))} /><Field label="Period End" type="datetime-local" value={subscription.current_period_end} onChange={value => setSubscription(f => ({ ...f, current_period_end: value }))} /></div><label className="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" checked={subscription.auto_renew} onChange={e => setSubscription(f => ({ ...f, auto_renew: e.target.checked }))} />Auto renewal</label><button onClick={saveSubscription} className="mt-4 rounded-xl bg-amber-700 px-4 py-2.5 text-sm font-bold text-white">Simpan Subscription</button></Card>
      </div>

      <Card title="Tenant / Company / Branch" description="Hierarki tenant untuk integrasi operasional dan billing." ><div className="overflow-x-auto"><table className="w-full min-w-[920px] text-sm"><thead><tr className="border-b border-stone-200 text-left text-xs uppercase text-stone-500"><th className="px-3 py-3">Tenant</th><th>Company</th><th>Branch</th><th>License</th><th>Subscription</th></tr></thead><tbody>{tenant.companies.flatMap(c => c.branches.map(b => <tr key={`${tenant.id}-${c.id}-${b.id}`} className="border-b border-stone-100"><td className="px-3 py-3"><b>{tenant.id} · {tenant.name}</b><div className="text-xs text-stone-400">{tenant.code}</div></td><td>{c.id} · {c.name}</td><td>{b.id} · {b.name}</td><td>{tenant.license?.plan_name ?? 'Unlicensed'} · {tenant.license?.max_users ?? '∞'} users</td><td>{tenant.subscription?.status ?? '—'} · {tenant.subscription ? `${tenant.subscription.currency} ${tenant.subscription.amount}` : '—'}</td></tr>))}</tbody></table></div></Card>

      <Card title="Pembuatan Tenant Admin" description="Provisioning berantai Tenant → Company → Branch."><div className="grid gap-4 lg:grid-cols-3"><Select label="Tenant ID" value={tenantId} onChange={chooseTenant}>{tenants.map(t => <option key={t.id} value={t.id}>{t.id} · {t.name}</option>)}</Select><Select label="Company ID" value={companyId} disabled={!tenantId} onChange={setCompanyId}>{companies.map(c => <option key={c.id} value={c.id}>{c.id} · {c.name}</option>)}</Select><Select label="Branch ID" value={branchId} disabled={!companyId} onChange={setBranchId}>{branches.map(b => <option key={b.id} value={b.id}>{b.id} · {b.name}</option>)}</Select></div><div className="mt-4 grid gap-4 lg:grid-cols-3"><Field label="Nama" value={create.name} onChange={value => setCreate(f => ({ ...f, name: value }))} /><Field label="Email" value={create.email} onChange={value => setCreate(f => ({ ...f, email: value }))} /><Field label="Password" type="password" value={create.password} onChange={value => setCreate(f => ({ ...f, password: value }))} /></div><button onClick={createAdmin} className="mt-4 rounded-xl bg-amber-700 px-4 py-2.5 text-sm font-bold text-white">Buat Tenant Admin</button></Card>

      <Card title="Tenant Admin & Permission" description="Permission per akun dibatasi oleh license tenant.">{admins.length === 0 ? <p className="text-sm text-stone-500">Belum ada tenant-admin.</p> : <div className="overflow-x-auto"><table className="w-full min-w-[850px] text-sm"><thead><tr className="border-b border-stone-200 text-left text-xs uppercase text-stone-500"><th className="px-3 py-3">Akun</th><th>Company</th><th>Branch</th><th>Status</th><th>Permission</th><th /></tr></thead><tbody>{admins.map(a => <tr key={a.membership_id} className="border-b border-stone-100"><td className="px-3 py-3"><b>{a.name}</b><div className="text-xs text-stone-400">{a.email}</div></td><td>{a.company_name}</td><td>{a.branch_name}</td><td>{a.status}</td><td>{a.permissions.length}</td><td><button onClick={() => openAdmin(a)} className="rounded-lg border border-stone-200 px-3 py-1.5 text-xs font-bold">Edit</button></td></tr>)}</tbody></table></div>}</Card>

      <Card title="License Change History" description="Audit perubahan plan dan status license pada tenant ini.">{events.length === 0 ? <p className="text-sm text-stone-500">Belum ada histori perubahan.</p> : <div className="overflow-x-auto"><table className="w-full min-w-[760px] text-sm"><thead><tr className="border-b border-stone-200 text-left text-xs uppercase text-stone-500"><th className="px-3 py-3">Waktu</th><th>Event</th><th>Plan</th><th>Status</th><th>Actor</th></tr></thead><tbody>{events.map(event => <tr key={event.id} className="border-b border-stone-100"><td className="px-3 py-3">{new Date(event.created_at).toLocaleString('id-ID')}</td><td>{event.event}</td><td>{event.from_plan_code ?? '—'} → {event.to_plan_code ?? '—'}</td><td>{event.from_status ?? '—'} → {event.to_status ?? '—'}</td><td>{event.actor?.name ?? 'system'}</td></tr>)}</tbody></table></div>}</Card>
    </>}

    {editingTenant && <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"><div className="w-full max-w-2xl rounded-3xl bg-white p-6"><div className="flex items-center justify-between"><div><div className="text-xs font-black uppercase text-amber-700">Platform Tenant</div><h2 className="text-2xl font-black">Edit Tenant</h2></div><button onClick={() => setEditingTenant(null)} className="rounded-xl border px-3 py-2">×</button></div><div className="mt-5 grid gap-4 md:grid-cols-2"><Field label="Nama" value={tenantForm.name} onChange={value => setTenantForm(f => ({ ...f, name: value }))} /><Field label="Code" value={tenantForm.code} onChange={value => setTenantForm(f => ({ ...f, code: value }))} /><Select label="Status" value={tenantForm.status} onChange={value => setTenantForm(f => ({ ...f, status: value }))}><option value="active">Active</option><option value="inactive">Inactive</option><option value="suspended">Suspended</option></Select><Field label="Timezone" value={tenantForm.timezone} onChange={value => setTenantForm(f => ({ ...f, timezone: value }))} /><Field label="Currency" value={tenantForm.currency} onChange={value => setTenantForm(f => ({ ...f, currency: value.toUpperCase() }))} /></div><div className="mt-5 flex justify-end gap-2"><button onClick={() => setEditingTenant(null)} className="rounded-xl border px-4 py-2.5 text-sm font-bold">Batal</button><button onClick={saveTenant} className="rounded-xl bg-stone-900 px-4 py-2.5 text-sm font-bold text-white">Simpan</button></div></div></div>}

    {editingAdmin && <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"><div className="max-h-[90vh] w-full max-w-5xl overflow-auto rounded-3xl bg-white p-6"><div className="flex items-center justify-between"><div><div className="text-xs font-black uppercase text-amber-700">Tenant Admin</div><h2 className="text-2xl font-black">Edit Akun</h2></div><button onClick={() => setEditingAdmin(null)} className="rounded-xl border px-3 py-2">×</button></div><div className="mt-5 grid gap-4 lg:grid-cols-3"><Field label="Nama" value={adminForm.name} onChange={value => setAdminForm(f => ({ ...f, name: value }))} /><Field label="Email" value={adminForm.email} onChange={value => setAdminForm(f => ({ ...f, email: value }))} /><Select label="Status" value={adminForm.status} onChange={value => setAdminForm(f => ({ ...f, status: value }))}><option value="active">Active</option><option value="inactive">Inactive</option></Select><Select label="Company" value={adminForm.company_id} onChange={value => setAdminForm(f => ({ ...f, company_id: value, branch_id: '' }))}>{companies.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}</Select><Select label="Branch" value={adminForm.branch_id} onChange={value => setAdminForm(f => ({ ...f, branch_id: value }))}>{(companies.find(c => String(c.id) === adminForm.company_id)?.branches ?? []).map(b => <option key={b.id} value={b.id}>{b.name}</option>)}</Select></div><div className="mt-6"><div className="mb-2 text-sm font-black">Licensed Permissions</div><div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">{licensedPermissions.map(permission => <label key={permission.id} className="flex items-center gap-2 rounded-xl border border-stone-200 px-3 py-2 text-xs"><input type="checkbox" checked={adminForm.permissions.includes(permission.name)} onChange={e => setAdminForm(f => ({ ...f, permissions: e.target.checked ? [...new Set([...f.permissions, permission.name])] : f.permissions.filter(x => x !== permission.name) }))} /><span><b>{permission.name}</b><span className="block text-stone-400">{permission.description ?? `${permission.module} / ${permission.resource}`}</span></span></label>)}</div></div><div className="mt-5 flex justify-end"><button onClick={saveAdmin} className="rounded-xl bg-stone-900 px-4 py-2.5 text-sm font-bold text-white">Simpan Perubahan</button></div></div></div>}
  </div></main></div>;
}

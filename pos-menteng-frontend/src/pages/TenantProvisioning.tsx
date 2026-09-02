import { useMemo, useState, type ReactNode } from 'react';
import { useNavigate } from 'react-router-dom';
import AdminSidebar from '../components/AdminSidebar';
import api from '../core/api/client';
import { isDeveloper } from '../core/auth/permissions';
import toast from 'react-hot-toast';

type Step = 1 | 2 | 3 | 4;
type Section = 'tenant' | 'company' | 'branch' | 'admin';

const plans = [
  { code: 'starter', name: 'Starter', features: ['pos', 'inventory'], max_users: 5, max_branches: 1 },
  { code: 'business', name: 'Business', features: ['pos', 'inventory', 'purchasing', 'sales', 'accounting'], max_users: 20, max_branches: 5 },
  { code: 'professional', name: 'Professional', features: ['pos', 'inventory', 'purchasing', 'sales', 'accounting', 'hrm'], max_users: 50, max_branches: 15 },
  { code: 'enterprise', name: 'Enterprise', features: ['pos', 'inventory', 'purchasing', 'sales', 'accounting', 'hrm', 'administration', 'audit', 'organization'], max_users: null, max_branches: null },
];

function Field({ label, value, onChange, type = 'text', placeholder }: { label: string; value: string; onChange: (value: string) => void; type?: string; placeholder?: string }) {
  return <label className="block"><span className="mb-1 block text-xs font-bold text-stone-500">{label}</span><input type={type} value={value} placeholder={placeholder} onChange={e => onChange(e.target.value)} className="w-full rounded-xl border border-stone-200 px-3 py-2.5 text-sm outline-none focus:border-amber-500" /></label>;
}
function Select({ label, value, onChange, children }: { label: string; value: string; onChange: (value: string) => void; children: ReactNode }) {
  return <label className="block"><span className="mb-1 block text-xs font-bold text-stone-500">{label}</span><select value={value} onChange={e => onChange(e.target.value)} className="w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm">{children}</select></label>;
}

export default function TenantProvisioning() {
  const navigate = useNavigate();
  const developer = isDeveloper();
  const [step, setStep] = useState<Step>(1);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState({
    tenant: { name: '', code: '', timezone: 'Asia/Jakarta', currency: 'IDR' },
    company: { code: '', name: '', legal_name: '', email: '', phone: '', address: '' },
    branch: { code: '', name: '', type: 'store', address: '' },
    admin: { name: '', email: '', password: '' },
    plan: 'starter', billing_cycle: 'monthly', amount: '0', subscription_no: '', expires_at: '', auto_renew: false,
  });

  const selectedPlan = useMemo(() => plans.find(plan => plan.code === form.plan)!, [form.plan]);
  const update = (section: Section, key: string, value: string) => {
    setForm(current => ({ ...current, [section]: { ...(current[section] as Record<string, string>), [key]: value } }));
  };

  const validateStep = () => {
    if (step === 1 && (!form.tenant.name || !form.tenant.code)) return 'Nama tenant dan kode tenant wajib diisi.';
    if (step === 2 && (!form.company.code || !form.company.name)) return 'Kode dan nama company wajib diisi.';
    if (step === 3 && (!form.branch.code || !form.branch.name)) return 'Kode dan nama branch wajib diisi.';
    if (step === 4 && (!form.admin.name || !form.admin.email || form.admin.password.length < 8)) return 'Data tenant admin wajib lengkap dan password minimal 8 karakter.';
    return null;
  };

  const submit = async () => {
    const validation = validateStep();
    if (validation) return toast.error(validation);
    setSaving(true);
    try {
      const response = await api.post('/v1/developer/provision-tenant', { ...form, amount: Number(form.amount || 0), expires_at: form.expires_at || null, auto_renew: form.auto_renew });
      toast.success(`Tenant ${response.data?.data?.tenant?.name ?? form.tenant.name} berhasil dibuat.`);
      navigate('/platform');
    } catch (error) {
      const message = error && typeof error === 'object' && 'response' in error ? String((error as { response?: { data?: { message?: string } } }).response?.data?.message ?? '') : '';
      toast.error(message || 'Provisioning tenant gagal.');
    } finally { setSaving(false); }
  };

  if (!developer) return <main className="min-h-screen bg-stone-50 p-10"><div className="mx-auto max-w-xl rounded-2xl border border-red-200 bg-white p-8 text-center"><div className="text-4xl">403</div><h1 className="mt-3 font-black">Tenant Provisioning</h1></div></main>;

  return <div className="flex min-h-screen bg-stone-50 text-stone-800"><AdminSidebar activePage="developer-console" /><main className="min-w-0 flex-1 overflow-auto p-6 lg:p-8"><div className="mx-auto max-w-5xl space-y-6">
    <header className="flex flex-wrap items-end justify-between gap-3"><div><div className="text-xs font-black uppercase tracking-[.18em] text-amber-700">Platform · Provisioning</div><h1 className="mt-1 text-3xl font-black">Buat Tenant Baru</h1><p className="mt-1 text-sm text-stone-500">Satu wizard membuat Tenant, Company, Branch, Tenant Admin, License, dan Subscription secara atomik.</p></div><button onClick={() => navigate('/platform')} className="rounded-xl border border-stone-200 bg-white px-4 py-2.5 text-sm font-bold">Kembali</button></header>
    <div className="grid grid-cols-4 gap-2">{(['Tenant', 'Company', 'Branch', 'Admin & License'] as const).map((label, index) => <button key={label} onClick={() => index + 1 <= step && setStep((index + 1) as Step)} className={`rounded-xl px-3 py-3 text-sm font-bold ${step === index + 1 ? 'bg-stone-900 text-white' : 'border border-stone-200 bg-white text-stone-500'}`}>{index + 1}. {label}</button>)}</div>
    <section className="rounded-3xl border border-stone-200 bg-white p-6 shadow-sm">
      {step === 1 && <div className="space-y-5"><div><h2 className="text-xl font-black">Identitas Tenant</h2><p className="text-sm text-stone-500">Tenant adalah boundary utama data dan subscription.</p></div><div className="grid gap-4 md:grid-cols-2"><Field label="Nama Tenant" value={form.tenant.name} onChange={v => update('tenant', 'name', v)} placeholder="PT Contoh Indonesia" /><Field label="Kode Tenant" value={form.tenant.code} onChange={v => update('tenant', 'code', v.toUpperCase())} placeholder="CONT-001" /><Field label="Timezone" value={form.tenant.timezone} onChange={v => update('tenant', 'timezone', v)} /><Field label="Currency" value={form.tenant.currency} onChange={v => update('tenant', 'currency', v.toUpperCase())} /></div></div>}
      {step === 2 && <div className="space-y-5"><div><h2 className="text-xl font-black">Company Utama</h2><p className="text-sm text-stone-500">Minimal satu company menjadi induk branch pertama.</p></div><div className="grid gap-4 md:grid-cols-2"><Field label="Kode Company" value={form.company.code} onChange={v => update('company', 'code', v.toUpperCase())} placeholder="MAIN" /><Field label="Nama Company" value={form.company.name} onChange={v => update('company', 'name', v)} placeholder="PT Contoh Indonesia" /><Field label="Legal Name" value={form.company.legal_name} onChange={v => update('company', 'legal_name', v)} /><Field label="Email" value={form.company.email} onChange={v => update('company', 'email', v)} /><Field label="Telepon" value={form.company.phone} onChange={v => update('company', 'phone', v)} /><Field label="Alamat" value={form.company.address} onChange={v => update('company', 'address', v)} /></div></div>}
      {step === 3 && <div className="space-y-5"><div><h2 className="text-xl font-black">Branch Pertama</h2><p className="text-sm text-stone-500">Branch otomatis terhubung ke tenant/company yang baru dibuat.</p></div><div className="grid gap-4 md:grid-cols-2"><Field label="Kode Branch" value={form.branch.code} onChange={v => update('branch', 'code', v.toUpperCase())} placeholder="JKT-01" /><Field label="Nama Branch" value={form.branch.name} onChange={v => update('branch', 'name', v)} placeholder="Jakarta Pusat" /><Select label="Tipe Branch" value={form.branch.type} onChange={v => update('branch', 'type', v)}><option value="store">Store</option><option value="office">Office</option><option value="warehouse">Warehouse</option></Select><Field label="Alamat Branch" value={form.branch.address} onChange={v => update('branch', 'address', v)} /></div></div>}
      {step === 4 && <div className="space-y-6"><div><h2 className="text-xl font-black">Tenant Admin + License + Subscription</h2><p className="text-sm text-stone-500">Semuanya dibuat dalam satu transaksi provisioning.</p></div><div><h3 className="mb-3 font-black">Tenant Admin</h3><div className="grid gap-4 md:grid-cols-3"><Field label="Nama" value={form.admin.name} onChange={v => update('admin', 'name', v)} /><Field label="Email" value={form.admin.email} onChange={v => update('admin', 'email', v)} /><Field label="Password" type="password" value={form.admin.password} onChange={v => update('admin', 'password', v)} /></div></div><div><h3 className="mb-3 font-black">Subscription</h3><div className="grid gap-4 md:grid-cols-3"><Select label="Plan" value={form.plan} onChange={v => setForm(f => ({ ...f, plan: v }))}>{plans.map(plan => <option key={plan.code} value={plan.code}>{plan.name}</option>)}</Select><Select label="Billing Cycle" value={form.billing_cycle} onChange={v => setForm(f => ({ ...f, billing_cycle: v }))}><option value="monthly">Monthly</option><option value="quarterly">Quarterly</option><option value="yearly">Yearly</option></Select><Field label="Harga / Periode" type="number" value={form.amount} onChange={v => setForm(f => ({ ...f, amount: v }))} /><Field label="Subscription No" value={form.subscription_no} onChange={v => setForm(f => ({ ...f, subscription_no: v }))} placeholder="Otomatis" /><Field label="License Expiry" type="date" value={form.expires_at} onChange={v => setForm(f => ({ ...f, expires_at: v }))} /><label className="flex items-center gap-2 rounded-xl border border-stone-200 px-3"><input type="checkbox" checked={form.auto_renew} onChange={e => setForm(f => ({ ...f, auto_renew: e.target.checked }))} />Auto renewal</label></div></div><div className="rounded-2xl bg-stone-50 p-4"><div className="font-black">{selectedPlan.name}</div><div className="mt-1 text-sm text-stone-500">{selectedPlan.features.join(' · ')}</div><div className="mt-2 text-xs text-stone-500">Limit: {selectedPlan.max_users ?? '∞'} users · {selectedPlan.max_branches ?? '∞'} branches</div></div></div>}
      <div className="mt-8 flex justify-between gap-3 border-t border-stone-100 pt-5">{step > 1 ? <button onClick={() => setStep((step - 1) as Step)} className="rounded-xl border border-stone-200 px-4 py-2.5 text-sm font-bold">Sebelumnya</button> : <span />}{step < 4 ? <button onClick={() => { const error = validateStep(); if (error) toast.error(error); else setStep((step + 1) as Step); }} className="rounded-xl bg-stone-900 px-5 py-2.5 text-sm font-bold text-white">Lanjut</button> : <button disabled={saving} onClick={submit} className="rounded-xl bg-amber-700 px-5 py-2.5 text-sm font-bold text-white disabled:opacity-50">{saving ? 'Membuat Tenant...' : '🚀 Buat Tenant'}</button>}</div>
    </section>
  </div></main></div>;
}

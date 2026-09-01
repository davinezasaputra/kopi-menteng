import { useEffect, useMemo, useState } from 'react';
import AdminSidebar from '../components/AdminSidebar';
import api from '../core/api/client';
import { isDeveloper } from '../core/auth/permissions';
import toast from 'react-hot-toast';

type Location = { id: number; code: string; name: string; type: 'store' | 'warehouse' | 'office'; status: string };
type Branch = { id: number; code: string; name: string; type?: string; status: string; locations: Location[] };
type Company = { id: number; code: string; name: string; legal_name?: string; status: string; branches: Branch[] };
type Tenant = { id: number; code: string; name: string; status: string; timezone?: string; currency?: string; companies: Company[] };

type FormState = Record<string, string>;
const emptyCompany = { code: '', name: '', legal_name: '', email: '', phone: '', address: '' };
const emptyBranch = { code: '', name: '', type: 'store', address: '' };
const emptyLocation = { code: '', name: '', type: 'store', address: '' };

const errorMessage = (error: unknown): string => {
  if (!error || typeof error !== 'object' || !('response' in error)) return '';
  const response = (error as { response?: { data?: { message?: string } } }).response;
  return String(response?.data?.message ?? '');
};

function Field({ label, value, onChange }: { label: string; value: string; onChange: (v: string) => void }) {
  return <label className="block"><span className="mb-1 block text-xs font-bold text-stone-500">{label}</span><input value={value} onChange={e => onChange(e.target.value)} className="w-full rounded-xl border border-stone-200 px-3 py-2.5 text-sm outline-none focus:border-amber-500" /></label>;
}

function TypeSelect({ value, onChange }: { value: string; onChange: (v: string) => void }) {
  return <select value={value} onChange={e => onChange(e.target.value)} className="w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm"><option value="store">Store</option><option value="warehouse">Warehouse</option><option value="office">Office</option></select>;
}

export default function DeveloperOrganization() {
  const developer = isDeveloper();
  const [tenants, setTenants] = useState<Tenant[]>([]);
  const [tenantId, setTenantId] = useState('');
  const [expandedCompany, setExpandedCompany] = useState<number | null>(null);
  const [expandedBranch, setExpandedBranch] = useState<number | null>(null);
  const [companyForm, setCompanyForm] = useState<FormState>(emptyCompany);
  const [branchForm, setBranchForm] = useState<FormState>(emptyBranch);
  const [locationForm, setLocationForm] = useState<FormState>(emptyLocation);

  const tenant = tenants.find(t => String(t.id) === tenantId);
  const companies = useMemo(() => tenant?.companies ?? [], [tenant]);

  const load = async () => {
    try {
      const response = await api.get('/v1/developer/tenants');
      setTenants(response.data?.data ?? []);
    } catch (error) { toast.error(errorMessage(error) || 'Data organisasi gagal dimuat.'); }
  };

  useEffect(() => { if (developer) void load(); }, [developer]);

  const addCompany = async () => {
    if (!tenantId || !companyForm.code || !companyForm.name) return toast.error('Tenant, code, dan nama company wajib diisi.');
    try {
      await api.post(`/v1/developer/tenants/${tenantId}/companies`, companyForm);
      toast.success('Company berhasil dibuat.'); setCompanyForm({ ...emptyCompany }); await load();
    } catch (error) { toast.error(errorMessage(error) || 'Company gagal dibuat.'); }
  };

  const addBranch = async (companyId: number) => {
    if (!branchForm.code || !branchForm.name) return toast.error('Code dan nama branch wajib diisi.');
    try {
      await api.post(`/v1/developer/tenants/${tenantId}/companies/${companyId}/branches`, branchForm);
      toast.success('Branch berhasil dibuat.'); setBranchForm({ ...emptyBranch }); await load();
    } catch (error) { toast.error(errorMessage(error) || 'Branch gagal dibuat.'); }
  };

  const addLocation = async (companyId: number, branchId: number) => {
    if (!locationForm.code || !locationForm.name) return toast.error('Code dan nama location wajib diisi.');
    try {
      await api.post(`/v1/developer/tenants/${tenantId}/companies/${companyId}/branches/${branchId}/locations`, locationForm);
      toast.success('Location berhasil dibuat.'); setLocationForm({ ...emptyLocation }); await load();
    } catch (error) { toast.error(errorMessage(error) || 'Location gagal dibuat.'); }
  };

  if (!developer) return <main className="min-h-screen bg-stone-50 p-10"><div className="mx-auto max-w-xl rounded-2xl bg-white p-8 text-center">403 · Developer only</div></main>;

  return <div className="flex min-h-screen bg-stone-50 text-stone-800">
    <AdminSidebar activePage="organization-explorer" />
    <main className="min-w-0 flex-1 overflow-auto p-6 lg:p-8">
      <div className="mx-auto max-w-7xl space-y-6">
        <header><div className="text-xs font-black uppercase tracking-[.18em] text-amber-700">Platform · Organization</div><h1 className="mt-1 text-3xl font-black">Organization Explorer</h1><p className="mt-1 text-sm text-stone-500">Kelola hierarchy Tenant → Company → Branch → Location dari satu tempat.</p></header>

        <section className="rounded-3xl border border-stone-200 bg-white p-5 shadow-sm">
          <div className="grid gap-4 md:grid-cols-[1fr_auto]">
            <label className="block"><span className="mb-1 block text-xs font-bold text-stone-500">Tenant</span><select value={tenantId} onChange={e => setTenantId(e.target.value)} className="w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm"><option value="">Pilih tenant...</option>{tenants.map(t => <option key={t.id} value={t.id}>{t.code} · {t.name}</option>)}</select></label>
            {tenant && <div className="self-end rounded-xl bg-stone-900 px-4 py-2.5 text-sm font-bold text-white">{tenant.companies.length} Company · {tenant.companies.reduce((n,c)=>n+c.branches.length,0)} Branch</div>}
          </div>
        </section>

        {tenant && <>
          <section className="rounded-3xl border border-stone-200 bg-white p-5 shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-3"><div><h2 className="font-black">Companies</h2><p className="text-sm text-stone-500">Satu tenant dapat memiliki banyak legal/business company.</p></div></div>
            <div className="mt-4 grid gap-3 md:grid-cols-3">
              <Field label="Company Code" value={companyForm.code} onChange={v => setCompanyForm(f => ({ ...f, code: v }))} />
              <Field label="Company Name" value={companyForm.name} onChange={v => setCompanyForm(f => ({ ...f, name: v }))} />
              <Field label="Legal Name" value={companyForm.legal_name} onChange={v => setCompanyForm(f => ({ ...f, legal_name: v }))} />
            </div>
            <div className="mt-3 flex justify-end"><button onClick={() => void addCompany()} className="rounded-xl bg-amber-700 px-4 py-2.5 text-sm font-black text-white">＋ Add Company</button></div>
            <div className="mt-5 space-y-3">
              {companies.map(company => {
                const open = expandedCompany === company.id;
                return <div key={company.id} className="rounded-2xl border border-stone-200">
                  <button onClick={() => setExpandedCompany(open ? null : company.id)} className="flex w-full items-center justify-between p-4 text-left"><span><span className="font-black">🏢 {company.name}</span><span className="ml-2 text-xs font-bold text-stone-400">{company.code} · {company.branches.length} branch</span></span><span>{open ? '−' : '+'}</span></button>
                  {open && <div className="border-t border-stone-100 p-4">
                    <div className="grid gap-3 md:grid-cols-4"><Field label="Branch Code" value={branchForm.code} onChange={v => setBranchForm(f => ({ ...f, code: v }))} /><Field label="Branch Name" value={branchForm.name} onChange={v => setBranchForm(f => ({ ...f, name: v }))} /><Field label="Branch Type" value={branchForm.type} onChange={v => setBranchForm(f => ({ ...f, type: v }))} /><Field label="Address" value={branchForm.address} onChange={v => setBranchForm(f => ({ ...f, address: v }))} /></div>
                    <div className="mt-3 flex justify-end"><button onClick={() => void addBranch(company.id)} className="rounded-xl border border-stone-300 px-4 py-2.5 text-sm font-black">＋ Add Branch</button></div>
                    <div className="mt-4 space-y-2">
                      {company.branches.map(branch => {
                        const branchOpen = expandedBranch === branch.id;
                        return <div key={branch.id} className="ml-2 rounded-2xl bg-stone-50">
                          <button onClick={() => setExpandedBranch(branchOpen ? null : branch.id)} className="flex w-full items-center justify-between p-4 text-left"><span><span className="font-black">📍 {branch.name}</span><span className="ml-2 text-xs text-stone-400">{branch.code} · {branch.status}</span></span><span>{branchOpen ? '−' : '+'}</span></button>
                          {branchOpen && <div className="border-t border-stone-200 p-4">
                            <div className="grid gap-3 md:grid-cols-4"><Field label="Location Code" value={locationForm.code} onChange={v => setLocationForm(f => ({ ...f, code: v }))} /><Field label="Location Name" value={locationForm.name} onChange={v => setLocationForm(f => ({ ...f, name: v }))} /><div><span className="mb-1 block text-xs font-bold text-stone-500">Location Type</span><TypeSelect value={locationForm.type} onChange={v => setLocationForm(f => ({ ...f, type: v }))} /></div><Field label="Address" value={locationForm.address} onChange={v => setLocationForm(f => ({ ...f, address: v }))} /></div>
                            <div className="mt-3 flex justify-end"><button onClick={() => void addLocation(company.id, branch.id)} className="rounded-xl bg-stone-900 px-4 py-2.5 text-sm font-black text-white">＋ Add Location</button></div>
                            {branch.locations?.length ? <div className="mt-4 grid gap-2 md:grid-cols-3">{branch.locations.map(location => <div key={location.id} className="rounded-xl border border-stone-200 bg-white p-3"><div className="font-bold">{location.type === 'store' ? '🛒' : location.type === 'warehouse' ? '📦' : '🏛️'} {location.name}</div><div className="mt-1 text-xs text-stone-400">{location.code} · {location.status}</div></div>)}</div> : <div className="mt-4 text-sm text-stone-400">Belum ada Store / Warehouse / Office.</div>}
                          </div>}
                        </div>;
                      })}
                    </div>
                  </div>}
                </div>;
              })}
              {!companies.length && <div className="rounded-xl bg-stone-50 p-4 text-sm text-stone-500">Tenant ini belum memiliki company.</div>}
            </div>
          </section>
        </>}
      </div>
    </main>
  </div>;
}

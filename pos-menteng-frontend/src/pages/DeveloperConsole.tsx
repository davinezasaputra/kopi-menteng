import { useState } from 'react';
import AdminSidebar from '../components/AdminSidebar';
import api from '../core/api/client';
import { isDeveloper } from '../core/auth/permissions';
import toast from 'react-hot-toast';

type FormState = Record<string, string>;

const inputStyle = { width: '100%', border: '1px solid #d6d3d1', borderRadius: 12, padding: '10px 12px', fontSize: 14, outline: 'none', boxSizing: 'border-box' as const };
const buttonStyle = { border: 0, borderRadius: 12, padding: '10px 14px', fontWeight: 800, cursor: 'pointer' };

function Field({ label, value, onChange, type = 'text' }: { label: string; value: string; onChange: (value: string) => void; type?: string }) {
  return <label style={{ display: 'grid', gap: 6 }}><span style={{ fontSize: 12, fontWeight: 800, color: '#78716c' }}>{label}</span><input type={type} value={value} onChange={e => onChange(e.target.value)} style={inputStyle} /></label>;
}

function Card({ title, description, children }: { title: string; description: string; children: React.ReactNode }) {
  return <section style={{ background: '#fff', border: '1px solid #e7e5e4', borderRadius: 20, padding: 20, boxShadow: '0 2px 10px rgba(0,0,0,.04)' }}><h2 style={{ margin: 0, fontSize: 17, fontWeight: 900, color: '#1c1917' }}>{title}</h2><p style={{ margin: '5px 0 16px', fontSize: 13, color: '#78716c' }}>{description}</p>{children}</section>;
}

function Form({ fields, submitLabel, onSubmit }: { fields: Array<{ key: string; label: string; type?: string }>; submitLabel: string; onSubmit: (data: FormState) => Promise<void> }) {
  const [data, setData] = useState<FormState>({});
  const [saving, setSaving] = useState(false);
  const set = (key: string, value: string) => setData(current => ({ ...current, [key]: value }));
  const submit = async (event: React.FormEvent) => { event.preventDefault(); setSaving(true); try { await onSubmit(data); setData({}); } finally { setSaving(false); } };
  return <form onSubmit={submit} style={{ display: 'grid', gap: 12 }}>{fields.map(field => <Field key={field.key} label={field.label} value={data[field.key] ?? ''} type={field.type} onChange={value => set(field.key, value)} />)}<button disabled={saving} style={{ ...buttonStyle, background: '#1c1917', color: '#fff', opacity: saving ? .6 : 1 }}>{saving ? 'Memproses...' : submitLabel}</button></form>;
}

export default function DeveloperConsole() {
  const developer = isDeveloper();
  const [tenantId, setTenantId] = useState('');
  const [tenant, setTenant] = useState<any>(null);
  const [loadingTenant, setLoadingTenant] = useState(false);

  if (!developer) return <div style={{ padding: 40, fontFamily: 'system-ui' }}>Akses Developer Console hanya untuk developer.</div>;

  const errorMessage = (error: unknown) => error && typeof error === 'object' && 'response' in error ? String((error as { response?: { data?: { message?: string } } }).response?.data?.message ?? '') : '';
  const run = async (request: () => Promise<unknown>, success: string) => { try { await request(); toast.success(success); } catch (error) { toast.error(errorMessage(error) || 'Operasi gagal.'); throw error; } };

  const inspectTenant = async () => {
    if (!tenantId) return;
    setLoadingTenant(true);
    try { const response = await api.get(`/v1/organizations/tenants/${tenantId}`); setTenant(response.data?.data ?? null); }
    catch (error) { setTenant(null); toast.error(errorMessage(error) || 'Tenant tidak ditemukan.'); }
    finally { setLoadingTenant(false); }
  };

  return <div style={{ minHeight: '100vh', display: 'flex', background: '#f5f5f4', fontFamily: 'system-ui, sans-serif' }}>
    <AdminSidebar activePage="developer-console" />
    <main style={{ flex: 1, padding: 28, overflow: 'auto' }}>
      <div style={{ maxWidth: 1180, margin: '0 auto' }}>
        <div style={{ marginBottom: 24 }}>
          <div style={{ fontSize: 12, fontWeight: 900, letterSpacing: '.14em', color: '#b45309' }}>PLATFORM · GOD MODE</div>
          <h1 style={{ margin: '4px 0', fontSize: 30, fontWeight: 950, color: '#1c1917' }}>Developer Console</h1>
          <p style={{ margin: 0, color: '#78716c' }}>Provisioning dan inspeksi struktur multi-tenant tanpa terikat permission ERP tenant.</p>
        </div>

        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(210px, 1fr))', gap: 14, marginBottom: 24 }}>
          {[
            ['Identity', 'Developer', '🔐'],
            ['Authorization', 'God Mode', '⚡'],
            ['Scope', 'Platform + Tenant', '🌐'],
            ['Provisioning', 'Enabled', '🏗️'],
          ].map(([label, value, icon]) => <div key={label} style={{ background: '#1c1917', color: '#fff', borderRadius: 18, padding: 17 }}><div style={{ fontSize: 20 }}>{icon}</div><div style={{ marginTop: 9, fontSize: 11, color: '#a8a29e', fontWeight: 800 }}>{label}</div><div style={{ fontSize: 16, fontWeight: 900 }}>{value}</div></div>)}
        </div>

        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))', gap: 18 }}>
          <Card title="Create Tenant" description="Membuat tenant baru sekaligus standard tenant roles.">
            <Form fields={[{ key: 'name', label: 'Tenant Name' }, { key: 'code', label: 'Tenant Code' }, { key: 'timezone', label: 'Timezone' }, { key: 'currency', label: 'Currency' }]} submitLabel="Provision Tenant" onSubmit={data => run(() => api.post('/v1/organizations/tenants', data), 'Tenant berhasil dibuat.')} />
          </Card>

          <Card title="Create Company" description="Tambahkan company ke tenant tertentu.">
            <Form fields={[{ key: 'tenant_id', label: 'Tenant ID' }, { key: 'code', label: 'Company Code' }, { key: 'name', label: 'Company Name' }, { key: 'legal_name', label: 'Legal Name' }, { key: 'email', label: 'Email' }, { key: 'phone', label: 'Phone' }, { key: 'address', label: 'Address' }]} submitLabel="Create Company" onSubmit={data => run(() => api.post('/v1/organizations/companies', data), 'Company berhasil dibuat.')} />
          </Card>

          <Card title="Create Branch" description="Tambahkan branch pada company tertentu.">
            <Form fields={[{ key: 'company_id', label: 'Company ID' }, { key: 'code', label: 'Branch Code' }, { key: 'name', label: 'Branch Name' }, { key: 'type', label: 'Type' }, { key: 'phone', label: 'Phone' }, { key: 'address', label: 'Address' }, { key: 'latitude', label: 'Latitude' }, { key: 'longitude', label: 'Longitude' }]} submitLabel="Create Branch" onSubmit={data => run(() => api.post('/v1/organizations/branches', data), 'Branch berhasil dibuat.')} />
          </Card>

          <Card title="Create Warehouse" description="Tambahkan warehouse pada branch tertentu.">
            <Form fields={[{ key: 'branch_id', label: 'Branch ID' }, { key: 'code', label: 'Warehouse Code' }, { key: 'name', label: 'Warehouse Name' }, { key: 'type', label: 'Type' }, { key: 'is_default', label: 'Default (true/false)' }]} submitLabel="Create Warehouse" onSubmit={data => run(() => api.post('/v1/organizations/warehouses', { ...data, is_default: data.is_default === 'true' }), 'Warehouse berhasil dibuat.')} />
          </Card>

          <Card title="Create Tenant Admin" description="Membuat user tenant-admin beserta membership primary.">
            <Form fields={[{ key: 'tenant_id', label: 'Tenant ID' }, { key: 'company_id', label: 'Company ID' }, { key: 'branch_id', label: 'Branch ID' }, { key: 'name', label: 'Name' }, { key: 'email', label: 'Email' }, { key: 'password', label: 'Password', type: 'password' }]} submitLabel="Create Tenant Admin" onSubmit={data => run(() => api.post('/v1/organizations/tenant-admins', data), 'Tenant admin berhasil dibuat.')} />
          </Card>

          <Card title="Inspect Tenant" description="Ambil hierarchy tenant → companies → branches → warehouses.">
            <div style={{ display: 'grid', gap: 12 }}>
              <Field label="Tenant ID" value={tenantId} onChange={setTenantId} />
              <button onClick={inspectTenant} disabled={loadingTenant} style={{ ...buttonStyle, background: '#b45309', color: '#fff', opacity: loadingTenant ? .6 : 1 }}>{loadingTenant ? 'Memuat...' : 'Inspect Tenant'}</button>
              {tenant && <pre style={{ margin: 0, maxHeight: 320, overflow: 'auto', padding: 14, borderRadius: 14, background: '#1c1917', color: '#d6d3d1', fontSize: 11 }}>{JSON.stringify(tenant, null, 2)}</pre>}
            </div>
          </Card>
        </div>
      </div>
    </main>
  </div>;
}

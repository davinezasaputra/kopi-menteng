import { useEffect, useState } from 'react';
import toast from 'react-hot-toast';
import AdminSidebar from '../components/AdminSidebar';
import { api } from '../core/api/client';

type RoleOption = { code: string; label: string };
type UserRow = { id: number | string; name: string; email: string; role?: string | null; memberships?: Array<{ role?: { code?: string; name?: string } | null }> };

const roleOptions: RoleOption[] = [
  { code: 'cashier', label: 'Kasir — POS' },
  { code: 'branch-manager', label: 'Branch Manager' },
  { code: 'sales-manager', label: 'Sales Manager' },
  { code: 'purchasing-manager', label: 'Purchasing Manager' },
  { code: 'warehouse-manager', label: 'Warehouse Manager' },
  { code: 'accountant', label: 'Accountant' },
  { code: 'hr-manager', label: 'HR Manager' },
];
const emptyForm = { name: '', email: '', password: '', role_code: 'cashier' };

const extractRows = <T,>(body: unknown): T[] => {
  if (Array.isArray(body)) return body as T[];
  if (!body || typeof body !== 'object') return [];
  const data = (body as { data?: unknown }).data;
  return Array.isArray(data) ? data as T[] : [];
};

export default function Users() {
  const [users, setUsers] = useState<UserRow[]>([]);
  const [showModal, setShowModal] = useState(false);
  const [formData, setFormData] = useState(emptyForm);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  const fetchUsers = async () => {
    setLoading(true);
    try { const response = await api.get('/users'); setUsers(extractRows<UserRow>(response.data)); }
    catch (error: any) { toast.error(error?.response?.data?.message || 'Gagal memuat data akun.'); }
    finally { setLoading(false); }
  };
  useEffect(() => { void fetchUsers(); }, []);

  const handleSave = async (event: React.FormEvent) => {
    event.preventDefault(); setSaving(true); const toastId = toast.loading('Menyimpan akun...');
    try { await api.post('/users', formData); toast.success('Akun karyawan berhasil ditambahkan.', { id: toastId }); setShowModal(false); setFormData(emptyForm); await fetchUsers(); }
    catch (error: any) { toast.error(error?.response?.data?.message || 'Gagal menambahkan akun karyawan.', { id: toastId }); }
    finally { setSaving(false); }
  };
  const handleDelete = async (id: number | string) => {
    if (!window.confirm('Yakin ingin menonaktifkan akun karyawan ini?')) return;
    try { await api.delete(`/users/${id}`); toast.success('Akun karyawan dinonaktifkan.'); await fetchUsers(); }
    catch (error: any) { toast.error(error?.response?.data?.message || 'Gagal menonaktifkan akun.'); }
  };
  const roleName = (user: UserRow) => user.memberships?.[0]?.role?.name || user.memberships?.[0]?.role?.code || user.role || '-';

  return <div className="flex h-screen w-full bg-stone-50 font-sans text-stone-800">
    <AdminSidebar activePage="users" />
    <div className="flex flex-1 flex-col overflow-hidden">
      <header className="flex min-h-20 items-center justify-between gap-4 border-b border-stone-200 bg-white px-8 shadow-sm"><div><h1 className="text-xl font-bold">Manajemen Akun</h1><p className="text-sm text-stone-500">Kelola akun login dan role ERP pada organization scope aktif.</p></div><button onClick={() => setShowModal(true)} className="rounded-xl bg-amber-700 px-5 py-2.5 font-bold text-white shadow-md hover:bg-amber-800">+ Tambah Akun Karyawan</button></header>
      <main className="flex-1 overflow-y-auto p-8"><div className="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm"><table className="w-full border-collapse text-left"><thead className="bg-stone-100 text-sm uppercase tracking-wider text-stone-500"><tr><th className="p-4 font-medium">Nama</th><th className="p-4 font-medium">Email</th><th className="p-4 font-medium">Role ERP</th><th className="p-4 text-center font-medium">Aksi</th></tr></thead><tbody className="divide-y divide-stone-100">{loading&&<tr><td colSpan={4} className="p-8 text-center text-stone-500">Memuat akun...</td></tr>}{!loading&&users.length===0&&<tr><td colSpan={4} className="p-8 text-center text-stone-500">Belum ada akun pada scope aktif.</td></tr>}{!loading&&users.map(user=><tr key={user.id} className="hover:bg-stone-50"><td className="p-4 font-bold">{user.name}</td><td className="p-4 text-stone-600">{user.email}</td><td className="p-4"><span className="rounded-full bg-blue-100 px-3 py-1 text-xs font-bold uppercase text-blue-700">{roleName(user)}</span></td><td className="p-4 text-center"><button onClick={()=>void handleDelete(user.id)} className="rounded-lg bg-red-100 px-3 py-1.5 text-xs font-bold text-red-700 hover:bg-red-200">Nonaktifkan</button></td></tr>)}</tbody></table></div></main>
    </div>
    {showModal&&<div className="fixed inset-0 z-50 flex items-center justify-center bg-stone-900/60 p-5 backdrop-blur-sm"><div className="w-full max-w-lg rounded-2xl bg-white p-8 shadow-2xl"><div className="mb-6 flex items-start justify-between"><div><h2 className="text-2xl font-bold">Tambah Akun Karyawan</h2><p className="mt-1 text-sm text-stone-500">Pilih role ERP untuk membership akun.</p></div><button type="button" onClick={()=>setShowModal(false)} className="text-stone-400 hover:text-stone-700">✕</button></div><form onSubmit={handleSave} className="space-y-4"><label className="block text-sm font-bold text-stone-600">Nama Lengkap<input required value={formData.name} onChange={e=>setFormData({...formData,name:e.target.value})} className="mt-1 w-full rounded-xl border border-stone-300 p-3 font-normal outline-none focus:border-amber-500" /></label><label className="block text-sm font-bold text-stone-600">Email Login<input type="email" required value={formData.email} onChange={e=>setFormData({...formData,email:e.target.value})} className="mt-1 w-full rounded-xl border border-stone-300 p-3 font-normal outline-none focus:border-amber-500" /></label><label className="block text-sm font-bold text-stone-600">Password<input type="password" required minLength={8} value={formData.password} onChange={e=>setFormData({...formData,password:e.target.value})} className="mt-1 w-full rounded-xl border border-stone-300 p-3 font-normal outline-none focus:border-amber-500" /></label><label className="block text-sm font-bold text-stone-600">Role ERP<select value={formData.role_code} onChange={e=>setFormData({...formData,role_code:e.target.value})} className="mt-1 w-full rounded-xl border border-stone-300 bg-white p-3 font-normal outline-none focus:border-amber-500">{roleOptions.map(role=><option key={role.code} value={role.code}>{role.label}</option>)}</select></label><div className="flex gap-3 border-t border-stone-100 pt-5"><button type="button" onClick={()=>setShowModal(false)} className="w-1/3 rounded-xl bg-stone-100 py-3 font-bold text-stone-600">Batal</button><button type="submit" disabled={saving} className="w-2/3 rounded-xl bg-amber-700 py-3 font-bold text-white shadow-md disabled:opacity-50">{saving?'Menyimpan...':'Simpan Akun'}</button></div></form></div></div>}
  </div>;
}

import { useState, useEffect } from 'react';
import toast from 'react-hot-toast';
import axios from 'axios';
import AdminSidebar from '../components/AdminSidebar';

export default function Users() {
  const [users, setUsers] = useState<any[]>([]);
  const [showModal, setShowModal] = useState(false);
  const [formData, setFormData] = useState({ name: '', email: '', password: '', role: 'kasir' });

  useEffect(() => {
    fetchUsers();
  }, []);

  const fetchUsers = async () => {
    try {
      const response = await axios.get('http://localhost:8000/api/users', {
        headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }
      });
      setUsers(response.data.data);
    } catch (error) {
      toast.error('Gagal memuat data karyawan');
    }
  };

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    const toastId = toast.loading('Menyimpan akun...');
    try {
      await axios.post('http://localhost:8000/api/users', formData, {
        headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }
      });
      toast.success('Karyawan baru berhasil ditambahkan!', { id: toastId });
      setShowModal(false);
      setFormData({ name: '', email: '', password: '', role: 'kasir' });
      fetchUsers();
    } catch (error: any) {
      toast.error(error.response?.data?.message || 'Email mungkin sudah dipakai.', { id: toastId });
    }
  };

  const handleDelete = async (id: number) => {
    if (!window.confirm('Yakin ingin memberhentikan karyawan ini?')) return;
    try {
      await axios.delete(`http://localhost:8000/api/users/${id}`, {
        headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }
      });
      toast.success('Akun dihapus.');
      fetchUsers();
    } catch (error: any) {
      toast.error(error.response?.data?.message || 'Gagal menghapus.');
    }
  };

  return (
    <div className="flex h-screen w-full bg-stone-50 font-sans text-stone-800">
      <AdminSidebar activePage="users"/>
      
      <div className="flex-1 flex flex-col overflow-hidden">
        <header className="h-20 bg-white border-b border-stone-200 flex items-center justify-between px-8 shadow-sm">
          <h1 className="text-xl font-bold text-stone-800">Manajemen Karyawan</h1>
          <button onClick={() => setShowModal(true)} className="bg-amber-700 hover:bg-amber-800 text-white px-5 py-2.5 rounded-xl font-bold transition shadow-md">
            + Tambah Karyawan
          </button>
        </header>

        <main className="flex-1 overflow-y-auto p-8">
          <div className="bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden">
            <table className="w-full text-left border-collapse">
              <thead>
                <tr className="bg-stone-100 text-stone-500 text-sm uppercase tracking-wider border-b border-stone-200">
                  <th className="p-4 font-medium">Nama Pegawai</th>
                  <th className="p-4 font-medium">Email Akses</th>
                  <th className="p-4 font-medium">Jabatan</th>
                  <th className="p-4 font-medium text-center">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-stone-100">
                {users.map((u) => (
                  <tr key={u.id} className="hover:bg-stone-50 transition">
                    <td className="p-4 font-bold text-stone-800">{u.name}</td>
                    <td className="p-4 text-stone-600">{u.email}</td>
                    <td className="p-4">
                      <span className={`px-3 py-1 rounded-full text-xs font-bold uppercase ${u.role === 'admin' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700'}`}>
                        {u.role}
                      </span>
                    </td>
                    <td className="p-4 flex justify-center">
                      <button onClick={() => handleDelete(u.id)} className="p-2 text-red-500 hover:bg-red-50 rounded-lg transition" title="Hapus Akun">🗑️</button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </main>
      </div>

      {showModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-stone-900/60 backdrop-blur-sm">
          <div className="w-full max-w-md rounded-2xl bg-white p-8 shadow-2xl">
            <h2 className="mb-6 text-2xl font-bold text-stone-800">Registrasi Pegawai Baru</h2>
            <form onSubmit={handleSave} className="space-y-4">
              <div>
                <label className="block text-sm font-bold text-stone-600 mb-1">Nama Lengkap</label>
                <input type="text" required value={formData.name} onChange={e => setFormData({...formData, name: e.target.value})} className="w-full rounded-xl border border-stone-300 p-3 outline-none focus:border-amber-500" />
              </div>
              <div>
                <label className="block text-sm font-bold text-stone-600 mb-1">Email Login</label>
                <input type="email" required value={formData.email} onChange={e => setFormData({...formData, email: e.target.value})} className="w-full rounded-xl border border-stone-300 p-3 outline-none focus:border-amber-500" />
              </div>
              <div>
                <label className="block text-sm font-bold text-stone-600 mb-1">Password</label>
                <input type="password" required minLength={6} value={formData.password} onChange={e => setFormData({...formData, password: e.target.value})} className="w-full rounded-xl border border-stone-300 p-3 outline-none focus:border-amber-500" />
              </div>
              <div>
                <label className="block text-sm font-bold text-stone-600 mb-1">Hak Akses (Role)</label>
                <select value={formData.role} onChange={e => setFormData({...formData, role: e.target.value})} className="w-full rounded-xl border border-stone-300 p-3 outline-none focus:border-amber-500">
                  <option value="kasir">Kasir (Hanya POS)</option>
                  <option value="manager">Manager (Akses Penuh)</option>
                  <option value="owner"> Owner (Akses Penuh)</option>
                </select>
              </div>
              <div className="mt-6 flex gap-4 pt-4 border-t border-stone-100">
                <button type="button" onClick={() => setShowModal(false)} className="w-1/3 rounded-xl bg-stone-100 py-3 font-bold text-stone-500">Batal</button>
                <button type="submit" className="w-2/3 rounded-xl bg-amber-700 py-3 font-bold text-white shadow-md">Simpan Pegawai</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}

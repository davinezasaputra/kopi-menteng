import { useState, useEffect } from 'react';
import axios from 'axios';
import toast from 'react-hot-toast';
import AdminSidebar from '../components/AdminSidebar';

export default function Customers() {
  const [customers, setCustomers] = useState<any[]>([]);
  const [showModal, setShowModal] = useState(false);
  const [formData, setFormData] = useState({ name: '', phone: '', tier: 'silver' });

  useEffect(() => {
    fetchCustomers();
  }, []);

  const fetchCustomers = async () => {
    try {
      const res = await axios.get('http://localhost:8000/api/customers', {
        headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }
      });
      setCustomers(res.data.data);
    } catch (error) {
      toast.error('Gagal memuat data pelanggan.');
    }
  };

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    const toastId = toast.loading('Menyimpan data member...');
    try {
      await axios.post('http://localhost:8000/api/customers', formData, {
        headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }
      });
      toast.success('Member berhasil didaftarkan!', { id: toastId });
      setShowModal(false);
      setFormData({ name: '', phone: '', tier: 'silver' });
      fetchCustomers();
    } catch (error: any) {
      toast.error(error.response?.data?.message || 'Nomor HP mungkin sudah terdaftar.', { id: toastId });
    }
  };

  return (
    <div className="flex h-screen w-full bg-stone-50 font-sans text-stone-800">
      <AdminSidebar activePage="customers" />
      
      <div className="flex-1 flex flex-col overflow-hidden">
        <header className="h-20 bg-white border-b border-stone-200 flex items-center justify-between px-8 shadow-sm">
          <h1 className="text-xl font-bold text-stone-800">Database Pelanggan (CRM)</h1>
          <button onClick={() => setShowModal(true)} className="bg-amber-700 hover:bg-amber-800 text-white px-5 py-2.5 rounded-xl font-bold transition shadow-md">
            + Daftar Member Baru
          </button>
        </header>

        <main className="flex-1 overflow-y-auto p-8">
          <div className="bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden">
            <table className="w-full text-left border-collapse">
              <thead className="bg-stone-100 text-stone-500 text-sm uppercase">
                <tr><th className="p-4">Nama Pelanggan</th><th className="p-4">No. HP (WhatsApp)</th><th className="p-4">Poin Loyalitas</th><th className="p-4">Tier Member</th></tr>
              </thead>
              <tbody className="divide-y divide-stone-100">
                {customers.map(c => (
                  <tr key={c.id} className="hover:bg-stone-50 transition">
                    <td className="p-4 font-bold text-stone-800">{c.name}</td>
                    <td className="p-4 text-stone-600">{c.phone}</td>
                    <td className="p-4 font-black text-amber-700">{c.points} Pts</td>
                    <td className="p-4">
                      <span className={`px-3 py-1 rounded-full text-xs font-bold uppercase ${c.tier === 'vip' ? 'bg-purple-100 text-purple-700' : c.tier === 'gold' ? 'bg-yellow-100 text-yellow-700' : 'bg-stone-200 text-stone-600'}`}>
                        {c.tier}
                      </span>
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
            <h2 className="mb-6 text-2xl font-bold text-stone-800">Registrasi Member</h2>
            <form onSubmit={handleSave} className="space-y-4">
              <div>
                <label className="block text-sm font-bold text-stone-600 mb-1">Nama Lengkap</label>
                <input type="text" required value={formData.name} onChange={e => setFormData({...formData, name: e.target.value})} className="w-full rounded-xl border border-stone-300 p-3 outline-none focus:border-amber-500" />
              </div>
              <div>
                <label className="block text-sm font-bold text-stone-600 mb-1">Nomor HP</label>
                <input type="text" required value={formData.phone} onChange={e => setFormData({...formData, phone: e.target.value})} className="w-full rounded-xl border border-stone-300 p-3 outline-none focus:border-amber-500" placeholder="08..." />
              </div>
              <div>
                <label className="block text-sm font-bold text-stone-600 mb-1">Level (Tier)</label>
                <select value={formData.tier} onChange={e => setFormData({...formData, tier: e.target.value})} className="w-full rounded-xl border border-stone-300 p-3 outline-none focus:border-amber-500">
                  <option value="silver">Silver (Reguler)</option>
                  <option value="gold">Gold (Diskon Khusus)</option>
                  <option value="vip">VIP (Prioritas)</option>
                </select>
              </div>
              <div className="mt-6 flex gap-4 pt-4 border-t border-stone-100">
                <button type="button" onClick={() => setShowModal(false)} className="w-1/3 rounded-xl bg-stone-100 py-3 font-bold text-stone-500">Batal</button>
                <button type="submit" className="w-2/3 rounded-xl bg-amber-700 py-3 font-bold text-white shadow-md">Simpan Data</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
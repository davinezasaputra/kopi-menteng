import { useState, useEffect } from 'react';
import axios from 'axios';
import toast from 'react-hot-toast';
import AdminSidebar from '../components/AdminSidebar';

export default function Hrm() {
  const [activeTab, setActiveTab] = useState<'attendance' | 'payroll'>('payroll');
  const [attendances, setAttendances] = useState<any[]>([]);
  const [payrolls, setPayrolls] = useState<any[]>([]);
  const [users, setUsers] = useState<any[]>([]);
  
  const [showModal, setShowModal] = useState(false);
  const [form, setForm] = useState({ user_id: '', period: new Date().toISOString().slice(0, 7), base_salary: '', bonus: '' });

  useEffect(() => {
    if (activeTab === 'attendance') fetchAttendances();
    else fetchPayrolls();
    fetchUsers();
  }, [activeTab]);

  const fetchAttendances = async () => {
    const res = await axios.get('http://localhost:8000/api/hrm/attendances', { headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }});
    setAttendances(res.data.data);
  };

  const fetchPayrolls = async () => {
    const res = await axios.get('http://localhost:8000/api/hrm/payrolls', { headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }});
    setPayrolls(res.data.data);
  };

  const fetchUsers = async () => {
    const res = await axios.get('http://localhost:8000/api/users', { headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }});
    setUsers(res.data.data);
  };

  const handleGeneratePayroll = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await axios.post('http://localhost:8000/api/hrm/payrolls', form, { headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }});
      toast.success('Slip Gaji Diterbitkan!');
      setShowModal(false);
      fetchPayrolls();
    } catch (e) { toast.error('Gagal menerbitkan gaji'); }
  };

  const handleMarkAsPaid = async (id: number) => {
    try {
      await axios.put(`http://localhost:8000/api/hrm/payrolls/${id}/pay`, {}, { headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }});
      toast.success('Status diubah menjadi Dibayar!');
      fetchPayrolls();
    } catch (e) { toast.error('Gagal update status'); }
  };

  const formatRp = (angka: number) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka || 0);

  return (
    <div className="flex h-screen w-full bg-stone-50 font-sans text-stone-800">
      <AdminSidebar activePage="hrm" />
      
      <div className="flex-1 flex flex-col overflow-hidden">
        <header className="h-20 bg-white border-b border-stone-200 flex items-center justify-between px-8 shadow-sm">
          <h1 className="text-xl font-bold text-stone-800">HRIS & Penggajian</h1>
          <div className="flex gap-2">
            <button onClick={() => setActiveTab('attendance')} className={`px-4 py-2 font-bold rounded-lg transition ${activeTab === 'attendance' ? 'bg-stone-800 text-white' : 'bg-stone-100 text-stone-500 hover:bg-stone-200'}`}>Data Absensi</button>
            <button onClick={() => setActiveTab('payroll')} className={`px-4 py-2 font-bold rounded-lg transition ${activeTab === 'payroll' ? 'bg-stone-800 text-white' : 'bg-stone-100 text-stone-500 hover:bg-stone-200'}`}>Slip Gaji</button>
          </div>
        </header>

        <main className="flex-1 overflow-y-auto p-8">
          <div className="mb-4 flex justify-end">
            {activeTab === 'payroll' && (
              <button onClick={() => setShowModal(true)} className="bg-amber-700 text-white px-4 py-2 rounded-lg font-bold shadow-md hover:bg-amber-800">+ Buat Slip Gaji</button>
            )}
          </div>

          <div className="bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden">
            {activeTab === 'attendance' ? (
              <table className="w-full text-left border-collapse">
                <thead className="bg-stone-100 text-stone-500 text-sm uppercase">
                  <tr><th className="p-4">Tanggal & Waktu</th><th className="p-4">Nama Pegawai</th><th className="p-4">Status</th></tr>
                </thead>
                <tbody className="divide-y divide-stone-100">
                  {attendances.map(att => (
                    <tr key={att.id} className="hover:bg-stone-50">
                      <td className="p-4 font-bold text-stone-700">{new Date(att.clock_in).toLocaleString('id-ID')}</td>
                      <td className="p-4 font-bold">{att.user?.name}</td>
                      <td className="p-4 uppercase text-xs font-bold text-green-600">{att.status}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            ) : (
              <table className="w-full text-left border-collapse">
                <thead className="bg-stone-100 text-stone-500 text-sm uppercase">
                  <tr><th className="p-4">Periode</th><th className="p-4">Pegawai</th><th className="p-4 text-right">Total Gaji</th><th className="p-4 text-center">Status</th><th className="p-4 text-center">Aksi</th></tr>
                </thead>
                <tbody className="divide-y divide-stone-100">
                  {payrolls.map(pay => (
                    <tr key={pay.id} className="hover:bg-stone-50">
                      <td className="p-4 font-bold text-stone-600">{pay.period}</td>
                      <td className="p-4 font-bold">{pay.user?.name}</td>
                      <td className="p-4 text-right font-black text-amber-700">{formatRp(pay.total_salary)}</td>
                      <td className="p-4 text-center">
                        <span className={`px-3 py-1 rounded-full text-xs font-bold uppercase ${pay.is_paid ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                          {pay.is_paid ? 'LUNAS' : 'PENDING'}
                        </span>
                      </td>
                      <td className="p-4 text-center">
                        {!pay.is_paid && (
                          <button onClick={() => handleMarkAsPaid(pay.id)} className="text-xs bg-stone-800 text-white px-3 py-1 rounded hover:bg-stone-900">Bayar</button>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        </main>
      </div>

      {showModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-stone-900/60 backdrop-blur-sm">
          <div className="w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl">
            <h2 className="mb-4 text-lg font-bold">Terbitkan Slip Gaji</h2>
            <form onSubmit={handleGeneratePayroll} className="space-y-4">
              <select required value={form.user_id} onChange={e => setForm({...form, user_id: e.target.value})} className="w-full border p-3 rounded-xl bg-stone-50 font-bold">
                <option value="" disabled>-- Pilih Karyawan --</option>
                {users.map(u => <option key={u.id} value={u.id}>{u.name} ({u.role})</option>)}
              </select>
              <input type="month" required value={form.period} onChange={e => setForm({...form, period: e.target.value})} className="w-full border p-3 rounded-xl bg-stone-50" />
              <input type="number" placeholder="Gaji Pokok (Rp)" required value={form.base_salary} onChange={e => setForm({...form, base_salary: e.target.value})} className="w-full border p-3 rounded-xl bg-stone-50" />
              <input type="number" placeholder="Bonus / Lembur (Rp)" value={form.bonus} onChange={e => setForm({...form, bonus: e.target.value})} className="w-full border p-3 rounded-xl bg-stone-50" />
              <div className="flex gap-2 pt-2"><button type="button" onClick={() => setShowModal(false)} className="flex-1 py-3 bg-stone-100 rounded-xl font-bold text-stone-500">Batal</button><button type="submit" className="flex-1 py-3 bg-amber-700 text-white rounded-xl font-bold">Simpan</button></div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
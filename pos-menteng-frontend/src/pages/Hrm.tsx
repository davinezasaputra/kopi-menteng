import { useState, useEffect } from 'react';
import axios from 'axios';
import toast from 'react-hot-toast';
import AdminSidebar from '../components/AdminSidebar';

type SummaryData = {
  total_employees: number;
  present_today: number;
  late_today: number;
  pending_payroll: number;
  monthly_payroll_total: number;
};

export default function Hrm() {
  const [activeTab, setActiveTab] = useState<'attendance' | 'payroll'>('payroll');
  const [attendances, setAttendances] = useState<any[]>([]);
  const [payrolls, setPayrolls] = useState<any[]>([]);
  const [employees, setEmployees] = useState<any[]>([]);
  const [summary, setSummary] = useState<SummaryData>({
    total_employees: 0,
    present_today: 0,
    late_today: 0,
    pending_payroll: 0,
    monthly_payroll_total: 0,
  });

  const [showModal, setShowModal] = useState(false);
  const [form, setForm] = useState({
    employee_id: '',
    period: new Date().toISOString().slice(0, 7),
    base_salary: '',
    allowance: '',
    deduction: '',
    bonus: '',
  });

  useEffect(() => {
    fetchEmployees();
    fetchSummary();
    if (activeTab === 'attendance') fetchAttendances();
    else fetchPayrolls();
  }, [activeTab]);

  const fetchAttendances = async () => {
    const res = await axios.get('http://localhost:8000/api/hrm/attendances', { headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }});
    setAttendances(res.data.data);
  };

  const fetchPayrolls = async () => {
    const res = await axios.get('http://localhost:8000/api/hrm/payrolls', { headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }});
    setPayrolls(res.data.data);
  };

  const fetchEmployees = async () => {
    const res = await axios.get('http://localhost:8000/api/employees', { headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }});
    setEmployees(res.data.data);
  };

  const fetchSummary = async () => {
    const res = await axios.get('http://localhost:8000/api/hrm/summary', { headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }});
    setSummary(res.data.data);
  };

  const handleGeneratePayroll = async (e: React.FormEvent) => {
    e.preventDefault();

    const payload = {
      employee_id: form.employee_id,
      period: form.period,
      base_salary: Number(form.base_salary || 0),
      allowance: Number(form.allowance || form.bonus || 0),
      deduction: Number(form.deduction || 0),
    };

    try {
      await axios.post('http://localhost:8000/api/hrm/payrolls', payload, { headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }});
      toast.success('Slip Gaji Diterbitkan!');
      setShowModal(false);
      setForm({
        employee_id: '',
        period: new Date().toISOString().slice(0, 7),
        base_salary: '',
        allowance: '',
        deduction: '',
        bonus: '',
      });
      fetchPayrolls();
      fetchSummary();
    } catch (e) {
      toast.error('Gagal menerbitkan gaji');
    }
  };

  const handleMarkAsPaid = async (id: number) => {
    try {
      await axios.put(`http://localhost:8000/api/hrm/payrolls/${id}/pay`, {}, { headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }});
      toast.success('Status diubah menjadi Dibayar!');
      fetchPayrolls();
      fetchSummary();
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
          <div className="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
            <div className="bg-white p-4 rounded-2xl border border-stone-200 shadow-sm">
              <div className="text-xs uppercase tracking-wide text-stone-500">Total Karyawan</div>
              <div className="mt-2 text-2xl font-black text-stone-800">{summary.total_employees}</div>
            </div>
            <div className="bg-white p-4 rounded-2xl border border-stone-200 shadow-sm">
              <div className="text-xs uppercase tracking-wide text-stone-500">Hadir Hari Ini</div>
              <div className="mt-2 text-2xl font-black text-green-600">{summary.present_today}</div>
            </div>
            <div className="bg-white p-4 rounded-2xl border border-stone-200 shadow-sm">
              <div className="text-xs uppercase tracking-wide text-stone-500">Terlambat</div>
              <div className="mt-2 text-2xl font-black text-amber-600">{summary.late_today}</div>
            </div>
            <div className="bg-white p-4 rounded-2xl border border-stone-200 shadow-sm">
              <div className="text-xs uppercase tracking-wide text-stone-500">Gaji Bulan Ini</div>
              <div className="mt-2 text-lg font-black text-amber-700">{formatRp(summary.monthly_payroll_total)}</div>
            </div>
          </div>

          <div className="mb-4 flex justify-end">
            {activeTab === 'payroll' && (
              <button onClick={() => setShowModal(true)} className="bg-amber-700 text-white px-4 py-2 rounded-lg font-bold shadow-md hover:bg-amber-800">+ Buat Slip Gaji</button>
            )}
          </div>

          <div className="bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden">
            {activeTab === 'attendance' ? (
              <table className="w-full text-left border-collapse">
                <thead className="bg-stone-100 text-stone-500 text-sm uppercase">
                  <tr><th className="p-4">Tanggal</th><th className="p-4">Nama Pegawai</th><th className="p-4">Jam Masuk</th><th className="p-4">Status</th></tr>
                </thead>
                <tbody className="divide-y divide-stone-100">
                  {attendances.map(att => (
                    <tr key={att.id} className="hover:bg-stone-50">
                      <td className="p-4 font-bold text-stone-700">{new Date(att.tanggal).toLocaleDateString('id-ID')}</td>
                      <td className="p-4 font-bold">{att.employee?.name}</td>
                      <td className="p-4 font-bold text-stone-600">{att.clock_in ? new Date(att.clock_in).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) : '-'}</td>
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
                      <td className="p-4 font-bold">{pay.employee?.name}</td>
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
              <select required value={form.employee_id} onChange={e => setForm({...form, employee_id: e.target.value})} className="w-full border p-3 rounded-xl bg-stone-50 font-bold">
                <option value="" disabled>-- Pilih Karyawan --</option>
                {employees.map(emp => <option key={emp.id} value={emp.id}>{emp.name} ({emp.position})</option>)}
              </select>
              <input type="month" required value={form.period} onChange={e => setForm({...form, period: e.target.value})} className="w-full border p-3 rounded-xl bg-stone-50" />
              <input type="number" placeholder="Gaji Pokok (Rp)" required value={form.base_salary} onChange={e => setForm({...form, base_salary: e.target.value})} className="w-full border p-3 rounded-xl bg-stone-50" />
              <input type="number" placeholder="Tunjangan (Rp)" value={form.allowance} onChange={e => setForm({...form, allowance: e.target.value})} className="w-full border p-3 rounded-xl bg-stone-50" />
              <input type="number" placeholder="Potongan (Rp)" value={form.deduction} onChange={e => setForm({...form, deduction: e.target.value})} className="w-full border p-3 rounded-xl bg-stone-50" />
              <input type="number" placeholder="Bonus lama / alias (opsional)" value={form.bonus} onChange={e => setForm({...form, bonus: e.target.value})} className="w-full border p-3 rounded-xl bg-stone-50" />
              <div className="flex gap-2 pt-2"><button type="button" onClick={() => setShowModal(false)} className="flex-1 py-3 bg-stone-100 rounded-xl font-bold text-stone-500">Batal</button><button type="submit" className="flex-1 py-3 bg-amber-700 text-white rounded-xl font-bold">Simpan</button></div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
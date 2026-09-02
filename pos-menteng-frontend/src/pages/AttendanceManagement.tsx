import { useEffect, useMemo, useState } from 'react';
import toast from 'react-hot-toast';
import AdminSidebar from '../components/AdminSidebar';
import { api } from '../core/api/client';

type Employee = { id: string; name: string; position?: string };
type Attendance = { id: number | string; tanggal: string; status?: string; clock_in?: string | null; clock_out?: string | null; late_minute?: number; early_leave_minute?: number; notes?: string | null; employee?: Employee };

const statusOptions = [
  ['hadir', 'Hadir'], ['sakit', 'Sakit'], ['terlambat', 'Late'], ['absen', 'Absence'],
] as const;

export default function AttendanceManagement() {
  const [rows, setRows] = useState<Attendance[]>([]);
  const [employees, setEmployees] = useState<Employee[]>([]);
  const [employeeId, setEmployeeId] = useState('');
  const [period, setPeriod] = useState(new Date().toISOString().slice(0, 7));
  const [status, setStatus] = useState('');
  const [loading, setLoading] = useState(true);
  const [savingId, setSavingId] = useState<string | null>(null);
  const [showOffDuty, setShowOffDuty] = useState(false);
  const [offDuty, setOffDuty] = useState({ employee_id: '', tanggal: new Date().toISOString().slice(0, 10), notes: '' });

  const filtered = useMemo(() => rows.filter(row => !employeeId || row.employee?.id === employeeId).filter(row => !status || row.status === status), [rows, employeeId, status]);

  const fetchData = async () => {
    setLoading(true);
    try {
      const [attendanceResponse, employeeResponse] = await Promise.all([api.get('/hrm/attendances', { params: { per_page: 100 } }), api.get('/employees')]);
      const unwrap = (body: any) => Array.isArray(body?.data) ? body.data : Array.isArray(body?.data?.data) ? body.data.data : [];
      setRows(unwrap(attendanceResponse.data)); setEmployees(unwrap(employeeResponse.data));
    } catch (error: any) { toast.error(error?.response?.data?.message ?? 'Attendance gagal dimuat.'); }
    finally { setLoading(false); }
  };

  useEffect(() => { void fetchData(); }, []);

  const changeStatus = async (row: Attendance, nextStatus: string) => {
    setSavingId(String(row.id));
    try {
      await api.post(`/hrm/attendances/${row.id}/status`, { status: nextStatus, notes: row.notes ?? undefined, late_minute: nextStatus === 'terlambat' ? (row.late_minute ?? 0) : 0 });
      toast.success('Status attendance diperbarui.'); await fetchData();
    } catch (error: any) { toast.error(error?.response?.data?.message ?? 'Status attendance gagal diubah.'); }
    finally { setSavingId(null); }
  };

  const clock = async (kind: 'clock-in' | 'clock-out') => {
    if (!employeeId) return toast.error('Pilih karyawan dahulu.');
    try { await api.post(`/hrm/attendance/${kind}`, { employee_id: employeeId }); toast.success(kind === 'clock-in' ? 'Clock-in berhasil.' : 'Clock-out berhasil.'); await fetchData(); }
    catch (error: any) { toast.error(error?.response?.data?.message ?? `${kind} gagal.`); }
  };

  const submitOffDuty = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!offDuty.employee_id || !offDuty.notes.trim()) return toast.error('Karyawan dan alasan off-duty wajib diisi.');
    try { await api.post('/hrm/attendance/off-duty', offDuty); toast.success('Off-duty berhasil dicatat.'); setShowOffDuty(false); setOffDuty(current => ({ ...current, notes: '' })); await fetchData(); }
    catch (error: any) { toast.error(error?.response?.data?.message ?? 'Off-duty gagal dicatat.'); }
  };

  const exportAttendance = async () => {
    const [year, month] = period.split('-');
    try {
      const response = await api.get('/hrm/attendances/export', { params: { year, month }, responseType: 'blob' });
      const url = URL.createObjectURL(response.data); const link = document.createElement('a'); link.href = url; link.download = `attendance_${period}.csv`; link.click(); URL.revokeObjectURL(url);
    } catch { toast.error('Export attendance gagal.'); }
  };

  return <div className="flex min-h-screen bg-stone-50 text-stone-800">
    <AdminSidebar activePage="attendance-management" />
    <main className="flex-1 p-6 lg:p-8">
      <div className="mx-auto max-w-7xl">
        <div className="mb-6 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
          <div><div className="text-xs font-bold uppercase tracking-[0.18em] text-amber-700">HRM · Attendance</div><h1 className="mt-1 text-2xl font-black text-stone-900">Kontrol Absensi Karyawan</h1><p className="mt-1 text-sm text-stone-500">Hadir, sakit, late, absence, clock-in/out, off-duty, dan export.</p></div>
          <div className="flex flex-wrap gap-2"><button onClick={() => setShowOffDuty(true)} className="rounded-xl border border-amber-300 bg-amber-50 px-4 py-2.5 text-sm font-bold text-amber-800">🛌 Off-duty</button><button onClick={exportAttendance} className="rounded-xl bg-stone-900 px-4 py-2.5 text-sm font-bold text-white">📤 Export CSV</button></div>
        </div>

        <section className="mb-5 grid gap-3 rounded-2xl border border-stone-200 bg-white p-4 md:grid-cols-4">
          <label className="text-sm font-bold text-stone-600">Periode<input type="month" value={period} onChange={e => setPeriod(e.target.value)} className="mt-1 w-full rounded-xl border border-stone-200 px-3 py-2 font-normal" /></label>
          <label className="text-sm font-bold text-stone-600">Karyawan<select value={employeeId} onChange={e => setEmployeeId(e.target.value)} className="mt-1 w-full rounded-xl border border-stone-200 px-3 py-2 font-normal"><option value="">Semua karyawan</option>{employees.map(e => <option key={e.id} value={e.id}>{e.name}</option>)}</select></label>
          <label className="text-sm font-bold text-stone-600">Status<select value={status} onChange={e => setStatus(e.target.value)} className="mt-1 w-full rounded-xl border border-stone-200 px-3 py-2 font-normal"><option value="">Semua status</option>{statusOptions.map(([value, label]) => <option key={value} value={value}>{label}</option>)}<option value="offduty">Off-duty</option><option value="pulang_cepat">Pulang cepat</option></select></label>
          <div className="flex items-end gap-2"><button onClick={() => void clock('clock-in')} className="flex-1 rounded-xl border border-green-300 bg-green-50 px-3 py-2.5 text-sm font-bold text-green-700">▶ Clock-in</button><button onClick={() => void clock('clock-out')} className="flex-1 rounded-xl border border-blue-300 bg-blue-50 px-3 py-2.5 text-sm font-bold text-blue-700">■ Clock-out</button></div>
        </section>

        <section className="overflow-hidden rounded-3xl border border-stone-200 bg-white shadow-sm">
          <div className="flex items-center justify-between border-b border-stone-100 px-5 py-4"><div className="font-black">Daftar Attendance</div><div className="text-xs text-stone-400">{filtered.length} data</div></div>
          {loading ? <div className="p-8 text-center text-stone-500">Memuat attendance...</div> : <div className="overflow-x-auto"><table className="min-w-full text-sm"><thead className="bg-stone-50 text-xs uppercase text-stone-500"><tr><th className="px-4 py-3 text-left">Tanggal</th><th className="px-4 py-3 text-left">Karyawan</th><th className="px-4 py-3 text-left">Status</th><th className="px-4 py-3 text-left">In</th><th className="px-4 py-3 text-left">Out</th><th className="px-4 py-3 text-left">Late</th><th className="px-4 py-3 text-left">Action</th></tr></thead><tbody>{filtered.map(row => <tr key={row.id} className="border-t border-stone-100"><td className="px-4 py-3">{String(row.tanggal).slice(0, 10)}</td><td className="px-4 py-3"><div className="font-bold">{row.employee?.name ?? '-'}</div><div className="text-xs text-stone-400">{row.employee?.position ?? '-'}</div></td><td className="px-4 py-3"><span className="rounded-full bg-stone-100 px-2.5 py-1 text-xs font-bold">{row.status ?? '-'}</span></td><td className="px-4 py-3">{row.clock_in ? new Date(row.clock_in).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) : '-'}</td><td className="px-4 py-3">{row.clock_out ? new Date(row.clock_out).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) : '-'}</td><td className="px-4 py-3">{row.late_minute ?? 0} m</td><td className="px-4 py-3"><select disabled={savingId === String(row.id)} value={row.status ?? ''} onChange={e => void changeStatus(row, e.target.value)} className="rounded-lg border border-stone-200 bg-white px-2 py-1.5 text-xs"><option value="">Pilih</option>{statusOptions.map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></td></tr>)}</tbody></table></div>}
        </section>
      </div>
    </main>

    {showOffDuty && <div className="fixed inset-0 z-50 flex items-center justify-center bg-stone-900/60 p-5"><form onSubmit={submitOffDuty} className="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl"><div className="mb-5 flex items-center justify-between"><div><h2 className="text-lg font-black">Form Off-duty</h2><p className="text-sm text-stone-500">Tetapkan satu karyawan sebagai off-duty pada tanggal tertentu.</p></div><button type="button" onClick={() => setShowOffDuty(false)} className="text-stone-400">✕</button></div><div className="space-y-4"><label className="block text-sm font-bold">Karyawan<select value={offDuty.employee_id} onChange={e => setOffDuty({ ...offDuty, employee_id: e.target.value })} className="mt-1 w-full rounded-xl border border-stone-200 px-3 py-2.5"><option value="">Pilih karyawan</option>{employees.map(e => <option key={e.id} value={e.id}>{e.name} · {e.position}</option>)}</select></label><label className="block text-sm font-bold">Tanggal<input type="date" value={offDuty.tanggal} onChange={e => setOffDuty({ ...offDuty, tanggal: e.target.value })} className="mt-1 w-full rounded-xl border border-stone-200 px-3 py-2.5" /></label><label className="block text-sm font-bold">Alasan / Catatan<textarea value={offDuty.notes} onChange={e => setOffDuty({ ...offDuty, notes: e.target.value })} rows={4} className="mt-1 w-full rounded-xl border border-stone-200 px-3 py-2.5" /></label></div><button className="mt-5 w-full rounded-xl bg-stone-900 px-4 py-3 font-bold text-white">Simpan Off-duty</button></form></div>}
  </div>;
}

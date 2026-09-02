import { useEffect, useState } from 'react';
import toast from 'react-hot-toast';
import { useNavigate } from 'react-router-dom';
import AdminSidebar from '../components/AdminSidebar';
import { api } from '../core/api/client';

type BillTemplate = {
  business_name: string; address: string | null; phone: string | null; logo_url: string | null;
  bill_title: string; bill_subtitle: string | null; ppn_rate: number | string; paper_width: '58mm' | '80mm';
  show_cashier: boolean; show_customer: boolean; show_order_type: boolean; show_tax: boolean;
  show_discount: boolean; show_sku: boolean; show_change: boolean; footer_text: string | null; wifi_text: string | null; is_active: boolean;
};
type AttendanceSetting = { clock_in_time: string; clock_in_grace_minutes: number; clock_out_time: string; clock_out_grace_minutes: number; auto_absence_enabled: boolean };
type Penalty = { penalty_type: 'late' | 'absence'; duration_threshold: string; amount_type: 'fixed' | 'percentage'; amount: number | string; is_active: boolean };
type ApiResponseBody = { data?: unknown; message?: unknown };

const defaultBill: BillTemplate = { business_name: 'KOPI MENTENG', address: '', phone: '', logo_url: '', bill_title: 'NOTA PENJUALAN', bill_subtitle: '', ppn_rate: 11, paper_width: '80mm', show_cashier: true, show_customer: true, show_order_type: true, show_tax: true, show_discount: true, show_sku: false, show_change: true, footer_text: 'Terima kasih atas kunjungan Anda!', wifi_text: '', is_active: true };
const defaultAttendance: AttendanceSetting = { clock_in_time: '08:00', clock_in_grace_minutes: 15, clock_out_time: '17:00', clock_out_grace_minutes: 0, auto_absence_enabled: false };

function errorMessage(error: unknown): string {
  if (!error || typeof error !== 'object' || !('response' in error)) return '';
  const response = (error as { response?: { data?: ApiResponseBody } }).response;
  return typeof response?.data?.message === 'string' ? response.data.message : '';
}

export default function BusinessRulesSettings() {
  const navigate = useNavigate();
  const [tab, setTab] = useState<'bill' | 'attendance'>('attendance');
  const [bill, setBill] = useState(defaultBill);
  const [attendance, setAttendance] = useState(defaultAttendance);
  const [penalties, setPenalties] = useState<Penalty[]>([
    { penalty_type: 'late', duration_threshold: '15', amount_type: 'fixed', amount: 0, is_active: true },
    { penalty_type: 'absence', duration_threshold: '1', amount_type: 'fixed', amount: 0, is_active: true },
  ]);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    void Promise.all([api.get('/pos/receipt-template'), api.get('/hrm/attendance/settings'), api.get('/hrm/attendance/penalties')])
      .then(([billResponse, attendanceResponse, penaltiesResponse]) => {
        const b = billResponse.data?.data; if (b) setBill({ ...defaultBill, ...b });
        const a = attendanceResponse.data?.data; if (a) setAttendance({ ...defaultAttendance, ...a, clock_in_time: String(a.clock_in_time).slice(0, 5), clock_out_time: String(a.clock_out_time).slice(0, 5) });
        const p = penaltiesResponse.data?.data; if (Array.isArray(p) && p.length) setPenalties(p);
      })
      .catch(() => toast.error('Pengaturan gagal dimuat.'));
  }, []);

  const saveBill = async () => {
    setSaving(true);
    try { const response = await api.put('/pos/receipt-template', bill); setBill({ ...bill, ...response.data?.data }); toast.success('Bill template & PPN tersimpan.'); }
    catch (error: unknown) { toast.error(errorMessage(error) || 'Bill template gagal disimpan.'); }
    finally { setSaving(false); }
  };

  const saveAttendance = async () => {
    setSaving(true);
    try { await api.patch('/hrm/attendance/settings', attendance); await api.put('/hrm/attendance/penalties', { penalties }); toast.success('Aturan clock-in, clock-out, dan denda tersimpan.'); }
    catch (error: unknown) { toast.error(errorMessage(error) || 'Aturan absensi gagal disimpan.'); }
    finally { setSaving(false); }
  };

  const updatePenalty = (index: number, patch: Partial<Penalty>) => setPenalties(current => current.map((item, i) => i === index ? { ...item, ...patch } : item));
  const addPenalty = () => setPenalties(current => [...current, { penalty_type: 'late', duration_threshold: '30', amount_type: 'fixed', amount: 0, is_active: true }]);

  return <div className="flex min-h-screen bg-stone-50 text-stone-800"><AdminSidebar activePage="business-rules" /><main className="flex-1 p-6 lg:p-8"><div className="mx-auto max-w-6xl">
    <div className="mb-6 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between"><div><div className="text-xs font-bold uppercase tracking-[0.18em] text-amber-700">Administration · Business Rules</div><h1 className="mt-1 text-2xl font-black text-stone-900">Template, Pajak & Aturan Kehadiran</h1><p className="mt-1 text-sm text-stone-500">Satu tempat untuk bill template, PPN, jam kerja, dan denda attendance.</p></div><button onClick={() => navigate('/inventory/menu-import')} className="rounded-xl border border-stone-300 bg-white px-4 py-2.5 text-sm font-bold hover:bg-stone-50">📥 Import Menu Excel</button></div>

    <div className="mb-5 flex gap-2 rounded-2xl border border-stone-200 bg-white p-2"><button onClick={() => setTab('attendance')} className={`rounded-xl px-4 py-2 text-sm font-bold ${tab === 'attendance' ? 'bg-stone-900 text-white' : 'text-stone-500 hover:bg-stone-50'}`}>🕘 Attendance Rules</button><button onClick={() => setTab('bill')} className={`rounded-xl px-4 py-2 text-sm font-bold ${tab === 'bill' ? 'bg-stone-900 text-white' : 'text-stone-500 hover:bg-stone-50'}`}>🧾 Bill Template & PPN</button></div>

    {tab === 'attendance' ? <section className="space-y-5"><div className="grid gap-5 rounded-3xl border border-stone-200 bg-white p-6 md:grid-cols-2"><div><h2 className="font-black">Jam kerja</h2><div className="mt-4 grid gap-4 sm:grid-cols-2"><Field label="Clock-in" type="time" value={attendance.clock_in_time} onChange={v => setAttendance({ ...attendance, clock_in_time: v })}/><Field label="Toleransi clock-in (menit)" type="number" value={String(attendance.clock_in_grace_minutes)} onChange={v => setAttendance({ ...attendance, clock_in_grace_minutes: Number(v) })}/><Field label="Clock-out" type="time" value={attendance.clock_out_time} onChange={v => setAttendance({ ...attendance, clock_out_time: v })}/><Field label="Toleransi clock-out (menit)" type="number" value={String(attendance.clock_out_grace_minutes)} onChange={v => setAttendance({ ...attendance, clock_out_grace_minutes: Number(v) })}/></div></div><div className="rounded-2xl bg-stone-50 p-4"><div className="font-black">Auto absence</div><label className="mt-4 flex items-center justify-between rounded-xl border border-stone-200 bg-white p-3"><span className="text-sm font-semibold">Tandai absence otomatis</span><input type="checkbox" checked={attendance.auto_absence_enabled} onChange={e => setAttendance({ ...attendance, auto_absence_enabled: e.target.checked })}/></label><p className="mt-3 text-xs text-stone-500">Rule ini menjadi konfigurasi bisnis; proses penandaan massal dapat dijalankan scheduler ketika diaktifkan.</p></div></div><div className="rounded-3xl border border-stone-200 bg-white p-6"><div className="flex items-center justify-between"><div><h2 className="font-black">Denda keterlambatan & absence</h2><p className="text-sm text-stone-500">Fixed = rupiah. Percentage = persentase dari gaji pokok.</p></div><button onClick={addPenalty} className="rounded-xl border border-stone-300 px-3 py-2 text-sm font-bold">+ Rule</button></div><div className="mt-4 space-y-3">{penalties.map((p, i) => <div key={i} className="grid gap-2 rounded-2xl bg-stone-50 p-3 md:grid-cols-5"><select value={p.penalty_type} onChange={e => updatePenalty(i, { penalty_type: e.target.value as Penalty['penalty_type'] })} className="rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm"><option value="late">Late</option><option value="absence">Absence</option></select><input value={p.duration_threshold} onChange={e => updatePenalty(i, { duration_threshold: e.target.value })} placeholder="Threshold menit/hari" className="rounded-xl border border-stone-200 px-3 py-2 text-sm"/><select value={p.amount_type} onChange={e => updatePenalty(i, { amount_type: e.target.value as Penalty['amount_type'] })} className="rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm"><option value="fixed">Fixed</option><option value="percentage">Percentage</option></select><input type="number" min="0" step="0.01" value={p.amount} onChange={e => updatePenalty(i, { amount: e.target.value })} placeholder="Amount" className="rounded-xl border border-stone-200 px-3 py-2 text-sm"/><button onClick={() => setPenalties(current => current.filter((_, idx) => idx !== i))} className="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm font-bold text-red-700">Hapus</button></div>)}</div></div><button disabled={saving} onClick={saveAttendance} className="rounded-xl bg-stone-900 px-5 py-3 font-bold text-white disabled:opacity-50">{saving ? 'Menyimpan...' : 'Simpan Attendance Rules'}</button></section> : <section className="grid gap-5 lg:grid-cols-[1.05fr_.95fr]"><div className="rounded-3xl border border-stone-200 bg-white p-6"><h2 className="font-black">Bill template</h2><div className="mt-4 grid gap-4 sm:grid-cols-2"><Field label="Judul bill" value={bill.bill_title} onChange={v => setBill({ ...bill, bill_title: v })}/><Field label="Subjudul" value={bill.bill_subtitle ?? ''} onChange={v => setBill({ ...bill, bill_subtitle: v })}/><Field label="Nama usaha" value={bill.business_name} onChange={v => setBill({ ...bill, business_name: v })}/><Field label="PPN (%)" type="number" value={String(bill.ppn_rate)} onChange={v => setBill({ ...bill, ppn_rate: v })}/><Field label="Alamat" area value={bill.address ?? ''} onChange={v => setBill({ ...bill, address: v })}/><Field label="Footer" area value={bill.footer_text ?? ''} onChange={v => setBill({ ...bill, footer_text: v })}/></div><div className="mt-5 grid gap-3 sm:grid-cols-2"><Toggle label="Tampilkan PPN" value={bill.show_tax} onChange={v => setBill({ ...bill, show_tax: v })}/><Toggle label="Tampilkan diskon" value={bill.show_discount} onChange={v => setBill({ ...bill, show_discount: v })}/><Toggle label="Tampilkan kasir" value={bill.show_cashier} onChange={v => setBill({ ...bill, show_cashier: v })}/><Toggle label="Template aktif" value={bill.is_active} onChange={v => setBill({ ...bill, is_active: v })}/></div><button disabled={saving} onClick={saveBill} className="mt-5 rounded-xl bg-stone-900 px-5 py-3 font-bold text-white">{saving ? 'Menyimpan...' : 'Simpan Bill Template'}</button></div><div className="rounded-3xl bg-stone-900 p-6 text-white"><div className="text-xs font-bold uppercase tracking-[0.18em] text-amber-400">Preview</div><div className="mx-auto mt-4 max-w-xs rounded-xl bg-white p-5 font-mono text-xs text-black shadow-2xl"><div className="text-center text-base font-black">{bill.business_name}</div><div className="mt-1 text-center font-bold">{bill.bill_title}</div>{bill.bill_subtitle && <div className="text-center">{bill.bill_subtitle}</div>}<div className="my-3 border-t border-dashed"/><div className="flex justify-between"><span>Subtotal</span><span>Rp 50.000</span></div>{bill.show_discount && <div className="flex justify-between"><span>Diskon</span><span>-Rp 2.000</span></div>}{bill.show_tax && <div className="flex justify-between"><span>PPN {bill.ppn_rate}%</span><span>Rp 5.280</span></div>}<div className="my-3 border-t border-dashed"/><div className="flex justify-between font-black"><span>TOTAL</span><span>Rp 53.280</span></div><div className="mt-4 text-center whitespace-pre-line">{bill.footer_text}</div></div></div></section>}
  </div></main></div>;
}

function Field({ label, value, onChange, type = 'text', area = false }: { label: string; value: string; onChange: (value: string) => void; type?: string; area?: boolean }) { return <label className="block"><span className="mb-1 block text-xs font-bold text-stone-500">{label}</span>{area ? <textarea rows={3} value={value} onChange={e => onChange(e.target.value)} className="w-full rounded-xl border border-stone-200 px-3 py-2.5 text-sm"/> : <input type={type} value={value} onChange={e => onChange(e.target.value)} className="w-full rounded-xl border border-stone-200 px-3 py-2.5 text-sm"/>}</label>; }
function Toggle({ label, value, onChange }: { label: string; value: boolean; onChange: (value: boolean) => void }) { return <label className="flex items-center justify-between rounded-xl border border-stone-200 p-3 text-sm font-semibold"><span>{label}</span><input type="checkbox" checked={value} onChange={e => onChange(e.target.checked)}/></label>; }

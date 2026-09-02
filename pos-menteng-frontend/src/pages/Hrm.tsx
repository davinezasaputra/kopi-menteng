import { useEffect, useMemo, useState } from 'react';
import toast from 'react-hot-toast';
import AdminSidebar from '../components/AdminSidebar';
import { api } from '../core/api/client';

type Employee = {
  id: string;
  name: string;
  position?: string;
  WA?: string;
  base_sallary?: number | string;
};

type Attendance = {
  id: number | string;
  tanggal: string;
  clock_in?: string | null;
  clock_out?: string | null;
  status?: string | null;
  late_minute?: number | string | null;
  employee?: { id?: string; name?: string; position?: string } | null;
};

type Payroll = {
  id: number | string;
  period: string;
  base_salary?: number | string;
  allowance?: number | string;
  deduction?: number | string;
  attendance_deduction?: number | string;
  total_salary?: number | string;
  is_paid?: boolean;
  employee?: { id?: string; name?: string; position?: string } | null;
};

type PayrollAutomationConfig = {
  enable_auto_fill: boolean;
  enable_whatsapp_notification: boolean;
  whatsapp_recipient_employee: boolean;
  whatsapp_recipient_manager: boolean;
  manager_phone: string | null;
  notification_timing: 'immediate' | 'next_day' | 'after_approval';
  message_template: string;
};

type PayrollNotification = {
  id: number | string;
  recipient_type: string;
  recipient_phone?: string | null;
  status: string;
  provider_status?: string | null;
  error_message?: string | null;
  sent_at?: string | null;
  payroll?: { id?: number | string; period?: string; employee?: { name?: string } | null } | null;
};

type SummaryData = {
  total_employees: number;
  present_today: number;
  late_today: number;
  pending_payroll: number;
  monthly_payroll_total: number;
};

const extractRows = <T,>(body: unknown): T[] => {
  if (Array.isArray(body)) return body as T[];
  if (!body || typeof body !== 'object') return [];
  const value = (body as { data?: unknown }).data;
  if (Array.isArray(value)) return value as T[];
  if (value && typeof value === 'object') {
    const nested = (value as { data?: unknown }).data;
    if (Array.isArray(nested)) return nested as T[];
  }
  return [];
};

const extractObject = <T,>(body: unknown): T | null => {
  if (!body || typeof body !== 'object') return null;
  const data = (body as { data?: unknown }).data;
  if (data && typeof data === 'object' && !Array.isArray(data)) {
    return data as T;
  }
  return body as T;
};

const numberValue = (value: unknown): number => {
  const parsed = Number(value ?? 0);
  return Number.isFinite(parsed) ? parsed : 0;
};

const currentPeriod = () => {
  const now = new Date();
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
};

const dateText = (value: unknown) => {
  const raw = String(value ?? '');
  if (!raw) return '-';
  const parsed = new Date(raw);
  if (Number.isNaN(parsed.getTime())) return raw.slice(0, 10) || '-';
  return new Intl.DateTimeFormat('id-ID', {
    timeZone: 'Asia/Jakarta',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(parsed);
};

const timeText = (value: unknown) => {
  const raw = String(value ?? '');
  if (!raw) return '-';
  const parsed = new Date(raw);
  if (Number.isNaN(parsed.getTime())) return raw.slice(11, 16) || raw;
  return new Intl.DateTimeFormat('id-ID', {
    timeZone: 'Asia/Jakarta',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).format(parsed);
};

export default function Hrm() {
  const [activeTab, setActiveTab] = useState<'attendance' | 'payroll' | 'notifications'>('payroll');
  const [attendances, setAttendances] = useState<Attendance[]>([]);
  const [payrolls, setPayrolls] = useState<Payroll[]>([]);
  const [notifications, setNotifications] = useState<PayrollNotification[]>([]);
  const [employees, setEmployees] = useState<Employee[]>([]);
  const [summary, setSummary] = useState<SummaryData>({
    total_employees: 0,
    present_today: 0,
    late_today: 0,
    pending_payroll: 0,
    monthly_payroll_total: 0,
  });
  const [config, setConfig] = useState<PayrollAutomationConfig>({
    enable_auto_fill: true,
    enable_whatsapp_notification: true,
    whatsapp_recipient_employee: true,
    whatsapp_recipient_manager: true,
    manager_phone: '',
    notification_timing: 'immediate',
    message_template: 'Slip gaji periode {period} untuk {employee_name} terlampir.',
  });
  const [showPayrollModal, setShowPayrollModal] = useState(false);
  const [showConfigModal, setShowConfigModal] = useState(false);
  const [savingConfig, setSavingConfig] = useState(false);
  const [submittingPayroll, setSubmittingPayroll] = useState(false);
  const [busyPayrollId, setBusyPayrollId] = useState<string | null>(null);
  const [form, setForm] = useState({
    employee_id: '',
    period: currentPeriod(),
    base_salary: '',
    allowance: '',
    deduction: '',
  });

  const selectedEmployee = useMemo(
    () => employees.find((employee) => employee.id === form.employee_id),
    [employees, form.employee_id],
  );

  const fetchEmployees = async () => {
    const response = await api.get('/employees');
    setEmployees(extractRows<Employee>(response.data));
  };

  const fetchSummary = async () => {
    const response = await api.get('/hrm/summary');
    setSummary((extractObject<SummaryData>(response.data) ?? summary));
  };

  const fetchAttendances = async () => {
    const response = await api.get('/hrm/attendances');
    setAttendances(extractRows<Attendance>(response.data));
  };

  const fetchPayrolls = async () => {
    const response = await api.get('/hrm/payrolls');
    setPayrolls(extractRows<Payroll>(response.data));
  };

  const fetchNotifications = async () => {
    const response = await api.get('/hrm/payroll/notifications');
    setNotifications(extractRows<PayrollNotification>(response.data));
  };

  const fetchConfig = async () => {
    const response = await api.get('/hrm/payroll/automation/config');
    const next = extractObject<PayrollAutomationConfig>(response.data);
    if (next) setConfig(next);
  };

  useEffect(() => {
    void Promise.all([fetchEmployees(), fetchSummary(), fetchConfig()]).catch(() => {
      toast.error('Data HRD gagal dimuat.');
    });
  }, []);

  useEffect(() => {
    const load = activeTab === 'attendance'
      ? fetchAttendances()
      : activeTab === 'payroll'
        ? fetchPayrolls()
        : fetchNotifications();
    void load.catch(() => toast.error('Data tab gagal dimuat.'));
  }, [activeTab]);

  const openPayrollModal = () => {
    const salary = numberValue(selectedEmployee?.base_sallary);
    setForm({
      employee_id: '',
      period: currentPeriod(),
      base_salary: salary > 0 ? String(salary) : '',
      allowance: '',
      deduction: '',
    });
    setShowPayrollModal(true);
  };

  const handleEmployeeChange = (employeeId: string) => {
    const employee = employees.find((row) => row.id === employeeId);
    setForm((current) => ({
      ...current,
      employee_id: employeeId,
      base_salary: employee?.base_sallary != null ? String(employee.base_sallary) : current.base_salary,
    }));
  };

  const handleGeneratePayroll = async (event: React.FormEvent) => {
    event.preventDefault();
    setSubmittingPayroll(true);

    try {
      const response = await api.post('/hrm/payrolls', {
        employee_id: form.employee_id,
        period: form.period,
        base_salary: numberValue(form.base_salary),
        allowance: numberValue(form.allowance),
        deduction: numberValue(form.deduction),
      });

      const created = extractObject<Payroll>(response.data);
      if (created?.id && config.enable_auto_fill) {
        await api.post(`/hrm/payrolls/${created.id}/generate-auto`);
      }

      toast.success('Slip gaji berhasil diterbitkan dan diisi otomatis.');
      setShowPayrollModal(false);
      await Promise.all([fetchPayrolls(), fetchSummary()]);
    } catch (error: any) {
      toast.error(error?.response?.data?.message ?? 'Gagal menerbitkan slip gaji.');
    } finally {
      setSubmittingPayroll(false);
    }
  };

  const handleAutoFill = async (payroll: Payroll) => {
    setBusyPayrollId(String(payroll.id));
    try {
      await api.post(`/hrm/payrolls/${payroll.id}/generate-auto`);
      toast.success(`Payroll ${payroll.period} berhasil di-auto-fill.`);
      await Promise.all([fetchPayrolls(), fetchSummary()]);
    } catch (error: any) {
      toast.error(error?.response?.data?.message ?? 'Auto-fill payroll gagal.');
    } finally {
      setBusyPayrollId(null);
    }
  };

  const handleMarkAsPaid = async (id: number | string) => {
    setBusyPayrollId(String(id));
    try {
      const response = await api.put(`/hrm/payrolls/${id}/pay`, {});
      const automation = response.data?.automation;
      if (automation?.status === 'queued') {
        toast.success('Gaji dibayar. PDF payroll dan pengiriman WhatsApp masuk antrean.');
      } else if (automation?.status === 'disabled') {
        toast.success('Gaji berhasil dibayar. WhatsApp automation sedang nonaktif.');
      } else {
        toast.success('Gaji berhasil dibayar.');
      }
      await Promise.all([fetchPayrolls(), fetchSummary(), fetchNotifications()]);
    } catch (error: any) {
      toast.error(error?.response?.data?.message ?? 'Gagal membayar payroll.');
    } finally {
      setBusyPayrollId(null);
    }
  };

  const handleManualWhatsApp = async (payroll: Payroll) => {
    setBusyPayrollId(String(payroll.id));
    try {
      const response = await api.post(`/hrm/payrolls/${payroll.id}/send-whatsapp`);
      const count = numberValue(response.data?.data?.notifications);
      toast.success(count > 0 ? `Pengiriman WhatsApp untuk ${count} penerima masuk antrean.` : 'Tidak ada penerima WhatsApp aktif.');
      await fetchNotifications();
    } catch (error: any) {
      toast.error(error?.response?.data?.message ?? 'Pengiriman WhatsApp gagal.');
    } finally {
      setBusyPayrollId(null);
    }
  };

  const handleRefreshNotification = async (notification: PayrollNotification) => {
    try {
      await api.get(`/hrm/payroll/notifications/${notification.id}/status`);
      await fetchNotifications();
    } catch (error: any) {
      toast.error(error?.response?.data?.message ?? 'Status WhatsApp gagal diperbarui.');
    }
  };

  const handleSaveConfig = async (event: React.FormEvent) => {
    event.preventDefault();
    setSavingConfig(true);
    try {
      const response = await api.patch('/hrm/payroll/automation/config', config);
      const next = extractObject<PayrollAutomationConfig>(response.data);
      if (next) setConfig(next);
      setShowConfigModal(false);
      toast.success('Konfigurasi payroll automation disimpan.');
    } catch (error: any) {
      toast.error(error?.response?.data?.message ?? 'Konfigurasi gagal disimpan.');
    } finally {
      setSavingConfig(false);
    }
  };

  const formatRp = (value: unknown) => new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
  }).format(numberValue(value));

  const statusBadge = (status: string | undefined | null) => {
    const normalized = String(status ?? '').toLowerCase();
    if (['sent', 'delivered', 'read'].includes(normalized)) return 'bg-green-100 text-green-700';
    if (['failed', 'undelivered'].includes(normalized)) return 'bg-red-100 text-red-700';
    if (['processing', 'queued', 'sending'].includes(normalized)) return 'bg-amber-100 text-amber-700';
    return 'bg-stone-100 text-stone-600';
  };

  return (
    <div className="flex h-screen w-full bg-stone-50 font-sans text-stone-800">
      <AdminSidebar activePage="hrm" />

      <div className="flex flex-1 flex-col overflow-hidden">
        <header className="flex min-h-20 items-center justify-between gap-4 border-b border-stone-200 bg-white px-8 shadow-sm">
          <div>
            <h1 className="text-xl font-bold text-stone-800">HRIS & Penggajian</h1>
            <p className="text-sm text-stone-500">Absensi, payroll, automation, dan pengiriman slip gaji.</p>
          </div>
          <div className="flex flex-wrap justify-end gap-2">
            <button onClick={() => setActiveTab('attendance')} className={`rounded-lg px-4 py-2 font-bold transition ${activeTab === 'attendance' ? 'bg-stone-800 text-white' : 'bg-stone-100 text-stone-500 hover:bg-stone-200'}`}>Data Absensi</button>
            <button onClick={() => setActiveTab('payroll')} className={`rounded-lg px-4 py-2 font-bold transition ${activeTab === 'payroll' ? 'bg-stone-800 text-white' : 'bg-stone-100 text-stone-500 hover:bg-stone-200'}`}>Slip Gaji</button>
            <button onClick={() => setActiveTab('notifications')} className={`rounded-lg px-4 py-2 font-bold transition ${activeTab === 'notifications' ? 'bg-stone-800 text-white' : 'bg-stone-100 text-stone-500 hover:bg-stone-200'}`}>WhatsApp</button>
            <button onClick={() => setShowConfigModal(true)} className="rounded-lg border border-stone-300 bg-white px-4 py-2 font-bold text-stone-700 hover:bg-stone-50">⚙ Automation</button>
          </div>
        </header>

        <main className="flex-1 overflow-y-auto p-8">
          <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-4">
            <div className="rounded-2xl border border-stone-200 bg-white p-4 shadow-sm"><div className="text-xs uppercase tracking-wide text-stone-500">Total Karyawan</div><div className="mt-2 text-2xl font-black">{summary.total_employees}</div></div>
            <div className="rounded-2xl border border-stone-200 bg-white p-4 shadow-sm"><div className="text-xs uppercase tracking-wide text-stone-500">Hadir Hari Ini</div><div className="mt-2 text-2xl font-black text-green-600">{summary.present_today}</div></div>
            <div className="rounded-2xl border border-stone-200 bg-white p-4 shadow-sm"><div className="text-xs uppercase tracking-wide text-stone-500">Terlambat</div><div className="mt-2 text-2xl font-black text-amber-600">{summary.late_today}</div></div>
            <div className="rounded-2xl border border-stone-200 bg-white p-4 shadow-sm"><div className="text-xs uppercase tracking-wide text-stone-500">Gaji Bulan Ini</div><div className="mt-2 text-lg font-black text-amber-700">{formatRp(summary.monthly_payroll_total)}</div></div>
          </div>

          {activeTab === 'payroll' && (
            <div className="mb-4 flex items-center justify-between rounded-2xl border border-amber-200 bg-amber-50 p-4">
              <div><div className="font-bold text-amber-900">Payroll Automation</div><div className="text-sm text-amber-800">{config.enable_auto_fill ? 'Auto-fill aktif dari data employee + attendance.' : 'Auto-fill nonaktif.'} {config.enable_whatsapp_notification ? 'WhatsApp aktif.' : 'WhatsApp nonaktif.'}</div></div>
              <button onClick={openPayrollModal} className="rounded-lg bg-amber-700 px-4 py-2 font-bold text-white shadow-md hover:bg-amber-800">+ Buat Slip Gaji</button>
            </div>
          )}

          <div className="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm">
            {activeTab === 'attendance' && (
              <table className="w-full border-collapse text-left">
                <thead className="bg-stone-100 text-sm uppercase text-stone-500"><tr><th className="p-4">Tanggal</th><th className="p-4">Pegawai</th><th className="p-4">Jam Masuk</th><th className="p-4">Terlambat</th><th className="p-4">Status</th></tr></thead>
                <tbody className="divide-y divide-stone-100">{attendances.map((att) => <tr key={att.id} className="hover:bg-stone-50"><td className="p-4 font-bold">{dateText(att.tanggal)}</td><td className="p-4 font-bold">{att.employee?.name ?? '-'}</td><td className="p-4 font-bold text-stone-600">{timeText(att.clock_in)}</td><td className="p-4 text-sm">{numberValue(att.late_minute)} menit</td><td className="p-4"><span className="rounded-full bg-stone-100 px-3 py-1 text-xs font-bold uppercase text-stone-700">{att.status ?? '-'}</span></td></tr>)}</tbody>
              </table>
            )}

            {activeTab === 'payroll' && (
              <table className="w-full border-collapse text-left">
                <thead className="bg-stone-100 text-sm uppercase text-stone-500"><tr><th className="p-4">Periode</th><th className="p-4">Pegawai</th><th className="p-4 text-right">Pokok</th><th className="p-4 text-right">Potongan</th><th className="p-4 text-right">Total</th><th className="p-4 text-center">Status</th><th className="p-4 text-center">Aksi</th></tr></thead>
                <tbody className="divide-y divide-stone-100">{payrolls.map((pay) => { const busy = busyPayrollId === String(pay.id); return <tr key={pay.id} className="hover:bg-stone-50"><td className="p-4 font-bold text-stone-600">{pay.period}</td><td className="p-4 font-bold">{pay.employee?.name ?? '-'}</td><td className="p-4 text-right">{formatRp(pay.base_salary)}</td><td className="p-4 text-right">{formatRp(numberValue(pay.deduction) + numberValue(pay.attendance_deduction))}</td><td className="p-4 text-right font-black text-amber-700">{formatRp(pay.total_salary)}</td><td className="p-4 text-center"><span className={`rounded-full px-3 py-1 text-xs font-bold uppercase ${pay.is_paid ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>{pay.is_paid ? 'LUNAS' : 'PENDING'}</span></td><td className="p-4"><div className="flex justify-center gap-2">{!pay.is_paid && <><button disabled={busy} onClick={() => void handleAutoFill(pay)} className="rounded-lg bg-stone-100 px-3 py-2 text-xs font-bold text-stone-700 disabled:opacity-50">{busy ? '...' : 'Auto-fill'}</button><button disabled={busy} onClick={() => void handleMarkAsPaid(pay.id)} className="rounded-lg bg-stone-800 px-3 py-2 text-xs font-bold text-white disabled:opacity-50">Bayar</button></>}{pay.is_paid && <button disabled={busy} onClick={() => void handleManualWhatsApp(pay)} className="rounded-lg bg-green-700 px-3 py-2 text-xs font-bold text-white disabled:opacity-50">Kirim WhatsApp</button>}</div></td></tr>; })}</tbody>
              </table>
            )}

            {activeTab === 'notifications' && (
              <table className="w-full border-collapse text-left">
                <thead className="bg-stone-100 text-sm uppercase text-stone-500"><tr><th className="p-4">Payroll</th><th className="p-4">Penerima</th><th className="p-4">Nomor</th><th className="p-4">Status</th><th className="p-4">Terkirim</th><th className="p-4 text-center">Aksi</th></tr></thead>
                <tbody className="divide-y divide-stone-100">{notifications.map((notification) => <tr key={notification.id} className="hover:bg-stone-50"><td className="p-4"><div className="font-bold">{notification.payroll?.employee?.name ?? '-'}</div><div className="text-xs text-stone-500">{notification.payroll?.period ?? '-'}</div></td><td className="p-4 capitalize">{notification.recipient_type}</td><td className="p-4 text-sm text-stone-600">{notification.recipient_phone ?? '-'}</td><td className="p-4"><span className={`rounded-full px-3 py-1 text-xs font-bold uppercase ${statusBadge(notification.provider_status ?? notification.status)}`}>{notification.provider_status ?? notification.status}</span>{notification.error_message && <div className="mt-1 max-w-sm text-xs text-red-600">{notification.error_message}</div>}</td><td className="p-4 text-sm text-stone-600">{notification.sent_at ? dateText(notification.sent_at) : '-'}</td><td className="p-4 text-center"><button onClick={() => void handleRefreshNotification(notification)} className="rounded-lg bg-stone-100 px-3 py-2 text-xs font-bold text-stone-700">Refresh</button></td></tr>)}</tbody>
              </table>
            )}
          </div>
        </main>
      </div>

      {showPayrollModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-stone-900/60 p-4 backdrop-blur-sm">
          <div className="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
            <div className="mb-5 flex items-start justify-between"><div><h2 className="text-lg font-bold">Terbitkan Slip Gaji</h2><p className="text-sm text-stone-500">Pilih karyawan; data gaji pokok akan diisi otomatis dari master.</p></div><button onClick={() => setShowPayrollModal(false)} className="text-xl text-stone-400">×</button></div>
            <form onSubmit={handleGeneratePayroll} className="space-y-4">
              <select required value={form.employee_id} onChange={(event) => handleEmployeeChange(event.target.value)} className="w-full rounded-xl border border-stone-300 bg-stone-50 p-3 font-bold"><option value="" disabled>-- Pilih Karyawan --</option>{employees.map((employee) => <option key={employee.id} value={employee.id}>{employee.name} {employee.position ? `(${employee.position})` : ''}</option>)}</select>
              {selectedEmployee && <div className="rounded-xl bg-stone-50 p-3 text-sm text-stone-600">WhatsApp employee: <span className="font-bold">{selectedEmployee.WA || 'Belum diisi'}</span></div>}
              <input type="month" required value={form.period} onChange={(event) => setForm({ ...form, period: event.target.value })} className="w-full rounded-xl border border-stone-300 bg-stone-50 p-3" />
              <div className="grid grid-cols-1 gap-3 md:grid-cols-2"><label className="text-sm font-bold text-stone-600">Gaji Pokok<input type="number" min="0" required value={form.base_salary} onChange={(event) => setForm({ ...form, base_salary: event.target.value })} className="mt-1 w-full rounded-xl border border-stone-300 bg-stone-50 p-3" /></label><label className="text-sm font-bold text-stone-600">Tunjangan<input type="number" min="0" value={form.allowance} onChange={(event) => setForm({ ...form, allowance: event.target.value })} className="mt-1 w-full rounded-xl border border-stone-300 bg-stone-50 p-3" /></label></div>
              <label className="text-sm font-bold text-stone-600">Potongan Manual<input type="number" min="0" value={form.deduction} onChange={(event) => setForm({ ...form, deduction: event.target.value })} className="mt-1 w-full rounded-xl border border-stone-300 bg-stone-50 p-3" /></label>
              <div className="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">{config.enable_auto_fill ? 'Setelah slip dibuat, sistem akan menghitung ulang dari attendance dan aturan denda yang aktif.' : 'Auto-fill sedang nonaktif; data disimpan sesuai nilai form.'}</div>
              <div className="flex gap-2 pt-2"><button type="button" onClick={() => setShowPayrollModal(false)} className="flex-1 rounded-xl bg-stone-100 py-3 font-bold text-stone-500">Batal</button><button disabled={submittingPayroll} type="submit" className="flex-1 rounded-xl bg-amber-700 py-3 font-bold text-white disabled:opacity-50">{submittingPayroll ? 'Memproses...' : 'Terbitkan Slip'}</button></div>
            </form>
          </div>
        </div>
      )}

      {showConfigModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-stone-900/60 p-4 backdrop-blur-sm">
          <div className="w-full max-w-xl rounded-2xl bg-white p-6 shadow-2xl">
            <div className="mb-5 flex items-start justify-between"><div><h2 className="text-lg font-bold">Payroll Automation</h2><p className="text-sm text-stone-500">Konfigurasi auto-fill, PDF, dan WhatsApp.</p></div><button onClick={() => setShowConfigModal(false)} className="text-xl text-stone-400">×</button></div>
            <form onSubmit={handleSaveConfig} className="space-y-4">
              <div className="grid grid-cols-1 gap-3 md:grid-cols-2"><label className="flex items-center gap-3 rounded-xl bg-stone-50 p-3"><input type="checkbox" checked={config.enable_auto_fill} onChange={(event) => setConfig({ ...config, enable_auto_fill: event.target.checked })} /> <span className="font-bold">Auto-fill payroll</span></label><label className="flex items-center gap-3 rounded-xl bg-stone-50 p-3"><input type="checkbox" checked={config.enable_whatsapp_notification} onChange={(event) => setConfig({ ...config, enable_whatsapp_notification: event.target.checked })} /> <span className="font-bold">WhatsApp aktif</span></label><label className="flex items-center gap-3 rounded-xl bg-stone-50 p-3"><input type="checkbox" checked={config.whatsapp_recipient_employee} onChange={(event) => setConfig({ ...config, whatsapp_recipient_employee: event.target.checked })} /> <span className="font-bold">Kirim ke employee</span></label><label className="flex items-center gap-3 rounded-xl bg-stone-50 p-3"><input type="checkbox" checked={config.whatsapp_recipient_manager} onChange={(event) => setConfig({ ...config, whatsapp_recipient_manager: event.target.checked })} /> <span className="font-bold">Kirim ke manager</span></label></div>
              <label className="text-sm font-bold text-stone-600">Nomor WhatsApp Manager<input value={config.manager_phone ?? ''} onChange={(event) => setConfig({ ...config, manager_phone: event.target.value })} placeholder="08xxxxxxxxxx" className="mt-1 w-full rounded-xl border border-stone-300 bg-stone-50 p-3" /></label>
              <label className="text-sm font-bold text-stone-600">Waktu pengiriman<select value={config.notification_timing} onChange={(event) => setConfig({ ...config, notification_timing: event.target.value as PayrollAutomationConfig['notification_timing'] })} className="mt-1 w-full rounded-xl border border-stone-300 bg-stone-50 p-3"><option value="immediate">Segera setelah payroll dibayar</option><option value="next_day">Besok pukul 09:00</option><option value="after_approval">Setelah approval</option></select></label>
              <label className="text-sm font-bold text-stone-600">Template Pesan<input value={config.message_template} onChange={(event) => setConfig({ ...config, message_template: event.target.value })} className="mt-1 w-full rounded-xl border border-stone-300 bg-stone-50 p-3" /></label>
              <div className="rounded-xl border border-stone-200 bg-stone-50 p-3 text-sm text-stone-600">Placeholder tersedia: <span className="font-mono">{'{period}'}</span> dan <span className="font-mono">{'{employee_name}'}</span>.</div>
              <div className="flex gap-2 pt-2"><button type="button" onClick={() => setShowConfigModal(false)} className="flex-1 rounded-xl bg-stone-100 py-3 font-bold text-stone-500">Batal</button><button disabled={savingConfig} type="submit" className="flex-1 rounded-xl bg-stone-800 py-3 font-bold text-white disabled:opacity-50">{savingConfig ? 'Menyimpan...' : 'Simpan Konfigurasi'}</button></div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}

import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import axios from 'axios';
import toast from 'react-hot-toast';
import { AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';
import AdminSidebar from '../components/AdminSidebar'; // Sesuaikan path jika berbeda
import { formatNumberInput, parseNumberInput } from '../utils/numberFormat';

export default function Dashboard() {
  const navigate = useNavigate();
  const [metrics, setMetrics] = useState<any>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [showExpenseModal, setShowExpenseModal] = useState(false);
  const [expenseData, setExpenseData] = useState({ name: '', amount: '', expense_date: new Date().toISOString().split('T')[0] });
  const [filterMode, setFilterMode] = useState<'daily' | 'monthly' | 'yearly'>('monthly');
  const [selectedDate, setSelectedDate] = useState(new Date().toISOString().split('T')[0]);
  const [selectedMonth, setSelectedMonth] = useState<number>(new Date().getMonth() + 1);
  const [selectedYear, setSelectedYear] = useState<number>(new Date().getFullYear());

  const monthOptions = [
    { value: 1, label: 'Januari' },
    { value: 2, label: 'Februari' },
    { value: 3, label: 'Maret' },
    { value: 4, label: 'April' },
    { value: 5, label: 'Mei' },
    { value: 6, label: 'Juni' },
    { value: 7, label: 'Juli' },
    { value: 8, label: 'Agustus' },
    { value: 9, label: 'September' },
    { value: 10, label: 'Oktober' },
    { value: 11, label: 'November' },
    { value: 12, label: 'Desember' },
  ];

  const yearOptions = Array.from({ length: 5 }, (_, index) => new Date().getFullYear() - 2 + index);
  const selectedPeriodLabel = filterMode === 'daily'
    ? new Date(`${selectedDate}T00:00:00`).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
    : filterMode === 'yearly'
      ? String(selectedYear)
      : new Date(selectedYear, selectedMonth - 1, 1).toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });

  useEffect(() => {
    const token = localStorage.getItem('token');
    if (!token) {
      navigate('/');
      return;
    }
    fetchDashboardData();
  }, [navigate, filterMode, selectedDate, selectedMonth, selectedYear]);

  const handleAddExpense = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await axios.post('http://localhost:8000/api/finance/expenses', { ...expenseData, amount: parseNumberInput(expenseData.amount) }, {
        headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }
      });
      toast.success('Pengeluaran berhasil dicatat!');
      setShowExpenseModal(false);
      setExpenseData({ name: '', amount: '', expense_date: new Date().toISOString().split('T')[0] });
      fetchDashboardData(); // Refresh laba rugi
    } catch (error) {
      toast.error('Gagal mencatat pengeluaran.');
    }
  };

  const handleExportCsv = async () => {
    const toastId = toast.loading('Menyiapkan dokumen Excel...');
    try {
      const response = await axios.get('http://localhost:8000/api/finance/export', {
        headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` },
        params: { period: filterMode, date: selectedDate, month: selectedMonth, year: selectedYear },
        responseType: 'blob' // Wajib untuk mengunduh file
      });
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      const exportLabel = filterMode === 'daily'
        ? selectedDate
        : filterMode === 'yearly'
          ? String(selectedYear)
          : `${selectedYear}-${String(selectedMonth).padStart(2, '0')}`;
      link.setAttribute('download', `Laporan_Kopi_Menteng_${exportLabel}.csv`);
      document.body.appendChild(link);
      link.click();
      toast.success('Berhasil diunduh!', { id: toastId });
    } catch (error) {
      toast.error('Gagal mengunduh laporan.', { id: toastId });
    }
  };

  const fetchDashboardData = async () => {
    setIsLoading(true);
    const token = localStorage.getItem('token');
    try {
      const response = await axios.get('http://localhost:8000/api/finance/dashboard', {
        headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' },
        params: { period: filterMode, date: selectedDate, month: selectedMonth, year: selectedYear }
      });
      setMetrics(response.data.data);
    } catch (error) {
      console.error("Gagal memuat data dasbor", error);
    } finally {
      setIsLoading(false);
    }
  };

  const formatRp = (angka: number) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka || 0);

  if (isLoading) {
    return <div className="flex h-screen items-center justify-center bg-stone-50 font-bold text-stone-500">Menyiapkan Laporan Keuangan...</div>;
  }

  const grossRevenue = Number(metrics?.summary?.gross_revenue || 0);
  const netProfit = Number(metrics?.summary?.net_profit || 0);
  const totalOrders = Number(metrics?.summary?.total_orders || 0);
  const totalOpex = Number(metrics?.summary?.total_opex || 0);
  const totalCogs = Number(metrics?.summary?.total_cogs || 0);
  const profitMargin = grossRevenue > 0 ? (netProfit / grossRevenue) * 100 : 0;
  const avgOrder = totalOrders > 0 ? grossRevenue / totalOrders : 0;
  const cogsRatio = grossRevenue > 0 ? (totalCogs / grossRevenue) * 100 : 0;
  const barPurchases = Number(metrics?.shopping_summary?.bar || 0);
  const kitchenPurchases = Number(metrics?.shopping_summary?.dapur || 0);
  const restockPurchases = metrics?.restock_purchases || [];

  return (
    <div className="flex h-screen w-full bg-slate-100 font-sans text-slate-800">
      <AdminSidebar activePage="dashboard" />

      <div className="flex-1 flex flex-col overflow-hidden">
        <header className="h-24 border-b border-slate-200 bg-white/90 backdrop-blur-sm flex items-center px-8 shadow-sm justify-between">
          <div>
            <p className="text-[10px] font-bold uppercase tracking-[0.32em] text-slate-400">Business overview</p>
            <h1 className="text-xl font-bold text-slate-800">Dasbor Keuangan & Operasional</h1>
          </div>
          <div className="flex gap-3">
            <button onClick={() => setShowExpenseModal(true)} className="bg-red-50 text-red-600 px-4 py-2 rounded-xl font-bold border border-red-200 hover:bg-red-100 transition shadow-sm">
              💸 Catat Pengeluaran
            </button>
            <button onClick={handleExportCsv} className="bg-slate-900 text-white px-4 py-2 rounded-xl font-bold hover:bg-slate-800 transition shadow-md">
              📊 Unduh Excel (CSV)
            </button>
          </div>
        </header>

        <main className="flex-1 overflow-y-auto p-8 relative">
          <div className="mb-6 rounded-3xl bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-700 p-6 text-white shadow-[0_18px_40px_rgba(15,23,42,0.22)]">
            <div className="flex items-center justify-between gap-4 flex-wrap">
              <div>
                <p className="text-[10px] font-bold uppercase tracking-[0.28em] text-slate-300">Ringkasan {selectedPeriodLabel}</p>
                <h2 className="mt-2 text-4xl font-black">{formatRp(netProfit)}</h2>
              </div>

              <div className="flex items-center gap-3">
                <div className="rounded-2xl bg-white/10 px-4 py-2 text-right backdrop-blur-sm border border-white/10">
                  <p className="text-[10px] uppercase tracking-[0.2em] text-slate-300">Margin</p>
                  <p className="text-xl font-black">{profitMargin.toFixed(1)}%</p>
                </div>
                <div className="rounded-2xl bg-emerald-500/20 px-4 py-2 text-right border border-emerald-300/30">
                  <p className="text-[10px] uppercase tracking-[0.2em] text-emerald-100">Status</p>
                  <p className="text-xl font-black text-emerald-100">{netProfit >= 0 ? 'Profit' : 'Risk'}</p>
                </div>
              </div>
            </div>
          </div>

          <div className="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div>
              <p className="text-[10px] font-bold uppercase tracking-[0.25em] text-slate-400">Filter laporan</p>
              <h3 className="text-base font-bold text-slate-800">Pilih periode</h3>
            </div>

            <div className="flex flex-wrap items-center gap-3">
              <select
                value={filterMode}
                onChange={(e) => setFilterMode(e.target.value as 'daily' | 'monthly' | 'yearly')}
                className="rounded-xl border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-bold text-indigo-700 outline-none focus:border-indigo-500"
              >
                <option value="daily">Harian</option>
                <option value="monthly">Bulanan</option>
                <option value="yearly">Tahunan</option>
              </select>

              {filterMode === 'daily' && (
                <input
                  type="date"
                  value={selectedDate}
                  onChange={(e) => setSelectedDate(e.target.value)}
                  className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700 outline-none focus:border-indigo-500"
                />
              )}

              {filterMode === 'monthly' && (
                <select
                  value={selectedMonth}
                  onChange={(e) => setSelectedMonth(Number(e.target.value))}
                  className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700 outline-none focus:border-indigo-500"
                >
                  {monthOptions.map((month) => (
                    <option key={month.value} value={month.value}>{month.label}</option>
                  ))}
                </select>
              )}

              {filterMode !== 'daily' && (
                <select
                  value={selectedYear}
                  onChange={(e) => setSelectedYear(Number(e.target.value))}
                  className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700 outline-none focus:border-indigo-500"
                >
                  {yearOptions.map((year) => (
                    <option key={year} value={year}>{year}</option>
                  ))}
                </select>
              )}
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-5 gap-4 mb-8">
            <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm border-t-4 border-t-blue-500">
              <p className="text-xs font-bold text-slate-500 mb-1">Omzet Kotor</p>
              <h3 className="text-2xl font-black text-slate-800">{formatRp(grossRevenue)}</h3>
            </div>
            <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm border-t-4 border-t-orange-500">
              <p className="text-xs font-bold text-slate-500 mb-1">PPN</p>
              <h3 className="text-2xl font-black text-slate-800">{formatRp(Number(metrics?.summary?.tax_payable || 0))}</h3>
            </div>
            <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm border-t-4 border-t-amber-700">
              <p className="text-xs font-bold text-slate-500 mb-1">HPP / Modal</p>
              <h3 className="text-2xl font-black text-slate-800">{formatRp(totalCogs)}</h3>
            </div>
            <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm border-t-4 border-t-red-500">
              <p className="text-xs font-bold text-slate-500 mb-1">Biaya Operasional</p>
              <h3 className="text-2xl font-black text-red-600">-{formatRp(totalOpex)}</h3>
            </div>
            <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-lg border-t-4 border-t-green-500 relative overflow-hidden">
              <p className="text-xs font-bold text-slate-500 mb-1">Laba Bersih</p>
              <h3 className="text-2xl font-black text-green-600">{formatRp(netProfit)}</h3>
            </div>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
              <p className="text-xs font-bold uppercase tracking-[0.2em] text-slate-400">Total order</p>
              <h3 className="mt-3 text-3xl font-black text-slate-800">{totalOrders}</h3>
            </div>
            <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
              <p className="text-xs font-bold uppercase tracking-[0.2em] text-slate-400">Rata-rata order</p>
              <h3 className="mt-3 text-3xl font-black text-slate-800">{formatRp(avgOrder)}</h3>
            </div>
            <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
              <p className="text-xs font-bold uppercase tracking-[0.2em] text-slate-400">Status performa</p>
              <h3 className={`mt-3 text-2xl font-black ${netProfit >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                {netProfit >= 0 ? 'Menguntungkan' : 'Butuh evaluasi'}
              </h3>
            </div>
          </div>

          <div className="mb-8 grid grid-cols-1 gap-4 md:grid-cols-2">
            <div className="rounded-2xl border border-blue-100 bg-gradient-to-br from-blue-50 to-white p-5 shadow-sm">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <p className="text-[10px] font-bold uppercase tracking-[0.22em] text-blue-500">Belanja Bar</p>
                  <h3 className="mt-2 text-2xl font-black text-slate-800">{formatRp(barPurchases)}</h3>
                  <p className="mt-1 text-sm text-slate-500">Total pembelian bahan minuman</p>
                </div>
                <span className="rounded-xl bg-blue-100 px-3 py-2 text-lg">☕</span>
              </div>
            </div>
            <div className="rounded-2xl border border-orange-100 bg-gradient-to-br from-orange-50 to-white p-5 shadow-sm">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <p className="text-[10px] font-bold uppercase tracking-[0.22em] text-orange-500">Belanja Dapur</p>
                  <h3 className="mt-2 text-2xl font-black text-slate-800">{formatRp(kitchenPurchases)}</h3>
                  <p className="mt-1 text-sm text-slate-500">Total pembelian bahan makanan</p>
                </div>
                <span className="rounded-xl bg-orange-100 px-3 py-2 text-lg">🍳</span>
              </div>
            </div>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div className="lg:col-span-2 bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
              <div className="flex items-center justify-between mb-6">
                <h2 className="text-lg font-bold text-slate-800">Tren Penjualan & Laba Harian</h2>
                <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-500">{selectedPeriodLabel}</span>
              </div>
              <div className="h-80">
                <ResponsiveContainer width="100%" height="100%">
                  <AreaChart data={metrics?.chart_data} margin={{ top: 10, right: 10, left: 0, bottom: 0 }}>
                    <defs>
                      <linearGradient id="colorGross" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="5%" stopColor="#3b82f6" stopOpacity={0.3}/>
                        <stop offset="95%" stopColor="#3b82f6" stopOpacity={0}/>
                      </linearGradient>
                      <linearGradient id="colorNet" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="5%" stopColor="#22c55e" stopOpacity={0.3}/>
                        <stop offset="95%" stopColor="#22c55e" stopOpacity={0}/>
                      </linearGradient>
                    </defs>
                    <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#e2e8f0" />
                    <XAxis dataKey="date" tick={{fontSize: 12}} stroke="#94a3b8" tickFormatter={(str) => new Date(str).toLocaleDateString('id-ID', {day:'numeric', month:'short'})} />
                    <YAxis tick={{fontSize: 12}} stroke="#94a3b8" tickFormatter={(val) => `Rp ${val / 1000}k`} />
                    <Tooltip formatter={(value) => formatRp(Number(value) || 0)} labelFormatter={(label) => label != null ? new Date(String(label)).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long' }) : ''} />
                    <Area type="monotone" dataKey="gross_revenue" name="Omzet Kotor" stroke="#3b82f6" strokeWidth={3} fillOpacity={1} fill="url(#colorGross)" />
                    <Area type="monotone" dataKey="net_profit" name="Laba Bersih" stroke="#22c55e" strokeWidth={3} fillOpacity={1} fill="url(#colorNet)" />
                  </AreaChart>
                </ResponsiveContainer>
              </div>
            </div>

            <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col">
              <h2 className="text-lg font-bold text-slate-800 mb-6">Menu Terlaris</h2>
              <div className="flex-1 overflow-y-auto">
                {metrics?.top_products?.length === 0 ? (
                  <div className="h-full flex items-center justify-center text-sm text-slate-400 italic">Belum ada data penjualan.</div>
                ) : (
                  <div className="space-y-4">
                    {metrics?.top_products?.map((item: any, index: number) => (
                      <div key={index} className="flex items-center justify-between pb-4 border-b border-slate-100 last:border-0">
                        <div className="flex items-center gap-3">
                          <div className="flex items-center justify-center w-8 h-8 rounded-full bg-slate-100 text-slate-600 font-bold text-sm">
                            #{index + 1}
                          </div>
                          <div>
                            <p className="font-bold text-slate-800 text-sm">{item.product?.name}</p>
                            <p className="text-xs text-amber-700 font-bold mt-0.5">{item.total_qty} Terjual</p>
                          </div>
                        </div>
                        <div className="text-right">
                          <p className="text-xs text-slate-500 mb-0.5">Omzet</p>
                          <p className="font-bold text-slate-800 text-sm">{formatRp(item.total_revenue)}</p>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>
          </div>

          <div className="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
              <h2 className="text-lg font-bold text-slate-800 mb-4">Insight operasi</h2>
              <div className="space-y-3 text-sm text-slate-600">
                <div className="flex items-center justify-between rounded-xl bg-slate-50 p-3">
                  <span>Rasio HPP terhadap omzet</span>
                  <span className="font-bold text-slate-800">{cogsRatio.toFixed(1)}%</span>
                </div>
                <div className="flex items-center justify-between rounded-xl bg-slate-50 p-3">
                  <span>Biaya operasional</span>
                  <span className="font-bold text-slate-800">{formatRp(totalOpex)}</span>
                </div>
                <div className="flex items-center justify-between rounded-xl bg-slate-50 p-3">
                  <span>Margin bersih</span>
                  <span className={`font-bold ${netProfit >= 0 ? 'text-green-600' : 'text-red-600'}`}>{profitMargin.toFixed(1)}%</span>
                </div>
              </div>
            </div>

            <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
              <h2 className="text-lg font-bold text-slate-800 mb-4">Action needed</h2>
              <div className="space-y-3 text-sm text-slate-600">
                <div className="rounded-xl border border-amber-200 bg-amber-50 p-3">
                  <p className="font-bold text-amber-700">Peringatan stok</p>
                  <p className="mt-1">Pantau bahan baku yang mendekati titik re-order agar produksi tidak terganggu.</p>
                </div>
                <div className="rounded-xl border border-blue-200 bg-blue-50 p-3">
                  <p className="font-bold text-blue-700">Potensi naikkan margin</p>
                  <p className="mt-1">Evaluasi menu dengan margin rendah agar laba bersih lebih stabil.</p>
                </div>
              </div>
            </div>
          </div>

          <div className="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div className="mb-5 flex items-center justify-between gap-3">
              <div>
                <h2 className="text-lg font-bold text-slate-800">Riwayat pengeluaran</h2>
                <p className="mt-1 text-sm text-slate-500">Transaksi biaya operasional pada {selectedPeriodLabel}.</p>
              </div>
              <span className="rounded-full bg-red-50 px-3 py-1 text-xs font-bold text-red-600">
                {metrics?.expenses?.length || 0} transaksi
              </span>
            </div>

            {metrics?.expenses?.length ? (
              <div className="overflow-x-auto">
                <table className="w-full min-w-[620px] text-left text-sm">
                  <thead className="border-b border-slate-200 text-xs uppercase tracking-wider text-slate-400">
                    <tr>
                      <th className="px-3 py-3 font-bold">Tanggal</th>
                      <th className="px-3 py-3 font-bold">Jenis biaya</th>
                      <th className="px-3 py-3 font-bold">Dicatat oleh</th>
                      <th className="px-3 py-3 text-right font-bold">Nominal</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {metrics.expenses.map((expense: any) => (
                      <tr key={expense.id} className="text-slate-600 transition hover:bg-slate-50">
                        <td className="px-3 py-4">{new Date(`${expense.expense_date}T00:00:00`).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })}</td>
                        <td className="px-3 py-4 font-bold text-slate-800">{expense.name}</td>
                        <td className="px-3 py-4">{expense.recorded_by || 'System'}</td>
                        <td className="px-3 py-4 text-right font-bold text-red-600">-{formatRp(Number(expense.amount))}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                Belum ada pengeluaran tercatat pada periode ini.
              </div>
            )}
          </div>

          <div className="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div className="mb-5 flex items-center justify-between gap-3">
              <div>
                <h2 className="text-lg font-bold text-slate-800">Detail belanja bahan</h2>
                <p className="mt-1 text-sm text-slate-500">Harga restock yang tercatat pada {selectedPeriodLabel}.</p>
              </div>
              <span className="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700">
                {restockPurchases.length} transaksi
              </span>
            </div>

            {restockPurchases.length ? (
              <div className="overflow-x-auto">
                <table className="w-full min-w-[680px] text-left text-sm">
                  <thead className="border-b border-slate-200 text-xs uppercase tracking-wider text-slate-400">
                    <tr>
                      <th className="px-3 py-3 font-bold">Bahan</th>
                      <th className="px-3 py-3 font-bold">Kategori</th>
                      <th className="px-3 py-3 font-bold">Jumlah</th>
                      <th className="px-3 py-3 font-bold">Tanggal</th>
                      <th className="px-3 py-3 text-right font-bold">Harga beli</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {restockPurchases.map((purchase: any) => (
                      <tr key={purchase.id} className="text-slate-600 transition hover:bg-slate-50">
                        <td className="px-3 py-4 font-bold text-slate-800">{purchase.material_name}</td>
                        <td className="px-3 py-4">
                          <span className={`rounded-full px-2.5 py-1 text-xs font-bold uppercase ${purchase.category === 'bar' ? 'bg-blue-100 text-blue-700' : 'bg-orange-100 text-orange-700'}`}>
                            {purchase.category}
                          </span>
                        </td>
                        <td className="px-3 py-4">{purchase.quantity_added} {purchase.unit}</td>
                        <td className="px-3 py-4">{new Date(purchase.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })}</td>
                        <td className="px-3 py-4 text-right font-bold text-amber-700">{formatRp(Number(purchase.total_cost))}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                Belum ada pembelian bahan yang tercatat pada periode ini.
              </div>
            )}
          </div>
        </main>
      </div>

      {showExpenseModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-stone-900/60 backdrop-blur-sm">
          <div className="w-full max-w-sm rounded-2xl bg-white p-8 shadow-2xl">
            <h2 className="text-xl font-bold text-stone-800 mb-6">Catat Biaya Operasional</h2>
            <form onSubmit={handleAddExpense} className="space-y-4">
              <div>
                <label className="text-sm font-bold text-stone-600 block mb-1">Jenis Pengeluaran</label>
                <input type="text" value={expenseData.name} onChange={e => setExpenseData({...expenseData, name: e.target.value})} placeholder="Cth: Bayar Listrik / Gaji" required className="w-full border rounded-xl p-3 bg-stone-50 outline-none focus:border-red-500" />
              </div>
              <div>
                <label className="text-sm font-bold text-stone-600 block mb-1">Nominal (Rp)</label>
                <input type="text" inputMode="numeric" value={expenseData.amount} onChange={e => setExpenseData({...expenseData, amount: formatNumberInput(e.target.value)})} placeholder="0" required className="w-full border rounded-xl p-3 bg-stone-50 outline-none focus:border-red-500" />
              </div>
              <div>
                <label className="text-sm font-bold text-stone-600 block mb-1">Tanggal</label>
                <input type="date" value={expenseData.expense_date} onChange={e => setExpenseData({...expenseData, expense_date: e.target.value})} required className="w-full border rounded-xl p-3 bg-stone-50 outline-none focus:border-red-500" />
              </div>
              <div className="flex gap-4 mt-6">
                <button type="button" onClick={() => setShowExpenseModal(false)} className="w-1/3 py-3 rounded-xl bg-stone-100 font-bold text-stone-600">Batal</button>
                <button type="submit" className="w-2/3 py-3 rounded-xl bg-red-600 font-bold text-white shadow-md hover:bg-red-700">Simpan Pengeluaran</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
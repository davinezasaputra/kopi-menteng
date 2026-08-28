import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import axios from 'axios';
import { AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';
import AdminSidebar from '../components/AdminSidebar'; // Sesuaikan path jika berbeda

export default function Dashboard() {
  const navigate = useNavigate();
  const [metrics, setMetrics] = useState<any>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const token = localStorage.getItem('token');
    if (!token) {
      navigate('/');
      return;
    }
    fetchDashboardData();
  }, [navigate]);

  const fetchDashboardData = async () => {
    setIsLoading(true);
    const token = localStorage.getItem('token');
    try {
      const response = await axios.get('http://localhost:8000/api/finance/dashboard', {
        headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
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

  return (
    <div className="flex h-screen w-full bg-stone-50 font-sans text-stone-800">
      <AdminSidebar activePage="history" />

      <div className="flex-1 flex flex-col overflow-hidden">
        <header className="h-20 bg-white border-b border-stone-200 flex items-center px-8 shadow-sm justify-between">
          <h1 className="text-xl font-bold text-stone-800">Dasbor Analitik Keuangan</h1>
          <div className="text-sm font-bold text-stone-500 bg-stone-100 px-4 py-2 rounded-lg">
            Bulan Ini: {new Date().toLocaleDateString('id-ID', { month: 'long', year: 'numeric' })}
          </div>
        </header>

        <main className="flex-1 overflow-y-auto p-8">
          
          {/* ================= KARTU MATRIKS (SUMMARY) ================= */}
          <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div className="bg-white p-6 rounded-2xl border border-stone-200 shadow-sm border-t-4 border-t-blue-500">
              <p className="text-sm font-bold text-stone-500 mb-1">Omzet Kotor (Gross)</p>
              <h3 className="text-3xl font-black text-stone-800">{formatRp(metrics?.summary?.gross_revenue)}</h3>
              <p className="text-xs text-stone-400 mt-2">Termasuk PPN 11%</p>
            </div>
            <div className="bg-white p-6 rounded-2xl border border-stone-200 shadow-sm border-t-4 border-t-red-500">
              <p className="text-sm font-bold text-stone-500 mb-1">Modal Bahan (HPP)</p>
              <h3 className="text-3xl font-black text-stone-800">{formatRp(metrics?.summary?.total_cogs)}</h3>
              <p className="text-xs text-stone-400 mt-2">Total Harga Pokok Penjualan</p>
            </div>
            <div className="bg-white p-6 rounded-2xl border border-stone-200 shadow-sm border-t-4 border-t-orange-500">
              <p className="text-sm font-bold text-stone-500 mb-1">Titipan PPN (11%)</p>
              <h3 className="text-3xl font-black text-stone-800">{formatRp(metrics?.summary?.tax_payable)}</h3>
              <p className="text-xs text-stone-400 mt-2">Wajib disetor ke negara</p>
            </div>
            <div className="bg-white p-6 rounded-2xl border border-stone-200 shadow-xl border-t-4 border-t-green-500 relative overflow-hidden">
              <div className="absolute -right-4 -bottom-4 text-green-100 text-8xl opacity-30">💰</div>
              <p className="text-sm font-bold text-stone-500 mb-1">Laba Bersih (Net Profit)</p>
              <h3 className="text-3xl font-black text-green-600 relative z-10">{formatRp(metrics?.summary?.net_profit)}</h3>
              <p className="text-xs text-stone-400 mt-2 relative z-10">Omzet - PPN - HPP</p>
            </div>
          </div>

          {/* ================= AREA GRAFIK & PRODUK ================= */}
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            {/* GRAFIK TREN PENJUALAN */}
            <div className="lg:col-span-2 bg-white p-6 rounded-2xl border border-stone-200 shadow-sm">
              <h2 className="text-lg font-bold text-stone-800 mb-6">Tren Penjualan & Laba Harian</h2>
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
                    <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#e7e5e4" />
                    <XAxis dataKey="date" tick={{fontSize: 12}} stroke="#a8a29e" tickFormatter={(str) => new Date(str).toLocaleDateString('id-ID', {day:'numeric', month:'short'})} />
                    <YAxis tick={{fontSize: 12}} stroke="#a8a29e" tickFormatter={(val) => `Rp ${val / 1000}k`} />
                    <Tooltip formatter={(value) => formatRp(Number(value) || 0)} labelFormatter={(label) => label != null ? new Date(String(label)).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long' }) : ''} />
                    <Area type="monotone" dataKey="gross_revenue" name="Omzet Kotor" stroke="#3b82f6" strokeWidth={3} fillOpacity={1} fill="url(#colorGross)" />
                    <Area type="monotone" dataKey="net_profit" name="Laba Bersih" stroke="#22c55e" strokeWidth={3} fillOpacity={1} fill="url(#colorNet)" />
                  </AreaChart>
                </ResponsiveContainer>
              </div>
            </div>

            {/* TABEL MENU TERLARIS */}
            <div className="bg-white p-6 rounded-2xl border border-stone-200 shadow-sm flex flex-col">
              <h2 className="text-lg font-bold text-stone-800 mb-6">Menu Terlaris (Bulan Ini)</h2>
              <div className="flex-1 overflow-y-auto">
                {metrics?.top_products?.length === 0 ? (
                  <div className="h-full flex items-center justify-center text-sm text-stone-400 italic">Belum ada data penjualan.</div>
                ) : (
                  <div className="space-y-4">
                    {metrics?.top_products?.map((item: any, index: number) => (
                      <div key={index} className="flex items-center justify-between pb-4 border-b border-stone-100 last:border-0">
                        <div className="flex items-center gap-3">
                          <div className="flex items-center justify-center w-8 h-8 rounded-full bg-stone-100 text-stone-600 font-bold text-sm">
                            #{index + 1}
                          </div>
                          <div>
                            <p className="font-bold text-stone-800 text-sm">{item.product?.name}</p>
                            <p className="text-xs text-amber-700 font-bold mt-0.5">{item.total_qty} Terjual</p>
                          </div>
                        </div>
                        <div className="text-right">
                          <p className="text-xs text-stone-500 mb-0.5">Sumbangan Omzet</p>
                          <p className="font-bold text-stone-800 text-sm">{formatRp(item.total_revenue)}</p>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>

          </div>
        </main>
      </div>
    </div>
  );
}
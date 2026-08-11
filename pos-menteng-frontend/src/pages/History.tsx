import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import axios from 'axios';

interface OrderItem {
  id: number;
  product_id: number;
  quantity: number;
  unit_price: number;
  product?: {
    name: string;
  };
}

interface Order {
  id: string;
  total: number;
  payment_method: string;
  status: string;
  created_at: string;
  items: OrderItem[];
}

export default function History() {
  const navigate = useNavigate();
  const [orders, setOrders] = useState<Order[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  
  // State Modal Detail
  const [selectedOrder, setSelectedOrder] = useState<Order | null>(null);

  useEffect(() => {
    const token = localStorage.getItem('token');
    if (!token) {
      navigate('/');
      return;
    }
    fetchOrders();
  }, [navigate]);

  const fetchOrders = async () => {
    const token = localStorage.getItem('token');
    setIsLoading(true);
    try {
      const response = await axios.get('http://localhost:8000/api/orders/history', {
        headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
      });
      setOrders(response.data.data);
    } catch (error) {
      console.error("Gagal mengambil data riwayat", error);
    } finally {
      setIsLoading(false);
    }
  };

  // Kalkulasi Cepat untuk Kartu Ringkasan
  const totalRevenue = orders.reduce((sum, order) => sum + Number(order.total), 0);
  const totalTransactions = orders.length;

  return (
    <div className="flex h-screen w-full bg-stone-50 font-sans text-stone-800">
      
      {/* SIDEBAR BACKOFFICE */}
      <div className="w-64 bg-stone-900 text-stone-300 flex flex-col">
        <div className="p-6 border-b border-stone-800 flex items-center gap-3">
          <div className="flex h-8 w-8 items-center justify-center rounded bg-amber-700 font-bold text-white text-xs">KM</div>
          <span className="font-bold text-white tracking-wide">Backoffice</span>
        </div>
        <nav className="flex-1 p-4 space-y-2">
          <button onClick={() => navigate('/pos')} className="w-full flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-stone-800 hover:text-white transition text-left">
            <span>🛒</span> Kasir (POS)
          </button>
          <button onClick={() => navigate('/inventory')} className="w-full flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-stone-800 hover:text-white transition text-left">
            <span>📦</span> Data Stok
          </button>
          <button className="w-full flex items-center gap-3 px-4 py-3 rounded-xl bg-amber-700/20 text-amber-500 font-medium transition text-left">
            <span>🧾</span> Riwayat & Laporan
          </button>
        </nav>
      </div>

      {/* KONTEN UTAMA */}
      <div className="flex-1 flex flex-col overflow-hidden">
        <header className="h-20 bg-white border-b border-stone-200 flex items-center px-8 shadow-sm">
          <h1 className="text-xl font-bold text-stone-800">Riwayat Transaksi</h1>
        </header>

        <main className="flex-1 overflow-y-auto p-8">
          
          {/* KARTU RINGKASAN */}
          <div className="grid grid-cols-2 gap-6 mb-8">
            <div className="bg-white p-6 rounded-2xl border border-stone-200 shadow-sm flex items-center gap-4">
              <div className="flex h-14 w-14 items-center justify-center rounded-full bg-green-100 text-2xl">💰</div>
              <div>
                <p className="text-sm font-bold text-stone-500">Total Pendapatan</p>
                <p className="text-2xl font-black text-stone-800">Rp {totalRevenue.toLocaleString('id-ID')}</p>
              </div>
            </div>
            <div className="bg-white p-6 rounded-2xl border border-stone-200 shadow-sm flex items-center gap-4">
              <div className="flex h-14 w-14 items-center justify-center rounded-full bg-blue-100 text-2xl">🧾</div>
              <div>
                <p className="text-sm font-bold text-stone-500">Total Transaksi</p>
                <p className="text-2xl font-black text-stone-800">{totalTransactions} Pesanan</p>
              </div>
            </div>
          </div>

          {/* TABEL RIWAYAT */}
          <div className="bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden">
            <table className="w-full text-left border-collapse">
              <thead>
                <tr className="bg-stone-100 text-stone-500 text-sm uppercase tracking-wider border-b border-stone-200">
                  <th className="p-4 font-medium text-center w-16">ID</th>
                  <th className="p-4 font-medium">Waktu Transaksi</th>
                  <th className="p-4 font-medium">Metode</th>
                  <th className="p-4 font-medium">Total Harga</th>
                  <th className="p-4 font-medium text-center">Status</th>
                  <th className="p-4 font-medium text-center w-32">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-stone-100">
                {isLoading ? (
                  <tr><td colSpan={6} className="p-8 text-center text-stone-500">Memuat data transaksi...</td></tr>
                ) : orders.length === 0 ? (
                  <tr><td colSpan={6} className="p-8 text-center text-stone-500">Belum ada transaksi terekam.</td></tr>
                ) : (
                  orders.map((order) => (
                    <tr key={order.id} className="hover:bg-stone-50 transition">
                      <td className="p-4 text-center font-mono text-stone-400">#{order.id}</td>
                      <td className="p-4 font-medium text-stone-700">
                        {new Date(order.created_at).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' })}
                      </td>
                      <td className="p-4">
                        <span className={`px-3 py-1 rounded-full text-xs font-bold uppercase ${order.payment_method === 'cash' ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700'}`}>
                          {order.payment_method}
                        </span>
                      </td>
                      <td className="p-4 font-bold text-stone-800">Rp {Number(order.total).toLocaleString('id-ID')}</td>
                      <td className="p-4 text-center">
                        <span className="px-3 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700">Sukses</span>
                      </td>
                      <td className="p-4 flex justify-center">
                        <button onClick={() => setSelectedOrder(order)} className="p-2 text-stone-500 hover:text-amber-700 hover:bg-amber-50 rounded-lg transition" title="Lihat Detail">
                          🔍 Detail
                        </button>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </main>
      </div>

      {/* MODAL DETAIL PESANAN */}
      {selectedOrder && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-stone-900/60 backdrop-blur-sm">
          <div className="w-full max-w-md rounded-2xl bg-white p-8 shadow-2xl">
            <div className="flex justify-between items-center mb-6">
              <h2 className="text-xl font-bold text-stone-800">Detail Pesanan #{selectedOrder.id}</h2>
              <button onClick={() => setSelectedOrder(null)} className="text-stone-400 hover:text-red-500 font-bold text-xl">&times;</button>
            </div>
            
            <div className="mb-4 text-sm text-stone-500 border-b border-stone-200 pb-4">
              <p>Waktu: {new Date(selectedOrder.created_at).toLocaleString('id-ID')}</p>
              <p className="uppercase">Pembayaran: {selectedOrder.payment_method}</p>
            </div>

            <div className="space-y-3 mb-6 max-h-60 overflow-y-auto">
              {selectedOrder.items && selectedOrder.items.length > 0 ? (
                selectedOrder.items.map((item) => (
                  <div key={item.id} className="flex justify-between items-center">
                    <div>
                      <p className="font-bold text-stone-700">{item.product?.name || 'Produk Dihapus'}</p>
                      <p className="text-xs text-stone-500">{item.quantity} x Rp {Number(item.unit_price).toLocaleString('id-ID')}</p>
                    </div>
                    <p className="font-bold text-stone-800">Rp {(item.quantity * item.unit_price).toLocaleString('id-ID')}</p>
                  </div>
                ))
              ) : (
                <p className="text-center text-sm text-stone-400 italic">Rincian item tidak ditemukan.</p>
              )}
            </div>

            <div className="border-t border-stone-200 pt-4 flex justify-between items-center text-lg font-black text-amber-700">
              <span>TOTAL</span>
              <span>Rp {Number(selectedOrder.total).toLocaleString('id-ID')}</span>
            </div>
            
            <button onClick={() => setSelectedOrder(null)} className="mt-8 w-full rounded-xl bg-stone-100 py-3 font-bold text-stone-600 hover:bg-stone-200 transition">
              Tutup
            </button>
          </div>
        </div>
      )}

    </div>
  );
}
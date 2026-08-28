import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import toast from 'react-hot-toast';
import axios from 'axios';

// --- INTERFACES ---
interface Product {
  id: string;
  name: string;
  price: number;
  stock: number;
  category_id: string;
  raw_materials?: {
    stock: number;
    pivot: { quantity_needed: number; };
  }[];
}

interface CartItem extends Product {
  quantity: number;
}

interface OrderItem {
  id: number;
  product_id: number;
  quantity: number;
  unit_price: number;
  subtotal: number;
  product?: { name: string; };
}

interface Order {
  id: string;
  total: number;
  payment_method: string;
  status: string;
  created_at: string;
  items: OrderItem[];
}


export default function Pos() {
  const navigate = useNavigate();
  const [products, setProducts] = useState<Product[]>([]);
  const [cart, setCart] = useState<CartItem[]>([]);
  const [user, setUser] = useState<{ name: string } | null>(null);
  
  // State Shift
  const [isShiftOpen, setIsShiftOpen] = useState(false);
  const [showShiftModal, setShowShiftModal] = useState(false);
  const [startingCash, setStartingCash] = useState('');
  const [shiftProcessing, setShiftProcessing] = useState(false);

  const fetchShiftStatus = async () => {
    const token = localStorage.getItem('token');
    if (!token) return;

    try {
      const response = await axios.get('http://localhost:8000/api/shifts/status', {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      setIsShiftOpen(Boolean(response.data.is_open));
    } catch (error) {
      console.error('Gagal mengambil status shift:', error);
      setIsShiftOpen(false);
    }
  };

  // State Tutup Shift
  const [showCloseShiftModal, setShowCloseShiftModal] = useState(false);
  const [endingCash, setEndingCash] = useState('');

  // State Struk & Pembayaran Utama
  const [showPaymentModal, setShowPaymentModal] = useState(false);
  const [isProcessing, setIsProcessing] = useState(false);
  const [isQrisReceipt, setIsQrisReceipt] = useState(false);
  const [receiptType, setReceiptType] = useState<'customer' | 'kitchen'>('customer');
  const [showCashModal, setShowCashModal] = useState(false);
  const [cashTendered, setCashTendered] = useState('');

  // ==========================================
  // STATE BARU: RIWAYAT KASIR & REPRINT
  // ==========================================
  const [showHistoryModal, setShowHistoryModal] = useState(false);
  const [historyOrders, setHistoryOrders] = useState<Order[]>([]);
  const [isHistoryLoading, setIsHistoryLoading] = useState(false);
  const [reprintOrder, setReprintOrder] = useState<Order | null>(null);

  const calculateEffectiveStock = (product: Product) => {
    let maxStock = product.stock;
    if (product.raw_materials && product.raw_materials.length > 0) {
      const recipeLimits = product.raw_materials.map(rm => Math.floor(rm.stock / rm.pivot.quantity_needed));
      const maxRecipeStock = Math.min(...recipeLimits);
      maxStock = Math.min(maxStock, maxRecipeStock);
    }
    return maxStock;
  };

  const fetchProducts = async () => {
    const token = localStorage.getItem('token');
    try {
      const response = await axios.get('http://localhost:8000/api/products', {
        headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
      });
      setProducts(response.data.data);
    } catch (error) {
      console.error("Gagal mengambil data produk", error);
    }
  };

  useEffect(() => {
    const token = localStorage.getItem('token');
    const userData = localStorage.getItem('user');
    
    if (!token) {
      navigate('/');
      return;
    }
    if (userData && userData !== 'undefined') setUser(JSON.parse(userData));

    fetchShiftStatus();

    // Deteksi Kembalian Midtrans
    const searchParams = new URLSearchParams(window.location.search);
    if (searchParams.get('order_id') && searchParams.get('status_code') === '200') {
      const savedCart = localStorage.getItem('pending_qris_cart');
      if (savedCart) {
        setCart(JSON.parse(savedCart));
        setIsQrisReceipt(true); 
        setShowPaymentModal(true);
        window.history.replaceState({}, document.title, window.location.pathname);
        localStorage.removeItem('pending_qris_cart');
        executePrintSequence(); 
      }
    }
    fetchProducts();
  }, [navigate]);

  const addToCart = (product: Product) => {
    setCart((prev) => {
      const existing = prev.find((item) => item.id === product.id);
      if (existing) return prev.map((item) => item.id === product.id ? { ...item, quantity: item.quantity + 1 } : item);
      return [...prev, { ...product, quantity: 1 }];
    });
  };

  const removeFromCart = (id: string) => setCart((prev) => prev.filter((item) => item.id !== id));

// --- KALKULASI PAJAK INKLUSIF (HARGA SUDAH TERMASUK PAJAK) ---
  const total = cart.reduce((sum, item) => sum + item.price * item.quantity, 0);
  const basePrice = total / 1.11; // DPP (Dasar Pengenaan Pajak)
  const tax = total - basePrice;  // Potongan PPN untuk laporan negara
  const changeAmount = Number(cashTendered) - total;

  const handleLogout = () => {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    localStorage.removeItem('isShiftOpen');
    setIsShiftOpen(false);
    navigate('/');
  };

  const handleOpenShift = async (e: React.FormEvent) => {
    e.preventDefault();
    setShiftProcessing(true);
    try {
      await axios.post('http://localhost:8000/api/shifts/open', { starting_cash: Number(startingCash) }, {
        headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }
      });
      setIsShiftOpen(true);
      localStorage.removeItem('isShiftOpen');
      setShowShiftModal(false);
    } catch (error: any) {
      toast.error('Gagal buka shift atau shift masih aktif.');
    } finally {
      setShiftProcessing(false);
    }
  };

  const handleCloseShift = async (e: React.FormEvent) => {
    e.preventDefault();
    setShiftProcessing(true);
    try {
      await axios.post('http://localhost:8000/api/shifts/close', { actual_ending_cash: Number(endingCash) }, {
        headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }
      });
      setIsShiftOpen(false);
      localStorage.removeItem('isShiftOpen');
      setShowCloseShiftModal(false);
      setEndingCash('');
      toast.success('Shift berhasil ditutup!');
    } catch (error) {
      toast.error('Gagal menutup shift.');
    } finally {
      setShiftProcessing(false);
    }
  };

  // --- FUNGSI ANTREAN CETAK OTOMATIS (BARU) ---
  const executePrintSequence = () => {
    setReceiptType('kitchen');
    setTimeout(() => {
      window.print(); 
      setReceiptType('customer');
      setTimeout(() => {
        window.print(); 
        setCart([]);
        setShowPaymentModal(false);
        setShowCashModal(false);
        setCashTendered('');
        setIsQrisReceipt(false);
      }, 500); 
    }, 500); 
  };

  const triggerPayment = () => !isShiftOpen ? setShowShiftModal(true) : setShowPaymentModal(true);

  const handleMidtransCheckout = async () => {
    setIsProcessing(true);
    try {
      const payload = { payment_method: 'qris', items: cart.map(item => ({ product_id: item.id, quantity: item.quantity })) };
      const response = await axios.post('http://localhost:8000/api/orders/checkout', payload, {
        headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }
      });
      if (response.data.status === 'success') {
        localStorage.setItem('pending_qris_cart', JSON.stringify(cart));
        window.location.href = response.data.payment_url;
      }
    } catch (error) {
      toast.error('Gagal Checkout.');
    } finally {
      setIsProcessing(false);
    }
  };

  const handleConfirmCashPayment = async (e: React.FormEvent) => {
    e.preventDefault();
    if (Number(cashTendered) < total){
      toast.error('Uang tunai tidak cukup untuk membayar total pesanan.');
      return;
    }
    setIsProcessing(true);
    const toastId = toast.loading('Memprosses Transasksi...');
    try {
      const payload = { payment_method: 'cash', items: cart.map(item => ({ product_id: item.id, quantity: item.quantity })) };
      await axios.post('http://localhost:8000/api/orders/checkout', payload, {
        headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }
      });

      toast.success('Transaksi Berhasil', {id: toastId});
      await fetchProducts();
      setIsQrisReceipt(false);
      executePrintSequence();
    } catch (error) {
      toast.error('Gagal menyimpan transaksi.');
    } finally {
      setIsProcessing(false);
    }
  };

  // ==========================================
  // FUNGSI BARU: RIWAYAT KASIR & REPRINT
  // ==========================================
  const handleOpenHistory = async () => {
    setShowHistoryModal(true);
    setIsHistoryLoading(true);
    try {
      const response = await axios.get('http://localhost:8000/api/orders', {
        headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }
      });
      setHistoryOrders(response.data.data);
    } catch (error) {
      console.error(error);
    } finally {
      setIsHistoryLoading(false);
    }
  };

  const handleReprintOrder = (order: Order) => {
    setReprintOrder(order);
    setTimeout(() => {
      window.print();
      setTimeout(() => setReprintOrder(null), 500); // Bersihkan memori cetak ulang setelah selesai
    }, 500);
  };

  return (
    <div className="flex h-screen w-full bg-stone-100 font-sans text-stone-800 relative">
      
      {/* ================= DAFTAR MENU ================= */}
      <div className="flex w-8/12 flex-col print:hidden">
        <header className="flex h-20 items-center justify-between bg-white px-8 shadow-sm">
          <div className="flex items-center gap-4">
            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-700 font-bold text-white">KM</div>
            <h1 className="text-xl font-bold tracking-tight">Kopi Menteng POS</h1>
          </div>
          <div className="flex items-center gap-4">
            {/* TOMBOL RIWAYAT KASIR */}
            <button onClick={handleOpenHistory} className="rounded-lg bg-stone-100 px-4 py-2 text-sm font-bold text-stone-600 border border-stone-300 hover:bg-stone-200 transition">
              🧾 Riwayat Nota
            </button>
            <div className="h-6 w-px bg-stone-300"></div>

            {!isShiftOpen ? (
              <button onClick={() => setShowShiftModal(true)} className="animate-pulse rounded-lg bg-amber-100 px-4 py-2 text-sm font-bold text-amber-700 border border-amber-300">
                Shift Ditutup
              </button>
            ) : (
              <button onClick={() => setShowCloseShiftModal(true)} className="rounded-lg bg-green-100 px-4 py-2 text-sm font-bold text-green-700 border border-green-300 hover:bg-green-200 transition">
                Shift Aktif (Tutup)
              </button>
            )}
            <div className="h-6 w-px bg-stone-300"></div>
            <span className="text-sm font-medium text-stone-500">{user?.name || 'Kasir'}</span>
            <button onClick={handleLogout} className="rounded-lg bg-red-50 px-4 py-2 text-sm font-bold text-red-600 hover:bg-red-100">Keluar</button>
          </div>
        </header>

        <div className="flex-1 overflow-y-auto p-8">
          {products.length === 0 ? (
            <div className="text-stone-500">Memuat data produk...</div>
          ) : (
            <div className="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-3 md:gap-4 pb-20">
              {products.map((product) => {
                const availableStock = calculateEffectiveStock(product);
                const isOutOfStock = availableStock <= 0;
                return (
                  <button
                    key={product.id}
                    disabled={isOutOfStock}
                    onClick={() => addToCart(product)}
                    className={`relative flex flex-col p-4 rounded-2xl border-2 text-left transition-all ${
                      isOutOfStock ? 'bg-stone-100 border-stone-200 grayscale opacity-60 cursor-not-allowed' : 'bg-white border-transparent shadow-sm hover:border-amber-500 hover:shadow-md'
                    }`}
                  >
                    <div className="flex-1">
                      <h3 className="font-bold text-stone-800 text-lg leading-tight mb-1">{product.name}</h3>
                      <p className="text-amber-700 font-black">Rp {Number(product.price).toLocaleString('id-ID')}</p>
                    </div>
                    <div className="mt-4 flex items-center justify-between">
                      {isOutOfStock ? (
                        <span className="bg-red-100 text-red-600 font-bold text-xs px-2.5 py-1 rounded-md">HABIS / SOLD OUT</span>
                      ) : (
                        <span className="bg-stone-100 text-stone-500 font-bold text-xs px-2.5 py-1 rounded-md">Tersedia: {availableStock}</span>
                      )}
                    </div>
                  </button>
                );
              })}
            </div>
          )}
        </div>
      </div>

      {/* ================= KERANJANG ================= */}
      <div className="z-10 flex w-4/12 flex-col border-l border-stone-200 bg-white shadow-xl print:hidden">
        <div className="border-b border-stone-100 p-6 text-center">
          <h2 className="text-xl font-bold text-stone-800">Pesanan Saat Ini</h2>
        </div>
        <div className="flex-1 overflow-y-auto p-6">
          {cart.length === 0 ? (
            <div className="flex h-full flex-col items-center justify-center text-stone-400">Belum ada pesanan</div>
          ) : (
            <div className="flex flex-col gap-4">
              {cart.map((item) => (
                <div key={item.id} className="flex items-center justify-between border-b border-stone-100 pb-4">
                  <div className="flex-1">
                    <h4 className="font-bold text-stone-700">{item.name}</h4>
                    <p className="text-sm text-amber-700">Rp {Number(item.price).toLocaleString('id-ID')} x {item.quantity}</p>
                  </div>
                  <div className="text-right">
                    <p className="font-bold text-stone-800">Rp {(item.price * item.quantity).toLocaleString('id-ID')}</p>
                    <button onClick={() => removeFromCart(item.id)} className="mt-1 text-xs font-medium text-red-500 hover:text-red-700">Hapus</button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
        <div className="border-t border-stone-200 bg-stone-50 p-6">
          <div className="mb-2 flex justify-between text-sm text-stone-500">
            <span>DPP (Dasar Harga)</span><span>Rp {Math.round(basePrice).toLocaleString('id-ID')}</span>
          </div>
          <div className="mb-4 flex justify-between text-sm text-stone-500">
            <span>Termasuk PPN 11%</span><span>Rp {Math.round(tax).toLocaleString('id-ID')}</span>
          </div>
          <div className="mb-6 flex justify-between text-2xl font-black text-stone-800">
            <span>Total Bayar</span><span>Rp {total.toLocaleString('id-ID')}</span>
          </div>
          <button onClick={triggerPayment} disabled={cart.length === 0} className={`w-full rounded-2xl py-4 text-lg font-bold text-white transition-all ${cart.length === 0 ? 'cursor-not-allowed bg-stone-300' : 'bg-amber-700 shadow-lg hover:bg-amber-800 active:scale-95'}`}>
            Bayar Pesanan
          </button>
        </div>
      </div>

      {/* ================= MODAL BUKA SHIFT ================= */}
      {showShiftModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-stone-900/60 backdrop-blur-sm print:hidden">
          <div className="w-full max-w-md rounded-2xl bg-white p-8 shadow-2xl">
            <h2 className="mb-2 text-2xl font-bold text-stone-800">Buka Shift Kasir</h2>
            <form onSubmit={handleOpenShift}>
              <div className="my-6 relative">
                <span className="absolute left-4 top-1/2 -translate-y-1/2 font-bold text-stone-400">Rp</span>
                <input type="number" value={startingCash} onChange={(e) => setStartingCash(e.target.value)} className="w-full rounded-xl border border-stone-300 bg-stone-50 p-4 pl-12 text-xl font-bold text-stone-800" placeholder="Modal awal..." required min="0" />
              </div>
              <div className="flex gap-4">
                <button type="button" onClick={() => setShowShiftModal(false)} className="w-1/3 rounded-xl bg-stone-100 py-3 font-bold text-stone-500">Batal</button>
                <button type="submit" disabled={shiftProcessing || !startingCash} className="w-2/3 rounded-xl bg-amber-700 py-3 font-bold text-white shadow-lg disabled:opacity-50">
                  {shiftProcessing ? 'Membuka...' : 'Buka Laci'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ================= MODAL TUTUP SHIFT ================= */}
      {showCloseShiftModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-stone-900/60 backdrop-blur-sm print:hidden">
          <div className="w-full max-w-md rounded-2xl bg-white p-8 shadow-2xl">
            <h2 className="mb-2 text-2xl font-bold text-red-600">Tutup Shift (End of Day)</h2>
            <p className="mb-6 text-sm text-stone-500">Hitung dan masukkan total uang fisik yang ada di dalam laci kasir saat ini.</p>
            <form onSubmit={handleCloseShift}>
              <div className="my-6 relative">
                <span className="absolute left-4 top-1/2 -translate-y-1/2 font-bold text-stone-400">Rp</span>
                <input type="number" value={endingCash} onChange={(e) => setEndingCash(e.target.value)} className="w-full rounded-xl border border-stone-300 bg-stone-50 p-4 pl-12 text-xl font-bold text-stone-800 focus:border-red-500 focus:ring-2 focus:ring-red-200" placeholder="Uang fisik akhir..." required min="0" />
              </div>
              <div className="flex gap-4">
                <button type="button" onClick={() => setShowCloseShiftModal(false)} className="w-1/3 rounded-xl bg-stone-100 py-3 font-bold text-stone-500">Batal</button>
                <button type="submit" disabled={shiftProcessing || !endingCash} className="w-2/3 rounded-xl bg-red-600 py-3 font-bold text-white shadow-lg disabled:opacity-50 hover:bg-red-700">
                  {shiftProcessing ? 'Memproses...' : 'Tutup Shift'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}


      {/* ================= MODAL RIWAYAT & CETAK ULANG ================= */}
      {showHistoryModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-stone-900/60 backdrop-blur-sm print:hidden">
          <div className="w-full max-w-4xl max-h-[90vh] flex flex-col rounded-2xl bg-white shadow-2xl overflow-hidden">
            <div className="flex items-center justify-between bg-stone-100 px-6 py-4 border-b border-stone-200">
              <h2 className="text-xl font-bold text-stone-800">Riwayat & Cetak Ulang Nota</h2>
              <button onClick={() => setShowHistoryModal(false)} className="text-stone-500 hover:text-red-500 font-bold text-xl">&times;</button>
            </div>
            
            <div className="flex-1 overflow-y-auto p-6">
              <table className="w-full text-left border-collapse">
                <thead>
                  <tr className="bg-stone-50 text-stone-500 text-sm uppercase tracking-wider border-b border-stone-200">
                    <th className="p-4 font-medium">Nota</th>
                    <th className="p-4 font-medium">Waktu</th>
                    <th className="p-4 font-medium">Metode</th>
                    <th className="p-4 font-medium">Total</th>
                    <th className="p-4 font-medium text-center">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-stone-100">
                  {isHistoryLoading ? (
                    <tr><td colSpan={5} className="p-8 text-center text-stone-500">Memuat riwayat...</td></tr>
                  ) : historyOrders.length === 0 ? (
                    <tr><td colSpan={5} className="p-8 text-center text-stone-500">Belum ada transaksi.</td></tr>
                  ) : (
                    historyOrders.map((order) => (
                      <tr key={order.id} className="hover:bg-stone-50 transition">
                        <td className="p-4 font-bold text-stone-700">INV-KM-{order.id.toString().padStart(4, '0')}</td>
                        <td className="p-4 text-stone-500 text-sm">
                          {new Date(order.created_at).toLocaleDateString('id-ID', { month: 'short', day: 'numeric' })} <br/>
                          <span className="text-xs">{new Date(order.created_at).toLocaleTimeString('id-ID')}</span>
                        </td>
                        <td className="p-4 text-xs font-bold uppercase text-stone-500">{order.payment_method}</td>
                        <td className="p-4 font-bold text-amber-700">Rp {Number(order.total).toLocaleString('id-ID')}</td>
                        <td className="p-4 text-center">
                          <button onClick={() => handleReprintOrder(order)} className="px-4 py-2 bg-stone-100 border border-stone-300 text-stone-700 rounded-lg hover:bg-stone-200 hover:text-stone-900 font-bold text-sm transition shadow-sm">
                            🖨️ Cetak
                          </button>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* ================= MODAL PEMBAYARAN UTAMA ================= */}
      {showPaymentModal && !reprintOrder && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-stone-900/80 backdrop-blur-sm print:static print:bg-white print:block print:inset-auto">
          <div className="flex w-full max-w-4xl overflow-hidden rounded-2xl bg-stone-100 shadow-2xl print:shadow-none print:w-auto print:rounded-none">
            {/* KIRI: KERTAS STRUK */}
            <div className="flex w-1/2 justify-center bg-stone-300 p-8 print:w-full print:p-0 print:bg-white overflow-y-auto max-h-[80vh] print:max-h-none print:overflow-visible">
              <div className="w-80 bg-white p-6 text-black shadow-md font-mono text-sm print:shadow-none print:w-full">
                {receiptType === 'customer' ? (
                  <>
                    <div className="text-center">
                      <h2 className="text-2xl font-bold">KOPI MENTENG</h2>
                      <p className="mt-1 text-xs">Jl. Jenderal Sudirman</p>
                    </div>
                    <div className="my-4 border-b-2 border-dashed border-gray-400"></div>
                    <div className="mb-4 flex justify-between text-xs">
                      <span>Kasir: {user?.name || 'Kasir'}</span>
                      <span>{new Date().toLocaleTimeString('id-ID', {hour: '2-digit', minute:'2-digit'})}</span>
                    </div>
                    <div className="mb-4 border-b-2 border-dashed border-gray-400"></div>
                    <div className="mb-4">
                      {cart.map((item) => (
                        <div key={item.id} className="mb-2">
                          <div className="font-bold">{item.name}</div>
                          <div className="flex justify-between">
                            <span>{item.quantity} x {item.price.toLocaleString('id-ID')}</span>
                            <span>{(item.price * item.quantity).toLocaleString('id-ID')}</span>
                          </div>
                        </div>
                      ))}
                    </div>
                    <div className="my-4 border-b-2 border-dashed border-gray-400"></div>
                    <div className="flex justify-between mb-1">
                      <span>Harga Dasar (DPP)</span><span>{Math.round(basePrice).toLocaleString('id-ID')}</span>
                    </div>
                    <div className="flex justify-between mb-1">
                      <span>Inc. PPN (11%)</span><span>{Math.round(tax).toLocaleString('id-ID')}</span>
                    </div>
                    <div className="mt-2 flex justify-between border-t border-gray-400 pt-2 text-lg font-bold">
                      <span>TOTAL</span><span>Rp {total.toLocaleString('id-ID')}</span>
                    </div>
                    {isQrisReceipt ? (
                      <div className="mt-2 flex justify-between text-sm font-bold text-stone-800"><span>PEMBAYARAN</span><span>QRIS (LUNAS)</span></div>
                    ) : cashTendered !== '' && changeAmount >= 0 ? (
                      <>
                        <div className="mt-2 flex justify-between text-sm"><span>Tunai</span><span>Rp {Number(cashTendered).toLocaleString('id-ID')}</span></div>
                        <div className="mt-1 flex justify-between text-sm"><span>Kembali</span><span>Rp {changeAmount.toLocaleString('id-ID')}</span></div>
                      </>
                    ) : null}
                    <div className="my-4 border-b-2 border-dashed border-gray-400"></div>
                    <div className="text-center text-xs pb-4">
                      <p>Terima kasih atas kunjungan Anda!</p>
                      <p className="mt-1">WiFi: kopimenteng_guest</p>
                    </div>
                  </>
                ) : (
                  <div className="mt-2">
                    <div className="text-center"><h2 className="text-3xl font-black text-black">TIKET DAPUR</h2></div>
                    <div className="my-4 border-b-4 border-black"></div>
                    <div className="mb-4 flex justify-between text-sm font-bold"><span>Pukul: {new Date().toLocaleTimeString('id-ID', {hour: '2-digit', minute:'2-digit'})}</span></div>
                    <div className="mb-4 space-y-3">
                      {cart.map((item) => (
                        <div key={`kot-${item.id}`} className="flex items-start text-2xl font-black uppercase">
                          <span className="w-12">{item.quantity}x</span><span className="flex-1 leading-tight">{item.name}</span>
                        </div>
                      ))}
                    </div>
                    <div className="mt-8 border-t-2 border-dashed border-black pt-4 text-center text-sm font-bold">~ SIAPKAN PESANAN ~</div>
                  </div>
                )}
              </div>
            </div>
            {/* KANAN: PANEL KONTROL */}
            <div className="w-1/2 bg-white p-8 print:hidden flex flex-col justify-between">
              <div>
                <h2 className="text-2xl font-bold text-stone-800 mb-6">Pilih Pembayaran</h2>
                <div className="space-y-4">
                  <button onClick={() => setShowCashModal(true)} className="w-full rounded-xl border-2 border-stone-200 p-4 text-left hover:border-amber-500 hover:bg-amber-50 flex items-center justify-between transition group">
                    <div><h3 className="font-bold text-stone-800 text-lg group-hover:text-amber-700">Tunai (Cash)</h3></div>
                    <span className="text-3xl">💵</span>
                  </button>
                  <button onClick={handleMidtransCheckout} disabled={isProcessing} className="w-full rounded-xl border-2 border-stone-200 p-4 text-left hover:border-blue-500 hover:bg-blue-50 flex items-center justify-between transition group">
                    <div><h3 className="font-bold text-stone-800 text-lg group-hover:text-blue-600">Digital (QRIS)</h3></div>
                    <span className="text-3xl">📱</span>
                  </button>
                </div>
              </div>
              <button onClick={() => setShowPaymentModal(false)} className="mt-8 w-full rounded-xl bg-stone-100 py-3 font-bold text-stone-600 hover:bg-stone-200">Batal</button>
            </div>
          </div>
        </div>
      )}

      {/* ================= MODAL LAINNYA (TETAP SAMA SEPERTI SEBELUMNYA) ================= */}
      {/* (Modal Cash, Modal Buka Shift, Modal Tutup Shift disembunyikan di cetak otomatis karena print:hidden pada parent-nya) */}
      {showCashModal && (
        <div className="fixed inset-0 z-[60] flex items-center justify-center bg-stone-900/80 backdrop-blur-sm print:hidden">
          <div className="w-full max-w-md rounded-2xl bg-white p-8 shadow-2xl">
            <h2 className="text-2xl font-bold text-stone-800">Pembayaran Tunai</h2>
            <div className="mt-4 flex justify-between rounded-xl bg-amber-50 p-4 border border-amber-200">
              <span className="text-amber-800 font-medium">Total Tagihan:</span>
              <span className="text-xl font-bold text-amber-700">Rp {total.toLocaleString('id-ID')}</span>
            </div>
            <form onSubmit={handleConfirmCashPayment} className="mt-6">
              <label className="mb-2 block text-sm font-bold text-stone-600">Uang Diterima (Rp)</label>
              <input type="number" value={cashTendered} onChange={(e) => setCashTendered(e.target.value)} className="w-full rounded-xl border border-stone-300 bg-stone-50 p-4 text-2xl font-bold text-stone-800 outline-none" placeholder="0" autoFocus />
              <div className="mt-4 grid grid-cols-3 gap-2">
                <button type="button" onClick={() => setCashTendered(total.toString())} className="rounded-lg border py-2 text-sm font-bold text-stone-600 bg-white">Uang Pas</button>
                <button type="button" onClick={() => setCashTendered('50000')} className="rounded-lg border py-2 text-sm font-bold text-stone-600 bg-white">50.000</button>
                <button type="button" onClick={() => setCashTendered('100000')} className="rounded-lg border py-2 text-sm font-bold text-stone-600 bg-white">100.000</button>
              </div>
              <div className={`mt-6 rounded-xl p-4 text-center border ${changeAmount < 0 && cashTendered !== '' ? 'bg-red-50 border-red-200 text-red-600' : 'bg-stone-100 border-stone-200 text-stone-800'}`}>
                <p className="text-sm font-medium">Kembalian</p>
                <p className="text-3xl font-bold mt-1">{cashTendered === '' ? 'Rp 0' : changeAmount < 0 ? 'UANG KURANG' : `Rp ${changeAmount.toLocaleString('id-ID')}`}</p>
              </div>
              <div className="mt-8 flex gap-4">
                <button type="button" onClick={() => { setShowCashModal(false); setCashTendered(''); }} className="w-1/3 rounded-xl bg-stone-100 py-4 font-bold text-stone-600">Batal</button>
                <button type="submit" disabled={changeAmount < 0 || cashTendered === ''} className="w-2/3 rounded-xl bg-stone-800 py-4 font-bold text-white">Cetak Struk</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ================= AREA CETAK ULANG KHUSUS (REPRINT) ================= */}
      {/* Bagian ini secara fisik ada di layar namun TERSEMBUNYI, dan HANYA MUNCUL DI KERTAS PRINT */}
      {reprintOrder && (
        <div className="hidden print:block w-full absolute top-0 left-0 bg-white">
          <div className="w-80 text-black font-mono text-sm mx-auto p-4">
            <div className="text-center">
              <h2 className="text-2xl font-bold">KOPI MENTENG</h2>
              <p className="mt-1 text-xs font-bold border border-black inline-block px-2 py-1">COPY / REPRINT</p>
            </div>
            <div className="my-4 border-b-2 border-dashed border-gray-400"></div>
            <div className="mb-4 text-xs">
              <div className="flex justify-between"><span>Waktu:</span><span>{new Date(reprintOrder.created_at).toLocaleString('id-ID')}</span></div>
              <div className="flex justify-between mt-1"><span>Nota:</span><span>INV-KM-{reprintOrder.id.toString().padStart(4, '0')}</span></div>
            </div>
            <div className="mb-4 border-b-2 border-dashed border-gray-400"></div>
            <div className="mb-4">
              {reprintOrder.items.map((item) => (
                <div key={item.id} className="mb-2">
                  <div className="font-bold">{item.product?.name || 'Item'}</div>
                  <div className="flex justify-between">
                    <span>{item.quantity} x {Number(item.unit_price).toLocaleString('id-ID')}</span>
                    <span>{Number(item.subtotal).toLocaleString('id-ID')}</span>
                  </div>
                </div>
              ))}
            </div>
            <div className="my-4 border-b-2 border-dashed border-gray-400"></div>
            <div className="mt-2 flex justify-between text-lg font-bold"><span>TOTAL</span><span>Rp {Number(reprintOrder.total).toLocaleString('id-ID')}</span></div>
            <div className="mt-2 flex justify-between text-sm uppercase"><span>METODE</span><span className="font-bold">{reprintOrder.payment_method}</span></div>
            <div className="my-4 border-b-2 border-dashed border-gray-400"></div>
            <div className="text-center text-xs pb-4">Terima kasih!</div>
          </div>
        </div>
      )}

    </div>
  );
}
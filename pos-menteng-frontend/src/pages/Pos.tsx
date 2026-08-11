import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import axios from 'axios';

interface Product {
  id: string;
  name: string;
  price: number;
  stock: number;
}

interface CartItem extends Product {
  quantity: number;
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

  // State Tutup Shift
  const [showCloseShiftModal, setShowCloseShiftModal] = useState(false);
  const [endingCash, setEndingCash] = useState('');

  // State Struk & Pembayaran
  const [showPaymentModal, setShowPaymentModal] = useState(false);
  const [isProcessing, setIsProcessing] = useState(false);

  // State Kalkulator Tunai (BARU)
  const [showCashModal, setShowCashModal] = useState(false);
  const [cashTendered, setCashTendered] = useState('');

  useEffect(() => {
    const token = localStorage.getItem('token');
    const userData = localStorage.getItem('user');
    
    if (!token) {
      navigate('/');
      return;
    }

    try {
      if (userData && userData !== 'undefined') {
        setUser(JSON.parse(userData));
      }
    } catch (error) {
      console.error("Gagal membaca data user");
    }

    const fetchProducts = async () => {
      try {
        const response = await axios.get('http://localhost:8000/api/products', {
          headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
        });
        setProducts(response.data.data);
      } catch (error) {
        console.error("Gagal mengambil data produk", error);
      }
    };

    fetchProducts();
  }, [navigate]);

  const addToCart = (product: Product) => {
    setCart((prev) => {
      const existing = prev.find((item) => item.id === product.id);
      if (existing) {
        return prev.map((item) =>
          item.id === product.id ? { ...item, quantity: item.quantity + 1 } : item
        );
      }
      return [...prev, { ...product, quantity: 1 }];
    });
  };

  const removeFromCart = (id: string) => {
    setCart((prev) => prev.filter((item) => item.id !== id));
  };

  const subtotal = cart.reduce((sum, item) => sum + item.price * item.quantity, 0);
  const tax = subtotal * 0.11;
  const total = subtotal + tax;

  // Hitung Kembalian Real-time
  const changeAmount = Number(cashTendered) - total;

  const handleLogout = () => {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    navigate('/');
  };

  // --- FUNGSI BUKA & TUTUP SHIFT ---
  const handleOpenShift = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!startingCash) return;
    setShiftProcessing(true);
    const token = localStorage.getItem('token');

    try {
      const response = await axios.post('http://localhost:8000/api/shifts/open', {
        starting_cash: Number(startingCash)
      }, {
        headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
      });

      if (response.data.status === 'success') {
        setIsShiftOpen(true);
        setShowShiftModal(false);
      }
    } catch (error: any) {
      const errorMessage = error.response?.data?.message || 'Gagal membuka shift.';
      if (errorMessage.toLowerCase().includes('belum ditutup') || errorMessage.toLowerCase().includes('aktif')) {
        setIsShiftOpen(true);
        setShowShiftModal(false);
        alert('Shift sebelumnya terdeteksi masih terbuka. Sesi dilanjutkan otomatis.');
      } else {
        alert(errorMessage);
      }
    } finally {
      setShiftProcessing(false);
    }
  };

  const handleCloseShift = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!endingCash) return;
    setShiftProcessing(true);
    const token = localStorage.getItem('token');

    try {
      const response = await axios.post('http://localhost:8000/api/shifts/close', {
        actual_ending_cash: Number(endingCash)
      }, {
        headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
      });

      if (response.data.status === 'success') {
        setIsShiftOpen(false);
        setShowCloseShiftModal(false);
        setEndingCash('');
        alert('Shift berhasil ditutup! Data pendapatan telah terekam di sistem.');
      }
    } catch (error: any) {
      alert(error.response?.data?.message || 'Gagal menutup shift.');
    } finally {
      setShiftProcessing(false);
    }
  };

  const triggerPayment = () => {
    if (!isShiftOpen) {
      setShowShiftModal(true);
    } else {
      setShowPaymentModal(true);
    }
  };

  const handleMidtransCheckout = async () => {
    const token = localStorage.getItem('token');
    setIsProcessing(true);
    try {
      const payload = {
        payment_method: 'qris',
        items: cart.map(item => ({ product_id: item.id, quantity: item.quantity }))
      };
      const response = await axios.post('http://localhost:8000/api/orders/checkout', payload, {
        headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
      });
      if (response.data.status === 'success' && response.data.payment_url) {
        window.location.href = response.data.payment_url;
      }
    } catch (error: any) {
      alert(error.response?.data?.message || 'Gagal Checkout.');
    } finally {
      setIsProcessing(false);
    }
  };

  // --- FUNGSI PEMBAYARAN TUNAI FINAL ---
  const handleConfirmCashPayment = async (e: React.FormEvent) => {
    e.preventDefault();
    if (Number(cashTendered) < total) return;

    const token = localStorage.getItem('token');
    setIsProcessing(true);
    
    try {
      // 1. Siapkan payload dengan payment_method: 'cash'
      const payload = {
        payment_method: 'cash',
        items: cart.map(item => ({ product_id: item.id, quantity: item.quantity }))
      };

      // 2. Tembak ke endpoint terpusat yang sudah Anda buat
      await axios.post('http://localhost:8000/api/orders/checkout', payload, {
        headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
      });

      // 3. Cetak struk dan bersihkan layar jika sukses
      window.print();
      setTimeout(() => {
        setCart([]);
        setShowPaymentModal(false);
        setShowCashModal(false);
        setCashTendered('');
      }, 1000);

    } catch (error: any) {
      alert(error.response?.data?.message || 'Gagal menyimpan transaksi tunai ke database.');
    } finally {
      setIsProcessing(false);
    }
  };

  return (
    <div className="flex h-screen w-full bg-stone-100 font-sans text-stone-800">
      
      {/* ================= DAFTAR MENU ================= */}
      <div className="flex w-8/12 flex-col print:hidden">
        <header className="flex h-20 items-center justify-between bg-white px-8 shadow-sm">
          <div className="flex items-center gap-4">
            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-700 font-bold text-white">KM</div>
            <h1 className="text-xl font-bold tracking-tight">Kopi Menteng POS</h1>
          </div>
          <div className="flex items-center gap-4">
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
          <h2 className="mb-6 text-2xl font-bold text-stone-800">Daftar Menu Tersedia</h2>
          {products.length === 0 ? (
            <div className="text-stone-500">Memuat data produk...</div>
          ) : (
            <div className="grid grid-cols-3 gap-6">
              {products.map((product) => (
                <button
                  key={product.id}
                  onClick={() => addToCart(product)}
                  disabled={product.stock <= 0}
                  className={`group flex flex-col items-start justify-between rounded-2xl border border-stone-200 bg-white p-5 shadow-sm transition-all ${
                    product.stock > 0 ? 'hover:border-amber-400 hover:shadow-md active:scale-95' : 'opacity-50 cursor-not-allowed'
                  }`}
                >
                  <div className="mb-4 flex h-24 w-full items-center justify-center rounded-xl bg-stone-100 group-hover:bg-amber-50">
                    <span className="text-4xl">☕</span>
                  </div>
                  <h3 className="line-clamp-2 text-left font-bold text-stone-700">{product.name}</h3>
                  <p className="mt-2 text-lg font-bold text-amber-700">Rp {Number(product.price).toLocaleString('id-ID')}</p>
                  <p className={`mt-1 text-xs ${product.stock <= 5 ? 'text-red-500 font-bold' : 'text-stone-400'}`}>Sisa: {product.stock}</p>
                </button>
              ))}
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
            <span>Subtotal</span><span>Rp {subtotal.toLocaleString('id-ID')}</span>
          </div>
          <div className="mb-4 flex justify-between text-sm text-stone-500">
            <span>Pajak (11%)</span><span>Rp {tax.toLocaleString('id-ID')}</span>
          </div>
          <div className="mb-6 flex justify-between text-2xl font-black text-stone-800">
            <span>Total</span><span>Rp {total.toLocaleString('id-ID')}</span>
          </div>
          <button 
            onClick={triggerPayment}
            disabled={cart.length === 0}
            className={`w-full rounded-2xl py-4 text-lg font-bold text-white transition-all ${
              cart.length === 0 ? 'cursor-not-allowed bg-stone-300' : 'bg-amber-700 shadow-lg hover:bg-amber-800 active:scale-95'
            }`}
          >
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

      {/* ================= MODAL KALKULATOR KEMBALIAN TUNAI (BARU) ================= */}
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
              <input
                type="number"
                value={cashTendered}
                onChange={(e) => setCashTendered(e.target.value)}
                className="w-full rounded-xl border border-stone-300 bg-stone-50 p-4 text-2xl font-bold text-stone-800 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 outline-none"
                placeholder="0"
                autoFocus
              />

              {/* Tombol Cepat (Quick Cash) */}
              <div className="mt-4 grid grid-cols-3 gap-2">
                <button type="button" onClick={() => setCashTendered(total.toString())} className="rounded-lg border border-stone-200 bg-white py-2 text-sm font-bold text-stone-600 hover:bg-amber-50 hover:text-amber-700 hover:border-amber-300 transition">
                  Uang Pas
                </button>
                <button type="button" onClick={() => setCashTendered('50000')} className="rounded-lg border border-stone-200 bg-white py-2 text-sm font-bold text-stone-600 hover:bg-amber-50 hover:text-amber-700 hover:border-amber-300 transition">
                  50.000
                </button>
                <button type="button" onClick={() => setCashTendered('100000')} className="rounded-lg border border-stone-200 bg-white py-2 text-sm font-bold text-stone-600 hover:bg-amber-50 hover:text-amber-700 hover:border-amber-300 transition">
                  100.000
                </button>
              </div>

              {/* Tampilan Kembalian Dinamis */}
              <div className={`mt-6 rounded-xl p-4 text-center border ${changeAmount < 0 && cashTendered !== '' ? 'bg-red-50 border-red-200 text-red-600' : 'bg-stone-100 border-stone-200 text-stone-800'}`}>
                <p className="text-sm font-medium">Kembalian</p>
                <p className="text-3xl font-bold mt-1">
                  {cashTendered === '' ? 'Rp 0' : changeAmount < 0 ? 'UANG KURANG' : `Rp ${changeAmount.toLocaleString('id-ID')}`}
                </p>
              </div>

              <div className="mt-8 flex gap-4">
                <button type="button" onClick={() => { setShowCashModal(false); setCashTendered(''); }} className="w-1/3 rounded-xl bg-stone-100 py-4 font-bold text-stone-600 hover:bg-stone-200">
                  Batal
                </button>
                <button type="submit" disabled={changeAmount < 0 || cashTendered === ''} className="w-2/3 rounded-xl bg-stone-800 py-4 font-bold text-white shadow-lg disabled:opacity-50 hover:bg-stone-900 transition-all flex items-center justify-center gap-2">
                  <span>🖨️</span> Cetak Struk
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ================= MODAL PEMBAYARAN & STRUK ================= */}
      {showPaymentModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-stone-900/80 backdrop-blur-sm print:static print:bg-white print:block print:inset-auto">
          <div className="flex w-full max-w-4xl overflow-hidden rounded-2xl bg-stone-100 shadow-2xl print:shadow-none print:w-auto print:rounded-none">
            {/* KIRI: KERTAS STRUK THERMAL */}
            <div className="flex w-1/2 justify-center bg-stone-300 p-8 print:w-full print:p-0 print:bg-white">
              <div className="w-80 bg-white p-6 text-black shadow-md font-mono text-sm print:shadow-none">
                <div className="text-center">
                  <h2 className="text-2xl font-bold">KOPI MENTENG</h2>
                  <p className="mt-1 text-xs">Jl. Jenderal Sudirman</p>
                  <p className="text-xs">Kota Pangkalpinang</p>
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
                  <span>Subtotal</span><span>{subtotal.toLocaleString('id-ID')}</span>
                </div>
                <div className="flex justify-between mb-1">
                  <span>PPN (11%)</span><span>{tax.toLocaleString('id-ID')}</span>
                </div>
                <div className="mt-2 flex justify-between border-t border-gray-400 pt-2 text-lg font-bold">
                  <span>TOTAL</span><span>Rp {total.toLocaleString('id-ID')}</span>
                </div>
                
                {/* Info Kembalian di Struk jika Pembayaran Tunai Valid */}
                {cashTendered !== '' && changeAmount >= 0 && (
                  <>
                    <div className="mt-1 flex justify-between text-sm">
                      <span>Tunai</span><span>Rp {Number(cashTendered).toLocaleString('id-ID')}</span>
                    </div>
                    <div className="mt-1 flex justify-between text-sm">
                      <span>Kembali</span><span>Rp {changeAmount.toLocaleString('id-ID')}</span>
                    </div>
                  </>
                )}

                <div className="my-4 border-b-2 border-dashed border-gray-400"></div>
                <div className="text-center text-xs">
                  <p>Terima kasih atas kunjungan Anda!</p>
                  <p className="mt-1">WiFi: kopimenteng_guest</p>
                </div>
              </div>
            </div>

            {/* KANAN: PILIHAN METODE PEMBAYARAN */}
            <div className="w-1/2 bg-white p-8 print:hidden flex flex-col justify-between">
              <div>
                <h2 className="text-2xl font-bold text-stone-800 mb-6">Pilih Pembayaran</h2>
                <div className="space-y-4">
                  {/* TOMBOL TUNAI SEKARANG MEMBUKA KALKULATOR */}
                  <button onClick={() => setShowCashModal(true)} className="w-full rounded-xl border-2 border-stone-200 p-4 text-left hover:border-amber-500 hover:bg-amber-50 flex items-center justify-between transition group">
                    <div>
                      <h3 className="font-bold text-stone-800 text-lg group-hover:text-amber-700">Tunai (Cash)</h3>
                      <p className="text-stone-500 text-sm">Input nominal & cetak struk</p>
                    </div>
                    <span className="text-3xl">💵</span>
                  </button>
                  <button onClick={handleMidtransCheckout} disabled={isProcessing} className="w-full rounded-xl border-2 border-stone-200 p-4 text-left hover:border-blue-500 hover:bg-blue-50 flex items-center justify-between transition group">
                    <div>
                      <h3 className="font-bold text-stone-800 text-lg group-hover:text-blue-600">Digital (QRIS / VA)</h3>
                      <p className="text-stone-500 text-sm">Lanjutkan ke Midtrans Snap</p>
                    </div>
                    <span className="text-3xl">📱</span>
                  </button>
                </div>
              </div>
              <div className="mt-8 flex gap-4">
                <button onClick={() => setShowPaymentModal(false)} className="w-1/3 rounded-xl bg-stone-100 py-3 font-bold text-stone-600 hover:bg-stone-200">
                  Batal
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

    </div>
  );
}
import { useState } from 'react';
import axios from 'axios';
import { useNavigate } from 'react-router-dom';

import logokopimenteng from '../assets/logo.png';

export default function Login() {
  const [pin, setPin] = useState('');
  const [error, setError] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  
  const navigate = useNavigate();

  const handleNumberClick = (num: string) => {
    if (pin.length < 6) {
      setPin((prev) => prev + num);
      setError(''); // Hapus pesan error saat mengetik ulang
    }
  };

  const handleDelete = () => {
    setPin((prev) => prev.slice(0, -1));
    setError('');
  };

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    if (pin.length !== 6) return;

    setIsLoading(true);
    setError('');

    try {
      // Menembak API Laravel (Pastikan port Laravel Anda 8000)
      const response = await axios.post('http://localhost:8000/api/login-pin', {
        pin: pin
      });

      if (response.data.status === 'success') {
        // Simpan token dan data user ke penyimpanan browser
        localStorage.setItem('token', response.data.data.token);
        localStorage.setItem('user', JSON.stringify(response.data.user));

        // Arahkan ke halaman POS
        navigate('/pos');
      }
    } catch (err: any) {
      // Tangani pesan error dari Laravel
      setError(err.response?.data?.message || 'Terjadi kesalahan koneksi ke server');
      setPin(''); // Kosongkan PIN agar kasir bisa mengetik ulang
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="flex h-screen w-full bg-[#FDFBF7] font-sans text-stone-800 selection:bg-amber-200">
      
      {/* BAGIAN KIRI: Branding Kopi Menteng */}
      <div className="relative hidden w-1/2 flex-col justify-between overflow-hidden bg-stone-900 p-12 text-stone-50 md:flex">
        <div className="absolute -left-20 -top-20 h-96 w-96 rounded-full bg-stone-800/50 blur-3xl"></div>
        <div className="absolute -bottom-32 -right-32 h-[30rem] w-[30rem] rounded-full bg-amber-900/20 blur-3xl"></div>

        <div className="relative z-10">
          <div className="mb-6 inline-flex h-24 w-24 items-center justify-center rounded-2xl border border-amber-700/30 bg-amber-700/20 p-4 text-amber-500 backdrop-blur-md">
            <img src={logokopimenteng} alt= "logo kopi menteng" className="h-full w-full object-contain"></img>
          </div>
          <h1 className="text-5xl font-black tracking-tight text-white">Kopi Menteng.</h1>
          <p className="mt-5 max-w-md text-lg font-light leading-relaxed text-stone-300">
            Sistem Point of Sales eksklusif yang dirancang untuk mendukung operasional cepat, akurat, dan pelayanan prima.
          </p>
        </div>
        
        <div className="relative z-10 text-sm font-medium tracking-wide text-stone-400">
          <p>Shift Laci: <span className="text-amber-500">TUTUP</span> &nbsp;|&nbsp; Versi 1.0.0</p>
        </div>
      </div>

      {/* BAGIAN KANAN: PIN Pad */}
      <div className="flex w-full flex-col items-center justify-center bg-[#FDFBF7] p-8 md:w-1/2">
        <div className="w-full max-w-sm">
          <div className="mb-8 text-center">
            <h2 className="text-3xl font-bold text-stone-800">Buka Sesi</h2>
            <p className="mt-2 text-stone-500">Otorisasi PIN Kasir Anda</p>
          </div>

          {/* Menampilkan Error Jika PIN Salah */}
          {error && (
            <div className="mb-6 rounded-xl border border-red-200 bg-red-50 p-3 text-center text-sm font-medium text-red-600">
              {error}
            </div>
          )}

          <div className="mb-10 flex justify-center gap-4">
            {[...Array(6)].map((_, index) => (
              <div
                key={index}
                className={`h-4 w-4 rounded-full transition-all duration-300 ${
                  index < pin.length 
                    ? 'scale-125 bg-amber-700 shadow-sm shadow-amber-700/50' 
                    : 'bg-stone-200'
                }`}
              ></div>
            ))}
          </div>

          <div className="grid grid-cols-3 gap-3">
            {[1, 2, 3, 4, 5, 6, 7, 8, 9].map((num) => (
              <button
                key={num}
                onClick={() => handleNumberClick(num.toString())}
                className="flex h-20 items-center justify-center rounded-2xl border border-stone-100 bg-white text-3xl font-semibold text-stone-700 shadow-sm transition-all hover:border-amber-200 hover:bg-amber-50 hover:text-amber-800 active:scale-95"
              >
                {num}
              </button>
            ))}
            
            <button
              onClick={() => setPin('')}
              className="flex h-20 items-center justify-center rounded-2xl bg-stone-100 text-sm font-bold uppercase tracking-wider text-stone-500 transition hover:bg-stone-200 active:scale-95"
            >
              Reset
            </button>
            
            <button
              onClick={() => handleNumberClick('0')}
              className="flex h-20 items-center justify-center rounded-2xl border border-stone-100 bg-white text-3xl font-semibold text-stone-700 shadow-sm transition-all hover:border-amber-200 hover:bg-amber-50 hover:text-amber-800 active:scale-95"
            >
              0
            </button>
            
            <button
              onClick={handleDelete}
              className="flex h-20 items-center justify-center rounded-2xl bg-stone-100 text-stone-500 transition hover:bg-stone-200 active:scale-95"
            >
              <svg className="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M12 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M3 12l6.414 6.414a2 2 0 001.414.586H19a2 2 0 002-2V7a2 2 0 00-2-2h-8.172a2 2 0 00-1.414.586L3 12z" />
              </svg>
            </button>
          </div>

          <button
            onClick={handleLogin}
            disabled={pin.length !== 6 || isLoading}
            className={`mt-10 flex h-16 w-full items-center justify-center rounded-2xl text-lg font-bold tracking-wide text-white transition-all duration-300 ${
              pin.length === 6 && !isLoading
                ? 'bg-amber-700 shadow-xl shadow-amber-700/30 hover:bg-amber-800 active:scale-95' 
                : 'cursor-not-allowed bg-stone-300 text-stone-100'
            }`}
          >
            {isLoading ? 'Memproses...' : 'Masuk ke Sistem'}
          </button>

        </div>
      </div>
    </div>
  );
}
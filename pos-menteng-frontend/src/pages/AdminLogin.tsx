import { useState } from 'react';
import axios from 'axios';
import { useNavigate } from 'react-router-dom';

import logokopimenteng from '../assets/logo.png';

export default function AdminLogin() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [isLoading, setIsLoading] = useState(false);

  const navigate = useNavigate();

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsLoading(true);
    setError('');

    try {
      const response = await axios.post('http://localhost:8000/api/login', {
        email,
        password,
      });

      if (response.data.status !== 'success') {
        setError(response.data.message || 'Login gagal.');
        return;
      }

      const data = response.data.data;
      const context = data.context ?? {};
      const permissions = Array.isArray(data.permissions) ? data.permissions : [];

      localStorage.setItem('token', data.token);
      localStorage.setItem('user', JSON.stringify(data.user));
      localStorage.setItem('permissions', JSON.stringify(permissions));
      localStorage.setItem('erp_role', String(context.role ?? ''));
      localStorage.setItem('erp_context', JSON.stringify({
        tenant_id: context.tenant_id ?? null,
        company_id: context.company_id ?? null,
        branch_id: context.branch_id ?? null,
        location_id: context.location_id ?? null,
        location_type: context.location_type ?? null,
      }));
      localStorage.removeItem('foundation_loaded');

      navigate('/dashboard');
    } catch (err: any) {
      setError(err.response?.data?.message || 'Terjadi kesalahan koneksi ke server');
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="flex h-screen w-full bg-[#FDFBF7] font-sans text-stone-800 selection:bg-amber-200">
      <div className="relative hidden w-1/2 flex-col justify-between overflow-hidden bg-stone-900 p-12 text-stone-50 md:flex">
        <div className="absolute -left-20 -top-20 h-96 w-96 rounded-full bg-stone-800/50 blur-3xl" />
        <div className="absolute -bottom-32 -right-32 h-[30rem] w-[30rem] rounded-full bg-amber-900/20 blur-3xl" />

        <div className="relative z-10">
          <div className="mb-6 inline-flex h-24 w-24 items-center justify-center rounded-2xl border border-amber-700/30 bg-amber-700/20 p-4 text-amber-500 backdrop-blur-md">
            <img src={logokopimenteng} alt="logo kopi menteng" className="h-full w-full object-contain" />
          </div>
          <h1 className="text-5xl font-black tracking-tight text-white">Kopi Menteng.</h1>
          <p className="mt-5 max-w-md text-lg font-light leading-relaxed text-stone-300">
            Area login admin untuk mengelola produk, bahan baku, stok, dan riwayat transaksi.
          </p>
        </div>

        <div className="relative z-10 text-sm font-medium tracking-wide text-stone-400">
          <p>Admin Panel</p>
        </div>
      </div>

      <div className="flex w-full flex-col items-center justify-center bg-[#FDFBF7] p-8 md:w-1/2">
        <div className="w-full max-w-md">
          <div className="mb-8 text-center">
            <h2 className="text-3xl font-bold text-stone-800">Login Admin</h2>
            <p className="mt-2 text-stone-500">Masuk ke backoffice Kopi Menteng</p>
          </div>

          {error && (
            <div className="mb-6 rounded-xl border border-red-200 bg-red-50 p-3 text-center text-sm font-medium text-red-600">
              {error}
            </div>
          )}

          <form onSubmit={handleLogin} className="space-y-5">
            <div>
              <label className="mb-2 block text-sm font-bold text-stone-600">Email</label>
              <input
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
                className="w-full rounded-2xl border border-stone-200 bg-white px-4 py-3 text-stone-800 outline-none transition focus:border-amber-500 focus:ring-2 focus:ring-amber-200"
                placeholder="admin@kopimenteng.com"
              />
            </div>

            <div>
              <label className="mb-2 block text-sm font-bold text-stone-600">Password</label>
              <input
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
                className="w-full rounded-2xl border border-stone-200 bg-white px-4 py-3 text-stone-800 outline-none transition focus:border-amber-500 focus:ring-2 focus:ring-amber-200"
                placeholder="Masukkan password"
              />
            </div>

            <button
              type="submit"
              disabled={isLoading || !email || !password}
              className={`flex h-14 w-full items-center justify-center rounded-2xl text-lg font-bold tracking-wide text-white transition-all duration-300 ${
                isLoading || !email || !password
                  ? 'cursor-not-allowed bg-stone-300 text-stone-100'
                  : 'bg-amber-700 shadow-xl shadow-amber-700/30 hover:bg-amber-800 active:scale-95'
              }`}
            >
              {isLoading ? 'Memproses...' : 'Masuk ke Admin'}
            </button>
          </form>
        </div>
      </div>
    </div>
  );
}

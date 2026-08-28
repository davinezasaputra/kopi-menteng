import { useNavigate } from 'react-router-dom';
import axios from 'axios';
import toast from 'react-hot-toast';

type AdminSidebarProps = {
  activePage?: 'dashboard' | 'pos' | 'inventory' | 'raw-materials' | 'history';
};

const menuItems = [
    { key: 'dashboard', label: 'Dashboard', icon: '📊', path: '/dashboard'},
    { key: 'pos', label: 'Kasir (POS)', icon: '🛒', path: '/pos' },
    { key: 'inventory', label: 'Data Produk', icon: '📦', path: '/inventory' },
    { key: 'raw-materials', label: 'Bahan Baku', icon: '🫙', path: '/raw-materials' },
    { key: 'history', label: 'Riwayat & Laporan', icon: '🧾', path: '/history' },
] as const;

export default function AdminSidebar({ activePage = 'dashboard' }: AdminSidebarProps) {
  const navigate = useNavigate();
  const user = JSON.parse(localStorage.getItem('user') || '{}');

  const handleLogout = async() => {
    const token = localStorage.getItem('token');
    const toastId = toast.loading('Logoutting...');

    if (token) {
        try{
            await axios.post('http://localhost:8000/api/logout', {}, {
                headers:{ 'Authorization' : `Bearer ${token}` }
            });
        } catch (error) {
            console.error ('Gagal menghapus token dari server', error);
        }
    }

    localStorage.removeItem ('token');
    localStorage.removeItem ('user');
    toast.success ('Berhasil Logout!', {id: toastId});
    navigate('/admin-login');
  };

  return (
    <div className="w-64 bg-stone-900 text-stone-300 flex flex-col">
      <div className="p-6 border-b border-stone-800 flex items-center gap-3">
        <div className="flex h-8 w-8 items-center justify-center rounded bg-amber-700 font-bold text-white text-xs">KM</div>
        <div className="flex flex-col">
          <span className="font-bold text-white tracking-wide">Backoffice</span>
          <p className="text-xs text-stone-300 tracking-wide">{user.name || 'Admin'}</p>
        </div>
      </div>
      <nav className="flex-1 p-4 space-y-2">
        {menuItems.map((item) => {
          const isActive = activePage === item.key;

          return (
            <button
              key={item.key}
              onClick={() => navigate(item.path)}
              className={`w-full flex items-center gap-3 px-4 py-3 rounded-xl transition text-left ${
                isActive
                  ? 'bg-amber-700/20 text-amber-500 font-medium'
                  : 'hover:bg-stone-800 hover:text-white'
              }`}
            >
              <span>{item.icon}</span>
              {item.label}
            </button>
          );
        })}
      </nav>

      <div className="border-t border-stone-800 p-4">
        <button
          onClick={handleLogout}
          className="w-full flex items-center justify-center gap-2 rounded-xl bg-red-500/10 px-4 py-3 text-sm font-bold text-red-400 transition hover:bg-red-500/20"
        >
          <span>⎋</span>
          Logout
        </button>
      </div>
    </div>
  );
}

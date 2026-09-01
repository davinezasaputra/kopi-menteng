import { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import axios from 'axios';
import toast from 'react-hot-toast';
import { can, canAny } from '../core/auth/permissions';

export type AdminSidebarProps = {
  activePage?: 'users' | 'dashboard' | 'pos' | 'inventory' | 'raw-materials' | 'history' | 'accounting' | 'customers' | 'employees' | 'hrm' | 'foundation' | 'operations' | 'inventory-operations' | 'purchasing-orders';
};

type MenuItem = {
  key: NonNullable<AdminSidebarProps['activePage']>;
  label: string;
  icon: string;
  path: string;
  permission?: string;
  anyOf?: string[];
};

type MenuGroup = {
  key: string;
  label: string;
  icon: string;
  items: MenuItem[];
};

const itemAllowed = (item: MenuItem): boolean => {
  if (item.permission) return can(item.permission);
  if (item.anyOf) return canAny(item.anyOf);
  return true;
};

const moduleGroups: MenuGroup[] = [
  {
    key: 'erp', label: 'ERP', icon: '🏢', items: [
      { key: 'operations', label: 'Overview', icon: '📊', path: '/erp/operations', anyOf: ['inventory.stock.view', 'purchasing.supplier.view', 'purchasing.order.view', 'sales.order.view', 'accounting.report.view'] },
      { key: 'inventory', label: 'Inventory', icon: '📦', path: '/inventory', permission: 'inventory.stock.view' },
      { key: 'inventory-operations', label: 'Kontrol Persediaan', icon: '📈', path: '/inventory/operations', anyOf: ['inventory.stock.view', 'inventory.stock.adjust'] },
      { key: 'raw-materials', label: 'Bahan Baku', icon: '🫙', path: '/raw-materials', permission: 'inventory.stock.view' },
      { key: 'purchasing-orders', label: 'Purchase Order', icon: '📝', path: '/purchasing/orders', anyOf: ['purchasing.order.view', 'purchasing.order.create'] },
      { key: 'accounting', label: 'Accounting / Finance', icon: '💲', path: '/accounting', anyOf: ['accounting.journal.view', 'accounting.erp_account.view', 'accounting.report.view'] },
      { key: 'history', label: 'Riwayat & Laporan', icon: '🧾', path: '/history', anyOf: ['sales.reporting.view', 'accounting.report.view', 'inventory.stock.view'] },
    ],
  },
  {
    key: 'pos', label: 'POS', icon: '🛒', items: [
      { key: 'pos', label: 'Kasir', icon: '🛒', path: '/pos', permission: 'pos.sale.view' },
    ],
  },
  {
    key: 'crm', label: 'CRM', icon: '🤝', items: [
      { key: 'customers', label: 'Pelanggan', icon: '👤', path: '/customers', permission: 'sales.order.view' },
    ],
  },
  {
    key: 'hrm', label: 'HRM', icon: '🧑‍💼', items: [
      { key: 'employees', label: 'Karyawan', icon: '🧑‍💻', path: '/employees', permission: 'hr.employee.view' },
      { key: 'hrm', label: 'HRD & Penggajian', icon: '💼', path: '/hrm', permission: 'hr.employee.view' },
    ],
  },
  {
    key: 'administration', label: 'Administration', icon: '⚙️', items: [
      { key: 'users', label: 'Users', icon: '👥', path: '/users', permission: 'users.user.view' },
      { key: 'foundation', label: 'Organizations & Access', icon: '🔐', path: '/admin/foundation', permission: 'rbac.role.view' },
    ],
  },
];

export default function AdminSidebar({ activePage = 'dashboard' }: AdminSidebarProps) {
  const navigate = useNavigate();
  const user = useMemo(() => {
    try { return JSON.parse(localStorage.getItem('user') || '{}') as { name?: string }; } catch { return {}; }
  }, []);
  const context = useMemo(() => {
    try { return JSON.parse(localStorage.getItem('erp_context') || '{}') as { tenant_id?: number; company_id?: number; branch_id?: number }; } catch { return {}; }
  }, []);

  const allowedGroups = useMemo(
    () => moduleGroups.map(group => ({ ...group, items: group.items.filter(itemAllowed) })).filter(group => group.items.length > 0),
    [],
  );

  const activeGroup = useMemo(
    () => allowedGroups.find(group => group.items.some(item => item.key === activePage))?.key ?? null,
    [activePage, allowedGroups],
  );
  const [openGroups, setOpenGroups] = useState<string[]>(activeGroup ? [activeGroup] : []);

  useEffect(() => {
    if (activeGroup) setOpenGroups(current => current.includes(activeGroup) ? current : [...current, activeGroup]);
  }, [activeGroup]);

  const toggleGroup = (key: string) => {
    setOpenGroups(current => current.includes(key) ? current.filter(item => item !== key) : [...current, key]);
  };

  const handleLogout = async () => {
    const toastId = toast.loading('Logoutting...');
    try {
      await axios.post('/v1/auth/logout');
      toast.success('Berhasil Logout!', { id: toastId });
    } catch (error) {
      console.error('Gagal logout dari server', error);
      toast.success('Sesi lokal ditutup.', { id: toastId });
    } finally {
      localStorage.removeItem('token');
      localStorage.removeItem('user');
      localStorage.removeItem('permissions');
      localStorage.removeItem('erp_context');
      localStorage.removeItem('foundation_loaded');
      navigate('/');
    }
  };

  return (
    <aside className="w-64 bg-stone-900 text-stone-300 flex flex-col">
      <div className="p-6 border-b border-stone-800 flex items-center gap-3">
        <div className="flex h-8 w-8 items-center justify-center rounded bg-amber-700 font-bold text-white text-xs">KM</div>
        <div className="flex flex-col min-w-0">
          <span className="font-bold text-white tracking-wide">Backoffice</span>
          <p className="text-xs text-stone-300 tracking-wide truncate">{user.name || 'Admin'}</p>
        </div>
      </div>

      <div className="px-4 pt-4">
        <div className="rounded-xl border border-stone-800 bg-stone-950/40 px-3 py-2 text-[11px] text-stone-400">
          <div className="font-semibold text-stone-300">Organization Context</div>
          <div className="mt-1 truncate">T: {context.tenant_id ?? '-'} · C: {context.company_id ?? '-'} · B: {context.branch_id ?? '-'}</div>
        </div>
      </div>

      <nav className="flex-1 p-4 space-y-2 overflow-y-auto">
        <button
          onClick={() => navigate('/dashboard')}
          className={`w-full flex items-center gap-3 px-4 py-3 rounded-xl transition text-left ${activePage === 'dashboard' ? 'bg-amber-700/20 text-amber-500 font-medium' : 'hover:bg-stone-800 hover:text-white'}`}
        >
          <span>📊</span>Dashboard
        </button>

        {allowedGroups.map(group => {
          const isOpen = openGroups.includes(group.key);
          const isActive = activeGroup === group.key;
          return (
            <div key={group.key}>
              <button
                onClick={() => toggleGroup(group.key)}
                className={`w-full flex items-center justify-between gap-3 px-4 py-3 rounded-xl transition text-left ${isActive ? 'bg-stone-800 text-white' : 'hover:bg-stone-800 hover:text-white'}`}
              >
                <span className="flex items-center gap-3"><span>{group.icon}</span><span className="font-semibold">{group.label}</span></span>
                <span className="text-xs text-stone-500">{isOpen ? '⌃' : '⌄'}</span>
              </button>

              {isOpen && (
                <div className="mt-1 ml-3 space-y-1 border-l border-stone-800 pl-2">
                  {group.items.map(item => (
                    <button
                      key={item.key}
                      onClick={() => navigate(item.path)}
                      className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-lg transition text-left text-sm ${activePage === item.key ? 'bg-amber-700/20 text-amber-500 font-medium' : 'hover:bg-stone-800 hover:text-white'}`}
                    >
                      <span>{item.icon}</span>{item.label}
                    </button>
                  ))}
                </div>
              )}
            </div>
          );
        })}
      </nav>

      <div className="border-t border-stone-800 p-4">
        <button onClick={handleLogout} className="w-full flex items-center justify-center gap-2 rounded-xl bg-red-500/10 px-4 py-3 text-sm font-bold text-red-400 transition hover:bg-red-500/20">
          <span>⎋</span>Logout
        </button>
      </div>
    </aside>
  );
}

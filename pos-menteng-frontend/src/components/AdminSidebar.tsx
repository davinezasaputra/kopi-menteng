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

type SubGroup = {
  key: string;
  label: string;
  items: MenuItem[];
};

type ModuleGroup = {
  key: string;
  label: string;
  icon: string;
  items?: MenuItem[];
  subgroups?: SubGroup[];
};

const itemAllowed = (item: MenuItem): boolean => {
  if (item.permission) return can(item.permission);
  if (item.anyOf) return canAny(item.anyOf);
  return true;
};

const moduleGroups: ModuleGroup[] = [
  {
    key: 'erp',
    label: 'ERP',
    icon: '🏢',
    subgroups: [
      {
        key: 'erp-overview',
        label: 'Overview',
        items: [
          { key: 'operations', label: 'Operations Center', icon: '📊', path: '/erp/operations', anyOf: ['inventory.stock.view', 'purchasing.supplier.view', 'purchasing.order.view', 'sales.order.view', 'accounting.report.view'] },
        ],
      },
      {
        key: 'erp-inventory',
        label: 'Inventory',
        items: [
          { key: 'inventory', label: 'Produk', icon: '📦', path: '/inventory', permission: 'inventory.stock.view' },
          { key: 'inventory-operations', label: 'Kontrol Persediaan', icon: '📈', path: '/inventory/operations', anyOf: ['inventory.stock.view', 'inventory.stock.adjust'] },
          { key: 'raw-materials', label: 'Bahan Baku', icon: '🫙', path: '/raw-materials', permission: 'inventory.stock.view' },
        ],
      },
      {
        key: 'erp-purchasing',
        label: 'Purchasing',
        items: [
          { key: 'purchasing-orders', label: 'Purchase Order', icon: '📝', path: '/purchasing/orders', anyOf: ['purchasing.order.view', 'purchasing.order.create'] },
        ],
      },
      {
        key: 'erp-finance',
        label: 'Finance & Accounting',
        items: [
          { key: 'accounting', label: 'Accounting / Finance', icon: '💲', path: '/accounting', anyOf: ['accounting.journal.view', 'accounting.erp_account.view', 'accounting.report.view'] },
          { key: 'history', label: 'Riwayat & Laporan', icon: '🧾', path: '/history', anyOf: ['sales.reporting.view', 'accounting.report.view', 'inventory.stock.view'] },
        ],
      },
    ],
  },
  {
    key: 'pos',
    label: 'POS',
    icon: '🛒',
    items: [
      { key: 'pos', label: 'Kasir', icon: '🛒', path: '/pos', permission: 'pos.sale.view' },
    ],
  },
  {
    key: 'crm',
    label: 'CRM',
    icon: '🤝',
    items: [
      { key: 'customers', label: 'Pelanggan', icon: '👤', path: '/customers', permission: 'sales.order.view' },
    ],
  },
  {
    key: 'hrm',
    label: 'HRM',
    icon: '🧑‍💼',
    items: [
      { key: 'employees', label: 'Karyawan', icon: '🧑‍💻', path: '/employees', permission: 'hr.employee.view' },
      { key: 'hrm', label: 'HRD & Penggajian', icon: '💼', path: '/hrm', permission: 'hr.employee.view' },
    ],
  },
  {
    key: 'administration',
    label: 'Administration',
    icon: '⚙️',
    items: [
      { key: 'users', label: 'Users', icon: '👥', path: '/users', permission: 'users.user.view' },
      { key: 'foundation', label: 'Organizations & Access', icon: '🔐', path: '/admin/foundation', permission: 'rbac.role.view' },
    ],
  },
];

function filterModule(module: ModuleGroup): ModuleGroup | null {
  const items = module.items?.filter(itemAllowed) ?? [];
  const subgroups = module.subgroups
    ?.map(group => ({ ...group, items: group.items.filter(itemAllowed) }))
    .filter(group => group.items.length > 0) ?? [];

  if (!items.length && !subgroups.length) return null;
  return { ...module, items, subgroups };
}

export default function AdminSidebar({ activePage = 'dashboard' }: AdminSidebarProps) {
  const navigate = useNavigate();
  const user = useMemo(() => {
    try { return JSON.parse(localStorage.getItem('user') || '{}') as { name?: string }; } catch { return {}; }
  }, []);
  const context = useMemo(() => {
    try { return JSON.parse(localStorage.getItem('erp_context') || '{}') as { tenant_id?: number; company_id?: number; branch_id?: number }; } catch { return {}; }
  }, []);

  const allowedModules = useMemo(
    () => moduleGroups.map(filterModule).filter((module): module is ModuleGroup => module !== null),
    [],
  );

  const activeModule = useMemo(
    () => allowedModules.find(module =>
      (module.items ?? []).some(item => item.key === activePage) ||
      (module.subgroups ?? []).some(group => group.items.some(item => item.key === activePage)),
    )?.key ?? null,
    [activePage, allowedModules],
  );

  const activeSubGroup = useMemo(
    () => allowedModules.flatMap(module => module.subgroups ?? []).find(group => group.items.some(item => item.key === activePage))?.key ?? null,
    [activePage, allowedModules],
  );

  const [openModules, setOpenModules] = useState<string[]>(activeModule ? [activeModule] : []);
  const [openSubGroups, setOpenSubGroups] = useState<string[]>(activeSubGroup ? [activeSubGroup] : []);

  useEffect(() => {
    if (activeModule) setOpenModules(current => current.includes(activeModule) ? current : [...current, activeModule]);
    if (activeSubGroup) setOpenSubGroups(current => current.includes(activeSubGroup) ? current : [...current, activeSubGroup]);
  }, [activeModule, activeSubGroup]);

  const toggleModule = (key: string) => {
    setOpenModules(current => current.includes(key) ? current.filter(item => item !== key) : [...current, key]);
  };

  const toggleSubGroup = (key: string) => {
    setOpenSubGroups(current => current.includes(key) ? current.filter(item => item !== key) : [...current, key]);
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

  const renderItem = (item: MenuItem, nested = false) => (
    <button
      key={item.key}
      onClick={() => navigate(item.path)}
      className={`w-full flex items-center gap-3 rounded-lg transition text-left text-sm ${nested ? 'px-3 py-2' : 'px-3 py-2.5'} ${activePage === item.key ? 'bg-amber-700/20 text-amber-500 font-medium' : 'hover:bg-stone-800 hover:text-white'}`}
    >
      <span>{item.icon}</span>{item.label}
    </button>
  );

  return (
    <aside className="w-72 bg-stone-900 text-stone-300 flex flex-col">
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

        {allowedModules.map(module => {
          const isOpen = openModules.includes(module.key);
          const isActive = activeModule === module.key;
          return (
            <div key={module.key}>
              <button
                onClick={() => toggleModule(module.key)}
                className={`w-full flex items-center justify-between gap-3 px-4 py-3 rounded-xl transition text-left ${isActive ? 'bg-stone-800 text-white' : 'hover:bg-stone-800 hover:text-white'}`}
              >
                <span className="flex items-center gap-3"><span>{module.icon}</span><span className="font-semibold">{module.label}</span></span>
                <span className="text-xs text-stone-500">{isOpen ? '⌃' : '⌄'}</span>
              </button>

              {isOpen && (
                <div className="mt-1 ml-3 space-y-1 border-l border-stone-800 pl-2">
                  {(module.items ?? []).map(item => renderItem(item))}

                  {(module.subgroups ?? []).map(group => {
                    const subOpen = openSubGroups.includes(group.key);
                    const subActive = group.items.some(item => item.key === activePage);
                    return (
                      <div key={group.key}>
                        <button
                          onClick={() => toggleSubGroup(group.key)}
                          className={`w-full flex items-center justify-between rounded-lg px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide ${subActive ? 'text-amber-500' : 'text-stone-500 hover:text-stone-300'}`}
                        >
                          <span>{group.label}</span><span>{subOpen ? '−' : '+'}</span>
                        </button>
                        {subOpen && <div className="space-y-1 pl-2">{group.items.map(item => renderItem(item, true))}</div>}
                      </div>
                    );
                  })}
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

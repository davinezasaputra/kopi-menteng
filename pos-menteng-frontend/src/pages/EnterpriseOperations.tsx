import { useEffect, useMemo, useState } from 'react';
import AdminSidebar from '../components/AdminSidebar';
import api from '../core/api/client';
import { extractRows } from '../core/api/normalize';

type ModuleKey = 'inventory' | 'purchasing' | 'sales' | 'finance';
type Row = Record<string, unknown>;

const modules: Array<{ key: ModuleKey; label: string; permission: string; endpoint: string }> = [
  { key: 'inventory', label: 'Inventory', permission: 'inventory.stock.view', endpoint: '/inventory/balances' },
  { key: 'purchasing', label: 'Purchasing', permission: 'purchasing.supplier.view', endpoint: '/purchasing/suppliers' },
  { key: 'sales', label: 'Sales', permission: 'sales.order.view', endpoint: '/sales/orders' },
  { key: 'finance', label: 'Finance', permission: 'accounting.report.view', endpoint: '/finance/reports/trial-balance' },
];

function summarize(row: Row): string {
  const preferred = ['code', 'name', 'sku', 'document_number', 'status', 'period'];
  const parts = preferred
    .filter((key) => row[key] !== undefined && row[key] !== null && row[key] !== '')
    .map((key) => `${key}: ${String(row[key])}`);
  return parts.length ? parts.join(' · ') : Object.entries(row).slice(0, 4).map(([k, v]) => `${k}: ${String(v)}`).join(' · ');
}

export default function EnterpriseOperations() {
  const [active, setActive] = useState<ModuleKey>('inventory');
  const [rows, setRows] = useState<Row[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const current = useMemo(() => modules.find((item) => item.key === active)!, [active]);

  const load = async () => {
    setLoading(true);
    setError('');
    try {
      const response = await api.get(current.endpoint);
      setRows(extractRows<Row>(response.data));
    } catch (err) {
      const message = err && typeof err === 'object' && 'response' in err
        ? String((err as { response?: { data?: { message?: string } } }).response?.data?.message || '')
        : '';
      setError(message || 'Data tidak dapat dimuat. Pastikan permission dan organization context tersedia.');
      setRows([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { void load(); }, [current.endpoint]);

  return (
    <div className="flex h-screen w-full bg-stone-50 font-sans text-stone-800">
      <AdminSidebar activePage="operations" />
      <div className="flex-1 flex flex-col overflow-hidden">
        <header className="h-20 bg-white border-b border-stone-200 px-8 flex items-center justify-between shadow-sm">
          <div>
            <h1 className="text-xl font-bold text-stone-800">ERP Operations</h1>
            <p className="text-xs text-stone-500 mt-1">Endpoint bisnis yang sudah tersedia kini punya workspace UI bersama.</p>
          </div>
          <button onClick={() => void load()} className="rounded-xl bg-stone-800 px-4 py-2 text-sm font-bold text-white hover:bg-stone-900">
            Refresh
          </button>
        </header>

        <main className="flex-1 overflow-y-auto p-8">
          <div className="mb-6 flex flex-wrap gap-2">
            {modules.map((item) => (
              <button
                key={item.key}
                onClick={() => setActive(item.key)}
                className={`rounded-xl px-5 py-2.5 text-sm font-bold transition ${active === item.key ? 'bg-amber-700 text-white shadow-md' : 'bg-white text-stone-500 border border-stone-200 hover:bg-stone-100'}`}
              >
                {item.label}
              </button>
            ))}
          </div>

          <section className="bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden">
            <div className="p-5 border-b border-stone-200 flex items-center justify-between">
              <div>
                <h2 className="font-bold text-stone-800">{current.label}</h2>
                <p className="text-xs text-stone-500 mt-1">GET {current.endpoint}</p>
              </div>
              <span className="rounded-full bg-green-50 px-3 py-1 text-xs font-bold text-green-700">Live API</span>
            </div>

            {loading && <div className="p-8 text-center text-stone-500">Memuat data...</div>}
            {!loading && error && <div className="m-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{error}</div>}
            {!loading && !error && rows.length === 0 && <div className="p-8 text-center text-stone-500">Belum ada data pada scope organisasi aktif.</div>}
            {!loading && !error && rows.length > 0 && (
              <div className="divide-y divide-stone-100">
                {rows.slice(0, 50).map((row, index) => (
                  <div key={String(row.id ?? row.code ?? index)} className="p-5 hover:bg-stone-50 transition">
                    <div className="font-medium text-stone-800">{summarize(row)}</div>
                    <div className="mt-2 grid grid-cols-2 md:grid-cols-4 gap-2 text-xs text-stone-500">
                      {Object.entries(row).slice(0, 8).map(([key, value]) => (
                        <div key={key} className="rounded-lg bg-stone-50 px-3 py-2">
                          <div className="font-semibold text-stone-400 uppercase">{key}</div>
                          <div className="mt-1 truncate text-stone-700">{String(value ?? '-')}</div>
                        </div>
                      ))}
                    </div>
                  </div>
                ))}
              </div>
            )}
          </section>
        </main>
      </div>
    </div>
  );
}

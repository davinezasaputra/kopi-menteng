import { useEffect, useMemo, useState } from 'react';
import toast from 'react-hot-toast';
import AdminSidebar from '../components/AdminSidebar';
import PermissionGate from '../components/PermissionGate';
import api from '../core/api/client';
import { extractRows } from '../core/api/normalize';

type Row = Record<string, unknown>;
type Tab = 'balance' | 'movements' | 'actions';

function text(value: unknown): string {
  return value === null || value === undefined || value === '' ? '-' : String(value);
}

function nestedName(row: Row, key: string): string {
  const value = row[key];
  if (value && typeof value === 'object' && !Array.isArray(value)) {
    const nested = value as { name?: unknown; code?: unknown };
    return text(nested.name ?? nested.code);
  }
  return text(value);
}

export default function InventoryOperations() {
  const [tab, setTab] = useState<Tab>('balance');
  const [rows, setRows] = useState<Row[]>([]);
  const [loading, setLoading] = useState(false);
  const [movementType, setMovementType] = useState('');
  const [form, setForm] = useState({ warehouse_id: '', product_id: '', quantity: '', unit_cost: '', notes: '' });

  const products = useMemo(() => {
    const map = new Map<string, string>();
    rows.forEach((row) => {
      const id = text(row.product_id);
      const name = nestedName(row, 'product');
      if (id !== '-') map.set(id, name === '-' ? id : name);
    });
    return Array.from(map.entries());
  }, [rows]);

  const warehouses = useMemo(() => {
    const map = new Map<string, string>();
    rows.forEach((row) => {
      const id = text(row.warehouse_id);
      const name = nestedName(row, 'warehouse');
      if (id !== '-') map.set(id, name === '-' ? id : name);
    });
    return Array.from(map.entries());
  }, [rows]);

  const endpoint = tab === 'movements' ? '/inventory/movements' : '/inventory/balances';

  const load = async () => {
    setLoading(true);
    try {
      const response = await api.get(endpoint, movementType ? { params: { movement_type: movementType } } : undefined);
      setRows(extractRows<Row>(response.data));
    } catch (error) {
      console.error('Inventory data load failed', error);
      toast.error('Data inventory gagal dimuat.');
      setRows([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { void load(); }, [endpoint, movementType]);

  const submitAction = async (action: 'receive' | 'issue' | 'adjust') => {
    if (!form.warehouse_id || !form.product_id || !form.quantity) {
      toast.error('Warehouse, produk, dan quantity wajib diisi.');
      return;
    }
    try {
      const payload = {
        warehouse_id: Number(form.warehouse_id),
        product_id: form.product_id,
        quantity: Number(form.quantity),
        ...(action !== 'issue' ? { unit_cost: Number(form.unit_cost || 0) } : {}),
        notes: form.notes || undefined,
      };
      await api.post(`/inventory/${action}`, payload);
      toast.success(`Stock ${action} berhasil diproses.`);
      setForm({ warehouse_id: '', product_id: '', quantity: '', unit_cost: '', notes: '' });
      setTab('balance');
      await load();
    } catch (error) {
      const message = error && typeof error === 'object' && 'response' in error
        ? String((error as { response?: { data?: { message?: string } } }).response?.data?.message || '')
        : '';
      toast.error(message || `Stock ${action} gagal diproses.`);
    }
  };

  return (
    <div className="flex h-screen w-full bg-stone-50 font-sans text-stone-800">
      <AdminSidebar activePage="inventory-operations" />
      <div className="flex-1 flex flex-col overflow-hidden">
        <header className="h-20 bg-white border-b border-stone-200 px-8 flex items-center justify-between shadow-sm">
          <div>
            <h1 className="text-xl font-bold">Kontrol Persediaan</h1>
            <p className="mt-1 text-xs text-stone-500">Stok operasional berdasarkan tenant, company, branch, dan warehouse aktif.</p>
          </div>
          <button onClick={() => void load()} className="rounded-xl bg-stone-800 px-4 py-2 text-sm font-bold text-white hover:bg-stone-900">Refresh</button>
        </header>

        <main className="flex-1 overflow-y-auto p-8 space-y-6">
          <div className="flex flex-wrap gap-2">
            {(['balance', 'movements', 'actions'] as Tab[]).map((item) => (
              <button key={item} onClick={() => setTab(item)} className={`rounded-xl px-5 py-2.5 text-sm font-bold ${tab === item ? 'bg-amber-700 text-white' : 'border border-stone-200 bg-white text-stone-600'}`}>
                {item === 'balance' ? 'Saldo Stok' : item === 'movements' ? 'Pergerakan' : 'Operasi Stok'}
              </button>
            ))}
            {tab === 'movements' && (
              <select value={movementType} onChange={(e) => setMovementType(e.target.value)} className="ml-auto rounded-xl border border-stone-300 bg-white px-3 py-2 text-sm">
                <option value="">Semua movement</option>
                <option value="receive">Receive</option>
                <option value="issue">Issue</option>
                <option value="adjustment">Adjustment</option>
              </select>
            )}
          </div>

          {tab === 'actions' ? (
            <section className="max-w-3xl rounded-2xl border border-stone-200 bg-white p-6 shadow-sm">
              <h2 className="text-lg font-bold">Operasi Stok</h2>
              <p className="mt-1 text-sm text-stone-500">Gunakan action sesuai otorisasi inventory.stock.adjust.</p>
              <div className="mt-6 grid gap-4 md:grid-cols-2">
                <select value={form.warehouse_id} onChange={(e) => setForm({ ...form, warehouse_id: e.target.value })} className="rounded-xl border border-stone-300 p-3">
                  <option value="">Pilih warehouse</option>
                  {warehouses.map(([id, name]) => <option key={id} value={id}>{name} ({id})</option>)}
                </select>
                <select value={form.product_id} onChange={(e) => setForm({ ...form, product_id: e.target.value })} className="rounded-xl border border-stone-300 p-3">
                  <option value="">Pilih produk</option>
                  {products.map(([id, name]) => <option key={id} value={id}>{name}</option>)}
                </select>
                <input value={form.quantity} onChange={(e) => setForm({ ...form, quantity: e.target.value })} type="number" step="0.0001" placeholder="Quantity" className="rounded-xl border border-stone-300 p-3" />
                <input value={form.unit_cost} onChange={(e) => setForm({ ...form, unit_cost: e.target.value })} type="number" step="0.01" placeholder="Unit cost (receive/adjust)" className="rounded-xl border border-stone-300 p-3" />
                <textarea value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })} placeholder="Catatan" className="md:col-span-2 rounded-xl border border-stone-300 p-3 min-h-24" />
              </div>
              <div className="mt-6 flex flex-wrap gap-3">
                <PermissionGate permission="inventory.stock.adjust">
                  <button onClick={() => void submitAction('receive')} className="rounded-xl bg-green-700 px-5 py-3 font-bold text-white">Receive</button>
                  <button onClick={() => void submitAction('issue')} className="rounded-xl bg-blue-700 px-5 py-3 font-bold text-white">Issue</button>
                  <button onClick={() => void submitAction('adjust')} className="rounded-xl bg-amber-700 px-5 py-3 font-bold text-white">Adjustment</button>
                </PermissionGate>
              </div>
            </section>
          ) : (
            <section className="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm">
              {loading ? <div className="p-10 text-center text-stone-500">Memuat data...</div> : rows.length === 0 ? <div className="p-10 text-center text-stone-500">Tidak ada data pada organization context aktif.</div> : (
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-sm">
                    <thead className="bg-stone-100 text-xs uppercase text-stone-500">
                      <tr>
                        <th className="p-4">Produk</th>
                        <th className="p-4">Warehouse</th>
                        <th className="p-4">Branch</th>
                        <th className="p-4">Quantity</th>
                        <th className="p-4">Reserved</th>
                        {tab === 'balance' ? <th className="p-4">Available</th> : <th className="p-4">Movement</th>}
                        {tab === 'movements' && <th className="p-4">Created</th>}
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-stone-100">
                      {rows.map((row, index) => {
                        const quantity = Number(row.quantity ?? 0);
                        const reserved = Number(row.reserved_quantity ?? 0);
                        return (
                          <tr key={text(row.id) + '-' + index} className="hover:bg-stone-50">
                            <td className="p-4 font-semibold">{nestedName(row, 'product')}</td>
                            <td className="p-4">{nestedName(row, 'warehouse')}</td>
                            <td className="p-4">{text(row.branch_id)}</td>
                            <td className="p-4">{quantity.toLocaleString('id-ID')}</td>
                            <td className="p-4">{reserved.toLocaleString('id-ID')}</td>
                            <td className="p-4 font-bold">{tab === 'balance' ? (quantity - reserved).toLocaleString('id-ID') : text(row.movement_type)}</td>
                            {tab === 'movements' && <td className="p-4 text-stone-500">{text(row.created_at)}</td>}
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              )}
            </section>
          )}
        </main>
      </div>
    </div>
  );
}

import { useEffect, useMemo, useState } from 'react';
import AdminSidebar from '../components/AdminSidebar';
import api from '../core/api/client';
import { extractRows } from '../core/api/normalize';
import { can } from '../core/auth/permissions';
import toast from 'react-hot-toast';

type Row = Record<string, unknown>;
type Option = { id: string; label: string; meta?: string };
type Line = { productId: string; quantity: number; unitCost: number; purchaseOrderItemId?: string; goodsReceiptItemId?: string };
type Section = 'suppliers' | 'requisitions' | 'orders' | 'receipts' | 'invoices' | 'payments' | 'returns' | 'credit-notes' | 'budgets' | 'approval-matrix' | 'reconciliation' | 'reports';

type Master = {
  suppliers: Option[]; products: Option[]; warehouses: Option[]; requisitions: Option[]; orders: Option[]; receipts: Option[]; invoices: Option[]; roles: Option[]; accounts: Option[];
};

const sections: Array<{ key: Section; label: string; permission: string }> = [
  { key: 'suppliers', label: 'Suppliers', permission: 'purchasing.supplier.view' },
  { key: 'requisitions', label: 'Purchase Requisitions', permission: 'purchasing.requisition.view' },
  { key: 'orders', label: 'Purchase Orders', permission: 'purchasing.order.view' },
  { key: 'receipts', label: 'Goods Receipts', permission: 'purchasing.receipt.view' },
  { key: 'invoices', label: 'Supplier Invoices', permission: 'purchasing.ap.view' },
  { key: 'payments', label: 'Supplier Payments', permission: 'purchasing.ap.view' },
  { key: 'returns', label: 'Supplier Returns', permission: 'purchasing.return.view' },
  { key: 'credit-notes', label: 'Credit Notes', permission: 'purchasing.credit_note.view' },
  { key: 'budgets', label: 'Budget', permission: 'purchasing.budget.view' },
  { key: 'approval-matrix', label: 'Approval Matrix', permission: 'purchasing.approval_matrix.view' },
  { key: 'reconciliation', label: 'Reconciliation', permission: 'purchasing.reconciliation.view' },
  { key: 'reports', label: 'Reports', permission: 'purchasing.reporting.view' },
];

const labelOf = (row: Row, keys: string[]): string => {
  for (const key of keys) {
    const value = row[key];
    if (value === undefined || value === null) continue;
    
    // Handle nested objects (e.g., supplier: { name: 'PT ABC' })
    if (typeof value === 'object' && !Array.isArray(value)) {
      const objValue = value as Record<string, unknown>;
      // Try to extract common label fields from nested object
      const nestedLabel = objValue.name ?? objValue.label ?? objValue.code ?? objValue.title;
      if (nestedLabel !== undefined && nestedLabel !== null && String(nestedLabel) !== '') {
        return String(nestedLabel);
      }
      // If no label found in object, try next key
      continue;
    }
    
    if (String(value) !== '') return String(value);
  }
  return row.id ? `#${row.id}` : '-';
};

const dateOf = (row: Row, keys: string[] = ['order_date', 'invoice_date', 'received_date', 'payment_date', 'created_at']): string => {
  for (const key of keys) {
    const value = row[key];
    if (value && typeof value === 'string') {
      // Parse and format date properly (not raw UTC)
      try {
        const dateStr = value.slice(0, 10); // YYYY-MM-DD format
        return dateStr;
      } catch {
        // Fall through to next key
      }
    }
  }
  return '';
};

const totalOf = (row: Row, keys: string[] = ['grand_total', 'total', 'amount', 'outstanding']): number => {
  for (const key of keys) {
    const value = row[key];
    if (value !== undefined && value !== null) {
      const parsed = num(value);
      if (parsed > 0 || parsed === 0) return parsed; // Include zero
    }
  }
  return 0;
};

const money = (value: unknown) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value) || 0);
const num = (value: unknown) => {
  const n = Number(value);
  return Number.isFinite(n) ? n : 0;
};

function options(rows: Row[], keys: string[]): Option[] {
  return rows.map(row => ({ id: String(row.id), label: labelOf(row, keys), meta: typeof row.code === 'string' ? row.code : undefined }));
}

function getProductPrice(row: Row): number {
  return num(row.purchase_price ?? row.cost ?? row.price ?? 0);
}

export default function PurchasingWorkspace() {
  const allowedSections = useMemo(() => sections.filter(section => can(section.permission)), []);
  const [section, setSection] = useState<Section>(allowedSections[0]?.key ?? 'orders');
  const [rows, setRows] = useState<Row[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState<Record<string, string>>({});
  const [lines, setLines] = useState<Line[]>([{ productId: '', quantity: 1, unitCost: 0 }]);
  const [masters, setMasters] = useState<Master>({ suppliers: [], products: [], warehouses: [], requisitions: [], orders: [], receipts: [], invoices: [], roles: [], accounts: [] });

  const endpoint = useMemo(() => ({
    suppliers: '/purchasing/suppliers', requisitions: '/purchasing/requisitions', orders: '/purchasing/orders', receipts: '/purchasing/goods-receipts', invoices: '/purchasing/invoices', payments: '/purchasing/payments', returns: '/purchasing/returns', 'credit-notes': '/purchasing/credit-notes', budgets: '/purchasing/budgets', 'approval-matrix': '/purchasing/approval-matrix', reconciliation: '/purchasing/reconciliation/orders', reports: '/purchasing/reports/dashboard',
  } satisfies Record<Section, string>)[section], [section]);

  useEffect(() => {
    const loadMasters = async () => {
      const entries: Array<[keyof Master, string, string[]]> = [
        ['suppliers', '/purchasing/suppliers', ['name', 'code']],
        ['products', '/products', ['name', 'code']],
        ['warehouses', '/warehouses', ['name', 'code']],
        ['requisitions', '/purchasing/requisitions', ['requisition_number', 'number']],
        ['orders', '/purchasing/orders', ['order_number', 'number']],
        ['receipts', '/purchasing/goods-receipts', ['receipt_number', 'number']],
        ['invoices', '/purchasing/invoices', ['invoice_number', 'number']],
        ['roles', '/roles', ['name', 'code']],
        ['accounts', '/erp/accounting/accounts', ['name', 'code']],
      ];
      const next = { ...masters };
      await Promise.all(entries.map(async ([key, path, keys]) => {
        try {
          const response = await api.get(path);
          next[key] = options(extractRows<Row>(response.data), keys);
        } catch {
          next[key] = [];
        }
      }));
      setMasters(next);
    };
    void loadMasters();
  }, []);

  const load = async () => {
    setLoading(true); setError('');
    try {
      const response = await api.get(endpoint);
      setRows(extractRows<Row>(response.data));
    } catch (err) {
      const message = err && typeof err === 'object' && 'response' in err ? String((err as { response?: { data?: { message?: string } } }).response?.data?.message ?? '') : '';
      setRows([]); setError(message || 'Data tidak dapat dimuat pada context organisasi aktif.');
    } finally { setLoading(false); }
  };

  useEffect(() => { void load(); }, [endpoint]);

  const reset = () => { setForm({}); setLines([{ productId: '', quantity: 1, unitCost: 0 }]); setShowForm(false); };
  const setField = (key: string, value: string) => setForm(current => ({ ...current, [key]: value }));

  const select = (label: string, key: string, items: Option[], required = false) => (
    <label className="block"><span className="mb-1 block text-xs font-bold text-stone-500">{label}{required ? ' *' : ''}</span><select value={form[key] ?? ''} onChange={e => setField(key, e.target.value)} className="w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-amber-600"><option value="">Pilih {label.toLowerCase()}...</option>{items.map(item => <option key={item.id} value={item.id}>{item.label}{item.meta ? ` · ${item.meta}` : ''}</option>)}</select></label>
  );

  const input = (label: string, key: string, type = 'text', required = false) => (
    <label className="block"><span className="mb-1 block text-xs font-bold text-stone-500">{label}{required ? ' *' : ''}</span><input type={type} value={form[key] ?? ''} onChange={e => setField(key, e.target.value)} className="w-full rounded-xl border border-stone-200 px-3 py-2.5 text-sm outline-none focus:border-amber-600" /></label>
  );

  const productLookup = useMemo(() => new Map(masters.products.map(product => [product.id, product])), [masters.products]);

  const updateLineProduct = (index: number, productId: string) => {
    const product = productLookup.get(productId);
    const parsed = product ? num((product as unknown as { price?: unknown }).price) : 0;
    setLines(current => current.map((line, i) => i === index ? { ...line, productId, unitCost: parsed } : line));
  };

  const subtotal = lines.reduce((sum, line) => sum + Math.max(0, line.quantity) * Math.max(0, line.unitCost), 0);
  const discount = Math.max(0, num(form.discount_amount));
  const taxPercent = Math.max(0, num(form.tax_percent));
  const tax = Math.max(0, num(form.tax_amount) || ((subtotal - discount) * taxPercent / 100));
  const grandTotal = Math.max(0, subtotal - discount + tax);

  const lineEditor = (unitLabel = 'Harga') => (
    <section className="rounded-2xl border border-stone-200 bg-white p-4 md:col-span-2">
      <div className="mb-3 flex items-center justify-between"><div><h3 className="font-bold text-stone-800">Item</h3><p className="text-xs text-stone-500">Pilih produk, quantity, dan harga. Semua payload teknis dibuat otomatis.</p></div><button type="button" onClick={() => setLines(current => [...current, { productId: '', quantity: 1, unitCost: 0 }])} className="rounded-lg border border-stone-200 px-3 py-2 text-xs font-bold hover:bg-stone-50">+ Tambah Item</button></div>
      <div className="space-y-3">{lines.map((line, index) => <div key={index} className="grid gap-2 md:grid-cols-[minmax(0,1fr)_120px_160px_130px_40px] items-end">
        <label className="block"><span className="mb-1 block text-xs font-bold text-stone-500">Produk</span><select value={line.productId} onChange={e => updateLineProduct(index, e.target.value)} className="w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm"><option value="">Pilih produk...</option>{masters.products.map(product => <option key={product.id} value={product.id}>{product.label}</option>)}</select></label>
        <label><span className="mb-1 block text-xs font-bold text-stone-500">Qty</span><input min={1} type="number" value={line.quantity} onChange={e => setLines(current => current.map((item, i) => i === index ? { ...item, quantity: Math.max(1, Number(e.target.value) || 1) } : item))} className="w-full rounded-xl border border-stone-200 px-3 py-2.5 text-sm" /></label>
        <label><span className="mb-1 block text-xs font-bold text-stone-500">{unitLabel}</span><input min={0} type="number" value={line.unitCost} onChange={e => setLines(current => current.map((item, i) => i === index ? { ...item, unitCost: Math.max(0, Number(e.target.value) || 0) } : item))} className="w-full rounded-xl border border-stone-200 px-3 py-2.5 text-sm" /></label>
        <div><span className="mb-1 block text-xs font-bold text-stone-500">Jumlah</span><div className="rounded-xl bg-stone-50 px-3 py-2.5 text-sm font-bold">{money(line.quantity * line.unitCost)}</div></div>
        <button type="button" disabled={lines.length === 1} onClick={() => setLines(current => current.filter((_, i) => i !== index))} className="h-10 rounded-xl border border-red-100 text-red-500 disabled:opacity-30">×</button>
      </div>)}</div>
      {section === 'orders' && <div className="mt-5 grid gap-3 md:grid-cols-4"><div className="md:col-start-2"><span className="text-xs text-stone-500">Subtotal</span><div className="font-bold">{money(subtotal)}</div></div><div><span className="text-xs text-stone-500">Discount</span><input type="number" min={0} value={form.discount_amount ?? ''} onChange={e => setField('discount_amount', e.target.value)} className="mt-1 w-full rounded-xl border border-stone-200 px-3 py-2 text-sm" /></div><div><span className="text-xs text-stone-500">PPN %</span><input type="number" min={0} value={form.tax_percent ?? ''} onChange={e => setField('tax_percent', e.target.value)} className="mt-1 w-full rounded-xl border border-stone-200 px-3 py-2 text-sm" /><div className="mt-1 text-xs text-stone-500">{money(tax)}</div></div><div className="md:col-start-4"><span className="text-xs text-stone-500">Grand Total</span><div className="text-xl font-extrabold text-stone-900">{money(grandTotal)}</div></div></div>}
    </section>
  );

  const submit = async () => {
    try {
      let payload: Record<string, unknown>;
      if (section === 'suppliers') payload = { code: form.code, name: form.name, tax_id: form.tax_id || undefined, contact_name: form.contact_name || undefined, phone: form.phone || undefined, email: form.email || undefined, address: form.address || undefined, payment_terms_days: form.payment_terms_days ? Number(form.payment_terms_days) : undefined, status: form.status || 'active' };
      else if (section === 'requisitions') payload = { warehouse_id: Number(form.warehouse_id), needed_by: form.needed_by || undefined, reason: form.reason || undefined, notes: form.notes || undefined, items: lines.filter(line => line.productId).map(line => ({ product_id: line.productId, quantity: line.quantity, estimated_unit_cost: line.unitCost })) };
      else if (section === 'orders') payload = { supplier_id: Number(form.supplier_id), warehouse_id: Number(form.warehouse_id), purchase_requisition_id: form.purchase_requisition_id ? Number(form.purchase_requisition_id) : undefined, expected_date: form.expected_date || undefined, discount_amount: discount, tax_amount: tax, notes: form.notes || undefined, items: lines.filter(line => line.productId).map(line => ({ product_id: line.productId, quantity: line.quantity, unit_cost: line.unitCost })) };
      else if (section === 'receipts') payload = { purchase_order_id: Number(form.purchase_order_id), warehouse_id: Number(form.warehouse_id), notes: form.notes || undefined, items: lines.filter(line => line.purchaseOrderItemId && line.productId).map(line => ({ purchase_order_item_id: Number(line.purchaseOrderItemId), quantity: line.quantity, unit_cost: line.unitCost })) };
      else if (section === 'invoices') payload = { goods_receipt_id: Number(form.goods_receipt_id), invoice_number: form.invoice_number, invoice_date: form.invoice_date || undefined, due_date: form.due_date || undefined, notes: form.notes || undefined };
      else if (section === 'payments') payload = { supplier_invoice_id: Number(form.supplier_invoice_id), amount: Number(form.amount), method: form.method || 'bank_transfer', reference: form.reference || undefined, notes: form.notes || undefined };
      else if (section === 'returns') payload = { purchase_order_id: Number(form.purchase_order_id), goods_receipt_id: Number(form.goods_receipt_id), warehouse_id: Number(form.warehouse_id), reason: form.reason || undefined, notes: form.notes || undefined, items: lines.filter(line => line.goodsReceiptItemId && line.productId).map(line => ({ goods_receipt_item_id: Number(line.goodsReceiptItemId), quantity: line.quantity })) };
      else if (section === 'credit-notes') payload = { supplier_return_id: Number(form.supplier_return_id), credit_note_number: form.credit_note_number, notes: form.notes || undefined };
      else if (section === 'budgets') payload = { budget_year: Number(form.budget_year), allocated_amount: Number(form.allocated_amount), notes: form.notes || undefined };
      else if (section === 'approval-matrix') payload = { approver_role_id: Number(form.approver_role_id), min_amount: Number(form.min_amount), max_amount: form.max_amount ? Number(form.max_amount) : undefined, priority: form.priority ? Number(form.priority) : 1, notes: form.notes || undefined };
      else return;

      await api.post(endpoint, payload);
      toast.success(`${sections.find(item => item.key === section)?.label ?? 'Data'} berhasil disimpan.`);
      reset();
      await load();
    } catch (err) {
      const message = err && typeof err === 'object' && 'response' in err ? String((err as { response?: { data?: { message?: string } } }).response?.data?.message ?? '') : '';
      toast.error(message || 'Data gagal disimpan.');
    }
  };

  const action = async (row: Row, verb: 'submit' | 'approve' | 'reject' | 'cancel') => {
    const id = String(row.id ?? '');
    if (!id) return;
    const path = `/purchasing/orders/${id}/${verb}`;
    try {
      if (verb === 'reject') {
        const reason = window.prompt('Alasan penolakan Purchase Order:');
        if (!reason) return;
        await api.post(path, { reason });
      } else await api.post(path);
      toast.success(`Purchase Order ${verb} berhasil.`);
      await load();
    } catch (err) {
      const message = err && typeof err === 'object' && 'response' in err ? String((err as { response?: { data?: { message?: string } } }).response?.data?.message ?? '') : '';
      toast.error(message || `Gagal ${verb} Purchase Order.`);
    }
  };

  const renderForm = () => {
    if (!showForm) return null;
    return <div className="fixed inset-0 z-50 bg-stone-950/40 p-4 overflow-y-auto"><div className="mx-auto max-w-5xl rounded-3xl bg-stone-50 shadow-2xl"><div className="flex items-center justify-between border-b border-stone-200 bg-white px-6 py-5 rounded-t-3xl"><div><div className="text-xs font-bold uppercase tracking-wider text-amber-700">Create</div><h2 className="text-xl font-extrabold text-stone-900">{sections.find(item => item.key === section)?.label}</h2></div><button onClick={reset} className="rounded-xl px-3 py-2 text-stone-500 hover:bg-stone-100">✕</button></div><div className="p-6">
      {section === 'suppliers' && <div className="grid gap-4 md:grid-cols-2">{input('Kode Supplier', 'code', 'text', true)}{input('Nama Supplier', 'name', 'text', true)}{input('NPWP / Tax ID', 'tax_id')}{input('Contact Person', 'contact_name')}{input('Telepon', 'phone')}{input('Email', 'email')}{input('Payment Terms (hari)', 'payment_terms_days', 'number')}{input('Alamat', 'address')}{select('Status', 'status', [{ id: 'active', label: 'Aktif' }, { id: 'inactive', label: 'Nonaktif' }])}</div>}
      {section === 'requisitions' && <div className="grid gap-4 md:grid-cols-2">{select('Warehouse', 'warehouse_id', masters.warehouses, true)}{input('Needed By', 'needed_by', 'date')}{input('Alasan', 'reason')}{input('Catatan', 'notes')}{lineEditor('Estimasi Harga')}</div>}
      {section === 'orders' && <div className="grid gap-4 md:grid-cols-2">{select('Supplier', 'supplier_id', masters.suppliers, true)}{select('Warehouse', 'warehouse_id', masters.warehouses, true)}{select('Purchase Requisition', 'purchase_requisition_id', masters.requisitions)}{input('Expected Date', 'expected_date', 'date')}{input('Catatan', 'notes')}{lineEditor('Harga Beli')}</div>}
      {section === 'receipts' && <div className="grid gap-4 md:grid-cols-2">{select('Purchase Order', 'purchase_order_id', masters.orders, true)}{select('Warehouse', 'warehouse_id', masters.warehouses, true)}{input('Catatan', 'notes')}{lineEditor('Unit Cost')}</div>}
      {section === 'invoices' && <div className="grid gap-4 md:grid-cols-2">{select('Goods Receipt', 'goods_receipt_id', masters.receipts, true)}{input('Nomor Invoice', 'invoice_number', 'text', true)}{input('Tanggal Invoice', 'invoice_date', 'date')}{input('Jatuh Tempo', 'due_date', 'date')}{input('Catatan', 'notes')}</div>}
      {section === 'payments' && <div className="grid gap-4 md:grid-cols-2">{select('Supplier Invoice', 'supplier_invoice_id', masters.invoices, true)}{input('Nominal Pembayaran', 'amount', 'number', true)}{select('Metode', 'method', [{ id: 'cash', label: 'Cash' }, { id: 'bank_transfer', label: 'Bank Transfer' }, { id: 'giro', label: 'Giro' }, { id: 'other', label: 'Lainnya' }])}{input('Referensi', 'reference')}{input('Catatan', 'notes')}</div>}
      {section === 'returns' && <div className="grid gap-4 md:grid-cols-2">{select('Purchase Order', 'purchase_order_id', masters.orders, true)}{select('Goods Receipt', 'goods_receipt_id', masters.receipts, true)}{select('Warehouse', 'warehouse_id', masters.warehouses, true)}{input('Alasan Return', 'reason')}{input('Catatan', 'notes')}{lineEditor('Unit Cost')}</div>}
      {section === 'credit-notes' && <div className="grid gap-4 md:grid-cols-2">{input('Supplier Return ID', 'supplier_return_id', 'text', true)}{input('Nomor Credit Note', 'credit_note_number', 'text', true)}{input('Catatan', 'notes')}</div>}
      {section === 'budgets' && <div className="grid gap-4 md:grid-cols-2">{input('Tahun Budget', 'budget_year', 'number', true)}{input('Allocated Amount', 'allocated_amount', 'number', true)}{input('Catatan', 'notes')}</div>}
      {section === 'approval-matrix' && <div className="grid gap-4 md:grid-cols-2">{select('Approver Role', 'approver_role_id', masters.roles, true)}{input('Minimum Amount', 'min_amount', 'number', true)}{input('Maximum Amount', 'max_amount', 'number')}{input('Priority', 'priority', 'number')}{input('Catatan', 'notes')}</div>}
      {(section === 'reconciliation' || section === 'reports' || section === 'invoices' || section === 'payments') && !['reconciliation', 'reports', 'invoices', 'payments'].includes(section) ? null : null}
      <div className="mt-6 flex justify-end gap-2"><button type="button" onClick={reset} className="rounded-xl border border-stone-200 bg-white px-4 py-2.5 text-sm font-bold">Batal</button><button type="button" onClick={() => void submit()} className="rounded-xl bg-amber-700 px-5 py-2.5 text-sm font-bold text-white hover:bg-amber-800">Simpan</button></div>
    </div></div></div>;
  };

  const summary = useMemo(() => {
    if (section === 'reports') return rows.length ? rows[0] : {};
    return { count: rows.length, amount: rows.reduce((sum, row) => sum + totalOf(row), 0) };
  }, [rows, section]);

  const statusOf = (row: Row) => String(row.status ?? row.state ?? '—').replaceAll('_', ' ');

  return <div className="flex h-screen w-full bg-stone-50 font-sans text-stone-800"><AdminSidebar activePage="purchasing-workspace" /><div className="flex min-w-0 flex-1 flex-col overflow-hidden"><header className="border-b border-stone-200 bg-white px-8 py-5"><div className="text-xs font-bold uppercase tracking-[0.18em] text-amber-700">ERP · Purchasing</div><h1 className="mt-1 text-2xl font-extrabold text-stone-900">Purchasing Workspace</h1><p className="mt-1 text-sm text-stone-500">Kelola seluruh siklus procurement tanpa JSON atau ID teknis.</p></header><div className="border-b border-stone-200 bg-white px-6"><div className="flex gap-1 overflow-x-auto py-3">{allowedSections.map(item => <button key={item.key} onClick={() => { setSection(item.key); reset(); }} className={`whitespace-nowrap rounded-xl px-3 py-2 text-xs font-bold ${section === item.key ? 'bg-amber-700 text-white' : 'text-stone-500 hover:bg-stone-100'}`}>{item.label}</button>)}</div></div><main className="min-h-0 flex-1 overflow-y-auto p-6 lg:p-8"><div className="mb-5 grid gap-4 sm:grid-cols-3"><div className="rounded-2xl border border-stone-200 bg-white p-5"><div className="text-xs font-bold uppercase text-stone-500">Records</div><div className="mt-2 text-2xl font-extrabold">{section === 'reports' ? '—' : rows.length}</div></div><div className="rounded-2xl border border-stone-200 bg-white p-5"><div className="text-xs font-bold uppercase text-stone-500">Aggregate</div><div className="mt-2 text-xl font-extrabold">{section === 'reports' ? 'Live Report' : money(summary.amount)}</div></div><div className="rounded-2xl border border-stone-200 bg-white p-5 flex items-center justify-between"><div><div className="text-xs font-bold uppercase text-stone-500">Workflow</div><div className="mt-2 text-sm font-bold text-stone-700">{sections.find(item => item.key === section)?.label}</div></div>{section !== 'reports' && <button onClick={() => setShowForm(true)} className="rounded-xl bg-amber-700 px-4 py-2.5 text-sm font-bold text-white">+ Tambah</button>}</div></div>
      {error && <div className="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}
      {loading ? <div className="rounded-2xl border border-stone-200 bg-white p-10 text-center text-sm text-stone-500">Memuat data...</div> : section === 'reports' ? <div className="grid gap-4 md:grid-cols-3">{rows.map((row, index) => <div key={index} className="rounded-2xl border border-stone-200 bg-white p-5"><div className="text-xs uppercase tracking-wide text-stone-500">{labelOf(row, ['name','supplier_name','period','report'])}</div><div className="mt-3 text-2xl font-extrabold text-stone-900">{money(row.amount ?? row.total ?? row.value)}</div><pre className="mt-4 max-h-40 overflow-auto rounded-xl bg-stone-950 p-3 text-[11px] text-stone-200">{JSON.stringify(row, null, 2)}</pre></div>)}</div> : <div className="overflow-hidden rounded-2xl border border-stone-200 bg-white"><div className="overflow-x-auto"><table className="min-w-full text-sm"><thead className="bg-stone-50 text-xs uppercase tracking-wide text-stone-500"><tr><th className="px-5 py-3 text-left">Document</th><th className="px-5 py-3 text-left">Status</th><th className="px-5 py-3 text-left">Partner</th><th className="px-5 py-3 text-right">Amount</th><th className="px-5 py-3 text-right">Action</th></tr></thead><tbody className="divide-y divide-stone-100">{rows.map((row, index) => <tr key={String(row.id ?? index)} className="hover:bg-stone-50"><td className="px-5 py-4"><div className="font-bold text-stone-900">{labelOf(row, ['order_number','requisition_number','receipt_number','invoice_number','payment_number','return_number','credit_note_number','code','name'])}</div><div className="text-xs text-stone-500">{dateOf(row)}</div></td><td className="px-5 py-4"><span className="rounded-full bg-stone-100 px-2.5 py-1 text-xs font-bold capitalize">{statusOf(row)}</span></td><td className="px-5 py-4 text-stone-600">{labelOf(row, ['supplier', 'contact_name', 'name'])}</td><td className="px-5 py-4 text-right font-bold">{money(totalOf(row))}</td><td className="px-5 py-4 text-right"><div className="flex justify-end gap-1">{section === 'orders' && ['draft','submitted','pending_approval'].includes(String(row.status ?? '')) && <button onClick={() => void action(row, 'submit')} className="rounded-lg px-2 py-1 text-xs font-bold text-amber-700 hover:bg-amber-50">Submit</button>}{section === 'orders' && ['submitted','pending_approval'].includes(String(row.status ?? '')) && can('purchasing.order.approve') && <button onClick={() => void action(row, 'approve')} className="rounded-lg px-2 py-1 text-xs font-bold text-emerald-700 hover:bg-emerald-50">Approve</button>}{section === 'orders' && ['submitted','pending_approval'].includes(String(row.status ?? '')) && can('purchasing.order.approve') && <button onClick={() => void action(row, 'reject')} className="rounded-lg px-2 py-1 text-xs font-bold text-red-700 hover:bg-red-50">Reject</button>}{section === 'orders' && ['draft','submitted','pending_approval'].includes(String(row.status ?? '')) && <button onClick={() => void action(row, 'cancel')} className="rounded-lg px-2 py-1 text-xs font-bold text-stone-600 hover:bg-stone-100">Cancel</button>}</div></td></tr>)}</tbody></table>{rows.length === 0 && <div className="p-10 text-center text-sm text-stone-500">Belum ada data untuk menu ini.</div>}</div></div>}
    </main>{renderForm()}</div></div>;
}

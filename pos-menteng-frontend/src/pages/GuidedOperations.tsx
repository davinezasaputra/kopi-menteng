import { useEffect, useMemo, useState } from 'react';
import AdminSidebar from '../components/AdminSidebar';
import api from '../core/api/client';
import { extractRows } from '../core/api/normalize';
import { can } from '../core/auth/permissions';
import toast from 'react-hot-toast';

type Option = { id: string; label: string; meta?: string };
type Product = Option & { price?: number };
type FormMode = 'supplier' | 'purchase-order' | 'sales-order' | 'cash-book' | 'journal';

type OrderLine = {
  product_id: string;
  quantity: string;
  unit_cost?: string;
  unit_price?: string;
};

const emptyLine = (): OrderLine => ({ product_id: '', quantity: '1', unit_cost: '', unit_price: '' });

function firstData(value: unknown): Record<string, unknown> | null {
  return value && typeof value === 'object' && !Array.isArray(value) ? value as Record<string, unknown> : null;
}

function money(value: number): string {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value || 0);
}

export default function GuidedOperations() {
  const modes = useMemo(() => [
    { key: 'supplier' as FormMode, label: 'Supplier', permission: 'purchasing.supplier.create' },
    { key: 'purchase-order' as FormMode, label: 'Purchase Order', permission: 'purchasing.order.create' },
    { key: 'sales-order' as FormMode, label: 'Sales Order', permission: 'sales.order.create' },
    { key: 'cash-book' as FormMode, label: 'Cash Book', permission: 'accounting.report.view' },
    { key: 'journal' as FormMode, label: 'Journal Entry', permission: 'accounting.erp_journal.create' },
  ].filter(item => can(item.permission)), []);

  const [mode, setMode] = useState<FormMode>(modes[0]?.key ?? 'supplier');
  const [suppliers, setSuppliers] = useState<Option[]>([]);
  const [customers, setCustomers] = useState<Option[]>([]);
  const [products, setProducts] = useState<Product[]>([]);
  const [accounts, setAccounts] = useState<Option[]>([]);
  const [warehouses, setWarehouses] = useState<Option[]>([]);
  const [loadingMaster, setLoadingMaster] = useState(false);
  const [supplierForm, setSupplierForm] = useState({ code: '', name: '', phone: '', email: '', address: '', payment_terms_days: '0' });
  const [poForm, setPoForm] = useState({ supplier_id: '', warehouse_id: '', expected_date: '', discount_amount: '0', tax_amount: '', notes: '' });
  const [salesForm, setSalesForm] = useState({ customer_id: '', warehouse_id: '', discount_amount: '0', tax_amount: '', notes: '' });
  const [lines, setLines] = useState<OrderLine[]>([emptyLine()]);
  const [cashForm, setCashForm] = useState({ account_code: '', from: '', to: '' });
  const [cashResult, setCashResult] = useState<Record<string, unknown> | null>(null);
  const [journalForm, setJournalForm] = useState({ journal_date: new Date().toISOString().slice(0, 10), reference: '', description: '' });
  const [journalLines, setJournalLines] = useState<Array<{ account_id: string; debit: string; credit: string }>>([
    { account_id: '', debit: '', credit: '' },
    { account_id: '', debit: '', credit: '' },
  ]);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (modes.length && !modes.some(item => item.key === mode)) setMode(modes[0].key);
  }, [mode, modes]);

  useEffect(() => {
    let active = true;
    const loadMasters = async () => {
      setLoadingMaster(true);
      try {
        const [supplierRes, customerRes, productRes, accountRes, warehouseRes] = await Promise.all([
          api.get('/purchasing/suppliers'),
          api.get('/customers'),
          api.get('/products'),
          api.get('/erp/accounting/accounts'),
          api.get('/warehouses'),
        ]);
        if (!active) return;
        setSuppliers(extractRows<Record<string, unknown>>(supplierRes.data).map(row => ({ id: String(row.id ?? ''), label: String(row.name ?? row.code ?? row.id ?? ''), meta: row.code ? String(row.code) : undefined })));
        setCustomers(extractRows<Record<string, unknown>>(customerRes.data).map(row => ({ id: String(row.id ?? ''), label: String(row.name ?? row.company_name ?? row.id ?? ''), meta: row.phone ? String(row.phone) : undefined })));
        setProducts(extractRows<Record<string, unknown>>(productRes.data).map(row => ({ id: String(row.id ?? ''), label: String(row.name ?? row.sku ?? row.id ?? ''), meta: row.sku ? String(row.sku) : undefined, price: Number(row.price ?? row.selling_price ?? 0) })));
        setAccounts(extractRows<Record<string, unknown>>(accountRes.data).map(row => ({ id: String(row.id ?? ''), label: `${String(row.code ?? '')} · ${String(row.name ?? '')}`, meta: String(row.type ?? '') })));
        setWarehouses(extractRows<Record<string, unknown>>(warehouseRes.data).map(row => ({ id: String(row.id ?? ''), label: String(row.name ?? row.code ?? row.id ?? ''), meta: row.code ? String(row.code) : undefined })));
      } catch {
        if (active) toast.error('Sebagian master data tidak dapat dimuat.');
      } finally {
        if (active) setLoadingMaster(false);
      }
    };
    void loadMasters();
    return () => { active = false; };
  }, []);

  const selectedProduct = (id: string) => products.find(item => item.id === id);

  const totalLines = lines.reduce((total, line) => {
    const product = selectedProduct(line.product_id);
    const value = Number(line.unit_price ?? line.unit_cost ?? product?.price ?? 0) * Number(line.quantity || 0);
    return total + value;
  }, 0);

  const updateLine = (index: number, patch: Partial<OrderLine>) => {
    setLines(current => current.map((line, lineIndex) => lineIndex === index ? { ...line, ...patch } : line));
  };

  const submit = async () => {
    setSaving(true);
    try {
      if (mode === 'supplier') {
        await api.post('/purchasing/suppliers', { ...supplierForm, payment_terms_days: Number(supplierForm.payment_terms_days || 0) });
        toast.success('Supplier berhasil dibuat.');
        setSupplierForm({ code: '', name: '', phone: '', email: '', address: '', payment_terms_days: '0' });
      } else if (mode === 'purchase-order') {
        const items = lines.filter(line => line.product_id && Number(line.quantity) > 0).map(line => ({ product_id: line.product_id, quantity: Number(line.quantity), unit_cost: Number(line.unit_cost || selectedProduct(line.product_id)?.price || 0) }));
        if (!poForm.supplier_id || !poForm.warehouse_id || items.length === 0) throw new Error('Supplier, warehouse, dan minimal satu barang wajib diisi.');
        await api.post('/purchasing/orders', { ...poForm, supplier_id: Number(poForm.supplier_id), warehouse_id: Number(poForm.warehouse_id), discount_amount: Number(poForm.discount_amount || 0), tax_amount: Number(poForm.tax_amount || 0), items });
        toast.success('Purchase Order berhasil dibuat.');
        setLines([emptyLine()]);
      } else if (mode === 'sales-order') {
        const items = lines.filter(line => line.product_id && Number(line.quantity) > 0).map(line => ({ product_id: line.product_id, quantity: Number(line.quantity), unit_price: Number(line.unit_price || selectedProduct(line.product_id)?.price || 0) }));
        if (!salesForm.warehouse_id || items.length === 0) throw new Error('Warehouse dan minimal satu barang wajib diisi.');
        await api.post('/sales/orders', { ...salesForm, customer_id: salesForm.customer_id ? Number(salesForm.customer_id) : undefined, warehouse_id: Number(salesForm.warehouse_id), discount_amount: Number(salesForm.discount_amount || 0), tax_amount: Number(salesForm.tax_amount || 0), items });
        toast.success('Sales Order berhasil dibuat.');
        setLines([emptyLine()]);
      } else if (mode === 'cash-book') {
        const params: Record<string, string> = {};
        if (cashForm.account_code) params.account_code = cashForm.account_code;
        if (cashForm.from) params.from = cashForm.from;
        if (cashForm.to) params.to = cashForm.to;
        const response = await api.get('/finance/cash-book', { params });
        setCashResult(firstData(response.data));
      } else {
        const linesPayload = journalLines.filter(line => line.account_id && (Number(line.debit) > 0 || Number(line.credit) > 0)).map(line => ({ account_id: Number(line.account_id), debit: Number(line.debit || 0), credit: Number(line.credit || 0) }));
        const debit = linesPayload.reduce((sum, line) => sum + line.debit, 0);
        const credit = linesPayload.reduce((sum, line) => sum + line.credit, 0);
        if (Math.abs(debit - credit) > 0.009) throw new Error(`Journal belum balance. Debit ${money(debit)} · Credit ${money(credit)}.`);
        if (linesPayload.length < 2) throw new Error('Minimal dua baris akun diperlukan.');
        await api.post('/erp/accounting/journals', { ...journalForm, lines: linesPayload });
        toast.success('Journal berhasil dibuat.');
      }
    } catch (error) {
      toast.error(error instanceof Error ? error.message : 'Operasi gagal diproses.');
    } finally {
      setSaving(false);
    }
  };

  const warehouseOptions: Option[] = warehouses;

  return (
    <div className="flex h-screen w-full bg-stone-50 font-sans text-stone-800">
      <AdminSidebar activePage="operations" />
      <div className="flex min-w-0 flex-1 flex-col overflow-hidden">
        <header className="border-b border-stone-200 bg-white px-8 py-5 shadow-sm">
          <div className="flex flex-wrap items-center justify-between gap-4">
            <div><div className="text-xs font-bold uppercase tracking-[0.18em] text-amber-700">Enterprise Operations</div><h1 className="mt-1 text-2xl font-bold text-stone-900">Workspace Operasional</h1><p className="mt-1 text-sm text-stone-500">Form bisnis dengan master data, auto-fill, perhitungan otomatis, dan permission.</p></div>
            {loadingMaster && <span className="rounded-full bg-stone-100 px-3 py-1 text-xs font-bold text-stone-500">Memuat master data…</span>}
          </div>
        </header>

        <main className="min-h-0 flex-1 overflow-y-auto p-6 lg:p-8">
          <div className="mb-6 flex flex-wrap gap-2">
            {modes.map(item => <button key={item.key} onClick={() => setMode(item.key)} className={`rounded-2xl px-5 py-3 text-sm font-bold ${mode === item.key ? 'bg-amber-700 text-white' : 'border border-stone-200 bg-white text-stone-600 hover:bg-stone-100'}`}>{item.label}</button>)}
          </div>

          <section className="max-w-5xl rounded-2xl border border-stone-200 bg-white p-6 shadow-sm">
            {mode === 'supplier' && <div className="space-y-5"><h2 className="text-lg font-bold">Tambah Supplier</h2><div className="grid gap-4 md:grid-cols-2">{[
              ['code', 'Kode Supplier', 'text'], ['name', 'Nama Supplier', 'text'], ['phone', 'Nomor Telepon', 'text'], ['email', 'Email', 'email'], ['payment_terms_days', 'Termin Pembayaran (hari)', 'number'],
            ].map(([name, label, type]) => <label key={name}><span className="mb-1 block text-xs font-bold text-stone-500">{label}</span><input type={type} value={supplierForm[name as keyof typeof supplierForm]} onChange={e => setSupplierForm(v => ({ ...v, [name]: e.target.value }))} className="w-full rounded-xl border border-stone-200 px-3 py-2.5" /></label>)}<label className="md:col-span-2"><span className="mb-1 block text-xs font-bold text-stone-500">Alamat</span><textarea value={supplierForm.address} onChange={e => setSupplierForm(v => ({ ...v, address: e.target.value }))} rows={3} className="w-full rounded-xl border border-stone-200 px-3 py-2.5" /></label></div><button disabled={saving} onClick={() => void submit()} className="rounded-xl bg-stone-900 px-5 py-2.5 text-sm font-bold text-white disabled:opacity-50">{saving ? 'Menyimpan…' : 'Simpan Supplier'}</button></div>}

            {mode === 'purchase-order' && <div className="space-y-6"><div><h2 className="text-lg font-bold">Purchase Order</h2><p className="text-sm text-stone-500">Pilih master data; field ID dan JSON tidak diperlukan.</p></div><div className="grid gap-4 md:grid-cols-2"><label><span className="mb-1 block text-xs font-bold text-stone-500">Supplier</span><select value={poForm.supplier_id} onChange={e => setPoForm(v => ({ ...v, supplier_id: e.target.value }))} className="w-full rounded-xl border border-stone-200 px-3 py-2.5"><option value="">Pilih supplier…</option>{suppliers.map(item => <option key={item.id} value={item.id}>{item.label}{item.meta ? ` · ${item.meta}` : ''}</option>)}</select></label><label><span className="mb-1 block text-xs font-bold text-stone-500">Warehouse</span><select value={poForm.warehouse_id} onChange={e => setPoForm(v => ({ ...v, warehouse_id: e.target.value }))} className="w-full rounded-xl border border-stone-200 px-3 py-2.5"><option value="">Pilih warehouse…</option>{warehouseOptions.map(item => <option key={item.id} value={item.id}>{item.label}{item.meta ? ` · ${item.meta}` : ''}</option>)}</select></label><label><span className="mb-1 block text-xs font-bold text-stone-500">Tanggal Diharapkan</span><input type="date" value={poForm.expected_date} onChange={e => setPoForm(v => ({ ...v, expected_date: e.target.value }))} className="w-full rounded-xl border border-stone-200 px-3 py-2.5" /></label></div><div className="rounded-2xl border border-stone-200 p-4"><div className="mb-3 flex items-center justify-between"><h3 className="font-bold">Barang</h3><button onClick={() => setLines(v => [...v, emptyLine()])} className="rounded-lg border border-stone-200 px-3 py-1.5 text-xs font-bold">+ Tambah Barang</button></div><div className="space-y-3">{lines.map((line, index) => <div key={index} className="grid gap-3 md:grid-cols-[2fr_1fr_1fr_auto] items-end"><label><span className="mb-1 block text-xs text-stone-500">Produk</span><select value={line.product_id} onChange={e => updateLine(index, { product_id: e.target.value, unit_cost: String(selectedProduct(e.target.value)?.price ?? '') })} className="w-full rounded-xl border border-stone-200 px-3 py-2.5"><option value="">Pilih produk…</option>{products.map(item => <option key={item.id} value={item.id}>{item.label}{item.meta ? ` · ${item.meta}` : ''}</option>)}</select></label><label><span className="mb-1 block text-xs text-stone-500">Qty</span><input type="number" min="0.01" value={line.quantity} onChange={e => updateLine(index, { quantity: e.target.value })} className="w-full rounded-xl border border-stone-200 px-3 py-2.5" /></label><label><span className="mb-1 block text-xs text-stone-500">Harga</span><input type="number" min="0" value={line.unit_cost ?? ''} onChange={e => updateLine(index, { unit_cost: e.target.value })} className="w-full rounded-xl border border-stone-200 px-3 py-2.5" /></label><button disabled={lines.length === 1} onClick={() => setLines(v => v.filter((_, i) => i !== index))} className="rounded-xl border border-red-200 px-3 py-2.5 text-xs font-bold text-red-600 disabled:opacity-40">Hapus</button></div>)}</div><div className="mt-4 text-right"><span className="text-sm text-stone-500">Estimasi total </span><span className="text-lg font-bold">{money(totalLines)}</span></div></div><button disabled={saving} onClick={() => void submit()} className="rounded-xl bg-stone-900 px-5 py-2.5 text-sm font-bold text-white disabled:opacity-50">{saving ? 'Menyimpan…' : 'Simpan Purchase Order'}</button></div>}

            {mode === 'sales-order' && <div className="space-y-6"><div><h2 className="text-lg font-bold">Sales Order</h2><p className="text-sm text-stone-500">Harga produk diisi otomatis dari master dan bisa dikoreksi sesuai permission.</p></div><div className="grid gap-4 md:grid-cols-2"><label><span className="mb-1 block text-xs font-bold text-stone-500">Customer</span><select value={salesForm.customer_id} onChange={e => setSalesForm(v => ({ ...v, customer_id: e.target.value }))} className="w-full rounded-xl border border-stone-200 px-3 py-2.5"><option value="">Customer Walk-in</option>{customers.map(item => <option key={item.id} value={item.id}>{item.label}{item.meta ? ` · ${item.meta}` : ''}</option>)}</select></label><label><span className="mb-1 block text-xs font-bold text-stone-500">Warehouse</span><select value={salesForm.warehouse_id} onChange={e => setSalesForm(v => ({ ...v, warehouse_id: e.target.value }))} className="w-full rounded-xl border border-stone-200 px-3 py-2.5"><option value="">Pilih warehouse…</option>{warehouseOptions.map(item => <option key={item.id} value={item.id}>{item.label}</option>)}</select></label></div><div className="rounded-2xl border border-stone-200 p-4"><div className="mb-3 flex items-center justify-between"><h3 className="font-bold">Barang</h3><button onClick={() => setLines(v => [...v, emptyLine()])} className="rounded-lg border border-stone-200 px-3 py-1.5 text-xs font-bold">+ Tambah Barang</button></div>{lines.map((line, index) => <div key={index} className="mb-3 grid gap-3 md:grid-cols-[2fr_1fr_1fr_auto] items-end"><label><span className="mb-1 block text-xs text-stone-500">Produk</span><select value={line.product_id} onChange={e => updateLine(index, { product_id: e.target.value, unit_price: String(selectedProduct(e.target.value)?.price ?? '') })} className="w-full rounded-xl border border-stone-200 px-3 py-2.5"><option value="">Pilih produk…</option>{products.map(item => <option key={item.id} value={item.id}>{item.label}{item.meta ? ` · ${item.meta}` : ''}</option>)}</select></label><label><span className="mb-1 block text-xs text-stone-500">Qty</span><input type="number" min="0.01" value={line.quantity} onChange={e => updateLine(index, { quantity: e.target.value })} className="w-full rounded-xl border border-stone-200 px-3 py-2.5" /></label><label><span className="mb-1 block text-xs text-stone-500">Harga</span><input type="number" min="0" value={line.unit_price ?? ''} onChange={e => updateLine(index, { unit_price: e.target.value })} className="w-full rounded-xl border border-stone-200 px-3 py-2.5" /></label><button disabled={lines.length === 1} onClick={() => setLines(v => v.filter((_, i) => i !== index))} className="rounded-xl border border-red-200 px-3 py-2.5 text-xs font-bold text-red-600 disabled:opacity-40">Hapus</button></div>)}<div className="text-right font-bold">Total {money(totalLines)}</div></div><button disabled={saving} onClick={() => void submit()} className="rounded-xl bg-stone-900 px-5 py-2.5 text-sm font-bold text-white disabled:opacity-50">{saving ? 'Menyimpan…' : 'Simpan Sales Order'}</button></div>}

            {mode === 'cash-book' && <div className="space-y-5"><div><h2 className="text-lg font-bold">Cash Book</h2><p className="text-sm text-stone-500">Account dipilih dari chart of accounts. Periode opsional.</p></div><div className="grid gap-4 md:grid-cols-3"><label className="md:col-span-1"><span className="mb-1 block text-xs font-bold text-stone-500">Account</span><select value={cashForm.account_code} onChange={e => setCashForm(v => ({ ...v, account_code: e.target.value }))} className="w-full rounded-xl border border-stone-200 px-3 py-2.5"><option value="">Account pertama / default</option>{accounts.map(item => <option key={item.id} value={item.label.split(' · ')[0]}>{item.label}</option>)}</select></label><label><span className="mb-1 block text-xs text-stone-500">Dari</span><input type="date" value={cashForm.from} onChange={e => setCashForm(v => ({ ...v, from: e.target.value }))} className="w-full rounded-xl border border-stone-200 px-3 py-2.5" /></label><label><span className="mb-1 block text-xs text-stone-500">Sampai</span><input type="date" value={cashForm.to} onChange={e => setCashForm(v => ({ ...v, to: e.target.value }))} className="w-full rounded-xl border border-stone-200 px-3 py-2.5" /></label></div><button disabled={saving} onClick={() => void submit()} className="w-full rounded-xl bg-stone-900 py-3 font-bold text-white">{saving ? 'Memuat…' : 'Tampilkan Cash Book'}</button>{cashResult && <div className="rounded-2xl bg-stone-50 p-5"><div className="text-sm text-stone-500">Account</div><div className="font-bold">{String(cashResult.account_code ?? '-')}</div><div className="mt-4 text-sm text-stone-500">Book Balance</div><div className="text-2xl font-bold">{money(Number(cashResult.book_balance ?? 0))}</div></div>}</div>}

            {mode === 'journal' && <div className="space-y-5"><div><h2 className="text-lg font-bold">Journal Entry</h2><p className="text-sm text-stone-500">Baris debit/kredit dikelola dari UI dan harus balance sebelum dikirim.</p></div><div className="grid gap-4 md:grid-cols-3"><label><span className="mb-1 block text-xs text-stone-500">Tanggal</span><input type="date" value={journalForm.journal_date} onChange={e => setJournalForm(v => ({ ...v, journal_date: e.target.value }))} className="w-full rounded-xl border border-stone-200 px-3 py-2.5" /></label><label><span className="mb-1 block text-xs font-bold text-stone-500">Reference</span><input value={journalForm.reference} onChange={e => setJournalForm(v => ({ ...v, reference: e.target.value }))} className="w-full rounded-xl border border-stone-200 px-3 py-2.5" /></label><label><span className="mb-1 block text-xs font-bold text-stone-500">Description</span><input value={journalForm.description} onChange={e => setJournalForm(v => ({ ...v, description: e.target.value }))} className="w-full rounded-xl border border-stone-200 px-3 py-2.5" /></label></div><div className="rounded-2xl border border-stone-200 p-4"><div className="mb-3 flex items-center justify-between"><h3 className="font-bold">Journal Lines</h3><button onClick={() => setJournalLines(v => [...v, { account_id: '', debit: '', credit: '' }])} className="rounded-lg border border-stone-200 px-3 py-1.5 text-xs font-bold">+ Tambah Baris</button></div>{journalLines.map((line, index) => <div key={index} className="mb-3 grid gap-3 md:grid-cols-[2fr_1fr_1fr_auto] items-end"><label><span className="mb-1 block text-xs text-stone-500">Account</span><select value={line.account_id} onChange={e => setJournalLines(v => v.map((item, i) => i === index ? { ...item, account_id: e.target.value } : item))} className="w-full rounded-xl border border-stone-200 px-3 py-2.5"><option value="">Pilih account…</option>{accounts.map(item => <option key={item.id} value={item.id}>{item.label}</option>)}</select></label><label><span className="mb-1 block text-xs text-stone-500">Debit</span><input type="number" min="0" value={line.debit} onChange={e => setJournalLines(v => v.map((item, i) => i === index ? { ...item, debit: e.target.value, credit: e.target.value ? '' : item.credit } : item))} className="w-full rounded-xl border border-stone-200 px-3 py-2.5" /></label><label><span className="mb-1 block text-xs text-stone-500">Credit</span><input type="number" min="0" value={line.credit} onChange={e => setJournalLines(v => v.map((item, i) => i === index ? { ...item, credit: e.target.value, debit: e.target.value ? '' : item.debit } : item))} className="w-full rounded-xl border border-stone-200 px-3 py-2.5" /></label><button disabled={journalLines.length <= 2} onClick={() => setJournalLines(v => v.filter((_, i) => i !== index))} className="rounded-xl border border-red-200 px-3 py-2.5 text-xs font-bold text-red-600 disabled:opacity-40">Hapus</button></div>)}<div className="flex flex-wrap justify-end gap-6 border-t border-stone-100 pt-4 text-sm"><span>Debit <b>{money(journalLines.reduce((s, x) => s + Number(x.debit || 0), 0))}</b></span><span>Credit <b>{money(journalLines.reduce((s, x) => s + Number(x.credit || 0), 0))}</b></span></div></div><button disabled={saving} onClick={() => void submit()} className="rounded-xl bg-stone-900 px-5 py-2.5 text-sm font-bold text-white">{saving ? 'Menyimpan…' : 'Simpan Journal'}</button></div>}
          </section>
        </main>
      </div>
    </div>
  );
}

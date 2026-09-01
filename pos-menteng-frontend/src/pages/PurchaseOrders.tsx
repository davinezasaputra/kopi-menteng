import { useEffect, useMemo, useState } from 'react';
import AdminSidebar from '../components/AdminSidebar';
import api from '../core/api/client';
import { extractRows } from '../core/api/normalize';
import toast from 'react-hot-toast';

type Supplier = { id: string; name: string; code?: string };
type Product = { id: string; name: string; sku?: string; price: number };
type Warehouse = { id: string; label: string };
type Line = { productId: string; quantity: number; unitCost: number };

const blankLine = (): Line => ({ productId: '', quantity: 1, unitCost: 0 });
const money = (value: number) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value || 0);

export default function PurchaseOrders() {
  const [suppliers, setSuppliers] = useState<Supplier[]>([]);
  const [products, setProducts] = useState<Product[]>([]);
  const [warehouses, setWarehouses] = useState<Warehouse[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [supplierId, setSupplierId] = useState('');
  const [warehouseId, setWarehouseId] = useState('');
  const [expectedDate, setExpectedDate] = useState('');
  const [discountRate, setDiscountRate] = useState(0);
  const [taxRate, setTaxRate] = useState(0);
  const [notes, setNotes] = useState('');
  const [lines, setLines] = useState<Line[]>([blankLine()]);

  useEffect(() => {
    let mounted = true;
    const loadMasterData = async () => {
      setLoading(true);
      try {
        const [supplierRes, productRes, balanceRes] = await Promise.all([
          api.get('/purchasing/suppliers'), api.get('/products'), api.get('/inventory/balances'),
        ]);
        if (!mounted) return;
        const nextSuppliers = extractRows<Record<string, unknown>>(supplierRes.data).map(row => ({
          id: String(row.id ?? ''), name: String(row.name ?? row.code ?? 'Supplier'), code: row.code ? String(row.code) : undefined,
        }));
        const nextProducts = extractRows<Record<string, unknown>>(productRes.data).map(row => ({
          id: String(row.id ?? ''), name: String(row.name ?? row.sku ?? 'Produk'), sku: row.sku ? String(row.sku) : undefined,
          price: Number(row.price ?? row.selling_price ?? row.cost ?? 0),
        }));
        const seen = new Set<string>();
        const nextWarehouses: Warehouse[] = [];
        extractRows<Record<string, unknown>>(balanceRes.data).forEach(row => {
          const id = String(row.warehouse_id ?? '');
          if (!id || seen.has(id)) return;
          seen.add(id); nextWarehouses.push({ id, label: `Warehouse ${id}` });
        });
        setSuppliers(nextSuppliers); setProducts(nextProducts); setWarehouses(nextWarehouses);
        if (nextSuppliers[0]) setSupplierId(nextSuppliers[0].id);
        if (nextWarehouses[0]) setWarehouseId(nextWarehouses[0].id);
      } catch (error) {
        console.error('Gagal memuat master Purchase Order', error); toast.error('Master data Purchase Order belum dapat dimuat.');
      } finally { if (mounted) setLoading(false); }
    };
    void loadMasterData();
    return () => { mounted = false; };
  }, []);

  const subtotal = useMemo(() => lines.reduce((sum, line) => sum + Math.max(0, line.quantity) * Math.max(0, line.unitCost), 0), [lines]);
  const discountAmount = useMemo(() => subtotal * Math.max(0, Math.min(100, discountRate)) / 100, [subtotal, discountRate]);
  const taxableAmount = Math.max(0, subtotal - discountAmount);
  const taxAmount = useMemo(() => taxableAmount * Math.max(0, Math.min(100, taxRate)) / 100, [taxableAmount, taxRate]);
  const grandTotal = taxableAmount + taxAmount;

  const updateLine = (index: number, patch: Partial<Line>) => {
    setLines(current => current.map((line, lineIndex) => {
      if (lineIndex !== index) return line;
      const next = { ...line, ...patch };
      if (patch.productId !== undefined) {
        const product = products.find(item => item.id === patch.productId);
        if (product) next.unitCost = product.price;
      }
      return next;
    }));
  };

  const submit = async () => {
    const validLines = lines.filter(line => line.productId && line.quantity > 0 && line.unitCost >= 0);
    if (!supplierId) return toast.error('Supplier wajib dipilih.');
    if (!warehouseId) return toast.error('Warehouse wajib dipilih.');
    if (!validLines.length) return toast.error('Tambahkan minimal satu barang.');
    setSaving(true);
    try {
      await api.post('/purchasing/orders', {
        supplier_id: Number(supplierId), warehouse_id: Number(warehouseId), expected_date: expectedDate || undefined,
        discount_amount: Number(discountAmount.toFixed(2)), tax_amount: Number(taxAmount.toFixed(2)), notes: notes || undefined,
        items: validLines.map(line => ({ product_id: line.productId, quantity: Number(line.quantity), unit_cost: Number(line.unitCost.toFixed(2)) })),
      });
      toast.success('Purchase Order berhasil dibuat.');
      setLines([blankLine()]); setDiscountRate(0); setTaxRate(0); setNotes(''); setExpectedDate('');
    } catch (error) {
      const message = error && typeof error === 'object' && 'response' in error ? String((error as { response?: { data?: { message?: string } } }).response?.data?.message ?? '') : '';
      toast.error(message || 'Purchase Order gagal dibuat.');
    } finally { setSaving(false); }
  };

  return (
    <div className="flex h-screen w-full bg-stone-50 font-sans text-stone-800">
      <AdminSidebar activePage="purchasing-orders" />
      <div className="flex min-w-0 flex-1 flex-col overflow-hidden">
        <header className="border-b border-stone-200 bg-white px-8 py-5 shadow-sm">
          <div className="text-xs font-bold uppercase tracking-[0.18em] text-amber-700">Purchasing</div>
          <h1 className="mt-1 text-2xl font-bold text-stone-900">Purchase Order</h1>
          <p className="mt-1 text-sm text-stone-500">Form bisnis tanpa JSON dengan harga, subtotal, diskon, PPN, dan grand total otomatis.</p>
        </header>
        <main className="min-h-0 flex-1 overflow-y-auto p-6 lg:p-8">
          {loading ? <div className="rounded-2xl border border-stone-200 bg-white p-10 text-center text-sm text-stone-500">Memuat master data…</div> :
            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
              <section className="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm">
                <div className="grid gap-4 md:grid-cols-2">
                  <label><span className="mb-1 block text-xs font-bold text-stone-500">Supplier</span><select value={supplierId} onChange={e => setSupplierId(e.target.value)} className="w-full rounded-xl border border-stone-200 px-3 py-2.5"><option value="">Pilih supplier…</option>{suppliers.map(item => <option key={item.id} value={item.id}>{item.name}{item.code ? ` · ${item.code}` : ''}</option>)}</select></label>
                  <label><span className="mb-1 block text-xs font-bold text-stone-500">Warehouse</span><select value={warehouseId} onChange={e => setWarehouseId(e.target.value)} className="w-full rounded-xl border border-stone-200 px-3 py-2.5"><option value="">Pilih warehouse…</option>{warehouses.map(item => <option key={item.id} value={item.id}>{item.label}</option>)}</select></label>
                  <label><span className="mb-1 block text-xs font-bold text-stone-500">Tanggal Diharapkan</span><input type="date" value={expectedDate} onChange={e => setExpectedDate(e.target.value)} className="w-full rounded-xl border border-stone-200 px-3 py-2.5" /></label>
                </div>
                <div className="mt-6 rounded-2xl border border-stone-200 p-4">
                  <div className="mb-4 flex items-center justify-between"><div><h2 className="font-bold text-stone-900">Daftar Barang</h2><p className="text-xs text-stone-500">Pilih produk. Harga master diisi otomatis dan tetap bisa disesuaikan.</p></div><button onClick={() => setLines(current => [...current, blankLine()])} className="rounded-xl border border-stone-200 px-3 py-2 text-xs font-bold">+ Tambah Barang</button></div>
                  <div className="space-y-3">{lines.map((line, index) => { const product = products.find(item => item.id === line.productId); return <div key={index} className="grid gap-3 rounded-xl bg-stone-50 p-3 md:grid-cols-[2fr_0.8fr_1fr_1fr_auto] md:items-end">
                    <label><span className="mb-1 block text-[11px] font-bold text-stone-500">Produk</span><select value={line.productId} onChange={e => updateLine(index, { productId: e.target.value })} className="w-full rounded-lg border border-stone-200 bg-white px-3 py-2 text-sm"><option value="">Pilih produk…</option>{products.map(item => <option key={item.id} value={item.id}>{item.name}{item.sku ? ` · ${item.sku}` : ''}</option>)}</select></label>
                    <label><span className="mb-1 block text-[11px] font-bold text-stone-500">Qty</span><input min="1" type="number" value={line.quantity} onChange={e => updateLine(index, { quantity: Math.max(1, Number(e.target.value) || 1) })} className="w-full rounded-lg border border-stone-200 bg-white px-3 py-2 text-sm" /></label>
                    <label><span className="mb-1 block text-[11px] font-bold text-stone-500">Harga Beli</span><input min="0" type="number" value={line.unitCost} onChange={e => updateLine(index, { unitCost: Math.max(0, Number(e.target.value) || 0) })} className="w-full rounded-lg border border-stone-200 bg-white px-3 py-2 text-sm" /></label>
                    <div className="rounded-lg bg-white px-3 py-2"><div className="text-[11px] font-bold text-stone-400">Jumlah</div><div className="mt-1 text-sm font-bold text-stone-900">{money(line.quantity * line.unitCost)}</div>{product && <div className="text-[10px] text-stone-400">Master: {money(product.price)}</div>}</div>
                    <button disabled={lines.length === 1} onClick={() => setLines(current => current.filter((_, lineIndex) => lineIndex !== index))} className="rounded-lg px-2 py-2 text-xs font-bold text-red-600 disabled:opacity-30">Hapus</button>
                  </div>; })}</div>
                </div>
                <label className="mt-6 block"><span className="mb-1 block text-xs font-bold text-stone-500">Catatan</span><textarea value={notes} onChange={e => setNotes(e.target.value)} rows={3} className="w-full rounded-xl border border-stone-200 px-3 py-2.5" placeholder="Catatan untuk supplier…" /></label>
              </section>
              <aside className="h-fit rounded-2xl border border-stone-200 bg-white p-6 shadow-sm"><h2 className="text-lg font-bold text-stone-900">Ringkasan PO</h2><div className="mt-5 space-y-4">
                <label className="block"><span className="mb-1 block text-xs font-bold text-stone-500">Diskon</span><div className="flex items-center gap-2"><input min="0" max="100" type="number" value={discountRate} onChange={e => setDiscountRate(Math.max(0, Math.min(100, Number(e.target.value) || 0)))} className="w-full rounded-xl border border-stone-200 px-3 py-2.5" /><span className="text-sm font-bold">%</span></div></label>
                <label className="block"><span className="mb-1 block text-xs font-bold text-stone-500">PPN</span><div className="flex items-center gap-2"><input min="0" max="100" type="number" value={taxRate} onChange={e => setTaxRate(Math.max(0, Math.min(100, Number(e.target.value) || 0)))} className="w-full rounded-xl border border-stone-200 px-3 py-2.5" /><span className="text-sm font-bold">%</span></div></label>
                <div className="border-t border-stone-200 pt-4 text-sm"><div className="flex justify-between py-1.5"><span className="text-stone-500">Subtotal</span><span className="font-semibold">{money(subtotal)}</span></div><div className="flex justify-between py-1.5"><span className="text-stone-500">Diskon</span><span className="font-semibold">- {money(discountAmount)}</span></div><div className="flex justify-between py-1.5"><span className="text-stone-500">Dasar PPN</span><span className="font-semibold">{money(taxableAmount)}</span></div><div className="flex justify-between py-1.5"><span className="text-stone-500">PPN</span><span className="font-semibold">{money(taxAmount)}</span></div><div className="mt-3 flex justify-between border-t border-stone-200 pt-4"><span className="font-bold text-stone-900">Grand Total</span><span className="text-lg font-extrabold text-amber-700">{money(grandTotal)}</span></div></div>
                <button disabled={saving} onClick={() => void submit()} className="w-full rounded-xl bg-stone-900 px-5 py-3 text-sm font-bold text-white disabled:opacity-50">{saving ? 'Menyimpan…' : 'Simpan Purchase Order'}</button>
              </div></aside>
            </div>}
        </main>
      </div>
    </div>
  );
}

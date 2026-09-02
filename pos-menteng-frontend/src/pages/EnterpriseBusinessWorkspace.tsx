import { useEffect, useMemo, useState } from 'react';
import AdminSidebar from '../components/AdminSidebar';
import api from '../core/api/client';
import { extractRows } from '../core/api/normalize';
import { can } from '../core/auth/permissions';
import toast from 'react-hot-toast';

type Row = Record<string, unknown>;
type Option = { id: string; label: string; meta?: string };
type Line = { productId: string; quantity: string; unitPrice: string };
type Workspace = 'purchasing' | 'sales' | 'finance';

type Resource = {
  key: string;
  label: string;
  endpoint: string;
  permission: string;
  kind: 'list' | 'requisition' | 'purchase-order' | 'goods-receipt' | 'supplier-invoice' | 'supplier-payment' | 'sales-order' | 'fulfillment' | 'sales-invoice' | 'customer-payment' | 'cash-book' | 'period';
  createPermission?: string;
  createEndpoint?: string;
};

const RESOURCES: Record<Workspace, Resource[]> = {
  purchasing: [
    { key: 'suppliers', label: 'Suppliers', endpoint: '/purchasing/suppliers', permission: 'purchasing.supplier.view', kind: 'list' },
    { key: 'requisitions', label: 'Requisitions', endpoint: '/purchasing/requisitions', permission: 'purchasing.requisition.view', createPermission: 'purchasing.requisition.create', createEndpoint: '/purchasing/requisitions', kind: 'requisition' },
    { key: 'orders', label: 'Purchase Orders', endpoint: '/purchasing/orders', permission: 'purchasing.order.view', createPermission: 'purchasing.order.create', createEndpoint: '/purchasing/orders', kind: 'purchase-order' },
    { key: 'goods-receipts', label: 'Goods Receipts', endpoint: '/purchasing/goods-receipts', permission: 'purchasing.receipt.view', createPermission: 'purchasing.receipt.create', createEndpoint: '/purchasing/goods-receipts', kind: 'goods-receipt' },
    { key: 'supplier-invoices', label: 'Supplier Invoices', endpoint: '/purchasing/invoices', permission: 'purchasing.ap.view', createPermission: 'purchasing.ap.create', createEndpoint: '/purchasing/invoices', kind: 'supplier-invoice' },
    { key: 'supplier-payments', label: 'Supplier Payments', endpoint: '/purchasing/payments', permission: 'purchasing.ap.view', createPermission: 'purchasing.ap.pay', createEndpoint: '/purchasing/payments', kind: 'supplier-payment' },
  ],
  sales: [
    { key: 'orders', label: 'Sales Orders', endpoint: '/sales/orders', permission: 'sales.order.view', createPermission: 'sales.order.create', createEndpoint: '/sales/orders', kind: 'sales-order' },
    { key: 'fulfillments', label: 'Fulfillments', endpoint: '/sales/fulfillments', permission: 'sales.fulfillment.view', createPermission: 'sales.fulfillment.create', createEndpoint: '/sales/fulfillments', kind: 'fulfillment' },
    { key: 'invoices', label: 'Sales Invoices', endpoint: '/sales/invoices', permission: 'sales.invoice.view', createPermission: 'sales.invoice.create', createEndpoint: '/sales/invoices', kind: 'sales-invoice' },
    { key: 'payments', label: 'Customer Payments', endpoint: '/sales/payments', permission: 'sales.payment.view', createPermission: 'sales.payment.create', createEndpoint: '/sales/payments', kind: 'customer-payment' },
    { key: 'receivables', label: 'Receivables', endpoint: '/sales/receivables', permission: 'sales.receivable.view', kind: 'list' },
  ],
  finance: [
    { key: 'periods', label: 'Fiscal Periods', endpoint: '/finance/periods', permission: 'accounting.fiscal_period.view', createPermission: 'accounting.fiscal_period.manage', createEndpoint: '/finance/periods', kind: 'period' },
    { key: 'trial-balance', label: 'Trial Balance', endpoint: '/finance/reports/trial-balance', permission: 'accounting.report.view', kind: 'list' },
    { key: 'profit-loss', label: 'Profit & Loss', endpoint: '/finance/reports/profit-loss', permission: 'accounting.report.view', kind: 'list' },
    { key: 'balance-sheet', label: 'Balance Sheet', endpoint: '/finance/reports/balance-sheet', permission: 'accounting.report.view', kind: 'list' },
    { key: 'cash-book', label: 'Cash Book', endpoint: '/finance/cash-book', permission: 'accounting.report.view', kind: 'cash-book' },
    { key: 'reconciliations', label: 'Reconciliations', endpoint: '/finance/reconciliations', permission: 'accounting.reconciliation.view', createPermission: 'accounting.reconciliation.create', createEndpoint: '/finance/reconciliations', kind: 'list' },
    { key: 'accounts', label: 'ERP Accounts', endpoint: '/erp/accounting/accounts', permission: 'accounting.erp_account.view', kind: 'list' },
    { key: 'journals', label: 'ERP Journals', endpoint: '/erp/accounting/journals', permission: 'accounting.erp_journal.view', kind: 'list' },
  ],
};

const getLabel = (row: Row, keys: string[]) => {
  for (const key of keys) {
    const value = row[key];
    if (value !== undefined && value !== null && String(value) !== '') return String(value);
  }
  return `#${row.id ?? ''}`;
};

function optionRows(rows: Row[], keys: string[]): Option[] {
  return rows.map(row => ({ id: String(row.id), label: getLabel(row, keys), meta: typeof row.code === 'string' ? row.code : undefined }));
}

export default function EnterpriseBusinessWorkspace() {
  const [workspace, setWorkspace] = useState<Workspace>('purchasing');
  const resources = useMemo(() => RESOURCES[workspace].filter(item => can(item.permission)), [workspace]);
  const [resourceKey, setResourceKey] = useState(resources[0]?.key ?? '');
  const resource = resources.find(item => item.key === resourceKey) ?? resources[0];
  const [rows, setRows] = useState<Row[]>([]);
  const [loading, setLoading] = useState(false);
  const [showForm, setShowForm] = useState(false);
  const [error, setError] = useState('');
  const [masters, setMasters] = useState<{ suppliers: Option[]; warehouses: Option[]; products: Option[]; customers: Option[]; orders: Option[]; receipts: Option[]; invoices: Option[]; salesOrders: Option[]; fulfillments: Option[]; accounts: Option[] }>({ suppliers: [], warehouses: [], products: [], customers: [], orders: [], receipts: [], invoices: [], salesOrders: [], fulfillments: [], accounts: [] });
  const [form, setForm] = useState<Record<string, string>>({});
  const [lines, setLines] = useState<Line[]>([{ productId: '', quantity: '1', unitPrice: '' }]);

  useEffect(() => {
    if (!resources.some(item => item.key === resourceKey)) setResourceKey(resources[0]?.key ?? '');
  }, [resources, resourceKey]);

  useEffect(() => {
    const loadMasters = async () => {
      const endpoints: Array<[keyof typeof masters, string, string[]]> = [
        ['suppliers', '/purchasing/suppliers', ['name', 'code']],
        ['warehouses', '/api-placeholder', ['name', 'code']],
        ['products', '/products', ['name', 'code']],
        ['customers', '/customers', ['name', 'email']],
        ['orders', '/purchasing/orders', ['order_number', 'number']],
        ['receipts', '/purchasing/goods-receipts', ['receipt_number', 'number']],
        ['invoices', '/purchasing/invoices', ['invoice_number', 'number']],
        ['salesOrders', '/sales/orders', ['order_number', 'number']],
        ['fulfillments', '/sales/fulfillments', ['fulfillment_number', 'number']],
        ['accounts', '/erp/accounting/accounts', ['name', 'code']],
      ];
      const next = { ...masters };
      await Promise.all(endpoints.map(async ([key, endpoint, labelKeys]) => {
        if (endpoint === '/api-placeholder') return;
        try {
          const response = await api.get(endpoint);
          next[key] = optionRows(extractRows<Row>(response.data), labelKeys);
        } catch {
          next[key] = [];
        }
      }));
      try {
        const warehouseResponse = await api.get('/warehouses');
        next.warehouses = optionRows(extractRows<Row>(warehouseResponse.data), ['name', 'code']);
      } catch {
        next.warehouses = [];
      }
      setMasters(next);
    };
    void loadMasters();
  }, []);

  const load = async () => {
    if (!resource) return;
    setLoading(true);
    setError('');
    try {
      const response = await api.get(resource.endpoint);
      setRows(extractRows<Row>(response.data));
    } catch (err) {
      const message = err && typeof err === 'object' && 'response' in err ? String((err as { response?: { data?: { message?: string } } }).response?.data?.message ?? '') : '';
      setRows([]);
      setError(message || 'Data tidak dapat dimuat pada context organisasi aktif.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { void load(); }, [resource?.endpoint]);

  const resetForm = () => {
    setForm({});
    setLines([{ productId: '', quantity: '1', unitPrice: '' }]);
    setShowForm(false);
  };

  const setField = (key: string, value: string) => setForm(current => ({ ...current, [key]: value }));

  const linePayload = () => lines.filter(line => line.productId && Number(line.quantity) > 0).map(line => ({ product_id: Number(line.productId), quantity: Number(line.quantity), ...(line.unitPrice ? { unit_price: Number(line.unitPrice), unit_cost: Number(line.unitPrice), estimated_unit_cost: Number(line.unitPrice) } : {}) }));

  const submit = async () => {
    if (!resource?.createEndpoint) return;
    let payload: Record<string, unknown> = {};
    if (resource.kind === 'requisition') payload = { warehouse_id: Number(form.warehouse_id), items: linePayload().map(item => ({ product_id: item.product_id, quantity: item.quantity, estimated_unit_cost: item.estimated_unit_cost ?? null })), needed_by: form.needed_by || undefined, reason: form.reason || undefined, notes: form.notes || undefined };
    if (resource.kind === 'purchase-order') payload = { supplier_id: Number(form.supplier_id), warehouse_id: Number(form.warehouse_id), purchase_requisition_id: form.purchase_requisition_id ? Number(form.purchase_requisition_id) : undefined, expected_date: form.expected_date || undefined, discount_amount: Number(form.discount_amount || 0), tax_amount: Number(form.tax_amount || 0), items: linePayload().map(item => ({ product_id: item.product_id, quantity: item.quantity, unit_cost: item.unit_cost ?? 0 })), notes: form.notes || undefined };
    if (resource.kind === 'goods-receipt') payload = { purchase_order_id: Number(form.purchase_order_id), warehouse_id: Number(form.warehouse_id), items: linePayload().map(item => ({ purchase_order_item_id: Number(form.purchase_order_item_id), quantity: item.quantity, unit_cost: item.unit_cost ?? 0 })), notes: form.notes || undefined };
    if (resource.kind === 'supplier-invoice') payload = { goods_receipt_id: Number(form.goods_receipt_id), invoice_number: form.invoice_number, invoice_date: form.invoice_date || undefined, due_date: form.due_date || undefined, notes: form.notes || undefined };
    if (resource.kind === 'supplier-payment') payload = { supplier_invoice_id: Number(form.supplier_invoice_id), amount: Number(form.amount), method: form.method || 'bank_transfer', reference: form.reference || undefined, notes: form.notes || undefined };
    if (resource.kind === 'sales-order') payload = { customer_id: form.customer_id ? Number(form.customer_id) : undefined, warehouse_id: Number(form.warehouse_id), items: linePayload().map(item => ({ product_id: item.product_id, quantity: item.quantity, unit_price: item.unit_price ?? 0 })), discount_amount: Number(form.discount_amount || 0), tax_amount: Number(form.tax_amount || 0), notes: form.notes || undefined };
    if (resource.kind === 'fulfillment') payload = { sales_order_id: Number(form.sales_order_id), warehouse_id: Number(form.warehouse_id), items: linePayload().map(item => ({ sales_order_item_id: Number(form.sales_order_item_id), quantity: item.quantity })), notes: form.notes || undefined };
    if (resource.kind === 'sales-invoice') payload = { sales_order_id: Number(form.sales_order_id), invoice_date: form.invoice_date || undefined, due_date: form.due_date || undefined, notes: form.notes || undefined };
    if (resource.kind === 'customer-payment') payload = { sales_invoice_id: Number(form.sales_invoice_id), amount: Number(form.amount), method: form.method || 'bank_transfer', reference: form.reference || undefined, notes: form.notes || undefined };
    if (resource.kind === 'cash-book') return;
    if (resource.kind === 'period') payload = { year: Number(form.year), month: Number(form.month), notes: form.notes || undefined };
    if (resource.key === 'reconciliations') payload = { account_code: form.account_code, statement_balance: Number(form.statement_balance), adjustment_amount: Number(form.adjustment_amount || 0), notes: form.notes || undefined };

    try {
      await api.post(resource.createEndpoint, payload);
      toast.success(`${resource.label} berhasil disimpan.`);
      resetForm();
      await load();
    } catch (err) {
      const message = err && typeof err === 'object' && 'response' in err ? String((err as { response?: { data?: { message?: string } } }).response?.data?.message ?? '') : '';
      toast.error(message || `Gagal menyimpan ${resource.label}.`);
    }
  };

  const chooseWorkspace = (next: Workspace) => {
    setWorkspace(next);
    const nextResources = RESOURCES[next].filter(item => can(item.permission));
    setResourceKey(nextResources[0]?.key ?? '');
    resetForm();
  };

  const commonSelect = (label: string, key: string, options: Option[], required = false) => (
    <label className="block"><span className="mb-1 block text-xs font-bold text-stone-500">{label}{required ? ' *' : ''}</span><select value={form[key] ?? ''} onChange={e => setField(key, e.target.value)} className="w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-amber-600"><option value="">Pilih {label.toLowerCase()}...</option>{options.map(option => <option key={option.id} value={option.id}>{option.label}{option.meta ? ` · ${option.meta}` : ''}</option>)}</select></label>
  );

  const field = (label: string, key: string, type = 'text', required = false) => (
    <label className="block"><span className="mb-1 block text-xs font-bold text-stone-500">{label}{required ? ' *' : ''}</span><input type={type} value={form[key] ?? ''} onChange={e => setField(key, e.target.value)} className="w-full rounded-xl border border-stone-200 px-3 py-2.5 text-sm outline-none focus:border-amber-600" /></label>
  );

  const lineEditor = () => (
    <div className="rounded-2xl border border-stone-200 bg-white p-4 md:col-span-2">
      <div className="mb-3 flex items-center justify-between"><div><div className="font-bold text-stone-800">Item</div><div className="text-xs text-stone-500">Pilih produk dan jumlah. Payload API dibuat otomatis.</div></div><button type="button" onClick={() => setLines(current => [...current, { productId: '', quantity: '1', unitPrice: '' }])} className="rounded-lg border border-stone-200 px-3 py-1.5 text-xs font-bold">+ Tambah Item</button></div>
      <div className="space-y-3">{lines.map((line, index) => <div key={index} className="grid gap-2 md:grid-cols-[minmax(0,1fr)_120px_160px_40px]"><select value={line.productId} onChange={e => setLines(current => current.map((item, i) => i === index ? { ...item, productId: e.target.value } : item))} className="rounded-xl border border-stone-200 px-3 py-2.5 text-sm"><option value="">Pilih produk...</option>{masters.products.map(option => <option key={option.id} value={option.id}>{option.label}</option>)}</select><input type="number" min="0.0001" value={line.quantity} onChange={e => setLines(current => current.map((item, i) => i === index ? { ...item, quantity: e.target.value } : item))} placeholder="Qty" className="rounded-xl border border-stone-200 px-3 py-2.5 text-sm" /><input type="number" min="0" value={line.unitPrice} onChange={e => setLines(current => current.map((item, i) => i === index ? { ...item, unitPrice: e.target.value } : item))} placeholder="Harga" className="rounded-xl border border-stone-200 px-3 py-2.5 text-sm" /><button type="button" onClick={() => setLines(current => current.length === 1 ? current : current.filter((_, i) => i !== index))} className="rounded-xl border border-red-200 text-red-600">×</button></div>)}</div>
    </div>
  );

  const renderForm = () => {
    if (!resource?.createEndpoint || !resource.createPermission || !can(resource.createPermission)) return null;
    if (resource.kind === 'requisition') return <div className="grid gap-3 md:grid-cols-2">{commonSelect('Warehouse', 'warehouse_id', masters.warehouses, true)}{field('Needed By', 'needed_by', 'date')}{field('Reason', 'reason')}{field('Notes', 'notes')} {lineEditor()}</div>;
    if (resource.kind === 'purchase-order') return <div className="grid gap-3 md:grid-cols-2">{commonSelect('Supplier', 'supplier_id', masters.suppliers, true)}{commonSelect('Warehouse', 'warehouse_id', masters.warehouses, true)}{commonSelect('Requisition', 'purchase_requisition_id', masters.orders)}{field('Expected Date', 'expected_date', 'date')}{field('Discount', 'discount_amount', 'number')}{field('Tax', 'tax_amount', 'number')}{field('Notes', 'notes')}{lineEditor()}</div>;
    if (resource.kind === 'goods-receipt') return <div className="grid gap-3 md:grid-cols-2">{commonSelect('Purchase Order', 'purchase_order_id', masters.orders, true)}{commonSelect('Warehouse', 'warehouse_id', masters.warehouses, true)}{field('Purchase Order Item', 'purchase_order_item_id', 'number', true)}{field('Notes', 'notes')}{lineEditor()}</div>;
    if (resource.kind === 'supplier-invoice') return <div className="grid gap-3 md:grid-cols-2">{commonSelect('Goods Receipt', 'goods_receipt_id', masters.receipts, true)}{field('Invoice Number', 'invoice_number', 'text', true)}{field('Invoice Date', 'invoice_date', 'date')}{field('Due Date', 'due_date', 'date')}{field('Notes', 'notes')}</div>;
    if (resource.kind === 'supplier-payment') return <div className="grid gap-3 md:grid-cols-2">{commonSelect('Supplier Invoice', 'supplier_invoice_id', masters.invoices, true)}{field('Amount', 'amount', 'number', true)}{<label className="block"><span className="mb-1 block text-xs font-bold text-stone-500">Method</span><select value={form.method ?? 'bank_transfer'} onChange={e => setField('method', e.target.value)} className="w-full rounded-xl border border-stone-200 px-3 py-2.5 text-sm"><option value="bank_transfer">Bank Transfer</option><option value="cash">Cash</option><option value="giro">Giro</option><option value="other">Other</option></select></label>}{field('Reference', 'reference')}{field('Notes', 'notes')}</div>;
    if (resource.kind === 'sales-order') return <div className="grid gap-3 md:grid-cols-2">{commonSelect('Customer', 'customer_id', masters.customers)}{commonSelect('Warehouse', 'warehouse_id', masters.warehouses, true)}{field('Discount', 'discount_amount', 'number')}{field('Tax', 'tax_amount', 'number')}{field('Notes', 'notes')}{lineEditor()}</div>;
    if (resource.kind === 'fulfillment') return <div className="grid gap-3 md:grid-cols-2">{commonSelect('Sales Order', 'sales_order_id', masters.salesOrders, true)}{commonSelect('Warehouse', 'warehouse_id', masters.warehouses, true)}{field('Sales Order Item', 'sales_order_item_id', 'number', true)}{field('Notes', 'notes')}{lineEditor()}</div>;
    if (resource.kind === 'sales-invoice') return <div className="grid gap-3 md:grid-cols-2">{commonSelect('Sales Order', 'sales_order_id', masters.salesOrders, true)}{field('Invoice Date', 'invoice_date', 'date')}{field('Due Date', 'due_date', 'date')}{field('Notes', 'notes')}</div>;
    if (resource.kind === 'customer-payment') return <div className="grid gap-3 md:grid-cols-2">{commonSelect('Sales Invoice', 'sales_invoice_id', masters.invoices, true)}{field('Amount', 'amount', 'number', true)}{field('Method', 'method')}{field('Reference', 'reference')}{field('Notes', 'notes')}</div>;
    if (resource.kind === 'period') return <div className="grid gap-3 md:grid-cols-2">{field('Year', 'year', 'number', true)}{field('Month', 'month', 'number', true)}{field('Notes', 'notes')}</div>;
    if (resource.key === 'reconciliations') return <div className="grid gap-3 md:grid-cols-2">{commonSelect('Account', 'account_id', masters.accounts, true)}{field('Account Code', 'account_code', 'text', true)}{field('Statement Balance', 'statement_balance', 'number', true)}{field('Adjustment', 'adjustment_amount', 'number')}{field('Notes', 'notes')}</div>;
    return null;
  };

  const renderCashBookControls = () => resource?.kind === 'cash-book' ? <div className="mb-4 grid gap-3 rounded-2xl border border-stone-200 bg-stone-50 p-4 md:grid-cols-3"><div className="md:col-span-1">{commonSelect('Account', 'cash_account', masters.accounts, true)}</div>{field('From', 'cash_from', 'date')}{field('To', 'cash_to', 'date')}<div className="md:col-span-3"><button onClick={() => { const code = masters.accounts.find(option => option.id === form.cash_account)?.meta || ''; const params = new URLSearchParams({ account_code: code }); if (form.cash_from) params.set('from', form.cash_from); if (form.cash_to) params.set('to', form.cash_to); void (async () => { try { const response = await api.get(`/finance/cash-book?${params.toString()}`); setRows(extractRows<Row>(response.data)); } catch { toast.error('Cash Book tidak dapat dimuat.'); } })(); }} className="rounded-xl bg-stone-900 px-4 py-2.5 text-sm font-bold text-white">Tampilkan Cash Book</button></div></div> : null;

  const labelValue = (value: unknown) => typeof value === 'object' && value !== null ? JSON.stringify(value) : String(value ?? '-');

  return <div className="flex h-screen w-full bg-stone-50 font-sans text-stone-800"><AdminSidebar activePage="operations" /><main className="min-w-0 flex-1 overflow-y-auto p-5 lg:p-8"><div className="mx-auto max-w-[1500px]"><div className="mb-6 flex flex-wrap items-center justify-between gap-4"><div><div className="text-xs font-bold uppercase tracking-[0.18em] text-amber-700">Enterprise Workspace</div><h1 className="mt-1 text-2xl font-bold text-stone-900">Business Operations</h1><p className="mt-1 text-sm text-stone-500">Form bisnis terstruktur tanpa JSON dan tanpa input ID internal.</p></div></div><div className="mb-5 flex flex-wrap gap-2">{(['purchasing','sales','finance'] as Workspace[]).map(item => <button key={item} onClick={() => chooseWorkspace(item)} className={`rounded-2xl px-5 py-3 text-sm font-bold ${workspace === item ? 'bg-amber-700 text-white' : 'border border-stone-200 bg-white text-stone-600'}`}>{item === 'purchasing' ? '🛒 Purchasing' : item === 'sales' ? '💰 Sales' : '📊 Finance'}</button>)}</div><div className="grid gap-6 xl:grid-cols-[250px_minmax(0,1fr)]"><aside className="rounded-2xl border border-stone-200 bg-white p-3 shadow-sm"><div className="px-3 pb-2 text-xs font-bold uppercase tracking-wider text-stone-400">Menu</div>{resources.map(item => <button key={item.key} onClick={() => { setResourceKey(item.key); resetForm(); }} className={`mb-1 w-full rounded-xl px-3 py-2.5 text-left text-sm font-semibold ${resource?.key === item.key ? 'bg-stone-900 text-white' : 'text-stone-600 hover:bg-stone-100'}`}>{item.label}</button>)}</aside><section className="min-w-0 rounded-2xl border border-stone-200 bg-white shadow-sm"><div className="border-b border-stone-200 p-5"><div className="flex flex-wrap items-start justify-between gap-3"><div><h2 className="text-lg font-bold">{resource?.label ?? 'Workspace'}</h2><div className="mt-1 text-xs text-stone-400">{resource?.endpoint}</div></div>{resource?.createEndpoint && resource.createPermission && can(resource.createPermission) && <button onClick={() => setShowForm(current => !current)} className="rounded-xl bg-amber-700 px-4 py-2 text-sm font-bold text-white">{showForm ? 'Tutup Form' : '+ Buat Baru'}</button>}</div></div>{showForm && <div className="border-b border-stone-200 bg-stone-50 p-5"><div className="mb-4 flex items-center justify-between"><div><h3 className="font-bold">{resource?.label}</h3><p className="text-xs text-stone-500">Field bisnis akan dibentuk menjadi payload API secara otomatis.</p></div><button onClick={resetForm} className="text-sm font-semibold text-stone-500">Batal</button></div>{renderForm()}<div className="mt-5 flex justify-end"><button onClick={() => void submit()} className="rounded-xl bg-stone-900 px-5 py-2.5 text-sm font-bold text-white">Simpan</button></div></div>}{renderCashBookControls()}{loading ? <div className="p-10 text-center text-sm text-stone-500">Memuat data...</div> : error ? <div className="m-5 rounded-2xl border border-red-200 bg-red-50 p-5 text-sm text-red-700">{error}</div> : rows.length === 0 ? <div className="p-10 text-center"><div className="text-3xl">📭</div><p className="mt-2 font-semibold">Belum ada data</p><p className="text-sm text-stone-500">Belum ada record pada scope organisasi aktif.</p></div> : <div className="divide-y divide-stone-100">{rows.slice(0,100).map((row,index) => <article key={`${String(row.id ?? index)}-${index}`} className="p-5 hover:bg-stone-50"><div className="font-semibold text-stone-900">{getLabel(row,['code','name','order_number','document_number','invoice_number','reference','status','period'])}</div><div className="mt-1 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">{Object.entries(row).slice(0,10).map(([key,value]) => <div key={key} className="rounded-xl bg-stone-50 p-2.5"><div className="text-[10px] font-bold uppercase text-stone-400">{key.replaceAll('_',' ')}</div><div className="mt-1 break-words text-xs text-stone-700">{labelValue(value)}</div></div>)}</div></article>)}</div>}</section></div></div></main></div>;
}

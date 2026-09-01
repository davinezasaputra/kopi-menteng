import { useEffect, useMemo, useState } from 'react';
import AdminSidebar from '../components/AdminSidebar';
import api from '../core/api/client';
import { extractRows, extractData } from '../core/api/normalize';
import { can, canAny } from '../core/auth/permissions';
import toast from 'react-hot-toast';

type Row = Record<string, unknown>;
type ModuleKey = 'purchasing' | 'sales' | 'finance';
type ProductOption = { id: string; label: string; price?: number };
type SelectOption = { id: string; label: string };
type LineItem = { sourceId: string; quantity: string; unitCost: string; unitPrice: string; reason: string };

type Resource = {
  key: string;
  label: string;
  endpoint: string;
  permission: string;
  createPermission?: string;
  form?: 'supplier' | 'requisition' | 'purchase-order' | 'goods-receipt' | 'supplier-invoice' | 'supplier-payment' | 'supplier-return' | 'credit-note' | 'budget' | 'purchasing-approval' | 'sales-order' | 'sales-approval' | 'fulfillment' | 'shipment' | 'sales-invoice' | 'customer-payment' | 'sales-return' | 'fiscal-period' | 'reconciliation' | 'erp-account' | 'erp-journal';
  createEndpoint?: string;
  staticParams?: Record<string, string>;
  actions?: Array<{ label: string; endpoint: (id: string) => string; permission: string; bodyField?: string; confirm?: string }>;
};

type ModuleConfig = { key: ModuleKey; label: string; icon: string; resources: Resource[] };

const modules: ModuleConfig[] = [
  {
    key: 'purchasing', label: 'Purchasing', icon: '🛒', resources: [
      { key: 'suppliers', label: 'Suppliers', endpoint: '/purchasing/suppliers', permission: 'purchasing.supplier.view', createPermission: 'purchasing.supplier.create', createEndpoint: '/purchasing/suppliers', form: 'supplier' },
      { key: 'requisitions', label: 'Requisitions', endpoint: '/purchasing/requisitions', permission: 'purchasing.requisition.view', createPermission: 'purchasing.requisition.create', createEndpoint: '/purchasing/requisitions', form: 'requisition', actions: [
        { label: 'Submit', endpoint: id => `/purchasing/requisitions/${id}/submit`, permission: 'purchasing.requisition.submit', confirm: 'Kirim requisition ini?' },
        { label: 'Cancel', endpoint: id => `/purchasing/requisitions/${id}/cancel`, permission: 'purchasing.requisition.cancel', confirm: 'Batalkan requisition ini?' },
      ] },
      { key: 'orders', label: 'Purchase Orders', endpoint: '/purchasing/orders', permission: 'purchasing.order.view', createPermission: 'purchasing.order.create', createEndpoint: '/purchasing/orders', form: 'purchase-order', actions: [
        { label: 'Submit', endpoint: id => `/purchasing/orders/${id}/submit`, permission: 'purchasing.order.submit', confirm: 'Submit purchase order?' },
        { label: 'Approve', endpoint: id => `/purchasing/orders/${id}/approve`, permission: 'purchasing.order.approve', confirm: 'Approve purchase order?' },
        { label: 'Reject', endpoint: id => `/purchasing/orders/${id}/reject`, permission: 'purchasing.order.approve', bodyField: 'reason', confirm: 'Reject purchase order?' },
        { label: 'Cancel', endpoint: id => `/purchasing/orders/${id}/cancel`, permission: 'purchasing.order.cancel', confirm: 'Cancel purchase order?' },
      ] },
      { key: 'goods-receipts', label: 'Goods Receipts', endpoint: '/purchasing/goods-receipts', permission: 'purchasing.receipt.view', createPermission: 'purchasing.receipt.create', createEndpoint: '/purchasing/goods-receipts', form: 'goods-receipt' },
      { key: 'invoices', label: 'Supplier Invoices', endpoint: '/purchasing/invoices', permission: 'purchasing.ap.view', createPermission: 'purchasing.ap.create', createEndpoint: '/purchasing/invoices', form: 'supplier-invoice' },
      { key: 'payments', label: 'Supplier Payments', endpoint: '/purchasing/payments', permission: 'purchasing.ap.view', createPermission: 'purchasing.ap.pay', createEndpoint: '/purchasing/payments', form: 'supplier-payment' },
      { key: 'returns', label: 'Supplier Returns', endpoint: '/purchasing/returns', permission: 'purchasing.return.view', createPermission: 'purchasing.return.create', createEndpoint: '/purchasing/returns', form: 'supplier-return' },
      { key: 'credit-notes', label: 'Credit Notes', endpoint: '/purchasing/credit-notes', permission: 'purchasing.credit_note.view', createPermission: 'purchasing.credit_note.create', createEndpoint: '/purchasing/credit-notes', form: 'credit-note' },
      { key: 'budgets', label: 'Budgets', endpoint: '/purchasing/budgets', permission: 'purchasing.budget.view', createPermission: 'purchasing.budget.create', createEndpoint: '/purchasing/budgets', form: 'budget' },
      { key: 'approval-matrix', label: 'Approval Matrix', endpoint: '/purchasing/approval-matrix', permission: 'purchasing.approval_matrix.view', createPermission: 'purchasing.approval_matrix.create', createEndpoint: '/purchasing/approval-matrix', form: 'purchasing-approval' },
      { key: 'dashboard-report', label: 'Dashboard Report', endpoint: '/purchasing/reports/dashboard', permission: 'purchasing.reporting.view' },
      { key: 'supplier-performance', label: 'Supplier Performance', endpoint: '/purchasing/reports/supplier-performance', permission: 'purchasing.reporting.view' },
      { key: 'ap-aging', label: 'AP Aging', endpoint: '/purchasing/reports/ap-aging', permission: 'purchasing.reporting.view' },
      { key: 'reconciliation', label: 'PO Reconciliation', endpoint: '/purchasing/reconciliation/orders', permission: 'purchasing.reconciliation.view' },
    ],
  },
  {
    key: 'sales', label: 'Sales', icon: '💰', resources: [
      { key: 'orders', label: 'Sales Orders', endpoint: '/sales/orders', permission: 'sales.order.view', createPermission: 'sales.order.create', createEndpoint: '/sales/orders', form: 'sales-order', actions: [
        { label: 'Submit', endpoint: id => `/sales/orders/${id}/submit`, permission: 'sales.order.submit', confirm: 'Submit sales order?' },
        { label: 'Approve', endpoint: id => `/sales/orders/${id}/approve`, permission: 'sales.order.approve', confirm: 'Approve sales order?' },
        { label: 'Reject', endpoint: id => `/sales/orders/${id}/reject`, permission: 'sales.order.approve', bodyField: 'reason', confirm: 'Reject sales order?' },
        { label: 'Cancel', endpoint: id => `/sales/orders/${id}/cancel`, permission: 'sales.order.cancel', confirm: 'Cancel sales order?' },
      ] },
      { key: 'approval-matrix', label: 'Approval Matrix', endpoint: '/sales/approval-matrix', permission: 'sales.approval_matrix.view', createPermission: 'sales.approval_matrix.create', createEndpoint: '/sales/approval-matrix', form: 'sales-approval' },
      { key: 'fulfillments', label: 'Fulfillments', endpoint: '/sales/fulfillments', permission: 'sales.fulfillment.view', createPermission: 'sales.fulfillment.create', createEndpoint: '/sales/fulfillments', form: 'fulfillment', actions: [
        { label: 'Pick', endpoint: id => `/sales/fulfillments/${id}/pick`, permission: 'sales.fulfillment.pick', confirm: 'Tandai fulfillment sebagai picked?' },
        { label: 'Pack', endpoint: id => `/sales/fulfillments/${id}/pack`, permission: 'sales.fulfillment.pack', confirm: 'Tandai fulfillment sebagai packed?' },
      ] },
      { key: 'shipments', label: 'Shipments', endpoint: '/sales/shipments', permission: 'sales.shipment.view', createPermission: 'sales.shipment.create', createEndpoint: '/sales/shipments', form: 'shipment' },
      { key: 'invoices', label: 'Sales Invoices', endpoint: '/sales/invoices', permission: 'sales.invoice.view', createPermission: 'sales.invoice.create', createEndpoint: '/sales/invoices', form: 'sales-invoice' },
      { key: 'receivables', label: 'Receivables', endpoint: '/sales/receivables', permission: 'sales.receivable.view' },
      { key: 'payments', label: 'Customer Payments', endpoint: '/sales/payments', permission: 'sales.payment.view', createPermission: 'sales.payment.create', createEndpoint: '/sales/payments', form: 'customer-payment' },
      { key: 'returns', label: 'Sales Returns', endpoint: '/sales/returns', permission: 'sales.return.view', createPermission: 'sales.return.create', createEndpoint: '/sales/returns', form: 'sales-return' },
      { key: 'dashboard-report', label: 'Dashboard Report', endpoint: '/sales/reports/dashboard', permission: 'sales.reporting.view' },
      { key: 'journals-report', label: 'Sales Journals', endpoint: '/sales/reports/journals', permission: 'sales.reporting.view' },
    ],
  },
  {
    key: 'finance', label: 'Finance', icon: '📊', resources: [
      { key: 'periods', label: 'Fiscal Periods', endpoint: '/finance/periods', permission: 'accounting.fiscal_period.view', createPermission: 'accounting.fiscal_period.manage', createEndpoint: '/finance/periods', form: 'fiscal-period', actions: [
        { label: 'Close', endpoint: id => `/finance/periods/${id}/close`, permission: 'accounting.period.close', confirm: 'Tutup fiscal period ini?' },
      ] },
      { key: 'trial-balance', label: 'Trial Balance', endpoint: '/finance/reports/trial-balance', permission: 'accounting.report.view' },
      { key: 'profit-loss', label: 'Profit & Loss', endpoint: '/finance/reports/profit-loss', permission: 'accounting.report.view' },
      { key: 'balance-sheet', label: 'Balance Sheet', endpoint: '/finance/reports/balance-sheet', permission: 'accounting.report.view' },
      { key: 'cash-book', label: 'Cash Book', endpoint: '/finance/cash-book', permission: 'accounting.report.view', form: 'cash-book' },
      { key: 'reconciliations', label: 'Reconciliations', endpoint: '/finance/reconciliations', permission: 'accounting.reconciliation.view', createPermission: 'accounting.reconciliation.create', createEndpoint: '/finance/reconciliations', form: 'reconciliation' },
      { key: 'accounts', label: 'ERP Accounts', endpoint: '/erp/accounting/accounts', permission: 'accounting.erp_account.view', createPermission: 'accounting.erp_account.create', createEndpoint: '/erp/accounting/accounts', form: 'erp-account' },
      { key: 'journals', label: 'ERP Journals', endpoint: '/erp/accounting/journals', permission: 'accounting.erp_journal.view', createPermission: 'accounting.erp_journal.create', createEndpoint: '/erp/accounting/journals', form: 'erp-journal' },
    ],
  },
];

function idOf(row: Row): string | null { const v = row.id; return v === undefined || v === null ? null : String(v); }
function textOf(row: Row, keys: string[]): string { for (const key of keys) { const value = row[key]; if (value !== undefined && value !== null && value !== '') return String(value); } return idOf(row) ?? '-'; }
function errorMessage(error: unknown): string { return error && typeof error === 'object' && 'response' in error ? String((error as { response?: { data?: { message?: string } } }).response?.data?.message || '') : ''; }
function formatMoney(v: unknown): string { const n = Number(v ?? 0); return Number.isFinite(n) ? new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n) : '-'; }

export default function EnterpriseOperations() {
  const accessibleModules = useMemo(() => modules.filter(m => canAny(m.resources.map(r => r.permission))), []);
  const [activeModule, setActiveModule] = useState<ModuleKey>(accessibleModules[0]?.key ?? 'finance');
  const module = useMemo(() => accessibleModules.find(m => m.key === activeModule) ?? accessibleModules[0], [accessibleModules, activeModule]);
  const visibleResources = useMemo(() => module?.resources.filter(r => can(r.permission)) ?? [], [module]);
  const [activeResourceKey, setActiveResourceKey] = useState<string>(visibleResources[0]?.key ?? '');
  const resource = visibleResources.find(r => r.key === activeResourceKey) ?? visibleResources[0];
  const [rows, setRows] = useState<Row[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [query, setQuery] = useState('');
  const [showCreate, setShowCreate] = useState(false);
  const [form, setForm] = useState<Record<string, string>>({});
  const [lineItems, setLineItems] = useState<LineItem[]>([{ sourceId: '', quantity: '1', unitCost: '', unitPrice: '', reason: '' }]);
  const [products, setProducts] = useState<ProductOption[]>([]);
  const [suppliers, setSuppliers] = useState<SelectOption[]>([]);
  const [customers, setCustomers] = useState<SelectOption[]>([]);
  const [warehouses, setWarehouses] = useState<SelectOption[]>([]);
  const [accounts, setAccounts] = useState<SelectOption[]>([]);
  const [roles, setRoles] = useState<SelectOption[]>([]);
  const [orders, setOrders] = useState<Row[]>([]);
  const [purchaseOrders, setPurchaseOrders] = useState<Row[]>([]);
  const [receipts, setReceipts] = useState<Row[]>([]);
  const [invoices, setInvoices] = useState<Row[]>([]);
  const [returns, setReturns] = useState<Row[]>([]);
  const [salesOrders, setSalesOrders] = useState<Row[]>([]);
  const [fulfillments, setFulfillments] = useState<Row[]>([]);
  const [salesInvoices, setSalesInvoices] = useState<Row[]>([]);

  const loadLookups = async () => {
    try {
      const results = await Promise.allSettled([
        api.get('/products'), api.get('/purchasing/suppliers'), api.get('/customers'), api.get('/inventory/balances'), api.get('/erp/accounting/accounts'), api.get('/v1/roles'),
        api.get('/purchasing/orders'), api.get('/purchasing/goods-receipts'), api.get('/purchasing/invoices'), api.get('/purchasing/returns'), api.get('/sales/orders'), api.get('/sales/fulfillments'), api.get('/sales/invoices'),
      ]);
      const value = (i: number) => results[i].status === 'fulfilled' ? results[i].value.data : null;
      const productRows = extractRows<Row>(value(0));
      const supplierRows = extractRows<Row>(value(1));
      const customerRows = extractRows<Row>(value(2));
      const balanceRows = extractRows<Row>(value(3));
      const accountRows = extractRows<Row>(value(4));
      const roleRows = extractRows<Row>(value(5));
      const poRows = extractRows<Row>(value(6));
      const receiptRows = extractRows<Row>(value(7));
      const invoiceRows = extractRows<Row>(value(8));
      const returnRows = extractRows<Row>(value(9));
      const salesOrderRows = extractRows<Row>(value(10));
      const fulfillmentRows = extractRows<Row>(value(11));
      const salesInvoiceRows = extractRows<Row>(value(12));
      setProducts(productRows.map(p => ({ id: String(p.id), label: textOf(p, ['name','code']), price: Number(p.price ?? p.selling_price ?? 0) })));
      setSuppliers(supplierRows.map(s => ({ id: String(s.id), label: textOf(s, ['name','code']) })));
      setCustomers(customerRows.map(c => ({ id: String(c.id), label: textOf(c, ['name','code']) })));
      const wh = new Map<string, string>();
      balanceRows.forEach(b => { const id = b.warehouse_id; if (id !== undefined && id !== null) wh.set(String(id), String((b.warehouse as Row | undefined)?.name ?? `Warehouse ${id}`)); });
      setWarehouses(Array.from(wh.entries()).map(([id, label]) => ({ id, label })));
      setAccounts(accountRows.map(a => ({ id: String(a.id), label: `${textOf(a,['code'])} · ${textOf(a,['name'])}` })));
      setRoles(roleRows.map(r => ({ id: String(r.id), label: textOf(r,['name','code']) })));
      setOrders(poRows); setPurchaseOrders(poRows); setReceipts(receiptRows); setInvoices(invoiceRows); setReturns(returnRows);
      setSalesOrders(salesOrderRows); setFulfillments(fulfillmentRows); setSalesInvoices(salesInvoiceRows);
    } catch (e) { console.error('Lookup loading failed', e); }
  };

  useEffect(() => { void loadLookups(); }, []);
  useEffect(() => { if (!visibleResources.some(r => r.key === activeResourceKey)) setActiveResourceKey(visibleResources[0]?.key ?? ''); }, [activeResourceKey, visibleResources]);

  const resetCreate = () => { setShowCreate(false); setForm({}); setLineItems([{ sourceId: '', quantity: '1', unitCost: '', unitPrice: '', reason: '' }]); };

  const load = async () => {
    if (!resource) return;
    setLoading(true); setError('');
    try {
      const params = resource.staticParams;
      const response = await api.get(resource.endpoint, { params });
      setRows(extractRows<Row>(response.data));
    } catch (e) { setRows([]); setError(errorMessage(e) || 'Data tidak dapat dimuat. Periksa permission dan organization context.'); }
    finally { setLoading(false); }
  };

  useEffect(() => { void load(); }, [resource?.endpoint, JSON.stringify(resource?.staticParams ?? {})]);

  const updateForm = (key: string, value: string) => setForm(current => ({ ...current, [key]: value }));
  const addLine = () => setLineItems(current => [...current, { sourceId: '', quantity: '1', unitCost: '', unitPrice: '', reason: '' }]);
  const removeLine = (index: number) => setLineItems(current => current.length === 1 ? current : current.filter((_, i) => i !== index));
  const updateLine = (index: number, key: keyof LineItem, value: string) => setLineItems(current => current.map((line, i) => i === index ? { ...line, [key]: value } : line));
  const selectedProduct = (id: string) => products.find(p => p.id === id);
  const applyProductDefaults = (index: number, productId: string) => { const product = selectedProduct(productId); updateLine(index, 'sourceId', productId); if (product?.price) updateLine(index, 'unitPrice', String(product.price)); };
  const payload = (): Record<string, unknown> => {
    switch (resource?.form) {
      case 'supplier': return { ...form, payment_terms_days: form.payment_terms_days ? Number(form.payment_terms_days) : undefined };
      case 'requisition': return { warehouse_id: Number(form.warehouse_id), items: lineItems.map(l => ({ product_id: l.sourceId, quantity: Number(l.quantity), estimated_unit_cost: l.unitCost ? Number(l.unitCost) : undefined })), needed_by: form.needed_by || undefined, reason: form.reason || undefined, notes: form.notes || undefined };
      case 'purchase-order': return { supplier_id: Number(form.supplier_id), warehouse_id: Number(form.warehouse_id), purchase_requisition_id: form.purchase_requisition_id ? Number(form.purchase_requisition_id) : undefined, expected_date: form.expected_date || undefined, discount_amount: Number(form.discount_amount || 0), tax_amount: Number(form.tax_amount || 0), items: lineItems.map(l => ({ product_id: l.sourceId, quantity: Number(l.quantity), unit_cost: Number(l.unitCost), discount_amount: 0, tax_amount: 0 })), notes: form.notes || undefined };
      case 'goods-receipt': { const order = purchaseOrders.find(o => String(o.id) === form.purchase_order_id); const available = ((order?.items as Row[]) || []); return { purchase_order_id: Number(form.purchase_order_id), warehouse_id: Number(form.warehouse_id), items: lineItems.map(l => ({ purchase_order_item_id: Number(l.sourceId), quantity: Number(l.quantity), unit_cost: Number(l.unitCost) })), notes: form.notes || undefined, _availableItems: available }; }
      case 'supplier-invoice': return { goods_receipt_id: Number(form.goods_receipt_id), invoice_number: form.invoice_number, invoice_date: form.invoice_date || undefined, due_date: form.due_date || undefined, notes: form.notes || undefined };
      case 'supplier-payment': return { supplier_invoice_id: Number(form.supplier_invoice_id), amount: Number(form.amount), method: form.method || 'bank_transfer', reference: form.reference || undefined, notes: form.notes || undefined };
      case 'supplier-return': return { purchase_order_id: Number(form.purchase_order_id), goods_receipt_id: Number(form.goods_receipt_id), warehouse_id: Number(form.warehouse_id), items: lineItems.map(l => ({ goods_receipt_item_id: Number(l.sourceId), quantity: Number(l.quantity) })), reason: form.reason || undefined, notes: form.notes || undefined };
      case 'credit-note': return { supplier_return_id: Number(form.supplier_return_id), credit_note_number: form.credit_note_number, amount: Number(form.amount), notes: form.notes || undefined };
      case 'budget': return { budget_year: Number(form.budget_year), allocated_amount: Number(form.allocated_amount), notes: form.notes || undefined };
      case 'purchasing-approval': return { approver_role_id: Number(form.approver_role_id), min_amount: Number(form.min_amount), max_amount: form.max_amount ? Number(form.max_amount) : undefined, priority: Number(form.priority || 1), notes: form.notes || undefined };
      case 'sales-order': return { customer_id: form.customer_id ? Number(form.customer_id) : undefined, warehouse_id: form.warehouse_id ? Number(form.warehouse_id) : undefined, items: lineItems.map(l => ({ product_id: l.sourceId, quantity: Number(l.quantity), unit_price: Number(l.unitPrice || selectedProduct(l.sourceId)?.price || 0) })), discount_amount: Number(form.discount_amount || 0), tax_amount: Number(form.tax_amount || 0), notes: form.notes || undefined };
      case 'sales-approval': return { approver_role_id: Number(form.approver_role_id), min_amount: Number(form.min_amount), max_amount: form.max_amount ? Number(form.max_amount) : undefined, notes: form.notes || undefined };
      case 'fulfillment': return { sales_order_id: Number(form.sales_order_id), warehouse_id: Number(form.warehouse_id), items: lineItems.map(l => ({ sales_order_item_id: Number(l.sourceId), quantity: Number(l.quantity) })), notes: form.notes || undefined };
      case 'shipment': return { fulfillment_id: Number(form.fulfillment_id), carrier: form.carrier || undefined, tracking_number: form.tracking_number || undefined, shipped_at: form.shipped_at || undefined, notes: form.notes || undefined };
      case 'sales-invoice': return { sales_order_id: Number(form.sales_order_id), invoice_date: form.invoice_date || undefined, due_date: form.due_date || undefined, notes: form.notes || undefined };
      case 'customer-payment': return { sales_invoice_id: Number(form.sales_invoice_id), amount: Number(form.amount), method: form.method || undefined, reference: form.reference || undefined, notes: form.notes || undefined };
      case 'sales-return': return { sales_order_id: Number(form.sales_order_id), warehouse_id: Number(form.warehouse_id), items: lineItems.map(l => ({ sales_order_item_id: Number(l.sourceId), quantity: Number(l.quantity), reason: l.reason || undefined })), reason: form.reason || undefined, notes: form.notes || undefined };
      case 'fiscal-period': return { year: Number(form.year), month: Number(form.month), notes: form.notes || undefined };
      case 'cash-book': return {};
      case 'reconciliation': return { account_code: form.account_code, statement_balance: Number(form.statement_balance), adjustment_amount: Number(form.adjustment_amount || 0), notes: form.notes || undefined };
      case 'erp-account': return { code: form.code, name: form.name, type: form.type, parent_id: form.parent_id ? Number(form.parent_id) : undefined, normal_balance: form.normal_balance || undefined };
      case 'erp-journal': return { journal_date: form.journal_date, reference: form.reference || undefined, description: form.description || undefined, lines: lineItems.map(l => ({ account_id: Number(l.sourceId), debit: Number(l.unitCost || 0), credit: Number(l.unitPrice || 0) })) };
      default: return form;
    }
  };

  const submitCreate = async () => {
    if (!resource?.createEndpoint) return;
    if (resource.form === 'cash-book') { await load(); resetCreate(); return; }
    try {
      const body = payload();
      if ('_availableItems' in body) delete body._availableItems;
      await api.post(resource.createEndpoint, body);
      toast.success(`${resource.label} berhasil dibuat.`); resetCreate(); await load(); await loadLookups();
    } catch (e) { toast.error(errorMessage(e) || 'Gagal menyimpan data.'); }
  };

  const runAction = async (row: Row, action: NonNullable<Resource['actions']>[number]) => {
    const id = idOf(row); if (!id) return toast.error('Record tidak memiliki ID.');
    if (action.confirm && !window.confirm(action.confirm)) return;
    try {
      let body: Record<string, unknown> | undefined;
      if (action.bodyField) { const reason = window.prompt('Alasan penolakan:'); if (!reason) return; body = { [action.bodyField]: reason }; }
      await api.post(action.endpoint(id), body); toast.success(`${action.label} berhasil.`); await load(); await loadLookups();
    } catch (e) { toast.error(errorMessage(e) || `${action.label} gagal.`); }
  };

  const filteredRows = rows.filter(row => !query.trim() || JSON.stringify(row).toLowerCase().includes(query.toLowerCase()));
  const selectedPO = purchaseOrders.find(o => String(o.id) === form.purchase_order_id);
  const selectedSalesOrder = salesOrders.find(o => String(o.id) === form.sales_order_id);
  const selectedReceipt = receipts.find(o => String(o.id) === form.goods_receipt_id);

  const renderSelect = (label: string, value: string, options: SelectOption[], onChange: (v: string) => void, required = false) => (
    <label><span className="mb-1 block text-xs font-bold text-stone-500">{label}{required ? ' *' : ''}</span><select value={value} onChange={e => onChange(e.target.value)} className="w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-amber-600"><option value="">Pilih {label.toLowerCase()}...</option>{options.map(o => <option key={o.id} value={o.id}>{o.label}</option>)}</select></label>
  );

  const renderInput = (label: string, key: string, type: 'text' | 'number' | 'date' = 'text', required = false, placeholder?: string) => (
    <label><span className="mb-1 block text-xs font-bold text-stone-500">{label}{required ? ' *' : ''}</span><input type={type} value={form[key] ?? ''} onChange={e => updateForm(key, e.target.value)} placeholder={placeholder} className="w-full rounded-xl border border-stone-200 px-3 py-2.5 text-sm outline-none focus:border-amber-600" /></label>
  );

  const renderLines = (mode: 'product' | 'po-item' | 'receipt-item' | 'sales-item' | 'account') => {
    let options: SelectOption[] = [];
    if (mode === 'product') options = products.map(p => ({ id: p.id, label: p.label }));
    if (mode === 'po-item') options = (((selectedPO?.items as Row[]) || [])).map((i, n) => ({ id: String(i.id), label: `${textOf(i, ['product_name','product_code']) || `Item ${n + 1}`} · sisa ${String(i.remaining_quantity ?? i.quantity ?? '-')}` }));
    if (mode === 'receipt-item') options = (((selectedReceipt?.items as Row[]) || [])).map((i, n) => ({ id: String(i.id), label: `${textOf(i, ['product_name','product_code']) || `Item ${n + 1}`} · tersedia ${String(i.quantity ?? '-')}` }));
    if (mode === 'sales-item') options = (((selectedSalesOrder?.items as Row[]) || [])).map((i, n) => ({ id: String(i.id), label: `${textOf(i, ['product_name','product_code']) || `Item ${n + 1}`} · qty ${String(i.quantity ?? '-')}` }));
    if (mode === 'account') options = accounts;
    return <div className="space-y-3">{lineItems.map((line, index) => <div key={index} className="rounded-2xl border border-stone-200 bg-white p-4"><div className="grid gap-3 md:grid-cols-[minmax(0,2fr)_120px_140px_140px_auto] md:items-end">{renderSelect(mode === 'account' ? 'Account' : 'Barang / Item', line.sourceId, options, v => mode === 'product' ? applyProductDefaults(index, v) : updateLine(index, 'sourceId', v), true)}<label><span className="mb-1 block text-xs font-bold text-stone-500">Quantity</span><input type="number" min="0.01" step="0.01" value={line.quantity} onChange={e => updateLine(index, 'quantity', e.target.value)} className="w-full rounded-xl border border-stone-200 px-3 py-2.5 text-sm" /></label>{(mode === 'product' || mode === 'po-item' || mode === 'receipt-item') && <label><span className="mb-1 block text-xs font-bold text-stone-500">Unit Cost</span><input type="number" min="0" step="0.01" value={line.unitCost} onChange={e => updateLine(index, 'unitCost', e.target.value)} className="w-full rounded-xl border border-stone-200 px-3 py-2.5 text-sm" /></label>}{(mode === 'product' || mode === 'sales-item') && <label><span className="mb-1 block text-xs font-bold text-stone-500">Unit Price</span><input type="number" min="0" step="0.01" value={line.unitPrice} onChange={e => updateLine(index, 'unitPrice', e.target.value)} className="w-full rounded-xl border border-stone-200 px-3 py-2.5 text-sm" /></label>}<button type="button" disabled={lineItems.length === 1} onClick={() => removeLine(index)} className="rounded-xl border border-red-200 px-3 py-2.5 text-sm font-bold text-red-600 disabled:opacity-40">Hapus</button></div>{mode === 'sales-item' && <input value={line.reason} onChange={e => updateLine(index, 'reason', e.target.value)} placeholder="Catatan item (opsional)" className="mt-3 w-full rounded-xl border border-stone-200 px-3 py-2.5 text-sm" />}</div>)}</div>;
  };

  const formView = () => {
    if (!resource?.form) return null;
    switch (resource.form) {
      case 'supplier': return <div className="grid gap-3 md:grid-cols-2">{renderInput('Kode Supplier','code','text',true)}{renderInput('Nama Supplier','name','text',true)}{renderInput('NPWP / Tax ID','tax_id')}{renderInput('Contact Person','contact_name')}{renderInput('Phone','phone')}{renderInput('Email','email')}{renderInput('Payment Terms (hari)','payment_terms_days','number')}<label className="md:col-span-2"><span className="mb-1 block text-xs font-bold text-stone-500">Alamat</span><textarea value={form.address ?? ''} onChange={e => updateForm('address',e.target.value)} rows={3} className="w-full rounded-xl border border-stone-200 px-3 py-2.5 text-sm" /></label></div>;
      case 'requisition': return <div className="space-y-4">{renderSelect('Warehouse',form.warehouse_id,warehouses,v=>updateForm('warehouse_id',v),true)}<div>{renderLines('product')}<button type="button" onClick={addLine} className="mt-3 rounded-xl border border-stone-300 px-4 py-2 text-sm font-bold">+ Tambah Barang</button></div>{renderInput('Needed By','needed_by','date')}<label><span className="mb-1 block text-xs font-bold text-stone-500">Alasan</span><textarea value={form.reason ?? ''} onChange={e=>updateForm('reason',e.target.value)} rows={3} className="w-full rounded-xl border border-stone-200 px-3 py-2.5 text-sm" /></label></div>;
      case 'purchase-order': return <div className="space-y-4"><div className="grid gap-3 md:grid-cols-2">{renderSelect('Supplier',form.supplier_id,suppliers,v=>updateForm('supplier_id',v),true)}{renderSelect('Warehouse',form.warehouse_id,warehouses,v=>updateForm('warehouse_id',v),true)}{renderInput('Expected Date','expected_date','date')}{renderInput('Discount','discount_amount','number')}{renderInput('Tax','tax_amount','number')}</div>{renderLines('product')}<button type="button" onClick={addLine} className="rounded-xl border border-stone-300 px-4 py-2 text-sm font-bold">+ Tambah Barang</button></div>;
      case 'goods-receipt': return <div className="space-y-4">{renderSelect('Purchase Order',form.purchase_order_id,purchaseOrders.map(o=>({id:String(o.id),label:textOf(o,['order_number','number'])})),v=>{ updateForm('purchase_order_id',v); setLineItems([{sourceId:'',quantity:'1',unitCost:'',unitPrice:'',reason:''}]); },true)}{renderSelect('Warehouse',form.warehouse_id,warehouses,v=>updateForm('warehouse_id',v),true)}{renderLines('po-item')}<button type="button" onClick={addLine} className="rounded-xl border border-stone-300 px-4 py-2 text-sm font-bold">+ Tambah Item Penerimaan</button></div>;
      case 'supplier-invoice': return <div className="grid gap-3 md:grid-cols-2">{renderSelect('Goods Receipt',form.goods_receipt_id,receipts.map(r=>({id:String(r.id),label:textOf(r,['receipt_number','number'])})),v=>updateForm('goods_receipt_id',v),true)}{renderInput('Nomor Invoice','invoice_number','text',true)}{renderInput('Tanggal Invoice','invoice_date','date')}{renderInput('Jatuh Tempo','due_date','date')}</div>;
      case 'supplier-payment': return <div className="grid gap-3 md:grid-cols-2">{renderSelect('Supplier Invoice',form.supplier_invoice_id,invoices.map(i=>({id:String(i.id),label:`${textOf(i,['invoice_number','number'])} · ${formatMoney(i.total_amount)}`})),v=>{updateForm('supplier_invoice_id',v); const inv=invoices.find(i=>String(i.id)===v); if(inv?.balance_due!==undefined) updateForm('amount',String(inv.balance_due));},true)}{renderInput('Jumlah Bayar','amount','number',true)}<label><span className="mb-1 block text-xs font-bold text-stone-500">Metode</span><select value={form.method ?? 'bank_transfer'} onChange={e=>updateForm('method',e.target.value)} className="w-full rounded-xl border border-stone-200 px-3 py-2.5 text-sm"><option value="bank_transfer">Bank Transfer</option><option value="cash">Cash</option><option value="giro">Giro</option><option value="other">Other</option></select></label>{renderInput('Reference','reference')}</div>;
      case 'supplier-return': return <div className="space-y-4"><div className="grid gap-3 md:grid-cols-2">{renderSelect('Purchase Order',form.purchase_order_id,purchaseOrders.map(o=>({id:String(o.id),label:textOf(o,['order_number','number'])})),v=>updateForm('purchase_order_id',v),true)}{renderSelect('Goods Receipt',form.goods_receipt_id,receipts.map(r=>({id:String(r.id),label:textOf(r,['receipt_number','number'])})),v=>{updateForm('goods_receipt_id',v); setLineItems([{sourceId:'',quantity:'1',unitCost:'',unitPrice:'',reason:''}]);},true)}{renderSelect('Warehouse',form.warehouse_id,warehouses,v=>updateForm('warehouse_id',v),true)}</div>{renderLines('receipt-item')}<button type="button" onClick={addLine} className="rounded-xl border border-stone-300 px-4 py-2 text-sm font-bold">+ Tambah Item Return</button></div>;
      case 'credit-note': return <div className="grid gap-3 md:grid-cols-2">{renderSelect('Supplier Return',form.supplier_return_id,returns.map(r=>({id:String(r.id),label:textOf(r,['return_number','number'])})),v=>updateForm('supplier_return_id',v),true)}{renderInput('Nomor Credit Note','credit_note_number','text',true)}{renderInput('Jumlah','amount','number',true)}</div>;
      case 'budget': return <div className="grid gap-3 md:grid-cols-2">{renderInput('Tahun Anggaran','budget_year','number',true)}{renderInput('Alokasi','allocated_amount','number',true)}</div>;
      case 'purchasing-approval': return <div className="grid gap-3 md:grid-cols-2">{renderSelect('Approver Role',form.approver_role_id,roles,v=>updateForm('approver_role_id',v),true)}{renderInput('Minimum Amount','min_amount','number',true)}{renderInput('Maximum Amount','max_amount','number')}{renderInput('Priority','priority','number')}</div>;
      case 'sales-order': return <div className="space-y-4"><div className="grid gap-3 md:grid-cols-2">{renderSelect('Customer',form.customer_id,customers,v=>updateForm('customer_id',v))}{renderSelect('Warehouse',form.warehouse_id,warehouses,v=>updateForm('warehouse_id',v))}{renderInput('Discount','discount_amount','number')}{renderInput('Tax','tax_amount','number')}</div>{renderLines('product')}<button type="button" onClick={addLine} className="rounded-xl border border-stone-300 px-4 py-2 text-sm font-bold">+ Tambah Produk</button></div>;
      case 'sales-approval': return <div className="grid gap-3 md:grid-cols-2">{renderSelect('Approver Role',form.approver_role_id,roles,v=>updateForm('approver_role_id',v),true)}{renderInput('Minimum Amount','min_amount','number',true)}{renderInput('Maximum Amount','max_amount','number')}</div>;
      case 'fulfillment': return <div className="space-y-4">{renderSelect('Sales Order',form.sales_order_id,salesOrders.map(o=>({id:String(o.id),label:textOf(o,['order_number','number'])})),v=>{updateForm('sales_order_id',v);setLineItems([{sourceId:'',quantity:'1',unitCost:'',unitPrice:'',reason:''}]);},true)}{renderSelect('Warehouse',form.warehouse_id,warehouses,v=>updateForm('warehouse_id',v),true)}{renderLines('sales-item')}<button type="button" onClick={addLine} className="rounded-xl border border-stone-300 px-4 py-2 text-sm font-bold">+ Tambah Item Fulfillment</button></div>;
      case 'shipment': return <div className="grid gap-3 md:grid-cols-2">{renderSelect('Fulfillment',form.fulfillment_id,fulfillments.map(f=>({id:String(f.id),label:textOf(f,['fulfillment_number','number'])})),v=>updateForm('fulfillment_id',v),true)}{renderInput('Carrier','carrier')}{renderInput('Tracking Number','tracking_number')}{renderInput('Shipped At','shipped_at','date')}</div>;
      case 'sales-invoice': return <div className="grid gap-3 md:grid-cols-2">{renderSelect('Sales Order',form.sales_order_id,salesOrders.map(o=>({id:String(o.id),label:textOf(o,['order_number','number'])})),v=>updateForm('sales_order_id',v),true)}{renderInput('Invoice Date','invoice_date','date')}{renderInput('Due Date','due_date','date')}</div>;
      case 'customer-payment': return <div className="grid gap-3 md:grid-cols-2">{renderSelect('Sales Invoice',form.sales_invoice_id,salesInvoices.map(i=>({id:String(i.id),label:`${textOf(i,['invoice_number','number'])} · ${formatMoney(i.total_amount)}`})),v=>{updateForm('sales_invoice_id',v); const inv=salesInvoices.find(i=>String(i.id)===v); if(inv?.balance_due!==undefined) updateForm('amount',String(inv.balance_due));},true)}{renderInput('Jumlah Bayar','amount','number',true)}{renderInput('Metode','method')}{renderInput('Reference','reference')}</div>;
      case 'sales-return': return <div className="space-y-4"><div className="grid gap-3 md:grid-cols-2">{renderSelect('Sales Order',form.sales_order_id,salesOrders.map(o=>({id:String(o.id),label:textOf(o,['order_number','number'])})),v=>{updateForm('sales_order_id',v);setLineItems([{sourceId:'',quantity:'1',unitCost:'',unitPrice:'',reason:''}]);},true)}{renderSelect('Warehouse',form.warehouse_id,warehouses,v=>updateForm('warehouse_id',v),true)}</div>{renderLines('sales-item')}<button type="button" onClick={addLine} className="rounded-xl border border-stone-300 px-4 py-2 text-sm font-bold">+ Tambah Item Return</button></div>;
      case 'fiscal-period': return <div className="grid gap-3 md:grid-cols-2">{renderInput('Tahun','year','number',true)}{renderInput('Bulan','month','number',true,'1-12')}</div>;
      case 'cash-book': return <div className="grid gap-3 md:grid-cols-2">{renderSelect('Account',form.account_code,accounts.map(a=>({id:a.label.split(' · ')[0],label:a.label})),v=>updateForm('account_code',v),true)}{renderInput('Dari','from','date')}{renderInput('Sampai','to','date')}</div>;
      case 'reconciliation': return <div className="grid gap-3 md:grid-cols-2">{renderSelect('Account',form.account_code,accounts.map(a=>({id:a.label.split(' · ')[0],label:a.label})),v=>updateForm('account_code',v),true)}{renderInput('Statement Balance','statement_balance','number',true)}{renderInput('Adjustment','adjustment_amount','number')}</div>;
      case 'erp-account': return <div className="grid gap-3 md:grid-cols-2">{renderInput('Code','code','text',true)}{renderInput('Name','name','text',true)}{renderInput('Type','type','text',true,'asset / liability / equity / revenue / expense')}{renderSelect('Parent Account',form.parent_id,accounts,v=>updateForm('parent_id',v))}{renderInput('Normal Balance','normal_balance')}</div>;
      case 'erp-journal': return <div className="space-y-4">{renderInput('Journal Date','journal_date','date',true)}{renderInput('Reference','reference')}{renderInput('Description','description')}<div>{renderLines('account')}<button type="button" onClick={addLine} className="mt-3 rounded-xl border border-stone-300 px-4 py-2 text-sm font-bold">+ Tambah Baris Jurnal</button><p className="mt-2 text-xs text-stone-500">Isi Debit dan Credit pada setiap baris. Sistem akan mengirim jurnal dalam format yang dibutuhkan backend.</p></div></div>;
      default: return null;
    }
  };

  const openCreate = () => { setForm({}); setLineItems([{ sourceId: '', quantity: '1', unitCost: '', unitPrice: '', reason: '' }]); setShowCreate(true); };

  return <div className="flex h-screen w-full bg-stone-50 font-sans text-stone-800"><AdminSidebar activePage="operations" /><div className="flex min-w-0 flex-1 flex-col overflow-hidden"><header className="border-b border-stone-200 bg-white px-8 py-5 shadow-sm"><div className="flex flex-wrap items-center justify-between gap-4"><div><div className="text-xs font-bold uppercase tracking-[0.18em] text-amber-700">Enterprise Workspace</div><h1 className="mt-1 text-2xl font-bold text-stone-900">ERP Operations</h1><p className="mt-1 text-sm text-stone-500">Form bisnis terpandu. Tidak perlu memasukkan JSON atau ID internal secara manual.</p></div><button onClick={() => void load()} className="rounded-xl bg-stone-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-stone-800">↻ Refresh</button></div></header><main className="min-h-0 flex-1 overflow-y-auto p-6 lg:p-8"><div className="mb-5 flex flex-wrap gap-2">{accessibleModules.map(item=><button key={item.key} onClick={()=>{setActiveModule(item.key);setQuery('');resetCreate();const next=item.resources.find(r=>can(r.permission));setActiveResourceKey(next?.key??'')}} className={`rounded-2xl px-5 py-3 text-sm font-bold transition ${activeModule===item.key?'bg-amber-700 text-white shadow-sm':'border border-stone-200 bg-white text-stone-600 hover:bg-stone-100'}`}><span className="mr-2">{item.icon}</span>{item.label}</button>)}</div><div className="grid gap-6 xl:grid-cols-[280px_minmax(0,1fr)]"><aside className="rounded-2xl border border-stone-200 bg-white p-3 shadow-sm"><div className="px-3 pb-2 text-xs font-bold uppercase tracking-wider text-stone-400">Menu</div><div className="space-y-1">{visibleResources.map(item=><button key={item.key} onClick={()=>{setActiveResourceKey(item.key);resetCreate();}} className={`w-full rounded-xl px-3 py-2.5 text-left text-sm font-semibold transition ${resource?.key===item.key?'bg-stone-900 text-white':'text-stone-600 hover:bg-stone-100'}`}>{item.label}</button>)}</div></aside><section className="min-w-0 rounded-2xl border border-stone-200 bg-white shadow-sm"><div className="border-b border-stone-200 p-5"><div className="flex flex-wrap items-start justify-between gap-4"><div><div className="flex items-center gap-2"><h2 className="text-lg font-bold text-stone-900">{resource?.label??'Workspace'}</h2>{resource&&<span className="rounded-full bg-green-50 px-2.5 py-1 text-[11px] font-bold text-green-700">LIVE</span>}</div><p className="mt-1 text-xs text-stone-400">Data mengikuti tenant / company / branch aktif.</p></div><div className="flex gap-2"><button onClick={()=>void loadLookups()} className="rounded-xl border border-stone-200 bg-white px-4 py-2 text-sm font-bold text-stone-700">↻ Master Data</button>{resource?.createEndpoint&&resource.createPermission&&can(resource.createPermission)&&<button onClick={openCreate} className="rounded-xl bg-amber-700 px-4 py-2 text-sm font-bold text-white hover:bg-amber-800">+ Buat {resource.label}</button>}<input value={query} onChange={e=>setQuery(e.target.value)} placeholder="Cari data..." className="w-44 rounded-xl border border-stone-200 px-3 py-2 text-sm outline-none focus:border-amber-600" /></div></div></div>{showCreate&&resource?.form&&<div className="border-b border-stone-200 bg-stone-50 p-5"><div className="mb-4 flex items-start justify-between"><div><h3 className="font-bold text-stone-900">Buat {resource.label}</h3><p className="mt-1 text-xs text-stone-500">Pilih data dari master. Sistem akan membentuk request otomatis.</p></div><button onClick={resetCreate} className="text-sm font-semibold text-stone-500">Tutup</button></div>{formView()}<div className="mt-5 flex justify-end"><button onClick={()=>void submitCreate()} className="rounded-xl bg-stone-900 px-5 py-2.5 text-sm font-bold text-white">Simpan</button></div></div>}{loading&&<div className="p-10 text-center text-sm text-stone-500">Memuat data...</div>}{!loading&&error&&<div className="m-5 rounded-2xl border border-red-200 bg-red-50 p-5 text-sm text-red-700">{error}</div>}{!loading&&!error&&filteredRows.length===0&&<div className="p-10 text-center"><div className="text-3xl">📭</div><p className="mt-2 font-semibold text-stone-700">Belum ada data</p><p className="text-sm text-stone-500">Tidak ada record dalam scope organisasi aktif.</p></div>}{!loading&&!error&&filteredRows.length>0&&<div className="divide-y divide-stone-100">{filteredRows.slice(0,100).map((row,index)=>{const actions=(resource?.actions??[]).filter(a=>can(a.permission));return <article key={`${idOf(row)??'row'}-${index}`} className="p-5 hover:bg-stone-50"><div className="flex flex-wrap items-start justify-between gap-3"><div><div className="font-semibold text-stone-900">{textOf(row,['code','name','order_number','invoice_number','receipt_number','number','document_number','status','reference'])}</div><div className="mt-1 text-xs text-stone-400">Status: {String(row.status??'-')}</div></div>{actions.length>0&&<div className="flex flex-wrap gap-2">{actions.map(a=><button key={a.label} onClick={()=>void runAction(row,a)} className="rounded-lg border border-stone-200 bg-white px-3 py-1.5 text-xs font-bold text-stone-700 hover:bg-stone-100">{a.label}</button>)}</div>}</div><div className="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">{Object.entries(row).filter(([key])=>!['tenant_id','company_id','branch_id'].includes(key)).slice(0,12).map(([key,value])=><div key={key} className="rounded-xl bg-stone-50 px-3 py-2"><div className="text-[10px] font-bold uppercase tracking-wide text-stone-400">{key.replaceAll('_',' ')}</div><div className="mt-1 break-words text-xs text-stone-700">{typeof value==='object'?JSON.stringify(value):String(value??'-')}</div></div>)}</div></article>})}</div>}</section></div></main></div></div>;
}

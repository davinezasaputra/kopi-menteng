import { useEffect, useMemo, useState } from 'react';
import AdminSidebar from '../components/AdminSidebar';
import api from '../core/api/client';
import { extractRows } from '../core/api/normalize';
import { can, canAny } from '../core/auth/permissions';
import toast from 'react-hot-toast';

type Row = Record<string, unknown>;
type ModuleKey = 'purchasing' | 'sales' | 'finance';
type ResourceKey = string;
type Field = { name: string; label: string; type?: 'text' | 'number' | 'date' | 'textarea' | 'select'; placeholder?: string; options?: string[]; required?: boolean };
type Resource = {
  key: ResourceKey;
  label: string;
  endpoint: string;
  permission: string;
  createEndpoint?: string;
  createPermission?: string;
  fields?: Field[];
  actions?: Array<{ label: string; endpoint: (id: string) => string; method?: 'post'; permission: string; bodyField?: string }>;
};

type ModuleConfig = { key: ModuleKey; label: string; icon: string; permission: string; resources: Resource[] };

const idField: Field = { name: 'id', label: 'Record ID', required: true, type: 'number' };
const reasonField: Field = { name: 'reason', label: 'Reason', required: true, type: 'textarea' };

const modules: ModuleConfig[] = [
  {
    key: 'purchasing', label: 'Purchasing', icon: '🛒', permission: 'purchasing.supplier.view',
    resources: [
      { key: 'suppliers', label: 'Suppliers', endpoint: '/purchasing/suppliers', permission: 'purchasing.supplier.view', createEndpoint: '/purchasing/suppliers', createPermission: 'purchasing.supplier.create', fields: [
        { name: 'code', label: 'Code', required: true }, { name: 'name', label: 'Name', required: true }, { name: 'tax_id', label: 'Tax ID' }, { name: 'contact_name', label: 'Contact' }, { name: 'phone', label: 'Phone' }, { name: 'email', label: 'Email' }, { name: 'address', label: 'Address', type: 'textarea' }, { name: 'payment_terms_days', label: 'Payment Terms (days)', type: 'number' },
      ] },
      { key: 'requisitions', label: 'Requisitions', endpoint: '/purchasing/requisitions', permission: 'purchasing.requisition.view', createEndpoint: '/purchasing/requisitions', createPermission: 'purchasing.requisition.create', fields: [
        { name: 'warehouse_id', label: 'Warehouse ID', type: 'number', required: true }, { name: 'items', label: 'Items JSON', type: 'textarea', required: true, placeholder: '[{"product_id":1,"quantity":1,"estimated_unit_cost":10000}]' }, { name: 'needed_by', label: 'Needed By', type: 'date' }, { name: 'reason', label: 'Reason', type: 'textarea' }, { name: 'notes', label: 'Notes', type: 'textarea' },
      ], actions: [
        { label: 'Submit', endpoint: id => `/purchasing/requisitions/${id}/submit`, permission: 'purchasing.requisition.submit' },
        { label: 'Cancel', endpoint: id => `/purchasing/requisitions/${id}/cancel`, permission: 'purchasing.requisition.cancel' },
      ] },
      { key: 'orders', label: 'Purchase Orders', endpoint: '/purchasing/orders', permission: 'purchasing.order.view', createEndpoint: '/purchasing/orders', createPermission: 'purchasing.order.create', fields: [
        { name: 'supplier_id', label: 'Supplier ID', type: 'number', required: true }, { name: 'warehouse_id', label: 'Warehouse ID', type: 'number', required: true }, { name: 'purchase_requisition_id', label: 'Requisition ID', type: 'number' }, { name: 'expected_date', label: 'Expected Date', type: 'date' }, { name: 'discount_amount', label: 'Discount', type: 'number' }, { name: 'tax_amount', label: 'Tax', type: 'number' }, { name: 'items', label: 'Items JSON', type: 'textarea', required: true, placeholder: '[{"product_id":1,"quantity":1,"unit_cost":10000}]' }, { name: 'notes', label: 'Notes', type: 'textarea' },
      ], actions: [
        { label: 'Submit', endpoint: id => `/purchasing/orders/${id}/submit`, permission: 'purchasing.order.submit' },
        { label: 'Approve', endpoint: id => `/purchasing/orders/${id}/approve`, permission: 'purchasing.order.approve' },
        { label: 'Reject', endpoint: id => `/purchasing/orders/${id}/reject`, permission: 'purchasing.order.approve', bodyField: 'reason' },
        { label: 'Cancel', endpoint: id => `/purchasing/orders/${id}/cancel`, permission: 'purchasing.order.cancel' },
      ] },
      { key: 'goods-receipts', label: 'Goods Receipts', endpoint: '/purchasing/goods-receipts', permission: 'purchasing.receipt.view', createEndpoint: '/purchasing/goods-receipts', createPermission: 'purchasing.receipt.create', fields: [
        { name: 'purchase_order_id', label: 'Purchase Order ID', type: 'number', required: true }, { name: 'warehouse_id', label: 'Warehouse ID', type: 'number', required: true }, { name: 'items', label: 'Receipt Items JSON', type: 'textarea', required: true, placeholder: '[{"purchase_order_item_id":1,"quantity":1,"unit_cost":10000}]' }, { name: 'notes', label: 'Notes', type: 'textarea' },
      ] },
      { key: 'invoices', label: 'Supplier Invoices', endpoint: '/purchasing/invoices', permission: 'purchasing.ap.view', createEndpoint: '/purchasing/invoices', createPermission: 'purchasing.ap.create', fields: [
        { name: 'goods_receipt_id', label: 'Goods Receipt ID', type: 'number', required: true }, { name: 'invoice_number', label: 'Invoice Number', required: true }, { name: 'invoice_date', label: 'Invoice Date', type: 'date' }, { name: 'due_date', label: 'Due Date', type: 'date' }, { name: 'notes', label: 'Notes', type: 'textarea' },
      ] },
      { key: 'payments', label: 'Supplier Payments', endpoint: '/purchasing/payments', permission: 'purchasing.ap.view', createEndpoint: '/purchasing/payments', createPermission: 'purchasing.ap.pay', fields: [
        { name: 'supplier_invoice_id', label: 'Invoice ID', type: 'number', required: true }, { name: 'amount', label: 'Amount', type: 'number', required: true }, { name: 'method', label: 'Method', type: 'select', options: ['cash', 'bank_transfer', 'giro', 'other'] }, { name: 'reference', label: 'Reference' }, { name: 'notes', label: 'Notes', type: 'textarea' },
      ] },
      { key: 'returns', label: 'Supplier Returns', endpoint: '/purchasing/returns', permission: 'purchasing.return.view', createEndpoint: '/purchasing/returns', createPermission: 'purchasing.return.create', fields: [
        { name: 'purchase_order_id', label: 'Purchase Order ID', type: 'number', required: true }, { name: 'goods_receipt_id', label: 'Goods Receipt ID', type: 'number', required: true }, { name: 'warehouse_id', label: 'Warehouse ID', type: 'number', required: true }, { name: 'items', label: 'Items JSON', type: 'textarea', required: true, placeholder: '[{"goods_receipt_item_id":1,"quantity":1}]' }, { name: 'reason', label: 'Reason', type: 'textarea' }, { name: 'notes', label: 'Notes', type: 'textarea' },
      ] },
      { key: 'credit-notes', label: 'Credit Notes', endpoint: '/purchasing/credit-notes', permission: 'purchasing.credit_note.view', createEndpoint: '/purchasing/credit-notes', createPermission: 'purchasing.credit_note.create', fields: [
        { name: 'supplier_return_id', label: 'Supplier Return ID', type: 'number', required: true }, { name: 'credit_note_number', label: 'Credit Note Number', required: true }, { name: 'amount', label: 'Amount', type: 'number', required: true }, { name: 'notes', label: 'Notes', type: 'textarea' },
      ] },
      { key: 'budgets', label: 'Budgets', endpoint: '/purchasing/budgets', permission: 'purchasing.budget.view', createEndpoint: '/purchasing/budgets', createPermission: 'purchasing.budget.create', fields: [
        { name: 'budget_year', label: 'Budget Year', type: 'number', required: true }, { name: 'allocated_amount', label: 'Allocated Amount', type: 'number', required: true }, { name: 'notes', label: 'Notes', type: 'textarea' },
      ] },
      { key: 'approval-matrix', label: 'Approval Matrix', endpoint: '/purchasing/approval-matrix', permission: 'purchasing.approval_matrix.view', createEndpoint: '/purchasing/approval-matrix', createPermission: 'purchasing.approval_matrix.create', fields: [
        { name: 'approver_role_id', label: 'Approver Role ID', type: 'number', required: true }, { name: 'min_amount', label: 'Min Amount', type: 'number', required: true }, { name: 'max_amount', label: 'Max Amount', type: 'number' }, { name: 'priority', label: 'Priority', type: 'number' }, { name: 'notes', label: 'Notes', type: 'textarea' },
      ] },
      { key: 'dashboard-report', label: 'Dashboard Report', endpoint: '/purchasing/reports/dashboard', permission: 'purchasing.reporting.view' },
      { key: 'supplier-performance', label: 'Supplier Performance', endpoint: '/purchasing/reports/supplier-performance', permission: 'purchasing.reporting.view' },
      { key: 'ap-aging', label: 'AP Aging', endpoint: '/purchasing/reports/ap-aging', permission: 'purchasing.reporting.view' },
      { key: 'reconciliation', label: 'PO Reconciliation', endpoint: '/purchasing/reconciliation/orders', permission: 'purchasing.reconciliation.view' },
    ],
  },
  {
    key: 'sales', label: 'Sales', icon: '💰', permission: 'sales.order.view',
    resources: [
      { key: 'orders', label: 'Sales Orders', endpoint: '/sales/orders', permission: 'sales.order.view', createEndpoint: '/sales/orders', createPermission: 'sales.order.create', fields: [
        { name: 'customer_id', label: 'Customer ID', type: 'number' }, { name: 'warehouse_id', label: 'Warehouse ID', type: 'number' }, { name: 'items', label: 'Items JSON', type: 'textarea', required: true, placeholder: '[{"product_id":1,"quantity":1,"unit_price":10000}]' }, { name: 'discount_amount', label: 'Discount', type: 'number' }, { name: 'tax_amount', label: 'Tax', type: 'number' }, { name: 'notes', label: 'Notes', type: 'textarea' },
      ], actions: [
        { label: 'Submit', endpoint: id => `/sales/orders/${id}/submit`, permission: 'sales.order.submit' },
        { label: 'Approve', endpoint: id => `/sales/orders/${id}/approve`, permission: 'sales.order.approve' },
        { label: 'Reject', endpoint: id => `/sales/orders/${id}/reject`, permission: 'sales.order.approve', bodyField: 'reason' },
        { label: 'Cancel', endpoint: id => `/sales/orders/${id}/cancel`, permission: 'sales.order.cancel' },
      ] },
      { key: 'approval-matrix', label: 'Approval Matrix', endpoint: '/sales/approval-matrix', permission: 'sales.approval_matrix.view', createEndpoint: '/sales/approval-matrix', createPermission: 'sales.approval_matrix.create', fields: [
        { name: 'approver_role_id', label: 'Approver Role ID', type: 'number', required: true }, { name: 'min_amount', label: 'Min Amount', type: 'number', required: true }, { name: 'max_amount', label: 'Max Amount', type: 'number' }, { name: 'notes', label: 'Notes', type: 'textarea' },
      ] },
      { key: 'fulfillments', label: 'Fulfillments', endpoint: '/sales/fulfillments', permission: 'sales.fulfillment.view', createEndpoint: '/sales/fulfillments', createPermission: 'sales.fulfillment.create', fields: [
        { name: 'sales_order_id', label: 'Sales Order ID', type: 'number', required: true }, { name: 'warehouse_id', label: 'Warehouse ID', type: 'number', required: true }, { name: 'items', label: 'Items JSON', type: 'textarea', required: true, placeholder: '[{"sales_order_item_id":1,"quantity":1}]' }, { name: 'notes', label: 'Notes', type: 'textarea' },
      ], actions: [
        { label: 'Pick', endpoint: id => `/sales/fulfillments/${id}/pick`, permission: 'sales.fulfillment.pick' },
        { label: 'Pack', endpoint: id => `/sales/fulfillments/${id}/pack`, permission: 'sales.fulfillment.pack' },
      ] },
      { key: 'shipments', label: 'Shipments', endpoint: '/sales/shipments', permission: 'sales.shipment.view', createEndpoint: '/sales/shipments', createPermission: 'sales.shipment.create', fields: [
        { name: 'fulfillment_id', label: 'Fulfillment ID', type: 'number', required: true }, { name: 'carrier', label: 'Carrier' }, { name: 'tracking_number', label: 'Tracking Number' }, { name: 'shipped_at', label: 'Shipped At', type: 'date' }, { name: 'notes', label: 'Notes', type: 'textarea' },
      ] },
      { key: 'invoices', label: 'Sales Invoices', endpoint: '/sales/invoices', permission: 'sales.invoice.view', createEndpoint: '/sales/invoices', createPermission: 'sales.invoice.create', fields: [
        { name: 'sales_order_id', label: 'Sales Order ID', type: 'number', required: true }, { name: 'invoice_date', label: 'Invoice Date', type: 'date' }, { name: 'due_date', label: 'Due Date', type: 'date' }, { name: 'notes', label: 'Notes', type: 'textarea' },
      ] },
      { key: 'receivables', label: 'Receivables', endpoint: '/sales/receivables', permission: 'sales.receivable.view', actions: [{ label: 'Aging', endpoint: id => `/sales/receivables/aging?record_id=${id}`, permission: 'sales.receivable.view' }] },
      { key: 'payments', label: 'Customer Payments', endpoint: '/sales/payments', permission: 'sales.payment.view', createEndpoint: '/sales/payments', createPermission: 'sales.payment.create', fields: [
        { name: 'sales_invoice_id', label: 'Invoice ID', type: 'number', required: true }, { name: 'amount', label: 'Amount', type: 'number', required: true }, { name: 'method', label: 'Method' }, { name: 'reference', label: 'Reference' }, { name: 'notes', label: 'Notes', type: 'textarea' },
      ] },
      { key: 'returns', label: 'Sales Returns', endpoint: '/sales/returns', permission: 'sales.return.view', createEndpoint: '/sales/returns', createPermission: 'sales.return.create', fields: [
        { name: 'sales_order_id', label: 'Sales Order ID', type: 'number', required: true }, { name: 'warehouse_id', label: 'Warehouse ID', type: 'number', required: true }, { name: 'items', label: 'Items JSON', type: 'textarea', required: true, placeholder: '[{"sales_order_item_id":1,"quantity":1,"reason":"damaged"}]' }, { name: 'reason', label: 'Reason', type: 'textarea' }, { name: 'notes', label: 'Notes', type: 'textarea' },
      ] },
      { key: 'dashboard-report', label: 'Dashboard Report', endpoint: '/sales/reports/dashboard', permission: 'sales.reporting.view' },
      { key: 'journals-report', label: 'Sales Journals', endpoint: '/sales/reports/journals', permission: 'sales.reporting.view' },
    ],
  },
  {
    key: 'finance', label: 'Finance', icon: '📊', permission: 'accounting.report.view',
    resources: [
      { key: 'periods', label: 'Fiscal Periods', endpoint: '/finance/periods', permission: 'accounting.fiscal_period.view', createEndpoint: '/finance/periods', createPermission: 'accounting.fiscal_period.manage', fields: [
        { name: 'period', label: 'Period', required: true, placeholder: '2026-09' }, { name: 'starts_at', label: 'Starts At', type: 'date' }, { name: 'ends_at', label: 'Ends At', type: 'date' },
      ], actions: [{ label: 'Close', endpoint: id => `/finance/periods/${id}/close`, permission: 'accounting.period.close' }] },
      { key: 'trial-balance', label: 'Trial Balance', endpoint: '/finance/reports/trial-balance', permission: 'accounting.report.view' },
      { key: 'profit-loss', label: 'Profit & Loss', endpoint: '/finance/reports/profit-loss', permission: 'accounting.report.view' },
      { key: 'balance-sheet', label: 'Balance Sheet', endpoint: '/finance/reports/balance-sheet', permission: 'accounting.report.view' },
      { key: 'cash-book', label: 'Cash Book', endpoint: '/finance/cash-book', permission: 'accounting.report.view' },
      { key: 'reconciliations', label: 'Reconciliations', endpoint: '/finance/reconciliations', permission: 'accounting.reconciliation.view', createEndpoint: '/finance/reconciliations', createPermission: 'accounting.reconciliation.create', fields: [
        { name: 'account_id', label: 'Account ID', type: 'number', required: true }, { name: 'date', label: 'Date', type: 'date', required: true }, { name: 'amount', label: 'Amount', type: 'number', required: true }, { name: 'reference', label: 'Reference' }, { name: 'notes', label: 'Notes', type: 'textarea' },
      ] },
      { key: 'accounts', label: 'ERP Accounts', endpoint: '/erp/accounting/accounts', permission: 'accounting.erp_account.view', createEndpoint: '/erp/accounting/accounts', createPermission: 'accounting.erp_account.create', fields: [
        { name: 'code', label: 'Code', required: true }, { name: 'name', label: 'Name', required: true }, { name: 'type', label: 'Type', required: true }, { name: 'parent_id', label: 'Parent ID', type: 'number' }, { name: 'normal_balance', label: 'Normal Balance' },
      ] },
      { key: 'journals', label: 'ERP Journals', endpoint: '/erp/accounting/journals', permission: 'accounting.erp_journal.view', createEndpoint: '/erp/accounting/journals', createPermission: 'accounting.erp_journal.create', fields: [
        { name: 'journal_date', label: 'Journal Date', type: 'date', required: true }, { name: 'reference', label: 'Reference' }, { name: 'description', label: 'Description', type: 'textarea' }, { name: 'lines', label: 'Lines JSON', type: 'textarea', required: true, placeholder: '[{"account_id":1,"debit":100000,"credit":0},{"account_id":2,"debit":0,"credit":100000}]' },
      ] },
    ],
  },
];

const moduleTabs = modules.filter(module => can(module.permission) || canAny(module.resources.map(resource => resource.permission)));

function getRecordId(row: Row): string | null {
  const value = row.id ?? row[`${Object.keys(row)[0] ?? ''}`];
  return value === undefined || value === null ? null : String(value);
}

function titleFromRow(row: Row): string {
  const preferred = ['code', 'name', 'number', 'document_number', 'invoice_number', 'status', 'period', 'reference'];
  const parts = preferred.filter(key => row[key] !== undefined && row[key] !== null && row[key] !== '').map(key => `${key}: ${String(row[key])}`);
  return parts.length ? parts.join(' · ') : Object.entries(row).slice(0, 3).map(([k, v]) => `${k}: ${String(v)}`).join(' · ');
}

function normalizeBody(form: Record<string, string>): Record<string, unknown> {
  const result: Record<string, unknown> = {};
  Object.entries(form).forEach(([key, value]) => {
    if (value === '') return;
    const trimmed = value.trim();
    if (trimmed.startsWith('[') || trimmed.startsWith('{')) {
      try { result[key] = JSON.parse(trimmed); return; } catch { /* keep as text */ }
    }
    if (/^-?\d+(\.\d+)?$/.test(trimmed)) result[key] = Number(trimmed);
    else result[key] = value;
  });
  return result;
}

export default function EnterpriseOperations() {
  const [activeModule, setActiveModule] = useState<ModuleKey>(moduleTabs[0]?.key ?? 'finance');
  const module = useMemo(() => modules.find(item => item.key === activeModule) ?? modules[0], [activeModule]);
  const visibleResources = useMemo(() => module.resources.filter(resource => can(resource.permission)), [module]);
  const [activeResourceKey, setActiveResourceKey] = useState<ResourceKey>(visibleResources[0]?.key ?? '');
  const resource = visibleResources.find(item => item.key === activeResourceKey) ?? visibleResources[0];
  const [rows, setRows] = useState<Row[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [query, setQuery] = useState('');
  const [showCreate, setShowCreate] = useState(false);
  const [form, setForm] = useState<Record<string, string>>({});

  useEffect(() => {
    if (!visibleResources.some(item => item.key === activeResourceKey)) setActiveResourceKey(visibleResources[0]?.key ?? '');
  }, [activeResourceKey, visibleResources]);

  const load = async () => {
    if (!resource) return;
    setLoading(true); setError('');
    try {
      const response = await api.get(resource.endpoint);
      setRows(extractRows<Row>(response.data));
    } catch (err) {
      const message = err && typeof err === 'object' && 'response' in err ? String((err as { response?: { data?: { message?: string } } }).response?.data?.message || '') : '';
      setRows([]); setError(message || 'Data tidak dapat dimuat. Pastikan permission dan organization context tersedia.');
    } finally { setLoading(false); }
  };

  useEffect(() => { void load(); }, [resource?.endpoint]);

  const switchResource = (key: string) => {
    setActiveResourceKey(key); setRows([]); setError(''); setQuery(''); setShowCreate(false); setForm({});
  };

  const submitCreate = async () => {
    if (!resource?.createEndpoint) return;
    try {
      await api.post(resource.createEndpoint, normalizeBody(form));
      toast.success(`${resource.label} berhasil dibuat.`);
      setShowCreate(false); setForm({});
      await load();
    } catch (err) {
      const message = err && typeof err === 'object' && 'response' in err ? String((err as { response?: { data?: { message?: string } } }).response?.data?.message || '') : '';
      toast.error(message || 'Gagal menyimpan data.');
    }
  };

  const runAction = async (row: Row, action: NonNullable<Resource['actions']>[number]) => {
    const id = getRecordId(row); if (!id) return toast.error('Record tidak memiliki ID.');
    try {
      const body = action.bodyField ? { [action.bodyField]: window.prompt('Masukkan reason:') || '' } : undefined;
      if (action.bodyField && !body?.[action.bodyField]) return;
      await api.post(action.endpoint(id), body);
      toast.success(`${action.label} berhasil.`);
      await load();
    } catch (err) {
      const message = err && typeof err === 'object' && 'response' in err ? String((err as { response?: { data?: { message?: string } } }).response?.data?.message || '') : '';
      toast.error(message || `${action.label} gagal.`);
    }
  };

  const filteredRows = rows.filter(row => {
    if (!query.trim()) return true;
    const haystack = JSON.stringify(row).toLowerCase();
    return haystack.includes(query.toLowerCase());
  });

  return (
    <div className="flex h-screen w-full bg-stone-50 font-sans text-stone-800">
      <AdminSidebar activePage="operations" />
      <div className="flex min-w-0 flex-1 flex-col overflow-hidden">
        <header className="border-b border-stone-200 bg-white px-8 py-5 shadow-sm">
          <div className="flex flex-wrap items-center justify-between gap-4">
            <div>
              <div className="text-xs font-bold uppercase tracking-[0.18em] text-amber-700">Enterprise Workspace</div>
              <h1 className="mt-1 text-2xl font-bold text-stone-900">ERP Operations</h1>
              <p className="mt-1 text-sm text-stone-500">Purchasing, Sales, dan Finance sekarang memiliki workspace operasional yang terhubung ke API bisnis.</p>
            </div>
            <button onClick={() => void load()} className="rounded-xl bg-stone-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-stone-800">↻ Refresh</button>
          </div>
        </header>

        <main className="min-h-0 flex-1 overflow-y-auto p-6 lg:p-8">
          <div className="mb-5 flex flex-wrap gap-2">
            {moduleTabs.map(item => (
              <button key={item.key} onClick={() => setActiveModule(item.key)} className={`rounded-2xl px-5 py-3 text-sm font-bold transition ${activeModule === item.key ? 'bg-amber-700 text-white shadow-sm' : 'border border-stone-200 bg-white text-stone-600 hover:bg-stone-100'}`}>
                <span className="mr-2">{item.icon}</span>{item.label}
              </button>
            ))}
          </div>

          <div className="grid gap-6 xl:grid-cols-[280px_minmax(0,1fr)]">
            <aside className="rounded-2xl border border-stone-200 bg-white p-3 shadow-sm">
              <div className="px-3 pb-2 text-xs font-bold uppercase tracking-wider text-stone-400">Workspace Menu</div>
              <div className="space-y-1">
                {visibleResources.map(item => (
                  <button key={item.key} onClick={() => switchResource(item.key)} className={`w-full rounded-xl px-3 py-2.5 text-left text-sm font-semibold transition ${resource?.key === item.key ? 'bg-stone-900 text-white' : 'text-stone-600 hover:bg-stone-100'}`}>
                    {item.label}
                  </button>
                ))}
              </div>
            </aside>

            <section className="min-w-0 rounded-2xl border border-stone-200 bg-white shadow-sm">
              <div className="border-b border-stone-200 p-5">
                <div className="flex flex-wrap items-start justify-between gap-4">
                  <div>
                    <div className="flex items-center gap-2"><h2 className="text-lg font-bold text-stone-900">{resource?.label}</h2><span className="rounded-full bg-green-50 px-2.5 py-1 text-[11px] font-bold text-green-700">LIVE API</span></div>
                    <p className="mt-1 font-mono text-xs text-stone-400">GET {resource?.endpoint}</p>
                  </div>
                  <div className="flex gap-2">
                    {resource?.createEndpoint && resource.createPermission && can(resource.createPermission) && <button onClick={() => setShowCreate(true)} className="rounded-xl bg-amber-700 px-4 py-2 text-sm font-bold text-white hover:bg-amber-800">+ Create</button>}
                    <input value={query} onChange={e => setQuery(e.target.value)} placeholder="Cari..." className="w-40 rounded-xl border border-stone-200 px-3 py-2 text-sm outline-none focus:border-amber-600" />
                  </div>
                </div>
              </div>

              {showCreate && resource?.fields && (
                <div className="border-b border-stone-200 bg-stone-50 p-5">
                  <div className="mb-3 flex items-center justify-between"><h3 className="font-bold">Create {resource.label}</h3><button onClick={() => setShowCreate(false)} className="text-sm font-semibold text-stone-500">Tutup</button></div>
                  <div className="grid gap-3 md:grid-cols-2">
                    {resource.fields.map(field => (
                      <label key={field.name} className={field.type === 'textarea' ? 'md:col-span-2' : ''}>
                        <span className="mb-1 block text-xs font-bold text-stone-500">{field.label}{field.required ? ' *' : ''}</span>
                        {field.type === 'textarea' ? <textarea value={form[field.name] ?? ''} onChange={e => setForm(v => ({ ...v, [field.name]: e.target.value }))} placeholder={field.placeholder} rows={4} className="w-full rounded-xl border border-stone-200 px-3 py-2 text-sm outline-none focus:border-amber-600" />
                          : field.type === 'select' ? <select value={form[field.name] ?? ''} onChange={e => setForm(v => ({ ...v, [field.name]: e.target.value }))} className="w-full rounded-xl border border-stone-200 px-3 py-2 text-sm"><option value="">Pilih...</option>{field.options?.map(option => <option key={option} value={option}>{option}</option>)}</select>
                          : <input type={field.type === 'number' ? 'number' : field.type === 'date' ? 'date' : 'text'} value={form[field.name] ?? ''} onChange={e => setForm(v => ({ ...v, [field.name]: e.target.value }))} placeholder={field.placeholder} className="w-full rounded-xl border border-stone-200 px-3 py-2 text-sm outline-none focus:border-amber-600" />}
                      </label>
                    ))}
                  </div>
                  <div className="mt-4 flex justify-end"><button onClick={() => void submitCreate()} className="rounded-xl bg-stone-900 px-5 py-2.5 text-sm font-bold text-white">Simpan</button></div>
                </div>
              )}

              {loading && <div className="p-10 text-center text-sm text-stone-500">Memuat data...</div>}
              {!loading && error && <div className="m-5 rounded-2xl border border-red-200 bg-red-50 p-5 text-sm text-red-700">{error}</div>}
              {!loading && !error && filteredRows.length === 0 && <div className="p-10 text-center"><div className="text-3xl">📭</div><p className="mt-2 font-semibold text-stone-700">Belum ada data</p><p className="text-sm text-stone-500">Tidak ada record pada scope organisasi aktif atau filter pencarian.</p></div>}

              {!loading && !error && filteredRows.length > 0 && (
                <div className="divide-y divide-stone-100">
                  {filteredRows.slice(0, 100).map((row, index) => {
                    const actions = (resource?.actions ?? []).filter(action => can(action.permission));
                    return (
                      <article key={`${getRecordId(row) ?? 'row'}-${index}`} className="p-5 hover:bg-stone-50">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                          <div><div className="font-semibold text-stone-900">{titleFromRow(row)}</div><div className="mt-1 text-xs text-stone-400">ID: {getRecordId(row) ?? '-'}</div></div>
                          {actions.length > 0 && <div className="flex flex-wrap gap-2">{actions.map(action => <button key={action.label} onClick={() => void runAction(row, action)} className="rounded-lg border border-stone-200 bg-white px-3 py-1.5 text-xs font-bold text-stone-700 hover:bg-stone-100">{action.label}</button>)}</div>}
                        </div>
                        <div className="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                          {Object.entries(row).slice(0, 12).map(([key, value]) => <div key={key} className="rounded-xl bg-stone-50 px-3 py-2"><div className="text-[10px] font-bold uppercase tracking-wide text-stone-400">{key}</div><div className="mt-1 break-words text-xs text-stone-700">{typeof value === 'object' ? JSON.stringify(value) : String(value ?? '-')}</div></div>)}
                        </div>
                      </article>
                    );
                  })}
                </div>
              )}
            </section>
          </div>
        </main>
      </div>
    </div>
  );
}

import { useEffect, useState } from 'react';
import AdminSidebar from '../components/AdminSidebar';
import api from '../core/api/client';
import { extractRows } from '../core/api/normalize';
import { canAny } from '../core/auth/permissions';
import toast from 'react-hot-toast';

type Summary = {
  inventoryCount: number;
  inventoryUnits: number;
  purchaseOrders: number;
  salesOrders: number;
  pendingApprovals: number;
  receivable: number;
  payable: number;
};

const money = (value: number) => new Intl.NumberFormat('id-ID', {
  style: 'currency', currency: 'IDR', maximumFractionDigits: 0,
}).format(value || 0);

function numberOf(value: unknown): number {
  const parsed = typeof value === 'number' ? value : Number(value);
  return Number.isFinite(parsed) ? parsed : 0;
}

export default function OperationsCenter() {
  const [summary, setSummary] = useState<Summary>({
    inventoryCount: 0,
    inventoryUnits: 0,
    purchaseOrders: 0,
    salesOrders: 0,
    pendingApprovals: 0,
    receivable: 0,
    payable: 0,
  });
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let mounted = true;
    const load = async () => {
      setLoading(true);
      const results = await Promise.allSettled([
        api.get('/inventory/balances'),
        api.get('/purchasing/orders'),
        api.get('/sales/orders'),
        api.get('/sales/receivables/aging'),
        api.get('/purchasing/reports/reconciliation'),
      ]);
      if (!mounted) return;

      const inventory = results[0].status === 'fulfilled' ? extractRows<Record<string, unknown>>(results[0].value.data) : [];
      const purchaseOrders = results[1].status === 'fulfilled' ? extractRows<Record<string, unknown>>(results[1].value.data) : [];
      const salesOrders = results[2].status === 'fulfilled' ? extractRows<Record<string, unknown>>(results[2].value.data) : [];
      const receivablePayload = results[3].status === 'fulfilled' ? results[3].value.data : null;
      const payablePayload = results[4].status === 'fulfilled' ? results[4].value.data : null;

      const inventoryUnits = inventory.reduce((sum, row) => sum + numberOf(row.quantity), 0);
      const purchasePending = purchaseOrders.filter(row => ['draft', 'submitted', 'pending_approval'].includes(String(row.status ?? ''))).length;
      const salesPending = salesOrders.filter(row => ['draft', 'submitted', 'pending_approval'].includes(String(row.status ?? ''))).length;

      const receivableData = receivablePayload && typeof receivablePayload === 'object' ? (receivablePayload as { data?: unknown }).data : null;
      const payableData = payablePayload && typeof payablePayload === 'object' ? (payablePayload as { data?: unknown }).data : null;
      const receivableRows = Array.isArray(receivableData) ? receivableData as Record<string, unknown>[] : [];
      const payableRows = Array.isArray(payableData) ? payableData as Record<string, unknown>[] : [];
      const receivable = receivableRows.reduce((sum, row) => sum + numberOf(row.outstanding ?? row.balance ?? row.amount), 0);
      const payable = payableRows.reduce((sum, row) => sum + numberOf(row.outstanding ?? row.balance ?? row.amount), 0);

      setSummary({
        inventoryCount: inventory.length,
        inventoryUnits,
        purchaseOrders: purchaseOrders.length,
        salesOrders: salesOrders.length,
        pendingApprovals: purchasePending + salesPending,
        receivable,
        payable,
      });
      setLoading(false);
    };
    void load().catch(() => toast.error('Ringkasan ERP gagal dimuat.'));
    return () => { mounted = false; };
  }, []);

  const quickLinks = [
    { label: 'Purchase Order', path: '/purchasing/orders', allowed: canAny(['purchasing.order.create', 'purchasing.order.view']) },
    { label: 'Kontrol Persediaan', path: '/inventory/operations', allowed: canAny(['inventory.stock.view', 'inventory.stock.adjust']) },
    { label: 'Sales', path: '/sales/orders', allowed: canAny(['sales.order.create', 'sales.order.view']) },
    { label: 'Finance', path: '/accounting', allowed: canAny(['accounting.report.view', 'accounting.journal.view']) },
    { label: 'HRM', path: '/hrm', allowed: canAny(['hr.employee.view']) },
  ].filter(item => item.allowed);

  return (
    <div className="flex h-screen w-full bg-stone-50 font-sans text-stone-800">
      <AdminSidebar activePage="operations" />
      <div className="flex min-w-0 flex-1 flex-col overflow-hidden">
        <header className="border-b border-stone-200 bg-white px-8 py-5 shadow-sm">
          <div className="text-xs font-bold uppercase tracking-[0.18em] text-amber-700">ERP</div>
          <h1 className="mt-1 text-2xl font-bold text-stone-900">Operations Center</h1>
          <p className="mt-1 text-sm text-stone-500">Ringkasan operasional dan pintasan ke modul bisnis. Transaksi dikerjakan di workspace masing-masing.</p>
        </header>

        <main className="min-h-0 flex-1 overflow-y-auto p-6 lg:p-8">
          {loading ? (
            <div className="rounded-2xl border border-stone-200 bg-white p-10 text-center text-sm text-stone-500">Memuat ringkasan ERP…</div>
          ) : (
            <>
              <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {[
                  ['Persediaan', summary.inventoryCount, `${summary.inventoryUnits.toLocaleString('id-ID')} unit`, '📦'],
                  ['Purchase Order', summary.purchaseOrders, 'dokumen', '📝'],
                  ['Sales Order', summary.salesOrders, 'dokumen', '🛒'],
                  ['Pending Approval', summary.pendingApprovals, 'menunggu tindakan', '⏳'],
                ].map(([label, value, meta, icon]) => (
                  <div key={String(label)} className="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
                    <div className="flex items-center justify-between"><span className="text-xs font-bold uppercase tracking-wide text-stone-500">{label}</span><span className="text-xl">{icon}</span></div>
                    <div className="mt-4 text-2xl font-extrabold text-stone-900">{Number(value).toLocaleString('id-ID')}</div>
                    <div className="mt-1 text-xs text-stone-500">{meta}</div>
                  </div>
                ))}
              </section>

              <section className="mt-6 grid gap-6 xl:grid-cols-[1fr_1fr]">
                <div className="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm">
                  <h2 className="text-lg font-bold text-stone-900">Posisi Keuangan</h2>
                  <div className="mt-5 grid gap-4 sm:grid-cols-2">
                    <div className="rounded-xl bg-stone-50 p-4"><div className="text-xs font-bold uppercase tracking-wide text-stone-500">Piutang</div><div className="mt-2 text-lg font-extrabold text-stone-900">{money(summary.receivable)}</div></div>
                    <div className="rounded-xl bg-stone-50 p-4"><div className="text-xs font-bold uppercase tracking-wide text-stone-500">Hutang</div><div className="mt-2 text-lg font-extrabold text-stone-900">{money(summary.payable)}</div></div>
                  </div>
                </div>

                <div className="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm">
                  <h2 className="text-lg font-bold text-stone-900">Akses Cepat</h2>
                  <p className="mt-1 text-sm text-stone-500">Buka modul operasional sesuai permission akun.</p>
                  <div className="mt-5 grid gap-2 sm:grid-cols-2">
                    {quickLinks.map(link => <button key={link.path} onClick={() => window.location.assign(link.path)} className="rounded-xl border border-stone-200 px-4 py-3 text-left text-sm font-bold text-stone-700 hover:bg-stone-50">{link.label} →</button>)}
                  </div>
                </div>
              </section>
            </>
          )}
        </main>
      </div>
    </div>
  );
}

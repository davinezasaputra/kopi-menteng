import { useEffect, useMemo, useState } from 'react';
import toast from 'react-hot-toast';
import api from '../core/api/client';
import { extractData } from '../core/api/normalize';

interface ReceiptTemplate {
  business_name: string;
  address: string | null;
  phone: string | null;
  logo_url: string | null;
  paper_width: '58mm' | '80mm';
  show_cashier: boolean;
  show_customer: boolean;
  show_order_type: boolean;
  show_tax: boolean;
  show_discount: boolean;
  show_sku: boolean;
  show_change: boolean;
  footer_text: string | null;
  wifi_text: string | null;
  is_active: boolean;
}

const defaults: ReceiptTemplate = {
  business_name: 'KOPI MENTENG',
  address: '',
  phone: '',
  logo_url: '',
  paper_width: '80mm',
  show_cashier: true,
  show_customer: true,
  show_order_type: true,
  show_tax: true,
  show_discount: true,
  show_sku: false,
  show_change: true,
  footer_text: 'Terima kasih atas kunjungan Anda!',
  wifi_text: '',
  is_active: true,
};

const money = (value: number) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);

export default function ReceiptTemplateSettings() {
  const [form, setForm] = useState<ReceiptTemplate>(defaults);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    let alive = true;
    api.get('/pos/receipt-template')
      .then(response => {
        if (!alive) return;
        const data = extractData<ReceiptTemplate>(response.data);
        setForm({ ...defaults, ...data });
      })
      .catch(() => toast.error('Template nota belum dapat dimuat. Menggunakan template default.'))
      .finally(() => { if (alive) setLoading(false); });
    return () => { alive = false; };
  }, []);

  const update = <K extends keyof ReceiptTemplate>(key: K, value: ReceiptTemplate[K]) => setForm(current => ({ ...current, [key]: value }));

  const save = async () => {
    setSaving(true);
    try {
      const response = await api.put('/pos/receipt-template', form);
      const data = extractData<ReceiptTemplate>(response.data);
      setForm({ ...defaults, ...data });
      toast.success('Template nota berhasil disimpan.');
    } catch (error) {
      const message = error && typeof error === 'object' && 'response' in error
        ? String((error as { response?: { data?: { message?: string } } }).response?.data?.message ?? '')
        : '';
      toast.error(message || 'Gagal menyimpan template nota.');
    } finally {
      setSaving(false);
    }
  };

  const width = useMemo(() => form.paper_width === '58mm' ? 'w-[220px]' : 'w-[300px]', [form.paper_width]);

  if (loading) return <div className="min-h-screen bg-stone-50 p-8 text-stone-600">Memuat pengaturan nota...</div>;

  return (
    <main className="min-h-screen bg-stone-50 p-6 lg:p-8">
      <div className="mx-auto max-w-6xl">
        <div className="mb-6 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
          <div>
            <div className="text-xs font-bold uppercase tracking-[0.18em] text-amber-700">POS · Pengaturan</div>
            <h1 className="mt-1 text-2xl font-black text-stone-900">Template Nota & Struk Kasir</h1>
            <p className="mt-1 text-sm text-stone-500">Atur tampilan struk per outlet tanpa perlu memasukkan ID teknis.</p>
          </div>
          <button onClick={save} disabled={saving} className="rounded-xl bg-stone-900 px-5 py-3 text-sm font-bold text-white shadow-sm disabled:opacity-50">
            {saving ? 'Menyimpan...' : 'Simpan Template'}
          </button>
        </div>

        <div className="grid gap-6 lg:grid-cols-[1.15fr_.85fr]">
          <section className="rounded-3xl border border-stone-200 bg-white p-5 shadow-sm">
            <h2 className="text-base font-black text-stone-900">Identitas Outlet</h2>
            <div className="mt-4 grid gap-4 md:grid-cols-2">
              <Field label="Nama Usaha" value={form.business_name} onChange={value => update('business_name', value)} />
              <Field label="Nomor Telepon" value={form.phone ?? ''} onChange={value => update('phone', value)} />
              <Field label="Alamat" value={form.address ?? ''} onChange={value => update('address', value)} area />
              <Field label="Logo URL" value={form.logo_url ?? ''} onChange={value => update('logo_url', value)} />
            </div>

            <div className="mt-7 border-t border-stone-100 pt-5">
              <h2 className="text-base font-black text-stone-900">Ukuran & Isi Struk</h2>
              <div className="mt-4">
                <label className="block text-sm font-bold text-stone-700">Ukuran Kertas</label>
                <select value={form.paper_width} onChange={e => update('paper_width', e.target.value as ReceiptTemplate['paper_width'])} className="mt-1 w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm md:max-w-xs">
                  <option value="58mm">58 mm</option>
                  <option value="80mm">80 mm</option>
                </select>
              </div>
              <div className="mt-4 grid gap-3 sm:grid-cols-2">
                <Toggle label="Nama kasir" checked={form.show_cashier} onChange={value => update('show_cashier', value)} />
                <Toggle label="Customer" checked={form.show_customer} onChange={value => update('show_customer', value)} />
                <Toggle label="Tipe pesanan" checked={form.show_order_type} onChange={value => update('show_order_type', value)} />
                <Toggle label="PPN / Pajak" checked={form.show_tax} onChange={value => update('show_tax', value)} />
                <Toggle label="Diskon" checked={form.show_discount} onChange={value => update('show_discount', value)} />
                <Toggle label="SKU produk" checked={form.show_sku} onChange={value => update('show_sku', value)} />
                <Toggle label="Kembalian" checked={form.show_change} onChange={value => update('show_change', value)} />
                <Toggle label="Template aktif" checked={form.is_active} onChange={value => update('is_active', value)} />
              </div>
            </div>

            <div className="mt-7 border-t border-stone-100 pt-5">
              <h2 className="text-base font-black text-stone-900">Pesan Footer</h2>
              <div className="mt-4 grid gap-4 md:grid-cols-2">
                <Field label="Footer struk" value={form.footer_text ?? ''} onChange={value => update('footer_text', value)} area />
                <Field label="Informasi WiFi / Promo" value={form.wifi_text ?? ''} onChange={value => update('wifi_text', value)} area />
              </div>
            </div>
          </section>

          <section className="rounded-3xl border border-stone-200 bg-stone-100 p-5 shadow-sm">
            <div className="mb-4 flex items-center justify-between">
              <div>
                <div className="text-xs font-bold uppercase tracking-[0.16em] text-stone-500">Live Preview</div>
                <h2 className="text-base font-black text-stone-900">Tampilan struk kasir</h2>
              </div>
              <span className="rounded-full bg-white px-3 py-1 text-xs font-bold text-stone-500">{form.paper_width}</span>
            </div>
            <div className={`${width} mx-auto rounded-sm bg-white p-5 font-mono text-[11px] leading-5 text-black shadow-lg`}>
              {form.logo_url && <img src={form.logo_url} alt="Logo outlet" className="mx-auto mb-2 max-h-14 max-w-[150px] object-contain" />}
              <div className="text-center text-sm font-black">{form.business_name}</div>
              {form.address && <div className="text-center">{form.address}</div>}
              {form.phone && <div className="text-center">{form.phone}</div>}
              <div className="my-2 border-t border-dashed border-black" />
              <div>No. INV: KM-20260902-001</div>
              <div>02/09/2026 03:46</div>
              {form.show_cashier && <div>Kasir: Davin</div>}
              {form.show_customer && <div>Customer: Umum</div>}
              {form.show_order_type && <div>Order: Dine In</div>}
              <div className="my-2 border-t border-dashed border-black" />
              <ReceiptLine label="Kopi Susu x2" value={money(30000)} />
              {form.show_sku && <div>SKU: MENU-001</div>}
              <ReceiptLine label="Croissant x1" value={money(22000)} />
              {form.show_sku && <div>SKU: MENU-002</div>}
              {form.show_discount && <ReceiptLine label="Diskon" value={`-${money(3_000)}`} />}
              {form.show_tax && <ReceiptLine label="PPN" value={money(4_851)} />}
              <div className="my-2 border-t border-dashed border-black" />
              <ReceiptLine label="TOTAL" value={money(53_851)} bold />
              <ReceiptLine label="Tunai" value={money(60_000)} />
              {form.show_change && <ReceiptLine label="Kembalian" value={money(6_149)} />}
              <div className="my-2 border-t border-dashed border-black" />
              {form.footer_text && <div className="text-center whitespace-pre-line">{form.footer_text}</div>}
              {form.wifi_text && <div className="mt-1 text-center whitespace-pre-line">{form.wifi_text}</div>}
            </div>
          </section>
        </div>
      </div>
    </main>
  );
}

function Field({ label, value, onChange, area = false }: { label: string; value: string; onChange: (value: string) => void; area?: boolean }) {
  return <label className="block"><span className="mb-1 block text-xs font-bold text-stone-500">{label}</span>{area ? <textarea value={value} onChange={e => onChange(e.target.value)} rows={3} className="w-full rounded-xl border border-stone-200 px-3 py-2.5 text-sm outline-none focus:border-amber-600" /> : <input value={value} onChange={e => onChange(e.target.value)} className="w-full rounded-xl border border-stone-200 px-3 py-2.5 text-sm outline-none focus:border-amber-600" />}</label>;
}

function Toggle({ label, checked, onChange }: { label: string; checked: boolean; onChange: (value: boolean) => void }) {
  return <label className="flex cursor-pointer items-center justify-between rounded-xl border border-stone-200 px-3 py-2.5"><span className="text-sm font-semibold text-stone-700">{label}</span><input type="checkbox" checked={checked} onChange={e => onChange(e.target.checked)} className="h-4 w-4" /></label>;
}

function ReceiptLine({ label, value, bold = false }: { label: string; value: string; bold?: boolean }) {
  return <div className={`flex justify-between gap-3 ${bold ? 'font-black' : ''}`}><span>{label}</span><span>{value}</span></div>;
}

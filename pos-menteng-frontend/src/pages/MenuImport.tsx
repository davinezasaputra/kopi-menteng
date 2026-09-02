import { useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import toast from 'react-hot-toast';
import AdminSidebar from '../components/AdminSidebar';
import { api } from '../core/api/client';

type ImportFailure = { row: number; errors: string[] };
type ImportResult = { created_count: number; updated_count: number; failed_count: number; failed_rows?: ImportFailure[] };
type ApiErrorBody = { message?: unknown };

function errorMessage(error: unknown): string {
  if (!error || typeof error !== 'object' || !('response' in error)) return '';
  const response = (error as { response?: { data?: ApiErrorBody } }).response;
  return typeof response?.data?.message === 'string' ? response.data.message : '';
}

export default function MenuImport() {
  const navigate = useNavigate();
  const inputRef = useRef<HTMLInputElement>(null);
  const [file, setFile] = useState<File | null>(null);
  const [busy, setBusy] = useState(false);
  const [result, setResult] = useState<ImportResult | null>(null);

  const chooseFile = (selected: File | null) => {
    if (!selected) return;
    if (!/\.(xlsx|xls|csv)$/i.test(selected.name)) {
      toast.error('File harus .xlsx, .xls, atau .csv');
      return;
    }
    setFile(selected);
    setResult(null);
  };

  const importFile = async () => {
    if (!file) return toast.error('Pilih file Excel terlebih dahulu.');
    setBusy(true);
    try {
      const body = new FormData();
      body.append('file', file);
      const response = await api.post('/inventory/menu-import', body, { headers: { 'Content-Type': 'multipart/form-data' } });
      setResult(response.data as ImportResult);
      toast.success(response.data?.message ?? 'Import menu selesai.');
    } catch (error: unknown) {
      toast.error(errorMessage(error) || 'Import menu gagal.');
      const body = error && typeof error === 'object' && 'response' in error ? (error as { response?: { data?: unknown } }).response?.data : null;
      setResult(body && typeof body === 'object' ? body as ImportResult : null);
    } finally {
      setBusy(false);
    }
  };

  const downloadTemplate = async () => {
    try {
      const response = await api.get('/inventory/menu-import/template', { responseType: 'blob' });
      const url = URL.createObjectURL(response.data);
      const link = document.createElement('a');
      link.href = url;
      link.download = 'template_menu.csv';
      link.click();
      URL.revokeObjectURL(url);
    } catch {
      toast.error('Template gagal diunduh.');
    }
  };

  return (
    <div className="flex min-h-screen bg-stone-50 text-stone-800">
      <AdminSidebar activePage="menu-import" />
      <main className="flex-1 p-6 lg:p-8">
        <div className="mx-auto max-w-5xl">
          <div className="mb-6 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div>
              <div className="text-xs font-bold uppercase tracking-[0.18em] text-amber-700">Inventory · Menu</div>
              <h1 className="mt-1 text-2xl font-black text-stone-900">Import Menu dari Excel</h1>
              <p className="mt-1 text-sm text-stone-500">Import membuat menu baru atau memperbarui menu dengan nama yang sama pada tenant aktif.</p>
            </div>
            <button onClick={() => navigate('/inventory')} className="rounded-xl border border-stone-300 bg-white px-4 py-2.5 text-sm font-bold hover:bg-stone-50">← Kembali</button>
          </div>

          <section className="rounded-3xl border border-stone-200 bg-white p-6 shadow-sm">
            <div className="grid gap-5 lg:grid-cols-[1.1fr_.9fr]">
              <div>
                <button onClick={downloadTemplate} className="mb-5 rounded-xl border border-amber-300 bg-amber-50 px-4 py-2.5 text-sm font-bold text-amber-800">📥 Download Template CSV</button>
                <div onClick={() => inputRef.current?.click()} className="cursor-pointer rounded-2xl border-2 border-dashed border-stone-300 bg-stone-50 p-10 text-center hover:border-amber-500 hover:bg-amber-50/40">
                  <div className="text-4xl">📊</div>
                  <div className="mt-3 font-black text-stone-800">Pilih file Excel</div>
                  <div className="mt-1 text-sm text-stone-500">.xlsx · .xls · .csv, maksimal 5 MB</div>
                  {file && <div className="mt-4 inline-flex rounded-full bg-stone-900 px-4 py-2 text-xs font-bold text-white">{file.name}</div>}
                </div>
                <input ref={inputRef} type="file" accept=".xlsx,.xls,.csv" className="hidden" onChange={e => chooseFile(e.target.files?.[0] ?? null)} />
                <button disabled={!file || busy} onClick={importFile} className="mt-4 w-full rounded-xl bg-stone-900 px-5 py-3 font-bold text-white disabled:cursor-not-allowed disabled:opacity-50">{busy ? 'Mengimpor...' : '🚀 Mulai Import Menu'}</button>
              </div>

              <div className="rounded-2xl bg-stone-50 p-5">
                <h2 className="font-black">Format kolom</h2>
                <div className="mt-3 space-y-2 text-sm text-stone-600">
                  <div><b>name</b> — wajib, nama menu</div>
                  <div><b>category</b> — opsional, nama kategori</div>
                  <div><b>price</b> — wajib, harga jual angka</div>
                  <div><b>description</b> — opsional</div>
                  <div><b>is_active</b> — 1/0, default 1</div>
                </div>
                <div className="mt-5 rounded-xl border border-blue-200 bg-blue-50 p-3 text-xs text-blue-800">Import bersifat tenant-scoped. Menu tenant lain tidak disentuh.</div>
              </div>
            </div>
          </section>

          {result && <section className="mt-6 rounded-3xl border border-stone-200 bg-white p-6 shadow-sm">
            <h2 className="font-black text-stone-900">Hasil Import</h2>
            <div className="mt-4 grid grid-cols-3 gap-3">
              <Metric label="Dibuat" value={result.created_count ?? 0} />
              <Metric label="Diupdate" value={result.updated_count ?? 0} />
              <Metric label="Gagal" value={result.failed_count ?? 0} />
            </div>
            {!!result.failed_rows?.length && <div className="mt-5 overflow-x-auto rounded-xl border border-red-200"><table className="min-w-full text-sm"><thead className="bg-red-50"><tr><th className="px-3 py-2 text-left">Baris</th><th className="px-3 py-2 text-left">Error</th></tr></thead><tbody>{result.failed_rows.map((row, i) => <tr key={i} className="border-t border-red-100"><td className="px-3 py-2">{row.row}</td><td className="px-3 py-2">{row.errors.join(', ')}</td></tr>)}</tbody></table></div>}
          </section>}
        </div>
      </main>
    </div>
  );
}

function Metric({ label, value }: { label: string; value: number }) {
  return <div className="rounded-2xl border border-stone-200 bg-stone-50 p-4"><div className="text-xs font-bold uppercase tracking-wide text-stone-500">{label}</div><div className="mt-1 text-2xl font-black">{value}</div></div>;
}

import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import toast from 'react-hot-toast';
import axios, { AxiosError } from 'axios';

interface ImportResult {
  status: 'success' | 'warning';
  message: string;
  count: number;
}

interface ErrorDetail {
  row: number;
  attribute: string;
  errors: string[];
  values: Record<string, unknown>;
}

export default function RawMaterialImport() {
  const navigate = useNavigate();
  const [file, setFile] = useState<File | null>(null);
  const [loading, setLoading] = useState(false);
  const [importResult, setImportResult] = useState<ImportResult | null>(null);
  const [errors, setErrors] = useState<ErrorDetail[]>([]);

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const selectedFile = e.target.files?.[0];
    if (selectedFile) {
      const validTypes = ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv'];
      if (!validTypes.includes(selectedFile.type)) {
        toast.error('Format file harus Excel (.xlsx, .xls) atau CSV');
        setFile(null);
        return;
      }
      if (selectedFile.size > 5 * 1024 * 1024) {
        toast.error('Ukuran file maksimal 5MB');
        setFile(null);
        return;
      }
      setFile(selectedFile);
      setErrors([]);
      setImportResult(null);
    }
  };

  const handleDownloadTemplate = async () => {
    try {
      const token = localStorage.getItem('token');
      const response = await axios.get('http://localhost:8000/api/raw-materials/import/template', {
        headers: { 'Authorization': `Bearer ${token}` },
        responseType: 'blob'
      });
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', 'template_bahan_baku.csv');
      document.body.appendChild(link);
      link.click();
      link.parentNode?.removeChild(link);
      toast.success('Template berhasil diunduh');
    } catch (error) {
      console.error(error);
      toast.error('Gagal mengunduh template');
    }
  };

  const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();

    if (!file) {
      toast.error('Pilih file terlebih dahulu');
      return;
    }

    setLoading(true);
    const formData = new FormData();
    formData.append('file', file);

    try {
      const token = localStorage.getItem('token');
      const response = await axios.post('http://localhost:8000/api/raw-materials/import', formData, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'multipart/form-data',
        },
      });

      if (response.data.status === 'success') {
        toast.success(`${response.data.imported_count || 0} bahan baku berhasil diimport`);
        setImportResult({
          status: 'success',
          message: response.data.message,
          count: response.data.imported_count,
        });
        setFile(null);
        setTimeout(() => navigate('/raw-materials'), 1500);
      } else if (response.data.status === 'warning') {
        toast((
          <span>
            ⚠ {response.data.imported_count || 0} bahan berhasil, ada yang gagal
          </span>
        ));
        setImportResult({
          status: 'warning',
          message: response.data.message,
          count: response.data.imported_count,
        });
        setErrors(response.data.failed_rows || []);
      }
    } catch (error) {
      console.error(error);
      const axiosError = error as AxiosError<{ errors?: ErrorDetail[]; message?: string }>;
      const errData = axiosError.response?.data;
      if (errData?.errors) {
        setErrors(errData.errors);
        toast.error('Validasi import gagal, periksa data di bawah');
      } else {
        toast.error(errData?.message || 'Gagal mengimport file');
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="p-6 bg-white rounded-lg shadow">
      <h1 className="text-2xl font-bold mb-4">Import Bahan Baku</h1>

      <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
        <h2 className="font-semibold text-blue-900 mb-2">Panduan Import:</h2>
        <ul className="text-sm text-blue-800 space-y-1 list-disc list-inside">
          <li>Download template terlebih dahulu untuk melihat format yang benar</li>
          <li>Isi data dengan kolom: name, category, unit, stock</li>
          <li>Category hanya boleh "bar" atau "dapur"</li>
          <li>Stock harus berupa angka (tidak boleh negatif)</li>
          <li>File maksimal 5MB, format Excel (.xlsx, .xls) atau CSV</li>
        </ul>
      </div>

      <button
        onClick={handleDownloadTemplate}
        className="mb-6 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-lg font-medium transition"
      >
        📥 Download Template
      </button>

      <form onSubmit={handleSubmit} className="mb-6">
        <div className="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-blue-400 transition">
          <input
            type="file"
            accept=".xlsx,.xls,.csv"
            onChange={handleFileChange}
            className="hidden"
            id="fileInput"
          />
          <label htmlFor="fileInput" className="cursor-pointer">
            <div className="text-4xl mb-2">📁</div>
            <p className="text-gray-600">
              {file ? file.name : 'Klik atau drag file Excel/CSV di sini'}
            </p>
            <p className="text-xs text-gray-400 mt-2">Ukuran maksimal 5MB</p>
          </label>
        </div>

        {file && (
          <div className="mt-4 p-3 bg-green-50 border border-green-200 rounded-lg">
            <p className="text-sm text-green-800">
              ✓ File dipilih: <span className="font-semibold">{file.name}</span> ({(file.size / 1024).toFixed(2)} KB)
            </p>
          </div>
        )}

        <button
          type="submit"
          disabled={!file || loading}
          className="mt-4 w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white rounded-lg font-medium transition"
        >
          {loading ? '⏳ Mengimport...' : '✓ Import Sekarang'}
        </button>
      </form>

      {importResult && (
        <div className={`p-4 rounded-lg mb-6 ${importResult.status === 'success' ? 'bg-green-50 border border-green-200' : 'bg-yellow-50 border border-yellow-200'}`}>
          <p className={`${importResult.status === 'success' ? 'text-green-800' : 'text-yellow-800'} font-semibold`}>
            {importResult.status === 'success' ? '✓ Berhasil!' : '⚠ Peringatan'}
          </p>
          <p className={`text-sm ${importResult.status === 'success' ? 'text-green-700' : 'text-yellow-700'}`}>
            {importResult.message} ({importResult.count} bahan berhasil)
          </p>
        </div>
      )}

      {errors.length > 0 && (
        <div className="bg-red-50 border border-red-200 rounded-lg p-4">
          <h3 className="font-semibold text-red-900 mb-3">Data yang Gagal Import ({errors.length} baris):</h3>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-red-100">
                  <th className="text-left px-3 py-2">Baris</th>
                  <th className="text-left px-3 py-2">Kolom</th>
                  <th className="text-left px-3 py-2">Nilai</th>
                  <th className="text-left px-3 py-2">Error</th>
                </tr>
              </thead>
              <tbody>
                {errors.map((error, idx) => (
                  <tr key={idx} className="border-t border-red-200">
                    <td className="px-3 py-2 text-red-700">{error.row}</td>
                    <td className="px-3 py-2 text-red-700">{error.attribute}</td>
                    <td className="px-3 py-2 text-red-700">{JSON.stringify(error.values)}</td>
                    <td className="px-3 py-2">
                      {Array.isArray(error.errors) ? error.errors.join(', ') : String(error.errors)}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  );
}
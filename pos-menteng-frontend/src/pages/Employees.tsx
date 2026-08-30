import { useState, useEffect } from 'react';
import axios from 'axios';
import toast from 'react-hot-toast';
import AdminSidebar from '../components/AdminSidebar';

type EmployeeForm = {
  name: string;
  tanggal_lahir: string;
  WA: string;
  position: string;
  join_date: string;
  base_sallary: string;
  status: 'active' | 'inactive';
};

const emptyForm: EmployeeForm = {
  name: '',
  tanggal_lahir: '',
  WA: '',
  position: '',
  join_date: '',
  base_sallary: '',
  status: 'active',
};

const formatRp = (value: number | string) => {
  const amount = Number(value || 0);
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(amount);
};

const formatDate = (value?: string) => {
  if (!value) return '-';
  return new Date(value).toLocaleDateString('id-ID');
};

export default function Employees() {
  const [employees, setEmployees] = useState<any[]>([]);
  const [query, setQuery] = useState('');
  const [showModal, setShowModal] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [formData, setFormData] = useState<EmployeeForm>(emptyForm);

  useEffect(() => {
    fetchEmployees();
  }, []);

  const fetchEmployees = async (keyword = '') => {
    try {
      const url = keyword
        ? `http://localhost:8000/api/employees/search?q=${encodeURIComponent(keyword)}`
        : 'http://localhost:8000/api/employees';

      const response = await axios.get(url, {
        headers: { Authorization: `Bearer ${localStorage.getItem('token')}` },
      });

      setEmployees(response.data.data || []);
    } catch (error) {
      toast.error('Gagal memuat data karyawan.');
    }
  };

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    fetchEmployees(query.trim());
  };

  const openCreateModal = () => {
    setEditingId(null);
    setFormData(emptyForm);
    setShowModal(true);
  };

  const openEditModal = (employee: any) => {
    setEditingId(employee.id);
    setFormData({
      name: employee.name || '',
      tanggal_lahir: employee.tanggal_lahir || '',
      WA: employee.WA || '',
      position: employee.position || '',
      join_date: employee.join_date || '',
      base_sallary: String(employee.base_sallary ?? ''),
      status: employee.status || 'active',
    });
    setShowModal(true);
  };

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();

    const payload = {
      ...formData,
      base_sallary: Number(formData.base_sallary || 0),
    };

    const toastId = toast.loading(editingId ? 'Memperbarui karyawan...' : 'Menyimpan karyawan...');

    try {
      if (editingId) {
        await axios.put(`http://localhost:8000/api/employees/${editingId}`, payload, {
          headers: { Authorization: `Bearer ${localStorage.getItem('token')}` },
        });
        toast.success('Data karyawan berhasil diperbarui.', { id: toastId });
      } else {
        await axios.post('http://localhost:8000/api/employees', payload, {
          headers: { Authorization: `Bearer ${localStorage.getItem('token')}` },
        });
        toast.success('Data karyawan berhasil ditambahkan.', { id: toastId });
      }

      setShowModal(false);
      setEditingId(null);
      setFormData(emptyForm);
      fetchEmployees(query.trim());
    } catch (error: any) {
      toast.error(error.response?.data?.message || 'Gagal menyimpan data karyawan.', { id: toastId });
    }
  };

  const handleDelete = async (id: string) => {
    if (!window.confirm('Yakin ingin menghapus data karyawan ini?')) return;

    try {
      await axios.delete(`http://localhost:8000/api/employees/${id}`, {
        headers: { Authorization: `Bearer ${localStorage.getItem('token')}` },
      });
      toast.success('Data karyawan dihapus.');
      fetchEmployees(query.trim());
    } catch (error: any) {
      toast.error(error.response?.data?.message || 'Gagal menghapus data karyawan.');
    }
  };

  return (
    <div className="flex h-screen w-full bg-stone-50 font-sans text-stone-800">
      <AdminSidebar activePage="employees" />

      <div className="flex-1 flex flex-col overflow-hidden">
        <header className="h-20 bg-white border-b border-stone-200 flex items-center justify-between px-8 shadow-sm gap-4">
          <h1 className="text-xl font-bold text-stone-800">Manajemen Karyawan</h1>

          <div className="flex items-center gap-3">
            <form onSubmit={handleSearch} className="flex items-center gap-2">
              <input
                type="text"
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                placeholder="Cari nama / WA / posisi"
                className="rounded-xl border border-stone-300 bg-stone-50 px-3 py-2 text-sm outline-none focus:border-amber-500"
              />
              <button type="submit" className="rounded-xl bg-stone-200 px-3 py-2 text-sm font-bold text-stone-700 hover:bg-stone-300">
                Cari
              </button>
            </form>

            <button
              onClick={openCreateModal}
              className="bg-amber-700 hover:bg-amber-800 text-white px-5 py-2.5 rounded-xl font-bold transition shadow-md"
            >
              + Tambah Karyawan
            </button>
          </div>
        </header>

        <main className="flex-1 overflow-y-auto p-8">
          <div className="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div className="bg-white rounded-2xl border border-stone-200 p-4 shadow-sm">
              <div className="text-xs uppercase tracking-wide text-stone-500">Total Karyawan</div>
              <div className="mt-2 text-3xl font-black text-stone-800">{employees.length}</div>
            </div>
            <div className="bg-white rounded-2xl border border-stone-200 p-4 shadow-sm">
              <div className="text-xs uppercase tracking-wide text-stone-500">Aktif</div>
              <div className="mt-2 text-3xl font-black text-green-600">
                {employees.filter((emp) => emp.status === 'active').length}
              </div>
            </div>
            <div className="bg-white rounded-2xl border border-stone-200 p-4 shadow-sm">
              <div className="text-xs uppercase tracking-wide text-stone-500">Gaji Total</div>
              <div className="mt-2 text-lg font-black text-amber-700">
                {formatRp(employees.reduce((sum, emp) => sum + Number(emp.base_sallary || 0), 0))}
              </div>
            </div>
          </div>

          <div className="bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden">
            <table className="w-full text-left border-collapse">
              <thead className="bg-stone-100 text-stone-500 text-sm uppercase tracking-wider">
                <tr>
                  <th className="p-4 font-medium">Nama</th>
                  <th className="p-4 font-medium">WA</th>
                  <th className="p-4 font-medium">Posisi</th>
                  <th className="p-4 font-medium">Status</th>
                  <th className="p-4 font-medium text-right">Gaji Pokok</th>
                  <th className="p-4 font-medium">Join Date</th>
                  <th className="p-4 font-medium text-center">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-stone-100">
                {employees.map((employee) => (
                  <tr key={employee.id} className="hover:bg-stone-50 transition">
                    <td className="p-4 font-bold text-stone-800">{employee.name}</td>
                    <td className="p-4 text-stone-600">{employee.WA}</td>
                    <td className="p-4 text-stone-600">{employee.position}</td>
                    <td className="p-4">
                      <span
                        className={`px-3 py-1 rounded-full text-xs font-bold uppercase ${
                          employee.status === 'active'
                            ? 'bg-green-100 text-green-700'
                            : 'bg-red-100 text-red-700'
                        }`}
                      >
                        {employee.status === 'active' ? 'Aktif' : 'Nonaktif'}
                      </span>
                    </td>
                    <td className="p-4 text-right font-black text-amber-700">
                      {formatRp(employee.base_sallary)}
                    </td>
                    <td className="p-4 text-stone-600">{formatDate(employee.join_date)}</td>
                    <td className="p-4">
                      <div className="flex justify-center gap-2">
                        <button
                          onClick={() => openEditModal(employee)}
                          className="rounded-lg bg-blue-100 px-3 py-1.5 text-xs font-bold text-blue-700 hover:bg-blue-200"
                        >
                          Edit
                        </button>
                        <button
                          onClick={() => handleDelete(employee.id)}
                          className="rounded-lg bg-red-100 px-3 py-1.5 text-xs font-bold text-red-700 hover:bg-red-200"
                        >
                          Hapus
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </main>
      </div>

      {showModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-stone-900/60 backdrop-blur-sm">
          <div className="w-full max-w-2xl rounded-2xl bg-white p-8 shadow-2xl">
            <h2 className="mb-6 text-2xl font-bold text-stone-800">
              {editingId ? 'Edit Karyawan' : 'Tambah Karyawan Baru'}
            </h2>

            <form onSubmit={handleSave} className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="md:col-span-2">
                <label className="block text-sm font-bold text-stone-600 mb-1">Nama Lengkap</label>
                <input
                  type="text"
                  required
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                  className="w-full rounded-xl border border-stone-300 p-3 outline-none focus:border-amber-500"
                />
              </div>

              <div>
                <label className="block text-sm font-bold text-stone-600 mb-1">Tanggal Lahir</label>
                <input
                  type="date"
                  value={formData.tanggal_lahir}
                  onChange={(e) => setFormData({ ...formData, tanggal_lahir: e.target.value })}
                  className="w-full rounded-xl border border-stone-300 p-3 outline-none focus:border-amber-500"
                />
              </div>

              <div>
                <label className="block text-sm font-bold text-stone-600 mb-1">Nomor WA</label>
                <input
                  type="text"
                  required
                  value={formData.WA}
                  onChange={(e) => setFormData({ ...formData, WA: e.target.value })}
                  placeholder="08xxxx"
                  className="w-full rounded-xl border border-stone-300 p-3 outline-none focus:border-amber-500"
                />
              </div>

              <div>
                <label className="block text-sm font-bold text-stone-600 mb-1">Posisi / Jabatan</label>
                <input
                  type="text"
                  required
                  value={formData.position}
                  onChange={(e) => setFormData({ ...formData, position: e.target.value })}
                  placeholder="Barista, Kasir, Manager"
                  className="w-full rounded-xl border border-stone-300 p-3 outline-none focus:border-amber-500"
                />
              </div>

              <div>
                <label className="block text-sm font-bold text-stone-600 mb-1">Tanggal Bergabung</label>
                <input
                  type="date"
                  value={formData.join_date}
                  onChange={(e) => setFormData({ ...formData, join_date: e.target.value })}
                  className="w-full rounded-xl border border-stone-300 p-3 outline-none focus:border-amber-500"
                />
              </div>

              <div>
                <label className="block text-sm font-bold text-stone-600 mb-1">Gaji Pokok</label>
                <input
                  type="number"
                  required
                  min="0"
                  value={formData.base_sallary}
                  onChange={(e) => setFormData({ ...formData, base_sallary: e.target.value })}
                  className="w-full rounded-xl border border-stone-300 p-3 outline-none focus:border-amber-500"
                />
              </div>

              <div>
                <label className="block text-sm font-bold text-stone-600 mb-1">Status</label>
                <select
                  value={formData.status}
                  onChange={(e) => setFormData({ ...formData, status: e.target.value as 'active' | 'inactive' })}
                  className="w-full rounded-xl border border-stone-300 p-3 outline-none focus:border-amber-500"
                >
                  <option value="active">Aktif</option>
                  <option value="inactive">Nonaktif</option>
                </select>
              </div>

              <div className="md:col-span-2 mt-4 flex gap-3 pt-4 border-t border-stone-100">
                <button
                  type="button"
                  onClick={() => setShowModal(false)}
                  className="w-1/3 rounded-xl bg-stone-100 py-3 font-bold text-stone-500"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  className="w-2/3 rounded-xl bg-amber-700 py-3 font-bold text-white shadow-md"
                >
                  {editingId ? 'Simpan Perubahan' : 'Simpan Karyawan'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}

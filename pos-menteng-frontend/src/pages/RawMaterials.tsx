import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import axios from 'axios';

const [showRestockModal, setShowRestockModal] = useState(false);
const [selectedMaterial, setSelectedMaterial] = useState<any>(null);
const [restockQty, setRestockQty] = useState('');
const [restockCost, setRestockCost] = useState('');
const [receiptFile, setReceiptFile] = useState<File | null>(null);

interface RawMaterial {
  id: string;
  name: string;
  category: string;
  unit: string;
  stock: number;
  is_shopping_requested: boolean;
}

export default function RawMaterials() {
  const navigate = useNavigate();
  const [materials, setMaterials] = useState<RawMaterial[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [activeTab, setActiveTab] = useState<'all' | 'bar' | 'dapur' | 'shopping'>('all');

  // State Modal Form
  const [showModal, setShowModal] = useState(false);
  const [isEditing, setIsEditing] = useState(false);
  const [editId, setEditId] = useState<string | null>(null);
  const [formData, setFormData] = useState({ name: '', category: 'bar', unit: 'gram', stock: '' });

  useEffect(() => {
    const token = localStorage.getItem('token');
    if (!token) navigate('/');
    else fetchMaterials();
  }, [navigate]);

  const fetchMaterials = async () => {
    setIsLoading(true);
    try {
      const token = localStorage.getItem('token');
      const response = await axios.get('http://localhost:8000/api/raw-materials', {
        headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
      });
      setMaterials(response.data.data);
    } catch (error) {
      console.error("Gagal mengambil data bahan baku", error);
    } finally {
      setIsLoading(false);
    }
  };

  const handleRestockSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const token = localStorage.getItem('token');
    
    // Gunakan FormData karena kita mengirim file gambar
    const formData = new FormData();
    formData.append('quantity_added', restockQty);
    formData.append('total_cost', restockCost);
    if (receiptFile) {
      formData.append('receipt_image', receiptFile);
    }

    try {
      await axios.post(`http://localhost:8000/api/raw-materials/${selectedMaterial.id}/restock`, formData, {
        headers: { 
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'multipart/form-data' 
        }
      });
      setShowRestockModal(false);
      setRestockQty('');
      setRestockCost('');
      setReceiptFile(null);
      fetchMaterials(); // Refresh tabel
    } catch (error) {
      alert('Gagal memperbarui stok dan HPP.');
    }
  };

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    const token = localStorage.getItem('token');
    const payload = {
      name: formData.name,
      category: formData.category,
      unit: formData.unit,
      stock: Number(formData.stock)
    };

    try {
      if (isEditing && editId) {
        await axios.put(`http://localhost:8000/api/raw-materials/${editId}`, payload, {
          headers: { 'Authorization': `Bearer ${token}` }
        });
      } else {
        await axios.post('http://localhost:8000/api/raw-materials', payload, {
          headers: { 'Authorization': `Bearer ${token}` }
        });
      }
      setShowModal(false);
      fetchMaterials();
    } catch (error) {
      alert('Gagal menyimpan data.');
    }
  };

  const handleDelete = async (id: string) => {
    if (!window.confirm('Yakin ingin menghapus bahan ini?')) return;
    const token = localStorage.getItem('token');
    try {
      await axios.delete(`http://localhost:8000/api/raw-materials/${id}`, {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      fetchMaterials();
    } catch (error) {
      alert('Gagal menghapus.');
    }
  };

  const toggleShoppingRequest = async (id: string) => {
    // 1. OPTIMISTIC UPDATE: Ubah warna & status di layar saat itu juga! (Tanpa loading)
    setMaterials(prevMaterials => 
      prevMaterials.map(item => 
        item.id === id ? { ...item, is_shopping_requested: !item.is_shopping_requested } : item
      )
    );

    const token = localStorage.getItem('token');
    
    try {
      // 2. Kirim perintah ke Laravel secara diam-diam di latar belakang
      await axios.put(`http://localhost:8000/api/raw-materials/${id}/toggle-request`, {}, {
        headers: { 
          'Authorization': `Bearer ${token}`,
          'Accept': 'application/json' 
        }
      });
      
      // CATATAN: Kita TIDAK LAGI memanggil fetchMaterials() di sini, 
      // sehingga layar tidak akan berkedip / loading ulang.

    } catch (error: any) {
      // 3. JIKA GAGAL: Kembalikan status tombol ke keadaan semula dan beri tahu user
      setMaterials(prevMaterials => 
        prevMaterials.map(item => 
          item.id === id ? { ...item, is_shopping_requested: !item.is_shopping_requested } : item
        )
      );
      alert(error.response?.data?.message || 'Gagal menyinkronkan status belanja ke server.');
      console.error(error);
    }
  };

  const openAddModal = () => {
    setIsEditing(false);
    setEditId(null);
    setFormData({ name: '', category: 'bar', unit: 'gram', stock: '' });
    setShowModal(true);
  };

  const openEditModal = (item: RawMaterial) => {
    setIsEditing(true);
    setEditId(item.id);
    setFormData({ name: item.name, category: item.category, unit: item.unit, stock: item.stock.toString() });
    setShowModal(true);
  };

  // Filter Data Berdasarkan Tab Aktif
  const filteredMaterials = materials.filter(item => {
    if (activeTab === 'bar') return item.category === 'bar';
    if (activeTab === 'dapur') return item.category === 'dapur';
    if (activeTab === 'shopping') return item.is_shopping_requested === true;
    return true; // 'all'
  });

  return (
    <div className="flex h-screen w-full bg-stone-50 font-sans text-stone-800">
      
      {/* SIDEBAR */}
      <div className="w-64 bg-stone-900 text-stone-300 flex flex-col">
        <div className="p-6 border-b border-stone-800 flex items-center gap-3">
          <div className="flex h-8 w-8 items-center justify-center rounded bg-amber-700 font-bold text-white text-xs">KM</div>
          <span className="font-bold text-white tracking-wide">Backoffice</span>
        </div>
        <nav className="flex-1 p-4 space-y-2">
          <button onClick={() => navigate('/pos')} className="w-full flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-stone-800 hover:text-white transition text-left">
            <span>🛒</span> Kasir (POS)
          </button>
          <button onClick={() => navigate('/inventory')} className="w-full flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-stone-800 hover:text-white transition text-left">
            <span>📦</span> Data Produk
          </button>
          <button className="w-full flex items-center gap-3 px-4 py-3 rounded-xl bg-amber-700/20 text-amber-500 font-medium transition text-left">
            <span>🫙</span> Bahan Baku
          </button>
          <button onClick={() => navigate('/history')} className="w-full flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-stone-800 hover:text-white transition text-left">
            <span>🧾</span> Riwayat & Laporan
          </button>
        </nav>
      </div>

      {/* KONTEN UTAMA */}
      <div className="flex-1 flex flex-col overflow-hidden">
        <header className="h-20 bg-white border-b border-stone-200 flex items-center justify-between px-8 shadow-sm">
          <h1 className="text-xl font-bold text-stone-800">Stok Bahan Baku & Dapur</h1>
          <button onClick={openAddModal} className="bg-amber-700 hover:bg-amber-800 text-white px-5 py-2.5 rounded-xl font-bold transition shadow-md">
            + Tambah Bahan
          </button>
        </header>

        <main className="flex-1 overflow-y-auto p-8">
          
          {/* TABS FILTER */}
          <div className="mb-6 flex gap-2">
            <button onClick={() => setActiveTab('all')} className={`px-5 py-2.5 rounded-xl font-bold text-sm transition-all ${activeTab === 'all' ? 'bg-stone-800 text-white shadow-md' : 'bg-white text-stone-500 border border-stone-200 hover:bg-stone-100'}`}>Semua Bahan</button>
            <button onClick={() => setActiveTab('bar')} className={`px-5 py-2.5 rounded-xl font-bold text-sm transition-all ${activeTab === 'bar' ? 'bg-amber-700 text-white shadow-md' : 'bg-white text-stone-500 border border-stone-200 hover:bg-stone-100'}`}>Bahan Bar</button>
            <button onClick={() => setActiveTab('dapur')} className={`px-5 py-2.5 rounded-xl font-bold text-sm transition-all ${activeTab === 'dapur' ? 'bg-amber-700 text-white shadow-md' : 'bg-white text-stone-500 border border-stone-200 hover:bg-stone-100'}`}>Bahan Dapur</button>
            <button onClick={() => setActiveTab('shopping')} className={`px-5 py-2.5 rounded-xl font-bold text-sm transition-all flex items-center gap-2 ${activeTab === 'shopping' ? 'bg-red-500 text-white shadow-md' : 'bg-red-50 text-red-500 border border-red-200 hover:bg-red-100'}`}>
              🛒 Daftar Belanja <span className="bg-white text-red-500 rounded-full px-2 py-0.5 text-xs">{materials.filter(m => m.is_shopping_requested).length}</span>
            </button>
          </div>

          <div className="bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden">
            <table className="w-full text-left border-collapse">
              <thead>
                <tr className="bg-stone-100 text-stone-500 text-sm uppercase tracking-wider border-b border-stone-200">
                  <th className="p-4 font-medium text-center w-16">Status</th>
                  <th className="p-4 font-medium">Nama Bahan</th>
                  <th className="p-4 font-medium">Kategori</th>
                  <th className="p-4 font-medium">Sisa Stok</th>
                  <th className="p-4 font-medium text-center w-32">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-stone-100">
                {isLoading ? (
                  <tr><td colSpan={5} className="p-8 text-center text-stone-500">Memuat data bahan baku...</td></tr>
                ) : filteredMaterials.length === 0 ? (
                  <tr><td colSpan={5} className="p-8 text-center text-stone-500">Data tidak ditemukan.</td></tr>
                ) : (
                  filteredMaterials.map((item) => (
                    <tr key={item.id} className={`transition ${item.is_shopping_requested ? 'bg-red-50/50' : 'hover:bg-stone-50'}`}>
                      <td className="p-4 text-center">
                        <button 
                          onClick={() => toggleShoppingRequest(item.id)}
                          className={`w-8 h-8 rounded-lg flex items-center justify-center transition-all shadow-sm border ${item.is_shopping_requested ? 'bg-red-500 border-red-600 text-white' : 'bg-white border-stone-300 text-stone-300 hover:border-red-400 hover:text-red-400'}`}
                          title={item.is_shopping_requested ? 'Batal Request Belanja' : 'Request Beli Bahan Ini'}
                        >
                          🛒
                        </button>
                      </td>
                      <td className="p-4 font-bold text-stone-700">
                        {item.name}
                        {item.is_shopping_requested && <span className="ml-2 text-xs text-red-500 font-normal italic">Butuh Restock</span>}
                      </td>
                      <td className="p-4">
                        <span className={`px-3 py-1 rounded-full text-xs font-bold uppercase ${item.category === 'bar' ? 'bg-blue-100 text-blue-700' : 'bg-orange-100 text-orange-700'}`}>
                          {item.category}
                        </span>
                      </td>
                      <td className="p-4 font-bold text-amber-700">
                        {item.stock} <span className="text-sm font-medium text-stone-400">{item.unit}</span>
                      </td>
                      <td className="p-4 flex justify-center gap-2">
                        <button onClick={() => openEditModal(item)} className="p-2 text-blue-500 hover:bg-blue-50 rounded-lg transition" title="Edit / Restock">✏️</button>
                        <button onClick={() => {
                          setSelectedMaterial(item);
                          setShowRestockModal(true);
                        }} className="p-2 text-green-500 hover:bg-green-50 rounded-lg transition" title="Restock">📦</button>
                        <button onClick={() => handleDelete(item.id)} className="p-2 text-red-500 hover:bg-red-50 rounded-lg transition" title="Hapus">🗑️</button>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </main>
      </div>

      {/* MODAL INPUT BAHAN */}
      {showModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-stone-900/60 backdrop-blur-sm">
          <div className="w-full max-w-md rounded-2xl bg-white p-8 shadow-2xl">
            <h2 className="mb-6 text-2xl font-bold text-stone-800">{isEditing ? 'Edit Bahan & Restock' : 'Input Bahan Baru'}</h2>
            <form onSubmit={handleSave} className="space-y-4">
              <div>
                <label className="block text-sm font-bold text-stone-600 mb-1">Nama Bahan</label>
                <input type="text" required value={formData.name} onChange={e => setFormData({...formData, name: e.target.value})} className="w-full rounded-xl border border-stone-300 p-3 outline-none focus:border-amber-500" placeholder="Misal: Biji Kopi Robusta" />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-bold text-stone-600 mb-1">Lokasi</label>
                  <select value={formData.category} onChange={e => setFormData({...formData, category: e.target.value})} className="w-full rounded-xl border border-stone-300 p-3 outline-none focus:border-amber-500">
                    <option value="bar">Bar (Minuman)</option>
                    <option value="dapur">Dapur (Makanan)</option>
                  </select>
                </div>
                <div>
                  <label className="block text-sm font-bold text-stone-600 mb-1">Satuan</label>
                  <select value={formData.unit} onChange={e => setFormData({...formData, unit: e.target.value})} className="w-full rounded-xl border border-stone-300 p-3 outline-none focus:border-amber-500">
                    <option value="gram">Gram</option>
                    <option value="kg">Kilogram</option>
                    <option value="ml">Mililiter</option>
                    <option value="liter">Liter</option>
                    <option value="pcs">Pcs / Buah</option>
                  </select>
                </div>
              </div>
              <div>
                <label className="block text-sm font-bold text-stone-600 mb-1">Stok Fisik Saat Ini</label>
                <input type="number" step="0.01" required min="0" value={formData.stock} onChange={e => setFormData({...formData, stock: e.target.value})} className="w-full rounded-xl border border-stone-300 p-3 outline-none focus:border-amber-500" placeholder="0" />
              </div>
              <div className="mt-6 flex gap-4 pt-4 border-t border-stone-100">
                <button type="button" onClick={() => setShowModal(false)} className="w-1/3 rounded-xl bg-stone-100 py-3 font-bold text-stone-500 hover:bg-stone-200">Batal</button>
                <button type="submit" className="w-2/3 rounded-xl bg-amber-700 py-3 font-bold text-white hover:bg-amber-800 shadow-md">Simpan Data</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {showRestockModal && selectedMaterial && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-stone-900/60 backdrop-blur-sm">
          <div className="w-full max-w-md rounded-2xl bg-white p-8 shadow-2xl">
            <h2 className="text-xl font-bold text-stone-800 mb-2">Restock: {selectedMaterial.name}</h2>
            <p className="text-sm text-stone-500 mb-6">Masukkan data belanja untuk memperbarui HPP otomatis.</p>
            
            <form onSubmit={handleRestockSubmit} className="space-y-4">
              <div>
                <label className="text-sm font-bold text-stone-600 block mb-1">Kuantitas Masuk ({selectedMaterial.unit})</label>
                <input type="number" value={restockQty} onChange={e => setRestockQty(e.target.value)} required className="w-full border rounded-lg p-3 bg-stone-50" />
              </div>
              <div>
                <label className="text-sm font-bold text-stone-600 block mb-1">Total Harga Beli (Rp)</label>
                <input type="number" value={restockCost} onChange={e => setRestockCost(e.target.value)} required className="w-full border rounded-lg p-3 bg-stone-50" />
              </div>
              <div>
                <label className="text-sm font-bold text-stone-600 block mb-1">Foto Struk (Opsional)</label>
                <input type="file" accept="image/*" onChange={e => setReceiptFile(e.target.files?.[0] || null)} className="w-full border rounded-lg p-2 bg-stone-50 text-sm" />
              </div>
              
              <div className="flex gap-4 mt-6">
                <button type="button" onClick={() => setShowRestockModal(false)} className="w-1/3 py-3 rounded-lg bg-stone-100 font-bold text-stone-600">Batal</button>
                <button type="submit" className="w-2/3 py-3 rounded-lg bg-amber-700 font-bold text-white shadow-lg">Simpan & Hitung HPP</button>
              </div>
            </form>
          </div>
        </div>
      )}

    </div>
  );
}
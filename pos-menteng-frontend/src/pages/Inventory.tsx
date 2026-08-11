import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import axios from 'axios';

interface Product {
  id: string;
  name: string;
  price: number;
  stock: number;
  category_id: string;
}

interface Category {
  id: string;
  name: string;
}

export default function Inventory() {
  const navigate = useNavigate();
  const [products, setProducts] = useState<Product[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  // State Modal & Form
  const [showModal, setShowModal] = useState(false);
  const [isEditing, setIsEditing] = useState(false);
  const [editId, setEditId] = useState<string | null>(null);
  const [formData, setFormData] = useState({ name: '', price: '', stock: '', category_id: '' });

  useEffect(() => {
    const token = localStorage.getItem('token');
    if (!token) {
      navigate('/');
      return;
    }
    fetchCategories();
    fetchProducts();
  }, [navigate]);

  const fetchCategories = async () => {
    const token = localStorage.getItem('token');
    try {
      const response = await axios.get('http://localhost:8000/api/categories', {
        headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
      });
      // Antisipasi struktur respons Laravel (terkadang di dalam .data, terkadang langsung)
      const catData = response.data.data || response.data;
      setCategories(catData);
    } catch (error) {
      console.error("Gagal mengambil data kategori", error);
    }
  };

  const fetchProducts = async () => {
    const token = localStorage.getItem('token');
    setIsLoading(true);
    try {
      const response = await axios.get('http://localhost:8000/api/products', {
        headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
      });
      setProducts(response.data.data);
    } catch (error) {
      console.error("Gagal mengambil data produk", error);
    } finally {
      setIsLoading(false);
    }
  };

  // --- TRIGGER TAMBAH PRODUK ---
  const handleAddClick = () => {
    setIsEditing(false);
    setEditId(null);
    setFormData({ 
      name: '', 
      price: '', 
      stock: '', 
      category_id: '' 
    });
    setShowModal(true);
  };

  // --- TRIGGER EDIT PRODUK ---
  const handleEditClick = (product: Product) => {
    setIsEditing(true);
    setEditId(product.id);
    setFormData({ 
      name: product.name, 
      price: product.price.toString(), 
      stock: product.stock.toString(), 
      category_id: product.category_id ? product.category_id.toString() : (categories.length > 0 ? categories[0].id : '')
    });
    setShowModal(true);
  };

  // --- SIMPAN (POST) ATAU PERBARUI (PUT) ---
  // --- SIMPAN (POST) ATAU PERBARUI (PUT) ---
  const handleSaveProduct = async (e: React.FormEvent) => {
    e.preventDefault();
    
    // PENJAGA KETAT: Blokir form jika kategori belum dipilih
    if (!formData.category_id || formData.category_id === '' || formData.category_id === '0') {
      alert('⚠️ WAJIB: Mohon pilih Kategori Produk terlebih dahulu dari dropdown!');
      return; // Hentikan eksekusi, jangan kirim ke Laravel
    }

    const token = localStorage.getItem('token');
    
    try {
      const payload = {
        name: formData.name,
        price: Number(formData.price),
        stock: Number(formData.stock),
        category_id: formData.category_id
      };

      if (isEditing && editId) {
        // UPDATE DATA
        await axios.put(`http://localhost:8000/api/products/${editId}`, payload, {
          headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
        });
      } else {
        // TAMBAH DATA BARU
        await axios.post('http://localhost:8000/api/products', payload, {
          headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
        });
      }
      
      setShowModal(false);
      fetchProducts(); // Refresh tabel setelah sukses
    } catch (error: any) {
      alert(error.response?.data?.message || 'Gagal menyimpan produk. Cek koneksi Anda.');
    }
  };

  // --- HAPUS (DELETE) ---
  const handleDelete = async (id: string) => {
    if (!window.confirm('Apakah Anda yakin ingin menghapus produk ini?')) return;
    
    const token = localStorage.getItem('token');
    try {
      await axios.delete(`http://localhost:8000/api/products/${id}`, {
        headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
      });
      fetchProducts();
    } catch (error) {
      alert('Gagal menghapus produk.');
    }
  };

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
          <button className="w-full flex items-center gap-3 px-4 py-3 rounded-xl bg-amber-700/20 text-amber-500 font-medium transition text-left">
            <span>📦</span> Data Stok
          </button>
        </nav>
      </div>

      {/* KONTEN UTAMA */}
      <div className="flex-1 flex flex-col overflow-hidden">
        <header className="h-20 bg-white border-b border-stone-200 flex items-center justify-between px-8 shadow-sm">
          <h1 className="text-xl font-bold text-stone-800">Manajemen Inventori</h1>
          <button onClick={handleAddClick} className="bg-amber-700 hover:bg-amber-800 text-white px-5 py-2.5 rounded-xl font-bold transition shadow-md shadow-amber-700/20">
            + Tambah Produk Baru
          </button>
        </header>

        <main className="flex-1 overflow-y-auto p-8">
          <div className="bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden">
            <table className="w-full text-left border-collapse">
              <thead>
                <tr className="bg-stone-100 text-stone-500 text-sm uppercase tracking-wider border-b border-stone-200">
                  <th className="p-4 font-medium w-16 text-center">No</th>
                  <th className="p-4 font-medium">Nama Produk</th>
                  <th className="p-4 font-medium">Harga Jual</th>
                  <th className="p-4 font-medium">Sisa Stok</th>
                  <th className="p-4 font-medium text-center w-32">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-stone-100">
                {isLoading ? (
                  <tr><td colSpan={5} className="p-8 text-center text-stone-500">Memuat data dari server...</td></tr>
                ) : products.length === 0 ? (
                  <tr><td colSpan={5} className="p-8 text-center text-stone-500">Belum ada produk. Silakan tambah baru.</td></tr>
                ) : (
                  products.map((product, index) => (
                    <tr key={product.id} className="hover:bg-stone-50 transition">
                      <td className="p-4 text-center text-stone-400">{index + 1}</td>
                      <td className="p-4 font-bold text-stone-700">{product.name}</td>
                      <td className="p-4 text-amber-700 font-medium">Rp {Number(product.price).toLocaleString('id-ID')}</td>
                      <td className="p-4">
                        <span className={`px-3 py-1 rounded-full text-xs font-bold ${product.stock > 10 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                          {product.stock} Porsi
                        </span>
                      </td>
                      <td className="p-4 flex justify-center gap-2">
                        {/* TOMBOL EDIT BARU */}
                        <button onClick={() => handleEditClick(product)} className="p-2 text-blue-500 hover:bg-blue-50 rounded-lg transition" title="Edit">
                          ✏️
                        </button>
                        <button onClick={() => handleDelete(product.id)} className="p-2 text-red-500 hover:bg-red-50 rounded-lg transition" title="Hapus">
                          🗑️
                        </button>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </main>
      </div>

      {/* MODAL FORM (DINAMIS UNTUK TAMBAH & EDIT) */}
      {showModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-stone-900/60 backdrop-blur-sm">
          <div className="w-full max-w-md rounded-2xl bg-white p-8 shadow-2xl">
            <h2 className="mb-6 text-2xl font-bold text-stone-800">
              {isEditing ? 'Edit Data Produk' : 'Input Produk Baru'}
            </h2>
            <form onSubmit={handleSaveProduct} className="space-y-4">
              <div>
                <label className="block text-sm font-bold text-stone-600 mb-1">Nama Produk</label>
                <input type="text" required value={formData.name} onChange={e => setFormData({...formData, name: e.target.value})} className="w-full rounded-xl border border-stone-300 p-3 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 outline-none" placeholder="Misal: V60 Gayo" />
              </div>

              <div>
                <label className="block text-sm font-bold text-stone-600 mb-1">Kategori</label>
                <select 
                  required 
                  value={formData.category_id} 
                  onChange={e => setFormData({...formData, category_id: e.target.value})} 
                  className="w-full rounded-xl border border-stone-300 p-3 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 outline-none"
                >
                  <option value="" disabled>-- Pilih Kategori --</option>
                  {categories.map(cat => (
                    <option key={cat.id} value={cat.id}>{cat.name}</option>
                  ))}
                </select>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-bold text-stone-600 mb-1">Harga (Rp)</label>
                  <input type="number" required min="0" value={formData.price} onChange={e => setFormData({...formData, price: e.target.value})} className="w-full rounded-xl border border-stone-300 p-3 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 outline-none" placeholder="0" />
                </div>
                <div>
                  <label className="block text-sm font-bold text-stone-600 mb-1">Stok Fisik</label>
                  <input type="number" required min="0" value={formData.stock} onChange={e => setFormData({...formData, stock: e.target.value})} className="w-full rounded-xl border border-stone-300 p-3 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 outline-none" placeholder="0" />
                </div>
              </div>

              <div className="mt-6 flex gap-4 pt-4 border-t border-stone-100">
                <button type="button" onClick={() => setShowModal(false)} className="w-1/3 rounded-xl bg-stone-100 py-3 font-bold text-stone-500 hover:bg-stone-200 transition">Batal</button>
                <button type="submit" className="w-2/3 rounded-xl bg-amber-700 py-3 font-bold text-white hover:bg-amber-800 shadow-md transition">
                  {isEditing ? 'Perbarui Data' : 'Simpan ke Database'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

    </div>
  );
}
import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import axios from 'axios';

interface RawMaterialPivot {
  id: string;
  name: string;
  unit: string;
  pivot: {
    quantity_needed: number;
  };
}

interface Product {
  id: string;
  name: string;
  price: number;
  stock: number;
  category_id: string;
  raw_materials?: RawMaterialPivot[];
}

interface Category {
  id: string;
  name: string;
}

interface RawMaterialOption {
  id: string;
  name: string;
  unit: string;
}

interface RecipeItem {
  raw_material_id: string;
  quantity_needed: number | string;
  name?: string;
  unit?: string;
}

export default function Inventory() {
  const navigate = useNavigate();
  const [products, setProducts] = useState<Product[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [availableRawMaterials, setAvailableRawMaterials] = useState<RawMaterialOption[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  // State Modal CRUD Produk
  const [showModal, setShowModal] = useState(false);
  const [isEditing, setIsEditing] = useState(false);
  const [editId, setEditId] = useState<string | null>(null);
  const [formData, setFormData] = useState({ name: '', price: '', stock: '', category_id: '' });

  // State Modal Resep (Auto-Deduct)
  const [showRecipeModal, setShowRecipeModal] = useState(false);
  const [recipeProduct, setRecipeProduct] = useState<Product | null>(null);
  const [recipeItems, setRecipeItems] = useState<RecipeItem[]>([]);

  useEffect(() => {
    const token = localStorage.getItem('token');
    if (!token) {
      navigate('/');
      return;
    }
    fetchCategories();
    fetchRawMaterials();
    fetchProducts();
  }, [navigate]);

  const fetchCategories = async () => {
    const token = localStorage.getItem('token');
    try {
      const response = await axios.get('http://localhost:8000/api/categories', {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      const catData = response.data.data || response.data;
      setCategories(catData);
    } catch (error) {
      console.error("Gagal mengambil data kategori");
    }
  };

  const fetchRawMaterials = async () => {
    const token = localStorage.getItem('token');
    try {
      const response = await axios.get('http://localhost:8000/api/raw-materials', {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      setAvailableRawMaterials(response.data.data);
    } catch (error) {
      console.error("Gagal mengambil data bahan baku");
    }
  };

  const fetchProducts = async () => {
    const token = localStorage.getItem('token');
    setIsLoading(true);
    try {
      const response = await axios.get('http://localhost:8000/api/products', {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      setProducts(response.data.data);
    } catch (error) {
      console.error("Gagal mengambil data produk");
    } finally {
      setIsLoading(false);
    }
  };

  // --- CRUD PRODUK ---
  const handleAddClick = () => {
    setIsEditing(false);
    setEditId(null);
    setFormData({ name: '', price: '', stock: '', category_id: '' });
    setShowModal(true);
  };

  const handleEditClick = (product: Product) => {
    setIsEditing(true);
    setEditId(product.id);
    setFormData({ 
      name: product.name, 
      price: product.price.toString(), 
      stock: product.stock.toString(), 
      category_id: product.category_id ? product.category_id.toString() : ''
    });
    setShowModal(true);
  };

  const handleSaveProduct = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!formData.category_id || formData.category_id === '') {
      alert('⚠️ Mohon pilih Kategori Produk!');
      return;
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
        await axios.put(`http://localhost:8000/api/products/${editId}`, payload, { headers: { 'Authorization': `Bearer ${token}` } });
      } else {
        await axios.post('http://localhost:8000/api/products', payload, { headers: { 'Authorization': `Bearer ${token}` } });
      }
      setShowModal(false);
      fetchProducts();
    } catch (error: any) {
      alert(error.response?.data?.message || 'Gagal menyimpan produk.');
    }
  };

  const handleDelete = async (id: string) => {
    if (!window.confirm('Yakin ingin menghapus produk ini?')) return;
    const token = localStorage.getItem('token');
    try {
      await axios.delete(`http://localhost:8000/api/products/${id}`, { headers: { 'Authorization': `Bearer ${token}` } });
      fetchProducts();
    } catch (error) {
      alert('Gagal menghapus produk.');
    }
  };

  // --- MANAJEMEN RESEP (AUTO-DEDUCT) ---
  const handleOpenRecipeModal = (product: Product) => {
    setRecipeProduct(product);
    // Muat resep yang sudah ada dari database
    if (product.raw_materials && product.raw_materials.length > 0) {
      setRecipeItems(
        product.raw_materials.map(rm => ({
          raw_material_id: rm.id,
          quantity_needed: rm.pivot.quantity_needed,
          name: rm.name,
          unit: rm.unit
        }))
      );
    } else {
      setRecipeItems([]);
    }
    setShowRecipeModal(true);
  };

  const addRecipeRow = () => {
    setRecipeItems([...recipeItems, { raw_material_id: '', quantity_needed: '' }]);
  };

  const updateRecipeRow = (index: number, field: string, value: string) => {
    const updated = [...recipeItems];
    if (field === 'raw_material_id') {
      const selectedRm = availableRawMaterials.find(rm => rm.id === value);
      updated[index] = { 
        ...updated[index], 
        raw_material_id: value, 
        name: selectedRm?.name, 
        unit: selectedRm?.unit 
      };
    } else {
      updated[index] = { ...updated[index], [field]: value };
    }
    setRecipeItems(updated);
  };

  const removeRecipeRow = (index: number) => {
    setRecipeItems(recipeItems.filter((_, i) => i !== index));
  };

  const handleSaveRecipe = async () => {
    if (!recipeProduct) return;
    
    // Validasi input kosong
    const isValid = recipeItems.every(item => item.raw_material_id !== '' && item.quantity_needed !== '');
    if (!isValid) {
      alert("Harap lengkapi pilihan bahan baku dan jumlahnya, atau hapus baris yang kosong.");
      return;
    }

    const token = localStorage.getItem('token');
    try {
      await axios.post(`http://localhost:8000/api/products/${recipeProduct.id}/recipe`, {
        recipe: recipeItems.map(item => ({
          raw_material_id: item.raw_material_id,
          quantity_needed: Number(item.quantity_needed)
        }))
      }, {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      
      alert("Resep berhasil disimpan! Bahan baku akan otomatis terpotong saat pesanan dibuat.");
      setShowRecipeModal(false);
      fetchProducts(); // Refresh agar data terbaru masuk
    } catch (error) {
      alert("Gagal menyimpan resep.");
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
            <span>📦</span> Data Produk
          </button>
          <button onClick={() => navigate('/raw-materials')} className="w-full flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-stone-800 hover:text-white transition text-left">
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
          <h1 className="text-xl font-bold text-stone-800">Manajemen Inventori & Produk</h1>
          <button onClick={handleAddClick} className="bg-amber-700 hover:bg-amber-800 text-white px-5 py-2.5 rounded-xl font-bold transition shadow-md">
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
                  <th className="p-4 font-medium">Sisa Porsi (Stok)</th>
                  <th className="p-4 font-medium text-center">Resep (BOM)</th>
                  <th className="p-4 font-medium text-center w-32">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-stone-100">
                {isLoading ? (
                  <tr><td colSpan={6} className="p-8 text-center text-stone-500">Memuat data dari server...</td></tr>
                ) : products.length === 0 ? (
                  <tr><td colSpan={6} className="p-8 text-center text-stone-500">Belum ada produk. Silakan tambah baru.</td></tr>
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
                      <td className="p-4 text-center">
                        <button onClick={() => handleOpenRecipeModal(product)} className="px-3 py-1.5 text-sm font-bold text-blue-600 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition">
                          {product.raw_materials && product.raw_materials.length > 0 ? '📖 Edit Resep' : '➕ Buat Resep'}
                        </button>
                      </td>
                      <td className="p-4 flex justify-center gap-2">
                        <button onClick={() => handleEditClick(product)} className="p-2 text-blue-500 hover:bg-blue-50 rounded-lg transition" title="Edit">✏️</button>
                        <button onClick={() => handleDelete(product.id)} className="p-2 text-red-500 hover:bg-red-50 rounded-lg transition" title="Hapus">🗑️</button>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </main>
      </div>

      {/* MODAL FORM PRODUK */}
      {showModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-stone-900/60 backdrop-blur-sm">
          <div className="w-full max-w-md rounded-2xl bg-white p-8 shadow-2xl">
            <h2 className="mb-6 text-2xl font-bold text-stone-800">{isEditing ? 'Edit Data Produk' : 'Input Produk Baru'}</h2>
            <form onSubmit={handleSaveProduct} className="space-y-4">
              <div>
                <label className="block text-sm font-bold text-stone-600 mb-1">Nama Produk</label>
                <input type="text" required value={formData.name} onChange={e => setFormData({...formData, name: e.target.value})} className="w-full rounded-xl border border-stone-300 p-3 outline-none focus:border-amber-500" placeholder="Misal: V60 Gayo" />
              </div>
              <div>
                <label className="block text-sm font-bold text-stone-600 mb-1">Kategori</label>
                <select required value={formData.category_id} onChange={e => setFormData({...formData, category_id: e.target.value})} className="w-full rounded-xl border border-stone-300 p-3 outline-none focus:border-amber-500">
                  <option value="" disabled>-- Pilih Kategori --</option>
                  {categories.map(cat => <option key={cat.id} value={cat.id}>{cat.name}</option>)}
                </select>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-bold text-stone-600 mb-1">Harga (Rp)</label>
                  <input type="number" required min="0" value={formData.price} onChange={e => setFormData({...formData, price: e.target.value})} className="w-full rounded-xl border border-stone-300 p-3 outline-none focus:border-amber-500" placeholder="0" />
                </div>
                <div>
                  <label className="block text-sm font-bold text-stone-600 mb-1">Sisa Porsi</label>
                  <input type="number" required min="0" value={formData.stock} onChange={e => setFormData({...formData, stock: e.target.value})} className="w-full rounded-xl border border-stone-300 p-3 outline-none focus:border-amber-500" placeholder="0" />
                </div>
              </div>
              <div className="mt-6 flex gap-4 pt-4 border-t border-stone-100">
                <button type="button" onClick={() => setShowModal(false)} className="w-1/3 rounded-xl bg-stone-100 py-3 font-bold text-stone-500 hover:bg-stone-200">Batal</button>
                <button type="submit" className="w-2/3 rounded-xl bg-amber-700 py-3 font-bold text-white hover:bg-amber-800 shadow-md">Simpan</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL RESEP (BILL OF MATERIALS) */}
      {showRecipeModal && (
        <div className="fixed inset-0 z-[60] flex items-center justify-center bg-stone-900/60 backdrop-blur-sm">
          <div className="w-full max-w-2xl rounded-2xl bg-white p-8 shadow-2xl">
            <div className="flex justify-between items-center mb-6">
              <div>
                <h2 className="text-2xl font-bold text-stone-800">Resep F&B</h2>
                <p className="text-stone-500 text-sm mt-1">Setiap 1 porsi <span className="font-bold text-amber-700">{recipeProduct?.name}</span> terjual, potong otomatis bahan baku berikut:</p>
              </div>
              <button onClick={() => setShowRecipeModal(false)} className="text-stone-400 hover:text-red-500 font-bold text-2xl">&times;</button>
            </div>
            
            <div className="space-y-3 mb-6 max-h-72 overflow-y-auto pr-2">
              {recipeItems.length === 0 ? (
                <div className="text-center p-6 border-2 border-dashed border-stone-200 rounded-xl text-stone-400">
                  Belum ada bahan baku yang dihubungkan.
                </div>
              ) : (
                recipeItems.map((item, index) => (
                  <div key={index} className="flex gap-3 items-end bg-stone-50 p-3 rounded-xl border border-stone-200">
                    <div className="flex-1">
                      <label className="block text-xs font-bold text-stone-500 mb-1">Pilih Bahan Baku</label>
                      <select 
                        value={item.raw_material_id} 
                        onChange={(e) => updateRecipeRow(index, 'raw_material_id', e.target.value)}
                        className="w-full p-2.5 rounded-lg border border-stone-300 outline-none focus:border-amber-500 text-sm font-medium"
                      >
                        <option value="" disabled>-- Pilih Bahan --</option>
                        {availableRawMaterials.map(rm => (
                          <option key={rm.id} value={rm.id}>{rm.name} ({rm.unit})</option>
                        ))}
                      </select>
                    </div>
                    <div className="w-32">
                      <label className="block text-xs font-bold text-stone-500 mb-1">Takaran / Porsi</label>
                      <div className="relative">
                        <input 
                          type="number" 
                          step="0.01"
                          min="0"
                          value={item.quantity_needed}
                          onChange={(e) => updateRecipeRow(index, 'quantity_needed', e.target.value)}
                          className="w-full p-2.5 rounded-lg border border-stone-300 outline-none focus:border-amber-500 text-sm font-bold pr-10"
                          placeholder="0"
                        />
                        <span className="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-stone-400">
                          {item.unit || '-'}
                        </span>
                      </div>
                    </div>
                    <button onClick={() => removeRecipeRow(index)} className="h-[42px] w-[42px] rounded-lg bg-red-50 text-red-500 border border-red-200 hover:bg-red-100 flex items-center justify-center transition" title="Hapus Bahan">
                      🗑️
                    </button>
                  </div>
                ))
              )}
            </div>

            <button onClick={addRecipeRow} className="w-full py-3 border-2 border-dashed border-amber-300 text-amber-700 font-bold rounded-xl hover:bg-amber-50 transition mb-6">
              + Tambah Komponen Bahan Baku
            </button>

            <div className="flex gap-4 pt-4 border-t border-stone-100">
              <button onClick={() => setShowRecipeModal(false)} className="w-1/3 rounded-xl bg-stone-100 py-3 font-bold text-stone-500 hover:bg-stone-200">Batal</button>
              <button onClick={handleSaveRecipe} className="w-2/3 rounded-xl bg-stone-800 py-3 font-bold text-white shadow-lg hover:bg-stone-900 transition flex items-center justify-center gap-2">
                <span>⚙️</span> Simpan Konfigurasi Resep
              </button>
            </div>
          </div>
        </div>
      )}

    </div>
  );
}
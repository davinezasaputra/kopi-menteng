import { useState, useEffect } from 'react';
import axios from 'axios';
import toast from 'react-hot-toast';
import AdminSidebar from '../components/AdminSidebar';
import { formatNumberInput, parseNumberInput } from '../utils/numberFormat';

export default function Accounting() {
  const [activeTab, setActiveTab] = useState<'accounts' | 'journals'>('accounts');
  const [accounts, setAccounts] = useState<any[]>([]);
  const [journals, setJournals] = useState<any[]>([]);
  
  const [showAccModal, setShowAccModal] = useState(false);
  const [accForm, setAccForm] = useState({ code: '', name: '', type: 'asset' });

  const [showJrnModal, setShowJrnModal] = useState(false);
  const [jrnForm, setJrnForm] = useState({ account_id: '', date: new Date().toISOString().split('T')[0], description: '', debit: '', credit: '' });

  useEffect(() => {
    if (activeTab === 'accounts') fetchAccounts();
    else fetchJournals();
  }, [activeTab]);

  const fetchAccounts = async () => {
    try {
      const res = await axios.get('http://localhost:8000/api/accounting/accounts', { headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }});
      setAccounts(res.data.data);
    } catch (e) { toast.error('Gagal memuat Daftar Akun'); }
  };

  const fetchJournals = async () => {
    try {
      const res = await axios.get('http://localhost:8000/api/accounting/journals', { headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }});
      setJournals(res.data.data);
    } catch (e) { toast.error('Gagal memuat Jurnal Umum'); }
  };

  const handleSaveAccount = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await axios.post('http://localhost:8000/api/accounting/accounts', accForm, { headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }});
      toast.success('Akun tersimpan!');
      setShowAccModal(false);
      fetchAccounts();
    } catch (e) { toast.error('Gagal menyimpan akun. Kode mungkin sudah ada.'); }
  };

  const handleSaveJournal = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await axios.post('http://localhost:8000/api/accounting/journals', { ...jrnForm, debit: parseNumberInput(jrnForm.debit), credit: parseNumberInput(jrnForm.credit) }, { headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }});
      toast.success('Jurnal dicatat!');
      setShowJrnModal(false);
      fetchJournals();
    } catch (e) { toast.error('Gagal mencatat jurnal.'); }
  };

  const formatRp = (angka: number) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka || 0);

  return (
    <div className="flex h-screen w-full bg-stone-50 font-sans text-stone-800">
      <AdminSidebar activePage="accounting" />
      
      <div className="flex-1 flex flex-col overflow-hidden">
        <header className="h-20 bg-white border-b border-stone-200 flex items-center justify-between px-8 shadow-sm">
          <h1 className="text-xl font-bold text-stone-800">Buku Besar Akuntansi</h1>
          <div className="flex gap-2">
            <button onClick={() => setActiveTab('accounts')} className={`px-4 py-2 font-bold rounded-lg transition ${activeTab === 'accounts' ? 'bg-stone-800 text-white' : 'bg-stone-100 text-stone-500 hover:bg-stone-200'}`}>Daftar Akun (COA)</button>
            <button onClick={() => { setActiveTab('journals'); fetchAccounts(); }} className={`px-4 py-2 font-bold rounded-lg transition ${activeTab === 'journals' ? 'bg-stone-800 text-white' : 'bg-stone-100 text-stone-500 hover:bg-stone-200'}`}>Jurnal Umum</button>
          </div>
        </header>

        <main className="flex-1 overflow-y-auto p-8">
          <div className="mb-4 flex justify-end">
            {activeTab === 'accounts' ? (
              <button onClick={() => setShowAccModal(true)} className="bg-amber-700 text-white px-4 py-2 rounded-lg font-bold shadow-md hover:bg-amber-800">+ Tambah Akun</button>
            ) : (
              <button onClick={() => setShowJrnModal(true)} className="bg-amber-700 text-white px-4 py-2 rounded-lg font-bold shadow-md hover:bg-amber-800">+ Catat Jurnal</button>
            )}
          </div>

          <div className="bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden">
            {activeTab === 'accounts' ? (
              <table className="w-full text-left border-collapse">
                <thead className="bg-stone-100 text-stone-500 text-sm uppercase">
                  <tr><th className="p-4">Kode</th><th className="p-4">Nama Akun</th><th className="p-4">Kategori</th><th className="p-4 text-right">Saldo Saat Ini</th></tr>
                </thead>
                <tbody className="divide-y divide-stone-100">
                  {accounts.map(acc => (
                    <tr key={acc.id} className="hover:bg-stone-50">
                      <td className="p-4 font-bold text-stone-700">{acc.code}</td>
                      <td className="p-4 font-bold">{acc.name}</td>
                      <td className="p-4 uppercase text-xs font-bold text-stone-500">{acc.type}</td>
                      <td className="p-4 text-right font-black text-amber-700">{formatRp(acc.balance)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            ) : (
              <table className="w-full text-left border-collapse">
                <thead className="bg-stone-100 text-stone-500 text-sm uppercase">
                  <tr><th className="p-4">Tanggal</th><th className="p-4">Keterangan</th><th className="p-4">Akun</th><th className="p-4 text-right">Debit</th><th className="p-4 text-right">Kredit</th></tr>
                </thead>
                <tbody className="divide-y divide-stone-100">
                  {journals.map(jrn => (
                    <tr key={jrn.id} className="hover:bg-stone-50 text-sm">
                      <td className="p-4 text-stone-500">{jrn.date}</td>
                      <td className="p-4 font-bold">{jrn.description}</td>
                      <td className="p-4"><span className="bg-stone-100 px-2 py-1 rounded text-xs font-bold">{jrn.account?.code}</span> {jrn.account?.name}</td>
                      <td className="p-4 text-right text-stone-700 font-bold">{jrn.debit > 0 ? formatRp(jrn.debit) : '-'}</td>
                      <td className="p-4 text-right text-stone-700 font-bold">{jrn.credit > 0 ? formatRp(jrn.credit) : '-'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        </main>
      </div>

      {/* MODAL COA */}
      {showAccModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-stone-900/60 backdrop-blur-sm">
          <div className="w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl">
            <h2 className="mb-4 text-lg font-bold">Buat Akun Baru (COA)</h2>
            <form onSubmit={handleSaveAccount} className="space-y-4">
              <input type="text" placeholder="Kode (Cth: 101)" required value={accForm.code} onChange={e => setAccForm({...accForm, code: e.target.value})} className="w-full border p-3 rounded-xl bg-stone-50" />
              <input type="text" placeholder="Nama Akun (Cth: Kas Tunai)" required value={accForm.name} onChange={e => setAccForm({...accForm, name: e.target.value})} className="w-full border p-3 rounded-xl bg-stone-50" />
              <select value={accForm.type} onChange={e => setAccForm({...accForm, type: e.target.value})} className="w-full border p-3 rounded-xl bg-stone-50 font-bold">
                <option value="asset">Aset (Harta)</option>
                <option value="liability">Liabilitas (Hutang)</option>
                <option value="equity">Ekuitas (Modal)</option>
                <option value="revenue">Pendapatan</option>
                <option value="expense">Beban (Biaya)</option>
              </select>
              <div className="flex gap-2 pt-2"><button type="button" onClick={() => setShowAccModal(false)} className="flex-1 py-3 bg-stone-100 rounded-xl font-bold text-stone-500">Batal</button><button type="submit" className="flex-1 py-3 bg-amber-700 text-white rounded-xl font-bold">Simpan</button></div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL JURNAL */}
      {showJrnModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-stone-900/60 backdrop-blur-sm">
          <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
            <h2 className="mb-4 text-lg font-bold">Catat Jurnal Umum</h2>
            <form onSubmit={handleSaveJournal} className="space-y-4">
              <select required value={jrnForm.account_id} onChange={e => setJrnForm({...jrnForm, account_id: e.target.value})} className="w-full border p-3 rounded-xl bg-stone-50">
                <option value="" disabled>-- Pilih Akun --</option>
                {accounts.map(acc => <option key={acc.id} value={acc.id}>[{acc.code}] {acc.name}</option>)}
              </select>
              <input type="date" required value={jrnForm.date} onChange={e => setJrnForm({...jrnForm, date: e.target.value})} className="w-full border p-3 rounded-xl bg-stone-50" />
              <input type="text" placeholder="Deskripsi Jurnal" required value={jrnForm.description} onChange={e => setJrnForm({...jrnForm, description: e.target.value})} className="w-full border p-3 rounded-xl bg-stone-50" />
              <div className="flex gap-4">
                <input type="text" inputMode="numeric" placeholder="Debit (Rp)" value={jrnForm.debit} onChange={e => setJrnForm({...jrnForm, debit: formatNumberInput(e.target.value)})} className="w-full border p-3 rounded-xl bg-stone-50" />
                <input type="text" inputMode="numeric" placeholder="Kredit (Rp)" value={jrnForm.credit} onChange={e => setJrnForm({...jrnForm, credit: formatNumberInput(e.target.value)})} className="w-full border p-3 rounded-xl bg-stone-50" />
              </div>
              <div className="flex gap-2 pt-2"><button type="button" onClick={() => setShowJrnModal(false)} className="flex-1 py-3 bg-stone-100 rounded-xl font-bold text-stone-500">Batal</button><button type="submit" className="flex-1 py-3 bg-amber-700 text-white rounded-xl font-bold">Simpan</button></div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
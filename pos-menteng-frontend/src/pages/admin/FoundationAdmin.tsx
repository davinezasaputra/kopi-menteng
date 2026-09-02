import { useEffect, useState } from 'react';
import api from '../../core/api/client';
import PermissionGate from '../../components/PermissionGate';
import EnterpriseAdminLayout from '../../layouts/EnterpriseAdminLayout';
import toast from 'react-hot-toast';

type Role = { id:number; name:string; code:string; permissions?: {name:string}[] };
type Permission = { id:number; module:string; resource:string; action:string; name:string };
type Audit = { id:number; event:string; module:string; entity_type?:string; entity_id?:string; created_at:string };
type Warehouse = { id:number; code:string; name:string };
type Branch = { id:number; code:string; name:string; type?:string; warehouses?: Warehouse[] };
type Company = { id:number; code:string; name:string; legal_name?:string; email?:string; phone?:string; branches?:Branch[] };

export default function FoundationAdmin(){
  const [roles,setRoles]=useState<Role[]>([]); const [permissions,setPermissions]=useState<Permission[]>([]); const [audits,setAudits]=useState<Audit[]>([]); const [companies,setCompanies]=useState<Company[]>([]); const [loading,setLoading]=useState(true); const [saving,setSaving]=useState(false);
  const [companyForm,setCompanyForm]=useState({code:'',name:'',legal_name:'',email:'',phone:'',address:''});
  const [branchForm,setBranchForm]=useState({company_id:'',code:'',name:'',type:'store',email:'',phone:'',address:''});

  const loadOrganizations=async()=>{const response=await api.get('/v1/organizations');setCompanies(response.data?.data||[]);};
  useEffect(()=>{Promise.all([api.get('/v1/roles'),api.get('/v1/permissions'),api.get('/v1/audit-logs?per_page=10'),loadOrganizations()]).finally(()=>setLoading(false));},[]);

  const saveCompany=async(event:React.FormEvent)=>{event.preventDefault();setSaving(true);try{await api.post('/v1/organizations/companies',companyForm);toast.success('Company berhasil ditambahkan.');setCompanyForm({code:'',name:'',legal_name:'',email:'',phone:'',address:''});await loadOrganizations();}catch(error:any){toast.error(error?.response?.data?.message||'Gagal menambahkan company.');}finally{setSaving(false);}};
  const saveBranch=async(event:React.FormEvent)=>{event.preventDefault();setSaving(true);try{await api.post('/v1/organizations/branches',branchForm);toast.success('Branch berhasil ditambahkan beserta warehouse MAIN.');setBranchForm(current=>({...current,code:'',name:'',email:'',phone:'',address:''}));await loadOrganizations();}catch(error:any){toast.error(error?.response?.data?.message||'Gagal menambahkan branch.');}finally{setSaving(false);}};

  return <EnterpriseAdminLayout>
    <main className="p-6 lg:p-8">
      <div className="mx-auto max-w-7xl space-y-6">
        <div><h1 className="text-2xl font-black text-stone-900">Organizations & Access</h1><p className="mt-1 text-sm text-stone-500">Kelola role, permission, company, dan branch pada tenant aktif.</p></div>

        <PermissionGate permission="rbac.role.view" fallback={<p>Access denied.</p>}>
          {loading?<p>Loading...</p>:<>
            <section className="grid gap-6 lg:grid-cols-2">
              <form onSubmit={saveCompany} className="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm">
                <div className="mb-5"><h2 className="text-lg font-black">Tambah Company</h2><p className="text-sm text-stone-500">Company baru dibuat hanya di tenant aktif.</p></div>
                <div className="grid gap-4 sm:grid-cols-2">
                  <label className="text-sm font-bold">Kode<input required value={companyForm.code} onChange={e=>setCompanyForm({...companyForm,code:e.target.value})} className="mt-1 w-full rounded-xl border border-stone-300 px-3 py-2.5 font-normal" placeholder="COMP-02" /></label>
                  <label className="text-sm font-bold">Nama<input required value={companyForm.name} onChange={e=>setCompanyForm({...companyForm,name:e.target.value})} className="mt-1 w-full rounded-xl border border-stone-300 px-3 py-2.5 font-normal" placeholder="Kopi Menteng Cabang Usaha" /></label>
                  <label className="text-sm font-bold sm:col-span-2">Legal Name<input value={companyForm.legal_name} onChange={e=>setCompanyForm({...companyForm,legal_name:e.target.value})} className="mt-1 w-full rounded-xl border border-stone-300 px-3 py-2.5 font-normal" /></label>
                  <label className="text-sm font-bold">Email<input type="email" value={companyForm.email} onChange={e=>setCompanyForm({...companyForm,email:e.target.value})} className="mt-1 w-full rounded-xl border border-stone-300 px-3 py-2.5 font-normal" /></label>
                  <label className="text-sm font-bold">Telepon<input value={companyForm.phone} onChange={e=>setCompanyForm({...companyForm,phone:e.target.value})} className="mt-1 w-full rounded-xl border border-stone-300 px-3 py-2.5 font-normal" /></label>
                  <label className="text-sm font-bold sm:col-span-2">Alamat<textarea value={companyForm.address} onChange={e=>setCompanyForm({...companyForm,address:e.target.value})} className="mt-1 w-full rounded-xl border border-stone-300 px-3 py-2.5 font-normal" rows={2} /></label>
                </div>
                <button disabled={saving} className="mt-5 rounded-xl bg-amber-700 px-5 py-2.5 font-bold text-white disabled:opacity-50">{saving?'Menyimpan...':'Tambah Company'}</button>
              </form>

              <form onSubmit={saveBranch} className="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm">
                <div className="mb-5"><h2 className="text-lg font-black">Tambah Branch</h2><p className="text-sm text-stone-500">Branch otomatis mendapatkan warehouse MAIN.</p></div>
                <div className="grid gap-4 sm:grid-cols-2">
                  <label className="text-sm font-bold sm:col-span-2">Company<select required value={branchForm.company_id} onChange={e=>setBranchForm({...branchForm,company_id:e.target.value})} className="mt-1 w-full rounded-xl border border-stone-300 bg-white px-3 py-2.5 font-normal"><option value="">Pilih company</option>{companies.map(company=><option key={company.id} value={company.id}>{company.code} · {company.name}</option>)}</select></label>
                  <label className="text-sm font-bold">Kode<input required value={branchForm.code} onChange={e=>setBranchForm({...branchForm,code:e.target.value})} className="mt-1 w-full rounded-xl border border-stone-300 px-3 py-2.5 font-normal" placeholder="BR-02" /></label>
                  <label className="text-sm font-bold">Nama<input required value={branchForm.name} onChange={e=>setBranchForm({...branchForm,name:e.target.value})} className="mt-1 w-full rounded-xl border border-stone-300 px-3 py-2.5 font-normal" placeholder="Kopi Menteng - Sudirman" /></label>
                  <label className="text-sm font-bold">Tipe<input value={branchForm.type} onChange={e=>setBranchForm({...branchForm,type:e.target.value})} className="mt-1 w-full rounded-xl border border-stone-300 px-3 py-2.5 font-normal" /></label>
                  <label className="text-sm font-bold">Telepon<input value={branchForm.phone} onChange={e=>setBranchForm({...branchForm,phone:e.target.value})} className="mt-1 w-full rounded-xl border border-stone-300 px-3 py-2.5 font-normal" /></label>
                  <label className="text-sm font-bold sm:col-span-2">Email<input type="email" value={branchForm.email} onChange={e=>setBranchForm({...branchForm,email:e.target.value})} className="mt-1 w-full rounded-xl border border-stone-300 px-3 py-2.5 font-normal" /></label>
                  <label className="text-sm font-bold sm:col-span-2">Alamat<textarea value={branchForm.address} onChange={e=>setBranchForm({...branchForm,address:e.target.value})} className="mt-1 w-full rounded-xl border border-stone-300 px-3 py-2.5 font-normal" rows={2} /></label>
                </div>
                <button disabled={saving || companies.length===0} className="mt-5 rounded-xl bg-stone-900 px-5 py-2.5 font-bold text-white disabled:opacity-50">{saving?'Menyimpan...':'Tambah Branch'}</button>
              </form>
            </section>

            <section className="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm"><div className="mb-4"><h2 className="text-lg font-black">Struktur Organization</h2><p className="text-sm text-stone-500">Company dan branch tenant aktif.</p></div>{companies.length===0?<p className="text-sm text-stone-500">Belum ada company.</p>:<div className="space-y-4">{companies.map(company=><div key={company.id} className="rounded-xl border border-stone-200 p-4"><div className="font-black">{company.code} · {company.name}</div><div className="mt-3 space-y-2">{(company.branches||[]).map(branch=><div key={branch.id} className="rounded-lg bg-stone-50 px-4 py-3"><div className="font-bold">{branch.code} · {branch.name}</div><div className="text-xs text-stone-500">{branch.warehouses?.map(w=>`${w.code} · ${w.name}`).join(' | ')||'Warehouse belum ada'}</div></div>)}</div></div>)}</div>}</section>

            <section><h2 className="text-lg font-black">Roles</h2><div className="overflow-hidden rounded-xl border border-stone-200 bg-white"><table className="w-full text-left"><thead className="bg-stone-50"><tr><th className="p-3">Name</th><th className="p-3">Code</th><th className="p-3">Permissions</th></tr></thead><tbody>{roles.map(r=><tr key={r.id} className="border-t border-stone-100"><td className="p-3">{r.name}</td><td className="p-3">{r.code}</td><td className="p-3">{r.permissions?.length||0}</td></tr>)}</tbody></table></div></section>
            <section><h2 className="text-lg font-black">Permission Catalog</h2><p className="text-sm text-stone-500">{permissions.length} permissions</p></section>
            <section><h2 className="text-lg font-black">Recent Audit</h2><ul className="space-y-1 text-sm text-stone-600">{audits.map(a=><li key={a.id}>{a.event} · {a.module} · {a.entity_type||''} · {a.created_at}</li>)}</ul></section>
          </>}
        </PermissionGate>
      </div>
    </main>
  </EnterpriseAdminLayout>;
}

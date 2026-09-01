import { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import axios from 'axios';
import toast from 'react-hot-toast';
import { can, canAny, isDeveloper } from '../core/auth/permissions';

export type AdminSidebarProps = { activePage?: string };
type MenuItem = { key: string; label: string; icon: string; path: string; permission?: string; anyOf?: string[] };
type SubGroup = { key: string; label: string; items: MenuItem[] };
type ModuleGroup = { key: string; label: string; icon: string; items?: MenuItem[]; subgroups?: SubGroup[] };

const allowed = (i: MenuItem) => isDeveloper() || (i.permission ? can(i.permission) : i.anyOf ? canAny(i.anyOf) : true);

const purchasing: MenuItem[] = [
  ['purchasing-workspace','Procurement Workspace','🛒'],
  ['purchasing-orders','Purchase Order','📝'],
  ['purchasing-requisitions','Requisitions','📋'],
  ['purchasing-receipts','Goods Receipt','📥'],
  ['purchasing-invoices','Supplier Invoice','🧾'],
  ['purchasing-payments','Supplier Payment','💳'],
  ['purchasing-returns','Supplier Return','↩️'],
  ['purchasing-credit-notes','Credit Notes','📄'],
  ['purchasing-budget','Budget','💰'],
  ['purchasing-approval','Approval Matrix','✅'],
  ['purchasing-reconciliation','Reconciliation','🔎'],
  ['purchasing-reports','Reports','📊'],
].map(([key,label,icon]) => ({
  key,label,icon,path:'/purchasing',
  anyOf: key==='purchasing-workspace'
    ? ['purchasing.supplier.view','purchasing.requisition.view','purchasing.order.view','purchasing.receipt.view','purchasing.ap.view','purchasing.return.view','purchasing.credit_note.view','purchasing.budget.view','purchasing.approval_matrix.view','purchasing.reconciliation.view','purchasing.reporting.view']
    : undefined,
}));

const modules: ModuleGroup[] = [
  { key:'platform',label:'Platform',icon:'⚡',items:[
    {key:'developer-console',label:'Developer Console',icon:'🧠',path:'/platform',permission:'platform.admin'},
  ]},
  { key:'erp',label:'ERP',icon:'🏢',subgroups:[
    { key:'erp-overview',label:'Overview',items:[
      {key:'operations',label:'Operations Center',icon:'📊',path:'/erp/operations',anyOf:['inventory.stock.view','purchasing.supplier.view','purchasing.order.view','sales.order.view','accounting.report.view']},
      {key:'guided-operations',label:'Guided Workspace',icon:'🧭',path:'/erp/operations/guided',anyOf:['purchasing.supplier.create','purchasing.order.create','sales.order.create','accounting.report.view','accounting.erp_journal.create']},
      {key:'raw-operations',label:'Enterprise Operations',icon:'🛠️',path:'/erp/operations/raw',anyOf:['inventory.stock.view','purchasing.supplier.view','sales.order.view','accounting.report.view']},
    ] },
    { key:'erp-inventory',label:'Inventory',items:[
      {key:'inventory',label:'Produk',icon:'📦',path:'/inventory',permission:'inventory.stock.view'},
      {key:'inventory-operations',label:'Kontrol Persediaan',icon:'📈',path:'/inventory/operations',anyOf:['inventory.stock.view','inventory.stock.adjust']},
      {key:'raw-materials',label:'Bahan Baku',icon:'🫙',path:'/raw-materials',permission:'inventory.stock.view'},
    ] },
    { key:'erp-purchasing',label:'Purchasing',items:purchasing},
    { key:'erp-finance',label:'Finance & Accounting',items:[
      {key:'accounting',label:'Accounting / Finance',icon:'💲',path:'/accounting',anyOf:['accounting.journal.view','accounting.erp_account.view','accounting.report.view']},
      {key:'history',label:'Riwayat & Laporan',icon:'🧾',path:'/history',anyOf:['sales.reporting.view','accounting.report.view','inventory.stock.view']},
    ] },
    { key:'erp-sales',label:'Sales',items:[
      {key:'customers',label:'Pelanggan',icon:'👤',path:'/customers',permission:'sales.order.view'},
      {key:'sales-workspace',label:'Sales Workspace',icon:'🧾',path:'/erp/operations/guided',anyOf:['sales.order.view','sales.order.create']},
    ] },
  ]},
  { key:'pos',label:'POS',icon:'🛒',items:[
    {key:'pos',label:'Kasir',icon:'🛒',path:'/pos',permission:'pos.sale.view'},
    {key:'receipt-template',label:'Template Nota & Struk',icon:'🧾',path:'/admin/pos/receipt-template',permission:'pos.receipt_template.view'},
  ]},
  { key:'hrm',label:'HRM',icon:'🧑‍💼',items:[
    {key:'employees',label:'Karyawan',icon:'🧑‍💻',path:'/employees',permission:'hr.employee.view'},
    {key:'hrm',label:'HRD & Penggajian',icon:'💼',path:'/hrm',permission:'hr.employee.view'},
  ]},
  { key:'administration',label:'Administration',icon:'⚙️',items:[
    {key:'users',label:'Users',icon:'👥',path:'/users',permission:'users.user.view'},
    {key:'foundation',label:'Organizations & Access',icon:'🔐',path:'/admin/foundation',permission:'rbac.role.view'},
  ]},
];

const filterModule = (m:ModuleGroup):ModuleGroup|null => {
  const items = m.items?.filter(allowed) ?? [];
  const subgroups = m.subgroups?.map(g=>({...g,items:g.items.filter(allowed)})).filter(g=>g.items.length>0) ?? [];
  return items.length || subgroups.length ? {...m,items,subgroups} : null;
};

export default function AdminSidebar({activePage='dashboard'}:AdminSidebarProps){
  const navigate=useNavigate();
  const developer=isDeveloper();
  const user=useMemo(()=>{try{return JSON.parse(localStorage.getItem('user')||'{}') as {name?:string;role?:string};}catch{return {}; }},[]);
  const context=useMemo(()=>{try{return JSON.parse(localStorage.getItem('erp_context')||'{}') as {tenant_id?:number;company_id?:number;branch_id?:number};}catch{return {}; }},[]);
  const allowedModules=useMemo(()=>modules.map(filterModule).filter((m):m is ModuleGroup=>!!m),[developer]);
  const activeModule=useMemo(()=>allowedModules.find(m=>(m.items??[]).some(i=>i.key===activePage)||(m.subgroups??[]).some(g=>g.items.some(i=>i.key===activePage)))?.key??null,[activePage,allowedModules]);
  const activeSub=useMemo(()=>allowedModules.flatMap(m=>m.subgroups??[]).find(g=>g.items.some(i=>i.key===activePage))?.key??null,[activePage,allowedModules]);
  const [openModules,setOpenModules]=useState<string[]>(activeModule?[activeModule]:[]);
  const [openSubs,setOpenSubs]=useState<string[]>(activeSub?[activeSub]:[]);
  useEffect(()=>{if(activeModule)setOpenModules(v=>v.includes(activeModule)?v:[...v,activeModule]);if(activeSub)setOpenSubs(v=>v.includes(activeSub)?v:[...v,activeSub]);},[activeModule,activeSub]);
  const logout=async()=>{const id=toast.loading('Logging out...');try{await axios.post('/v1/auth/logout');toast.success('Berhasil Logout!',{id});}catch(e){console.error(e);toast.success('Sesi lokal ditutup.',{id});}finally{['token','user','permissions','erp_context','foundation_loaded','erp_role'].forEach(k=>localStorage.removeItem(k));navigate('/');}};
  return <aside className="w-72 bg-stone-900 text-stone-300 flex flex-col">
    <div className="p-6 border-b border-stone-800 flex items-center gap-3"><div className="flex h-8 w-8 items-center justify-center rounded bg-amber-700 font-bold text-white text-xs">KM</div><div className="min-w-0"><span className="font-bold text-white">{developer?'Developer Console':'Backoffice'}</span><p className="text-xs text-stone-300 truncate">{user.name||'Admin'}{developer?' · GOD MODE':''}</p></div></div>
    <div className="px-4 pt-4"><div className="rounded-xl border border-stone-800 bg-stone-950/40 px-3 py-2 text-[11px] text-stone-400"><div className="font-semibold text-stone-300">{developer?'Platform + Tenant Context':'Organization Context'}</div><div className="mt-1 truncate">T: {context.tenant_id??'-'} · C: {context.company_id??'-'} · B: {context.branch_id??'-'}</div></div></div>
    <nav className="flex-1 p-4 space-y-2 overflow-y-auto">
      <button onClick={()=>navigate('/dashboard')} className={`w-full flex gap-3 px-4 py-3 rounded-xl text-left ${activePage==='dashboard'?'bg-amber-700/20 text-amber-500 font-medium':'hover:bg-stone-800 hover:text-white'}`}>📊 Dashboard</button>
      {allowedModules.map(m=>{const open=openModules.includes(m.key),active=activeModule===m.key;return <div key={m.key}><button onClick={()=>setOpenModules(v=>v.includes(m.key)?v.filter(x=>x!==m.key):[...v,m.key])} className={`w-full flex items-center justify-between gap-3 px-4 py-3 rounded-xl text-left ${active?'bg-stone-800 text-white':'hover:bg-stone-800 hover:text-white'}`}><span className="flex items-center gap-3"><span>{m.icon}</span><span className="font-semibold">{m.label}</span></span><span>{open?'⌃':'⌄'}</span></button>{open&&<div className="mt-1 ml-3 space-y-1 border-l border-stone-800 pl-2">{(m.items??[]).map(i=><button key={i.key} onClick={()=>navigate(i.path)} className={`w-full flex gap-3 rounded-lg px-3 py-2.5 text-left text-sm ${activePage===i.key?'bg-amber-700/20 text-amber-500 font-medium':'hover:bg-stone-800 hover:text-white'}`}><span>{i.icon}</span>{i.label}</button>)}{(m.subgroups??[]).map(g=>{const subOpen=openSubs.includes(g.key),subActive=g.items.some(i=>i.key===activePage);return <div key={g.key}><button onClick={()=>setOpenSubs(v=>v.includes(g.key)?v.filter(x=>x!==g.key):[...v,g.key])} className={`w-full flex items-center justify-between rounded-lg px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide ${subActive?'text-amber-500':'text-stone-500 hover:text-stone-300'}`}><span>{g.label}</span><span>{subOpen?'−':'+'}</span></button>{subOpen&&<div className="space-y-1 pl-2">{g.items.map(i=><button key={`${g.key}-${i.key}`} onClick={()=>navigate(i.path)} className={`w-full flex gap-3 rounded-lg px-3 py-2 text-left text-sm ${activePage===i.key?'bg-amber-700/20 text-amber-500 font-medium':'hover:bg-stone-800 hover:text-white'}`}><span>{i.icon}</span>{i.label}</button>)}</div>}</div>;})}</div>}</div>})}
    </nav>
    <div className="border-t border-stone-800 p-4"><button onClick={logout} className="w-full flex items-center justify-center gap-2 rounded-xl bg-red-500/10 px-4 py-3 text-sm font-bold text-red-400 hover:bg-red-500/20">⎋ Logout</button></div>
  </aside>;
}

import { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import AdminSidebar from '../components/AdminSidebar';
import api from '../core/api/client';
import { extractRows } from '../core/api/normalize';
import { can } from '../core/auth/permissions';
import toast from 'react-hot-toast';

type Row = Record<string, unknown>;
type Option = { id: string; label: string };
type Line = { productId: string; productName: string; quantity: number; unitCost: number };
type Section = 'suppliers'|'requisitions'|'orders'|'receipts'|'invoices'|'payments'|'returns'|'credit-notes'|'budgets'|'approval-matrix'|'reconciliation'|'reports';

const sections: Array<{key:Section; label:string; permission:string}> = [
  {key:'suppliers',label:'Suppliers',permission:'purchasing.supplier.view'},
  {key:'requisitions',label:'Purchase Requisitions',permission:'purchasing.requisition.view'},
  {key:'orders',label:'Purchase Orders',permission:'purchasing.order.view'},
  {key:'receipts',label:'Goods Receipts',permission:'purchasing.receipt.view'},
  {key:'invoices',label:'Supplier Invoices',permission:'purchasing.ap.view'},
  {key:'payments',label:'Supplier Payments',permission:'purchasing.ap.view'},
  {key:'returns',label:'Supplier Returns',permission:'purchasing.return.view'},
  {key:'credit-notes',label:'Credit Notes',permission:'purchasing.credit_note.view'},
  {key:'budgets',label:'Budget',permission:'purchasing.budget.view'},
  {key:'approval-matrix',label:'Approval Matrix',permission:'purchasing.approval_matrix.view'},
  {key:'reconciliation',label:'Reconciliation',permission:'purchasing.reconciliation.view'},
  {key:'reports',label:'Reports',permission:'purchasing.reporting.view'},
];

const endpoints: Record<Section,string> = {
  suppliers:'/purchasing/suppliers', requisitions:'/purchasing/requisitions', orders:'/purchasing/orders', receipts:'/purchasing/goods-receipts', invoices:'/purchasing/invoices', payments:'/purchasing/payments', returns:'/purchasing/returns', 'credit-notes':'/purchasing/credit-notes', budgets:'/purchasing/budgets', 'approval-matrix':'/purchasing/approval-matrix', reconciliation:'/purchasing/reconciliation/orders', reports:'/purchasing/reports/dashboard',
};

function text(row: Row, keys:string[]): string { for(const key of keys){ const value=row[key]; if(value!==undefined&&value!==null&&String(value)!=='') return String(value); } return row.id?`#${String(row.id)}`:'-'; }
function n(value: unknown): number { const parsed=Number(value); return Number.isFinite(parsed)?parsed:0; }
function money(value: unknown): string { return new Intl.NumberFormat('id-ID',{style:'currency',currency:'IDR',maximumFractionDigits:0}).format(n(value)); }
function opts(rows:Row[], keys:string[]):Option[]{ return rows.map(row=>({id:String(row.id),label:text(row,keys)})); }

export default function PurchasingWorkspaceV2(){
  const navigate=useNavigate();
  const allowed=useMemo(()=>sections.filter(section=>can(section.permission)),[]);
  const [section,setSection]=useState<Section>(allowed[0]?.key ?? 'orders');
  const [rows,setRows]=useState<Row[]>([]); const [loading,setLoading]=useState(true); const [error,setError]=useState('');
  const [showForm,setShowForm]=useState(false); const [form,setForm]=useState<Record<string,string>>({});
  const [suppliers,setSuppliers]=useState<Option[]>([]); const [products,setProducts]=useState<Array<Option & {price:number}>>([]); const [warehouses,setWarehouses]=useState<Option[]>([]); const [requisitions,setRequisitions]=useState<Option[]>([]); const [orders,setOrders]=useState<Option[]>([]); const [receipts,setReceipts]=useState<Option[]>([]); const [invoices,setInvoices]=useState<Option[]>([]); const [accounts,setAccounts]=useState<Option[]>([]);
  const [lines,setLines]=useState<Line[]>([{productId:'',productName:'',quantity:1,unitCost:0}]);

  const loadMasters=async()=>{
    const safe=async<T>(path:string, mapper:(rows:Row[])=>T, fallback:T):Promise<T>=>{try{const response=await api.get(path);return mapper(extractRows<Row>(response.data));}catch{return fallback;}};
    const [supplierRows,productRows,warehouseRows,requisitionRows,orderRows,receiptRows,invoiceRows,accountRows,inventoryRows]=await Promise.all([
      safe('/purchasing/suppliers',rows=>opts(rows,['name','code']),[]),
      safe('/products',rows=>rows.map(row=>({id:String(row.id),label:text(row,['name','code']),price:n(row.price)})),[]),
      safe('/inventory/balances',rows=>{const map=new Map<string,Option>();rows.forEach(row=>{const id=String(row.warehouse_id??'');if(id&&!map.has(id))map.set(id,{id,label: text(row,['warehouse_name','warehouse_code']) !== '-' ? text(row,['warehouse_name','warehouse_code']) : `Gudang #${id}`});});return [...map.values()];},[]),
      safe('/purchasing/requisitions',rows=>opts(rows,['requisition_number','number']),[]),
      safe('/purchasing/orders',rows=>opts(rows,['order_number','number']),[]),
      safe('/purchasing/goods-receipts',rows=>opts(rows,['receipt_number','number']),[]),
      safe('/purchasing/invoices',rows=>opts(rows,['invoice_number','number']),[]),
      safe('/erp/accounting/accounts',rows=>opts(rows,['name','code']),[]),
      safe('/inventory/balances',rows=>rows,[]),
    ]);
    setSuppliers(supplierRows);setProducts(productRows);setWarehouses(warehouseRows);setRequisitions(requisitionRows);setOrders(orderRows);setReceipts(receiptRows);setInvoices(invoiceRows);setAccounts(accountRows);
    if(!warehouseRows.length && inventoryRows.length) toast('Warehouse master belum punya endpoint list; form memakai context inventory yang tersedia.',{icon:'ℹ️'});
  };

  const load=async()=>{setLoading(true);setError('');try{const response=await api.get(endpoints[section]);setRows(extractRows<Row>(response.data));}catch(err){const message=err&&typeof err==='object'&&'response' in err?String((err as {response?:{data?:{message?:string}}}).response?.data?.message??''):'';setRows([]);setError(message||'Data tidak dapat dimuat pada context organisasi aktif.');}finally{setLoading(false);}};

  useEffect(()=>{void loadMasters();},[]); useEffect(()=>{void load();},[section]);
  const reset=()=>{setForm({});setLines([{productId:'',productName:'',quantity:1,unitCost:0}]);setShowForm(false);};
  const setField=(key:string,value:string)=>setForm(current=>({...current,[key]:value}));
  const updateProduct=(index:number,productId:string)=>{const product=products.find(item=>item.id===productId);setLines(current=>current.map((line,i)=>i===index?{...line,productId,productName:product?.label??'',unitCost:product?.price??0}:line));};
  const subtotal=lines.reduce((sum,line)=>sum+(line.quantity*line.unitCost),0); const discount=Math.max(0,n(form.discount_amount)); const taxPercent=Math.max(0,n(form.tax_percent)); const tax=(Math.max(0,subtotal-discount)*taxPercent)/100; const total=Math.max(0,subtotal-discount+tax);

  const submit=async()=>{
    try{
      let payload:Record<string,unknown>={};
      if(section==='suppliers') payload={code:form.code,name:form.name,tax_id:form.tax_id||undefined,contact_name:form.contact_name||undefined,phone:form.phone||undefined,email:form.email||undefined,address:form.address||undefined,payment_terms_days:form.payment_terms_days?Number(form.payment_terms_days):undefined,status:form.status||'active'};
      if(section==='requisitions') payload={warehouse_id:Number(form.warehouse_id),needed_by:form.needed_by||undefined,reason:form.reason||undefined,notes:form.notes||undefined,items:lines.filter(line=>line.productId).map(line=>({product_id:line.productId,quantity:line.quantity,estimated_unit_cost:line.unitCost}))};
      if(section==='orders') payload={supplier_id:Number(form.supplier_id),warehouse_id:Number(form.warehouse_id),purchase_requisition_id:form.purchase_requisition_id?Number(form.purchase_requisition_id):undefined,expected_date:form.expected_date||undefined,discount_amount:discount,tax_amount:tax,notes:form.notes||undefined,items:lines.filter(line=>line.productId).map(line=>({product_id:line.productId,quantity:line.quantity,unit_cost:line.unitCost}))};
      if(section==='invoices') payload={goods_receipt_id:Number(form.goods_receipt_id),invoice_number:form.invoice_number,invoice_date:form.invoice_date||undefined,due_date:form.due_date||undefined,notes:form.notes||undefined};
      if(section==='payments') payload={supplier_invoice_id:Number(form.supplier_invoice_id),amount:Number(form.amount),method:form.method||'bank_transfer',reference:form.reference||undefined,notes:form.notes||undefined};
      if(section==='budgets') payload={budget_year:Number(form.budget_year),allocated_amount:Number(form.allocated_amount),notes:form.notes||undefined};
      if(section==='approval-matrix'){toast('Approval Matrix membutuhkan role ID dari administration. Master role GET belum tersedia, jadi create dinonaktifkan.',{icon:'ℹ️'});return;}
      if(section==='cash-book') return;
      if(section==='returns'||section==='receipts'||section==='credit-notes'||section==='reconciliation'||section==='reports') return;
      if(section==='suppliers' && !can('purchasing.supplier.create')){toast.error('Tidak memiliki permission membuat supplier.');return;}
      await api.post(endpoints[section],payload);toast.success(`${sections.find(item=>item.key===section)?.label??'Data'} berhasil disimpan.`);reset();await load();
    }catch(err){const message=err&&typeof err==='object'&&'response' in err?String((err as {response?:{data?:{message?:string}}}).response?.data?.message??''):'';toast.error(message||'Gagal menyimpan data.');}
  };

  const action=async(id:string,actionName:'submit'|'approve'|'reject'|'cancel')=>{try{const body=actionName==='reject'?{reason:window.prompt('Alasan penolakan')||''}:undefined;await api.post(`${endpoints[section]}/${id}/${actionName}`,body);toast.success(`Dokumen berhasil ${actionName}.`);await load();}catch(err){const message=err&&typeof err==='object'&&'response' in err?String((err as {response?:{data?:{message?:string}}}).response?.data?.message??''):'';toast.error(message||`Gagal ${actionName} dokumen.`);}};

  const formFields=()=>{
    if(section==='suppliers') return <div className="grid gap-3 md:grid-cols-2"><Field label="Kode Supplier" k="code" req/><Field label="Nama Supplier" k="name" req/><Field label="Kontak" k="contact_name"/><Field label="Telepon" k="phone"/><Field label="Email" k="email"/><Field label="Termin Pembayaran (hari)" k="payment_terms_days" type="number"/><Field label="NPWP" k="tax_id"/><Field label="Alamat" k="address"/></div>;
    if(section==='requisitions') return <div className="grid gap-3 md:grid-cols-2"><Select label="Warehouse" k="warehouse_id" items={warehouses} req/><Field label="Dibutuhkan tanggal" k="needed_by" type="date"/><Field label="Alasan" k="reason"/><Field label="Catatan" k="notes"/><LineEditor/></div>;
    if(section==='orders') return <div className="grid gap-3 md:grid-cols-2"><Select label="Supplier" k="supplier_id" items={suppliers} req/><Select label="Warehouse" k="warehouse_id" items={warehouses} req/><Select label="Requisition" k="purchase_requisition_id" items={requisitions}/><Field label="Expected Date" k="expected_date" type="date"/><Field label="Diskon" k="discount_amount" type="number"/><Field label="PPN %" k="tax_percent" type="number"/><Field label="Catatan" k="notes"/><LineEditor showSummary/></div>;
    if(section==='invoices') return <div className="grid gap-3 md:grid-cols-2"><Select label="Goods Receipt" k="goods_receipt_id" items={receipts} req/><Field label="Nomor Invoice" k="invoice_number" req/><Field label="Tanggal Invoice" k="invoice_date" type="date"/><Field label="Jatuh Tempo" k="due_date" type="date"/><Field label="Catatan" k="notes"/></div>;
    if(section==='payments') return <div className="grid gap-3 md:grid-cols-2"><Select label="Supplier Invoice" k="supplier_invoice_id" items={invoices} req/><Field label="Jumlah Pembayaran" k="amount" type="number" req/><Select label="Metode" k="method" items={['cash','bank_transfer','giro','other'].map(value=>({id:value,label:value}))}/><Field label="Referensi" k="reference"/><Field label="Catatan" k="notes"/></div>;
    if(section==='budgets') return <div className="grid gap-3 md:grid-cols-2"><Field label="Tahun Anggaran" k="budget_year" type="number" req/><Field label="Allocated Amount" k="allocated_amount" type="number" req/><Field label="Catatan" k="notes"/></div>;
    return <div className="rounded-xl bg-amber-50 p-4 text-sm text-amber-800">Operasi ini menggunakan workflow berbasis dokumen. Pilih dokumen di tabel, lalu gunakan action yang tersedia.</div>;
  };

  function Field({label,k,type='text',req=false}:{label:string;k:string;type?:string;req?:boolean}){return <label className="block"><span className="mb-1 block text-xs font-bold text-stone-500">{label}{req?' *':''}</span><input type={type} value={form[k]??''} onChange={e=>setField(k,e.target.value)} className="w-full rounded-xl border border-stone-200 px-3 py-2.5 text-sm outline-none focus:border-amber-600" /></label>}
  function Select({label,k,items,req=false}:{label:string;k:string;items:Option[];req?:boolean}){return <label className="block"><span className="mb-1 block text-xs font-bold text-stone-500">{label}{req?' *':''}</span><select value={form[k]??''} onChange={e=>setField(k,e.target.value)} className="w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm"><option value="">Pilih {label.toLowerCase()}...</option>{items.map(item=><option key={item.id} value={item.id}>{item.label}</option>)}</select></label>}
  function LineEditor({showSummary=false}:{showSummary?:boolean}){return <div className="rounded-2xl border border-stone-200 p-4 md:col-span-2"><div className="mb-3 flex items-center justify-between"><div><div className="font-bold">Item</div><div className="text-xs text-stone-500">Harga otomatis dari master Product.</div></div><button type="button" onClick={()=>setLines(current=>[...current,{productId:'',productName:'',quantity:1,unitCost:0}])} className="rounded-lg border px-3 py-2 text-xs font-bold">+ Tambah Item</button></div>{lines.map((line,index)=><div key={index} className="mb-2 grid gap-2 md:grid-cols-[1fr_120px_160px_140px_40px] items-end"><label><span className="mb-1 block text-xs font-bold text-stone-500">Produk</span><select value={line.productId} onChange={e=>updateProduct(index,e.target.value)} className="w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm"><option value="">Pilih produk...</option>{products.map(product=><option key={product.id} value={product.id}>{product.label}</option>)}</select></label><label><span className="mb-1 block text-xs font-bold text-stone-500">Qty</span><input type="number" min={1} value={line.quantity} onChange={e=>setLines(current=>current.map((item,i)=>i===index?{...item,quantity:Math.max(1,n(e.target.value)||1)}:item))} className="w-full rounded-xl border px-3 py-2.5 text-sm"/></label><label><span className="mb-1 block text-xs font-bold text-stone-500">Harga Beli</span><input type="number" min={0} value={line.unitCost} onChange={e=>setLines(current=>current.map((item,i)=>i===index?{...item,unitCost:Math.max(0,n(e.target.value))}:item))} className="w-full rounded-xl border px-3 py-2.5 text-sm"/></label><div><span className="mb-1 block text-xs font-bold text-stone-500">Jumlah</span><div className="rounded-xl bg-stone-50 px-3 py-2.5 text-sm font-bold">{money(line.quantity*line.unitCost)}</div></div><button type="button" disabled={lines.length===1} onClick={()=>setLines(current=>current.filter((_,i)=>i!==index))} className="h-10 rounded-xl border border-red-100 text-red-500 disabled:opacity-30">×</button></div>)}{showSummary&&<div className="mt-4 ml-auto max-w-sm space-y-1 text-sm"><div className="flex justify-between"><span>Subtotal</span><strong>{money(subtotal)}</strong></div><div className="flex justify-between"><span>Diskon</span><strong>- {money(discount)}</strong></div><div className="flex justify-between"><span>PPN</span><strong>{money(tax)}</strong></div><div className="flex justify-between border-t pt-2 text-lg"><span>Grand Total</span><strong>{money(total)}</strong></div></div>}</div>}

  const label=sections.find(item=>item.key===section)?.label??'Purchasing'; const canCreate=section==='suppliers'?can('purchasing.supplier.create'):(section==='requisitions'?can('purchasing.requisition.create'):section==='orders'?can('purchasing.order.create'):section==='invoices'?can('purchasing.ap.create'):section==='payments'?can('purchasing.ap.pay'):section==='budgets'?can('purchasing.budget.create'):false);
  const columns=rows.slice(0,20).flatMap(row=>Object.keys(row).filter(key=>!['created_at','updated_at','deleted_at'].includes(key))).filter((key,index,array)=>array.indexOf(key)===index).slice(0,6);

  return <div className="flex h-screen w-full bg-stone-50 text-stone-800"><AdminSidebar activePage="purchasing-orders"/><div className="flex min-w-0 flex-1 flex-col overflow-hidden"><header className="border-b border-stone-200 bg-white px-8 py-5"><div className="text-xs font-bold uppercase tracking-[0.18em] text-amber-700">ERP · Purchasing</div><div className="mt-1 flex flex-wrap items-center justify-between gap-3"><div><h1 className="text-2xl font-bold text-stone-900">{label}</h1><p className="text-sm text-stone-500">Workflow purchasing berbasis data bisnis dan organization context.</p></div>{canCreate&&<button onClick={()=>setShowForm(true)} className="rounded-xl bg-amber-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-amber-800">+ Tambah</button>}</div></header><main className="min-h-0 flex-1 overflow-y-auto p-6 lg:p-8"><div className="mb-5 flex gap-2 overflow-x-auto">{allowed.map(item=><button key={item.key} onClick={()=>{setSection(item.key);reset();}} className={`whitespace-nowrap rounded-xl px-3 py-2 text-sm font-bold ${section===item.key?'bg-amber-700 text-white':'border border-stone-200 bg-white text-stone-600 hover:bg-stone-50'}`}>{item.label}</button>)}</div>{error&&<div className="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{error}</div>}{loading?<div className="rounded-2xl border bg-white p-10 text-center text-sm text-stone-500">Memuat {label}…</div>:<div className="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm">{rows.length===0?<div className="p-10 text-center text-sm text-stone-500">Belum ada data pada context saat ini.</div>:<div className="overflow-x-auto"><table className="min-w-full text-sm"><thead className="bg-stone-50"><tr>{columns.map(column=><th key={column} className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-stone-500">{column.replaceAll('_',' ')}</th>)}{(section==='orders')&&<th className="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-stone-500">Aksi</th>}</tr></thead><tbody>{rows.map((row,index)=><tr key={String(row.id??index)} className="border-t border-stone-100 hover:bg-stone-50">{columns.map(column=><td key={column} className="px-4 py-3 align-top"><span className="break-words">{typeof row[column]==='object'?'—':String(row[column]??'—')}</span></td>)}{section==='orders'&&<td className="px-4 py-3 text-right"><div className="flex justify-end gap-1">{['submit','approve','reject','cancel'].map(name=><button key={name} onClick={()=>void action(String(row.id),name as 'submit'|'approve'|'reject'|'cancel')} className="rounded-lg border border-stone-200 px-2 py-1 text-xs font-bold capitalize hover:bg-stone-100">{name}</button>)}</div></td>}</tr>)}</tbody></table></div>}</div>}{showForm&&<div className="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4"><div className="max-h-[90vh] w-full max-w-5xl overflow-y-auto rounded-3xl bg-stone-50 p-6 shadow-xl"><div className="mb-5 flex items-center justify-between"><div><h2 className="text-xl font-bold">Tambah {label}</h2><p className="text-sm text-stone-500">Isi data bisnis. Payload teknis dibuat otomatis.</p></div><button onClick={reset} className="rounded-lg px-3 py-2 text-xl text-stone-500">×</button></div><div className="grid gap-3">{formFields()}</div><div className="mt-6 flex justify-end gap-2"><button onClick={reset} className="rounded-xl border border-stone-200 bg-white px-4 py-2.5 text-sm font-bold">Batal</button><button onClick={()=>void submit()} className="rounded-xl bg-amber-700 px-4 py-2.5 text-sm font-bold text-white">Simpan</button></div></div></div>}</main></div></div>;
}

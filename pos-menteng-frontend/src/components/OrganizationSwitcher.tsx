import { useEffect, useState } from 'react';
import api from '../core/api/client';

type Membership = { id:number; tenant_id:number; company_id:number|null; branch_id:number|null; status:string; is_primary:boolean; tenant?:{name?:string}; company?:{name?:string}; branch?:{name?:string}; };

export default function OrganizationSwitcher(){
  const [items,setItems]=useState<Membership[]>([]); const [selected,setSelected]=useState('');
  useEffect(()=>{api.get('/v1/my-memberships').then(({data})=>{const rows=Array.isArray(data?.data)?data.data:[];setItems(rows);const current=JSON.parse(localStorage.getItem('erp_context')||'{}');const match=rows.find((row:Membership)=>row.tenant_id===current.tenant_id&&row.company_id===current.company_id&&row.branch_id===current.branch_id)||rows.find((row:Membership)=>row.is_primary);if(match)setSelected(String(match.id));}).catch(()=>undefined);},[]);
  const change=(value:string)=>{setSelected(value);const membership=items.find(item=>String(item.id)===value);if(!membership)return;localStorage.setItem('erp_context',JSON.stringify({tenant_id:membership.tenant_id,company_id:membership.company_id,branch_id:membership.branch_id}));window.location.reload();};
  return <label style={{display:'grid',gap:4,fontSize:12}}><span>Organization context</span><select value={selected} onChange={e=>change(e.target.value)}>{items.map(item=><option key={item.id} value={item.id}>{item.tenant?.name||`Tenant ${item.tenant_id}`} · {item.company?.name||`Company ${item.company_id??'-'}`} · {item.branch?.name||`Branch ${item.branch_id??'-'}`}</option>)}</select></label>;
}

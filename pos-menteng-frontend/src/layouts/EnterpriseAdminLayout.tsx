import type { ReactNode } from 'react';
import PermissionGate from '../components/PermissionGate';
import OrganizationSwitcher from '../components/OrganizationSwitcher';

export default function EnterpriseAdminLayout({ children }: { children: ReactNode }) {
  return <div style={{minHeight:'100vh'}}>
    <header style={{padding:16,display:'flex',justifyContent:'space-between',alignItems:'center',gap:16}}>
      <strong>Kopi Menteng ERP · Administration</strong>
      <OrganizationSwitcher />
    </header>
    <PermissionGate permission="rbac.role.view" fallback={<main style={{padding:24}}>Access denied.</main>}>
      <main>{children}</main>
    </PermissionGate>
  </div>;
}

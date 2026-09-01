import type { ReactNode } from 'react';
import { can, canAny } from '../core/auth/permissions';

type Props = { permission?: string; anyOf?: string[]; children: ReactNode; fallback?: ReactNode };

export default function PermissionGate({ permission, anyOf, children, fallback = null }: Props) {
  const allowed = permission ? can(permission) : anyOf ? canAny(anyOf) : false;
  return allowed ? <>{children}</> : <>{fallback}</>;
}

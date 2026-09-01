import { useEffect, useState } from 'react';
import api from '../api/client';

type Context = { tenant_id: number | null; company_id: number | null; branch_id: number | null; location_id: number | null; location_type?: string | null; role?: string; permissions?: string[] };

export function useFoundationContext() {
  const hasToken = Boolean(localStorage.getItem('token'));
  const [context, setContext] = useState<Context | null>(null);
  const [loading, setLoading] = useState(hasToken);

  useEffect(() => {
    const token = localStorage.getItem('token');
    if (!token) {
      localStorage.removeItem('foundation_loaded');
      return;
    }

    let alive = true;

    api.get('/v1/me')
      .then(({ data }) => {
        const next = data?.data || data;
        const apiContext = next?.context || {};
        const role = next?.role || apiContext?.role;
        const permissions = Array.isArray(next?.permissions)
          ? next.permissions
          : role === 'tenant-admin'
            ? ['*']
            : [];
        const normalized = {
          ...next,
          tenant_id: next?.tenant_id ?? apiContext?.tenant_id ?? null,
          company_id: next?.company_id ?? apiContext?.company_id ?? null,
          branch_id: next?.branch_id ?? apiContext?.branch_id ?? null,
          location_id: next?.location_id ?? apiContext?.location_id ?? null,
          location_type: next?.location_type ?? apiContext?.location_type ?? null,
          role,
          permissions,
        };

        if (!alive) return;
        setContext(normalized);
        localStorage.setItem('erp_context', JSON.stringify({
          tenant_id: normalized.tenant_id,
          company_id: normalized.company_id,
          branch_id: normalized.branch_id,
          location_id: normalized.location_id,
          location_type: normalized.location_type,
        }));
        localStorage.setItem('erp_role', String(normalized.role || ''));
        localStorage.setItem('permissions', JSON.stringify(permissions));
      })
      .catch(() => {
        if (!alive) return;
        setContext(null);
      })
      .finally(() => {
        if (!alive) return;
        localStorage.setItem('foundation_loaded', 'true');
        setLoading(false);
      });

    return () => { alive = false; };
  }, []);

  return { context, loading };
}

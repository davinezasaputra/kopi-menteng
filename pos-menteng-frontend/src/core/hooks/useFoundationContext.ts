import { useEffect, useState } from 'react';
import api from '../api/client';

type Context = { tenant_id: number | null; company_id: number | null; branch_id: number | null; role?: string; permissions?: string[] };

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
        if (!alive) return;
        setContext(next);
        localStorage.setItem('erp_context', JSON.stringify({
          tenant_id: next.tenant_id,
          company_id: next.company_id,
          branch_id: next.branch_id,
        }));
        localStorage.setItem('permissions', JSON.stringify(next.permissions || []));
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

import axios from 'axios';
import { API_URL } from '../config/env';

axios.defaults.baseURL = API_URL;
axios.defaults.headers.common.Accept = 'application/json';
axios.defaults.headers.common['Content-Type'] = 'application/json';

const isMutating = (method?: string) => ['POST', 'PUT', 'PATCH', 'DELETE'].includes((method || 'GET').toUpperCase());
const isExcludedIdempotencyPath = (url: string) => /\/v1\/auth\/(login|login-pin|logout)$/.test(url) || /\/midtrans\/webhook$/.test(url);
const requestPath = (url?: string) => {
  if (!url) return '';
  try { return new URL(url, API_URL).pathname; } catch { return url.split('?')[0] || ''; }
};

axios.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  if (token) config.headers.Authorization = `Bearer ${token}`;

  const contextRaw = localStorage.getItem('erp_context');
  if (contextRaw) {
    try {
      const context = JSON.parse(contextRaw) as { tenant_id?: number; company_id?: number; branch_id?: number };
      if (context.tenant_id) config.headers['X-Tenant-ID'] = String(context.tenant_id);
      if (context.company_id) config.headers['X-Company-ID'] = String(context.company_id);
      if (context.branch_id) config.headers['X-Branch-ID'] = String(context.branch_id);
    } catch {
      // Backend remains authoritative when client-side context is invalid.
    }
  }

  if (!config.headers['X-Request-ID']) {
    const requestId = typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function'
      ? crypto.randomUUID()
      : `web-${Date.now()}-${Math.random().toString(36).slice(2, 12)}`;
    config.headers['X-Request-ID'] = requestId;
  }

  const path = requestPath(config.url);
  if (isMutating(config.method) && !isExcludedIdempotencyPath(path) && !config.headers['X-Idempotency-Key']) {
    const slug = path.replace(/[^A-Za-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(-35) || 'request';
    config.headers['X-Idempotency-Key'] = `WEB-${Date.now()}-${slug}`.slice(0, 100);
  }

  return config;
});

axios.interceptors.response.use((response) => response, (error) => {
  if (error.response?.status === 401) {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    localStorage.removeItem('erp_context');
    localStorage.removeItem('permissions');
    localStorage.removeItem('foundation_loaded');
  }
  return Promise.reject(error);
});

export const api = axios;
export default axios;

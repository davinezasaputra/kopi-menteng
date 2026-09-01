import axios from 'axios';
import { API_URL } from '../config/env';

axios.defaults.baseURL = API_URL;
axios.defaults.headers.common.Accept = 'application/json';
axios.defaults.headers.common['Content-Type'] = 'application/json';

axios.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  const contextRaw = localStorage.getItem('erp_context');
  if (token) config.headers.Authorization = `Bearer ${token}`;
  if (contextRaw) {
    try {
      const context = JSON.parse(contextRaw) as { tenant_id?: number; company_id?: number; branch_id?: number };
      if (context.tenant_id) config.headers['X-Tenant-ID'] = String(context.tenant_id);
      if (context.company_id) config.headers['X-Company-ID'] = String(context.company_id);
      if (context.branch_id) config.headers['X-Branch-ID'] = String(context.branch_id);
    } catch {}
  }
  return config;
});

axios.interceptors.response.use((response) => response, (error) => {
  if (error.response?.status === 401) {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    localStorage.removeItem('erp_context');
    localStorage.removeItem('permissions');
  }
  return Promise.reject(error);
});

export const api = axios;
export default axios;

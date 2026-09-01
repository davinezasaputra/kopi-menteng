import api from '../core/api/client';

export const foundationService = {
  me: () => api.get('/v1/me'),
  memberships: () => api.get('/v1/memberships'),
  roles: () => api.get('/v1/roles'),
  permissions: () => api.get('/v1/permissions'),
  auditLogs: (params?: Record<string,string|number>) => api.get('/v1/audit-logs', { params }),
};

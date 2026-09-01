export function getPermissions(): string[] {
  try { return JSON.parse(localStorage.getItem('permissions') || '[]'); } catch { return []; }
}

export function isDeveloper(): boolean {
  try {
    const user = JSON.parse(localStorage.getItem('user') || '{}') as { role?: string };
    return user.role === 'developer' || localStorage.getItem('erp_role') === 'developer';
  } catch {
    return localStorage.getItem('erp_role') === 'developer';
  }
}

export function can(permission: string): boolean {
  const current = getPermissions();
  return isDeveloper() || current.includes('*') || current.includes(permission);
}

export function canAny(permissions: string[]): boolean {
  const current = getPermissions();
  return isDeveloper() || current.includes('*') || permissions.some((permission) => current.includes(permission));
}

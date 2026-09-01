export function getPermissions(): string[] {
  try { return JSON.parse(localStorage.getItem('permissions') || '[]'); } catch { return []; }
}

export function can(permission: string): boolean {
  const current = getPermissions();
  return current.includes('*') || current.includes(permission);
}

export function canAny(permissions: string[]): boolean {
  const current = getPermissions();
  return current.includes('*') || permissions.some((permission) => current.includes(permission));
}

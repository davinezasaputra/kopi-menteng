export function getPermissions(): string[] {
  try { return JSON.parse(localStorage.getItem('permissions') || '[]'); } catch { return []; }
}

export function can(permission: string): boolean {
  return getPermissions().includes(permission);
}

export function canAny(permissions: string[]): boolean {
  const current = getPermissions();
  return permissions.some((permission) => current.includes(permission));
}

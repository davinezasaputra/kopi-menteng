export function extractRows<T>(payload: unknown): T[] {
  if (Array.isArray(payload)) return payload as T[];

  if (payload && typeof payload === 'object') {
    const value = payload as { data?: unknown };
    if (Array.isArray(value.data)) return value.data as T[];
    if (value.data && typeof value.data === 'object') {
      const nested = value.data as { data?: unknown };
      if (Array.isArray(nested.data)) return nested.data as T[];
    }
  }

  return [];
}

export function extractData<T>(payload: unknown): T | null {
  if (!payload || typeof payload !== 'object') return null;
  const value = payload as { data?: unknown };
  if (value.data && typeof value.data === 'object' && !Array.isArray(value.data)) {
    const nested = value.data as { data?: unknown };
    if ('data' in nested && nested.data && typeof nested.data === 'object') return nested.data as T;
  }
  return (value.data as T | undefined) ?? (payload as T);
}

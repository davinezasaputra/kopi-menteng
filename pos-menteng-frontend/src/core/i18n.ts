export type Locale = 'id' | 'en';

export const getLocale = (): Locale => (localStorage.getItem('erp_locale') === 'en' ? 'en' : 'id');

export const setLocale = (locale: Locale): void => {
  localStorage.setItem('erp_locale', locale);
  document.documentElement.lang = locale;
  window.dispatchEvent(new Event('erp-locale-change'));
};

export const localeLabel = (locale: Locale): string => locale === 'en' ? 'English' : 'Bahasa Indonesia';

export const translations: Record<Locale, Record<string, string>> = {
  id: {
    dashboard: 'Dashboard', platform: 'Platform', developerConsole: 'Developer Console', organization: 'Organization', organizationExplorer: 'Organization Explorer',
    erp: 'ERP', pos: 'POS', hrm: 'HRM', administration: 'Administration', inventory: 'Inventory', purchasing: 'Purchasing', finance: 'Finance & Accounting', sales: 'Sales',
    operations: 'Operations Center', guided: 'Guided Workspace', enterpriseOperations: 'Enterprise Operations', products: 'Produk', stockControl: 'Kontrol Persediaan', rawMaterials: 'Bahan Baku', customers: 'Pelanggan', users: 'Users', access: 'Organizations & Access',
    cashier: 'Kasir', receipt: 'Template Nota & Struk', employees: 'Karyawan', payroll: 'HRD & Penggajian', language: 'Bahasa', logout: 'Logout',
  },
  en: {
    dashboard: 'Dashboard', platform: 'Platform', developerConsole: 'Developer Console', organization: 'Organization', organizationExplorer: 'Organization Explorer',
    erp: 'ERP', pos: 'POS', hrm: 'HRM', administration: 'Administration', inventory: 'Inventory', purchasing: 'Purchasing', finance: 'Finance & Accounting', sales: 'Sales',
    operations: 'Operations Center', guided: 'Guided Workspace', enterpriseOperations: 'Enterprise Operations', products: 'Products', stockControl: 'Inventory Control', rawMaterials: 'Raw Materials', customers: 'Customers', users: 'Users', access: 'Organizations & Access',
    cashier: 'Cashier', receipt: 'Receipt Template', employees: 'Employees', payroll: 'HR & Payroll', language: 'Language', logout: 'Logout',
  },
};

export const t = (key: string, locale: Locale = getLocale()): string => translations[locale][key] ?? key;

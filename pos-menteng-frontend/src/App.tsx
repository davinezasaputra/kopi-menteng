import { useEffect, type ReactNode } from 'react';
import { BrowserRouter, Routes, Route, Navigate, useNavigate } from 'react-router-dom';
import { Toaster } from 'react-hot-toast';
import axios from 'axios';
import './core/api/client';
import { can, canAny } from './core/auth/permissions';
import { useFoundationContext } from './core/hooks/useFoundationContext';
import Login from './pages/Login';
import AdminLogin from './pages/AdminLogin';
import Pos from './pages/Pos';
import Inventory from './pages/Inventory';
import InventoryOperations from './pages/InventoryOperations';
import History from './pages/History';
import RawMaterials from './pages/RawMaterials';
import RawMaterialImport from './pages/RawMaterialImport';
import Dashboard from './pages/Dashboard';
import Users from './pages/Users';
import Accounting from './pages/Accounting';
import Customers from './pages/Customer';
import Hrm from './pages/Hrm';
import Employees from './pages/Employees';
import FoundationAdmin from './pages/admin/FoundationAdmin';
import EnterpriseOperations from './pages/EnterpriseOperations';
import GuidedOperations from './pages/GuidedOperations';

const AxiosInterceptor = ({ children }: { children: ReactNode }) => {
  const navigate = useNavigate();
  useEffect(() => {
    const interceptor = axios.interceptors.response.use(response => response, error => {
      if (error.response?.status === 401) {
        localStorage.removeItem('token');
        localStorage.removeItem('user');
        localStorage.removeItem('permissions');
        localStorage.removeItem('erp_context');
        localStorage.removeItem('foundation_loaded');
        navigate('/', { replace: true });
      }
      return Promise.reject(error);
    });
    return () => axios.interceptors.response.eject(interceptor);
  }, [navigate]);
  return <>{children}</>;
};

const FoundationBootstrap = ({ children }: { children: ReactNode }) => {
  const { loading } = useFoundationContext();
  if (localStorage.getItem('token') && loading) {
    return <div className="min-h-screen bg-stone-50 flex items-center justify-center text-stone-600">Memuat konteks organisasi...</div>;
  }
  return <>{children}</>;
};

interface ProtectedRouteProps { children: ReactNode; requiredPermission?: string; requiredAnyPermission?: string[]; }
const ProtectedRoute = ({ children, requiredPermission, requiredAnyPermission }: ProtectedRouteProps) => {
  const token = localStorage.getItem('token');
  const userString = localStorage.getItem('user');
  if (!token || !userString || userString === 'undefined') return <Navigate to="/" replace />;
  if (requiredPermission && !can(requiredPermission)) return <Navigate to="/forbidden" replace />;
  if (requiredAnyPermission?.length && !canAny(requiredAnyPermission)) return <Navigate to="/forbidden" replace />;
  return <>{children}</>;
};

const Forbidden = () => (
  <main className="min-h-screen bg-stone-50 flex items-center justify-center px-6">
    <section className="max-w-md rounded-2xl border border-stone-200 bg-white p-8 text-center shadow-sm">
      <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-red-50 text-red-600">403</div>
      <h1 className="text-xl font-bold text-stone-900">Akses ditolak</h1>
      <p className="mt-2 text-sm text-stone-600">Akun ini tidak memiliki permission untuk membuka halaman tersebut.</p>
    </section>
  </main>
);

function App() {
  return (
    <BrowserRouter>
      <Toaster position="top-center" reverseOrder={false} />
      <FoundationBootstrap>
        <AxiosInterceptor>
          <Routes>
            <Route path="/" element={<Login />} />
            <Route path="/admin-login" element={<AdminLogin />} />
            <Route path="/forbidden" element={<Forbidden />} />
            <Route path="/pos" element={<ProtectedRoute requiredPermission="pos.sale.view"><Pos /></ProtectedRoute>} />
            <Route path="/inventory" element={<ProtectedRoute requiredPermission="inventory.stock.view"><Inventory /></ProtectedRoute>} />
            <Route path="/inventory/operations" element={<ProtectedRoute requiredPermission="inventory.stock.adjust"><InventoryOperations /></ProtectedRoute>} />
            <Route path="/history" element={<ProtectedRoute requiredAnyPermission={['sales.reporting.view', 'accounting.report.view', 'inventory.stock.view']}><History /></ProtectedRoute>} />
            <Route path="/raw-materials" element={<ProtectedRoute requiredPermission="inventory.stock.view"><RawMaterials /></ProtectedRoute>} />
            <Route path="/raw-materials/import" element={<ProtectedRoute requiredPermission="inventory.stock.adjust"><RawMaterialImport /></ProtectedRoute>} />
            <Route path="/dashboard" element={<ProtectedRoute requiredAnyPermission={['sales.reporting.view', 'accounting.report.view', 'inventory.stock.view']}><Dashboard /></ProtectedRoute>} />
            <Route path="/users" element={<ProtectedRoute requiredPermission="users.user.view"><Users /></ProtectedRoute>} />
            <Route path="/accounting" element={<ProtectedRoute requiredAnyPermission={['accounting.journal.view', 'accounting.erp_account.view', 'accounting.report.view']}><Accounting /></ProtectedRoute>} />
            <Route path="/customers" element={<ProtectedRoute requiredPermission="sales.order.view"><Customers /></ProtectedRoute>} />
            <Route path="/employees" element={<ProtectedRoute requiredPermission="hr.employee.view"><Employees /></ProtectedRoute>} />
            <Route path="/hrm" element={<ProtectedRoute requiredPermission="hr.employee.view"><Hrm /></ProtectedRoute>} />
            <Route path="/admin/foundation" element={<ProtectedRoute requiredPermission="rbac.role.view"><FoundationAdmin /></ProtectedRoute>} />
            <Route path="/erp/operations" element={<ProtectedRoute requiredAnyPermission={['purchasing.supplier.create', 'purchasing.order.create', 'sales.order.create', 'accounting.report.view', 'accounting.erp_journal.create']}><GuidedOperations /></ProtectedRoute>} />
            <Route path="/erp/operations/raw" element={<ProtectedRoute requiredAnyPermission={['inventory.stock.view', 'purchasing.supplier.view', 'sales.order.view', 'accounting.report.view']}><EnterpriseOperations /></ProtectedRoute>} />
          </Routes>
        </AxiosInterceptor>
      </FoundationBootstrap>
    </BrowserRouter>
  );
}

export default App;

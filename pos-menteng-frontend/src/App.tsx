import { useEffect } from 'react';
import { BrowserRouter, Routes, Route, Navigate, useNavigate } from 'react-router-dom';
import { Toaster } from 'react-hot-toast';
import axios from 'axios';
import './core/api/client';
import { useFoundationContext } from './core/hooks/useFoundationContext';
import Login from './pages/Login';
import AdminLogin from './pages/AdminLogin';
import Pos from './pages/Pos';
import Inventory from './pages/Inventory';
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

const AxiosInterceptor = ({ children }: { children: React.ReactNode }) => {
  const navigate = useNavigate();
  useEffect(() => {
    const interceptor = axios.interceptors.response.use((response) => response, (error) => {
      if (error.response?.status === 401) {
        localStorage.removeItem('token'); localStorage.removeItem('user'); localStorage.removeItem('permissions'); localStorage.removeItem('erp_context');
        navigate('/', { replace: true });
      }
      return Promise.reject(error);
    });
    return () => axios.interceptors.response.eject(interceptor);
  }, [navigate]);
  return <>{children}</>;
};

const FoundationBootstrap = () => {
  useFoundationContext();
  return null;
};

interface ProtectedRouteProps { children: React.ReactNode; allowedRoles?: string[]; requiredPermission?: string; }
const ProtectedRoute = ({ children, allowedRoles, requiredPermission }: ProtectedRouteProps) => {
  const token = localStorage.getItem('token');
  const userString = localStorage.getItem('user');
  if (!token || !userString || userString === 'undefined') return <Navigate to="/" replace />;
  let user: any;
  try { user = JSON.parse(userString); } catch { localStorage.removeItem('user'); return <Navigate to="/" replace />; }
  const permissions: string[] = (() => { try { return JSON.parse(localStorage.getItem('permissions') || '[]'); } catch { return []; } })();
  if (requiredPermission && permissions.length > 0 && !permissions.includes(requiredPermission)) return <Navigate to="/dashboard" replace />;
  if (!requiredPermission && allowedRoles && !allowedRoles.includes(user?.role)) {
    if (user?.role === 'cashier' || user?.role === 'kasir') return <Navigate to="/pos" replace />;
    return <Navigate to="/dashboard" replace />;
  }
  return children;
};

function App() {
  return <BrowserRouter>
    <Toaster position="top-center" reverseOrder={false} />
    <FoundationBootstrap />
    <AxiosInterceptor>
      <Routes>
        <Route path="/" element={<Login />} />
        <Route path="/admin-login" element={<AdminLogin />} />
        <Route path="/pos" element={<ProtectedRoute requiredPermission="pos.sale.view" allowedRoles={['developer','owner','manager','kasir']}><Pos /></ProtectedRoute>} />
        <Route path="/inventory" element={<ProtectedRoute requiredPermission="inventory.stock.view" allowedRoles={['developer','owner','manager']}><Inventory /></ProtectedRoute>} />
        <Route path="/history" element={<ProtectedRoute allowedRoles={['developer','owner','manager']}><History /></ProtectedRoute>} />
        <Route path="/raw-materials" element={<ProtectedRoute allowedRoles={['developer','owner','manager']}><RawMaterials /></ProtectedRoute>} />
        <Route path="/raw-materials/import" element={<ProtectedRoute allowedRoles={['developer','owner','manager']}><RawMaterialImport /></ProtectedRoute>} />
        <Route path="/dashboard" element={<ProtectedRoute allowedRoles={['developer','owner','manager']}><Dashboard /></ProtectedRoute>} />
        <Route path="/users" element={<ProtectedRoute requiredPermission="users.user.view" allowedRoles={['developer','owner','manager']}><Users /></ProtectedRoute>} />
        <Route path="/accounting" element={<ProtectedRoute requiredPermission="accounting.journal.view" allowedRoles={['developer','owner','manager']}><Accounting /></ProtectedRoute>} />
        <Route path="/customers" element={<ProtectedRoute allowedRoles={['developer','owner','manager']}><Customers /></ProtectedRoute>} />
        <Route path="/employees" element={<ProtectedRoute requiredPermission="hr.employee.view" allowedRoles={['developer','admin','owner','manager']}><Employees /></ProtectedRoute>} />
        <Route path="/hrm" element={<ProtectedRoute requiredPermission="hr.employee.view" allowedRoles={['developer','admin','owner','manager']}><Hrm /></ProtectedRoute>} />
        <Route path="/admin/foundation" element={<ProtectedRoute requiredPermission="rbac.role.view"><FoundationAdmin /></ProtectedRoute>} />
      </Routes>
    </AxiosInterceptor>
  </BrowserRouter>;
}

export default App;

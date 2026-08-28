import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import Login from './pages/Login';
import AdminLogin from './pages/AdminLogin';
import Pos from './pages/Pos';
import Inventory from './pages/Inventory';
import History from './pages/History';
import RawMaterials from './pages/RawMaterials';
import Dashboard from './pages/Dashboard';


interface ProtectedRouteProps {
 children: React.ReactNode;
  allowedRoles?: string[];
}

const ProtectedRoute = ({ children, allowedRoles }: ProtectedRouteProps) => {
    const token = localStorage.getItem('token');
    const userString = localStorage.getItem('user');
    if(!token || !userString){
      return <Navigate to = "/" replace />;
    }
    const user =JSON.parse(userString);
    if (allowedRoles && !allowedRoles.includes(user?.role)) {
      if(user?.role === 'cashier' || user?.role === 'kasir'){
        return <Navigate to ="/pos" replace />;
      }
      return <Navigate to = "/dashboard" replace />;
    }
    return children;
};

function App() {
  return (
    <BrowserRouter>
      <Routes>

        <Route path="/" element={<Login />} />
        <Route path="/admin-login" element={<AdminLogin />} />


        <Route path="/pos" element={<ProtectedRoute allowedRoles={['developer', 'owner', 'manager', 'kasir']}> <Pos /> </ProtectedRoute>} />
        <Route path="/inventory" element={<ProtectedRoute allowedRoles={['developer', 'owner', 'manager']}> <Inventory /> </ProtectedRoute>} />
        <Route path="/history" element={<ProtectedRoute allowedRoles={['developer', 'owner', 'manager']}> <History /> </ProtectedRoute>} />
        <Route path="/raw-materials" element={<ProtectedRoute allowedRoles={['developer', 'owner', 'manager']}> <RawMaterials /> </ProtectedRoute>} />
        <Route path="/dashboard" element={<ProtectedRoute allowedRoles={['developer', 'owner', 'manager']}> <Dashboard/> </ProtectedRoute>} />
      </Routes>
    </BrowserRouter>
  );
}

export default App;
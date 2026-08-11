import { BrowserRouter, Routes, Route } from 'react-router-dom';
import Login from './pages/Login';
import Pos from './pages/Pos';
import Inventory from './pages/Inventory';
import History from './pages/History'; // <-- Import halaman baru

function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/" element={<Login />} />
        <Route path="/pos" element={<Pos />} />
        <Route path="/inventory" element={<Inventory />} />
        <Route path="/history" element={<History />} /> {/* <-- Tambahkan rute ini */}
      </Routes>
    </BrowserRouter>
  );
}

export default App;
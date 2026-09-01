import { useState, useEffect } from 'react';
import axios from 'axios';
import toast from 'react-hot-toast';
import AdminSidebar from '../components/AdminSidebar';

export default function Users() {
  const [users, setUsers] = useState<any[]>([]);
  const [showModal, setShowModal] = useState(false);
  const [formData, setFormData] = useState({ name: '', email: '', password: '', role: 'kasir' });

  useEffect(() => {
    fetchUsers();
  }, []);

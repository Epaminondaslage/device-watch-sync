import { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useDeviceStore } from '@/stores/deviceStore';
import Login from './Login';

export default function Index() {
  const navigate = useNavigate();
  const { isAuthenticated } = useDeviceStore();

  useEffect(() => {
    if (isAuthenticated) {
      navigate('/dashboard');
    }
  }, [isAuthenticated, navigate]);

  return <Login />;
}

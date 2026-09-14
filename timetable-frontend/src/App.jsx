import { AppRouter } from './components/AppRouter';
import { AuthProvider } from './contexts/AuthContext';
import { ApiHealthProvider } from './contexts/ApiHealthContext';
import { NotificationProvider } from './contexts/NotificationContext';

function App() {
  return (
    <ApiHealthProvider>
      <AuthProvider>
        <NotificationProvider>
          <AppRouter />
        </NotificationProvider>
      </AuthProvider>
    </ApiHealthProvider>
  );
}

export default App;

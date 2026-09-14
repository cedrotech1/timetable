import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import apiClient from '../services/api/config';
import {
  isApiDown,
  reportApiDown,
  reportApiUp,
  subscribeApiHealth,
} from '../services/api/apiHealth';

const ApiHealthContext = createContext({
  offline: false,
  reason: '',
  checking: false,
  retryNow: async () => false,
  secondsUntilRetry: 0,
});

const RETRY_EVERY_MS = 8000;

async function probeApi() {
  try {
    // Accept any status so auth 401 still proves Node is up; treat gateway errors as down.
    const res = await apiClient.get('/health', {
      timeout: 5000,
      __apiHealthProbe: true,
      validateStatus: () => true,
    });
    const status = res?.status;
    if (status === 502 || status === 503 || status === 504) {
      reportApiDown(`http_${status}`);
      return false;
    }
    reportApiUp();
    return true;
  } catch (error) {
    if (error?.response) {
      const status = error.response.status;
      if (status === 502 || status === 503 || status === 504) {
        reportApiDown(`http_${status}`);
        return false;
      }
      reportApiUp();
      return true;
    }
    reportApiDown('unreachable');
    return false;
  }
}

export function ApiHealthProvider({ children }) {
  const [offline, setOffline] = useState(() => isApiDown());
  const [reason, setReason] = useState('');
  const [checking, setChecking] = useState(false);
  const [secondsUntilRetry, setSecondsUntilRetry] = useState(0);

  useEffect(() => {
    return subscribeApiHealth(({ down, reason: r }) => {
      setOffline(down);
      setReason(r || '');
    });
  }, []);

  const retryNow = useCallback(async () => {
    setChecking(true);
    try {
      return await probeApi();
    } finally {
      setChecking(false);
    }
  }, []);

  useEffect(() => {
    if (!offline) {
      setSecondsUntilRetry(0);
      return undefined;
    }

    let left = Math.ceil(RETRY_EVERY_MS / 1000);
    setSecondsUntilRetry(left);

    const tick = setInterval(() => {
      left -= 1;
      if (left <= 0) {
        left = Math.ceil(RETRY_EVERY_MS / 1000);
        setSecondsUntilRetry(left);
        setChecking(true);
        probeApi().finally(() => setChecking(false));
      } else {
        setSecondsUntilRetry(left);
      }
    }, 1000);

    // Immediate first probe when overlay appears
    setChecking(true);
    probeApi().finally(() => setChecking(false));

    return () => clearInterval(tick);
  }, [offline]);

  const value = useMemo(
    () => ({ offline, reason, checking, retryNow, secondsUntilRetry }),
    [offline, reason, checking, retryNow, secondsUntilRetry]
  );

  return <ApiHealthContext.Provider value={value}>{children}</ApiHealthContext.Provider>;
}

export function useApiHealth() {
  return useContext(ApiHealthContext);
}

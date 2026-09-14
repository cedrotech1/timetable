import axios from 'axios';
import { TOKEN_STORAGE_KEY, USER_STORAGE_KEY, loginPath, publicHref } from '../../utils/appPaths.js';
import { isApiUnreachableError, reportApiDown, reportApiUp } from './apiHealth.js';

function isLocalHostname(hostname) {
  return hostname === 'localhost' || hostname === '127.0.0.1' || hostname === '[::1]';
}

function apiPathPrefix() {
  const base = import.meta.env.BASE_URL || '/';
  // /uat/ → /uat/api/v1 ; /timetable/ → /timetable/api/v1 ; / → /api/v1
  const trimmed = String(base).replace(/\/+$/, '') || '';
  if (!trimmed || trimmed === '') return '/api/v1';
  return `${trimmed}/api/v1`;
}

function isSubpathDeploy() {
  const base = import.meta.env.BASE_URL || '/';
  const trimmed = String(base).replace(/\/+$/, '');
  return Boolean(trimmed && trimmed !== '');
}

export function resolveApiBaseUrl() {
  const fromEnv =
    import.meta.env.VITE_API_BASE_URL ||
    import.meta.env.VITE_API_BASE_URL_LOCAL;

  if (typeof window === 'undefined') {
    return fromEnv || 'http://localhost:9000/api/v1';
  }

  const { hostname, origin } = window.location;

  // Built for /timetable/ (or /uat/) on XAMPP → same-origin via Apache proxy
  if (isSubpathDeploy()) {
    if (fromEnv && /^https?:\/\//i.test(String(fromEnv))) return fromEnv;
    if (fromEnv && String(fromEnv).startsWith('/')) {
      return `${origin}${String(fromEnv).replace(/\/$/, '')}`;
    }
    return `${origin}${apiPathPrefix()}`;
  }

  if (isLocalHostname(hostname)) {
    return fromEnv || 'http://localhost:9000/api/v1';
  }

  return `${origin}${apiPathPrefix()}`;
}

const API_BASE_URL = resolveApiBaseUrl();
const API_TIMEOUT = Number(import.meta.env.VITE_API_TIMEOUT) || 60000;

function safeJsonTransform(data) {
  if (data == null || typeof data !== 'string') return data;
  const trimmed = data.trim();
  if (!trimmed) return null;
  if (!(trimmed.startsWith('{') || trimmed.startsWith('['))) {
    return {
      success: false,
      message: trimmed.startsWith('<')
        ? 'Server returned HTML instead of JSON.'
        : trimmed.length > 240
          ? `${trimmed.slice(0, 240)}…`
          : trimmed,
      __nonJsonBody: true,
    };
  }
  try {
    return JSON.parse(trimmed);
  } catch {
    return { success: false, message: 'Server returned invalid JSON.', __nonJsonBody: true };
  }
}

const apiClient = axios.create({
  baseURL: API_BASE_URL,
  timeout: API_TIMEOUT,
  headers: { 'Content-Type': 'application/json' },
  transformResponse: [safeJsonTransform],
});

apiClient.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem(TOKEN_STORAGE_KEY);
    if (token) config.headers.Authorization = `Bearer ${token}`;
    return config;
  },
  (error) => Promise.reject(error)
);

apiClient.interceptors.response.use(
  (response) => {
    if (!response?.config?.__apiHealthProbe && !response?.config?.__skipApiHealth) {
      reportApiUp();
    }
    return response;
  },
  (error) => {
    if (error.config?.__apiHealthProbe) return Promise.reject(error);

    if (!error.config?.__skipApiHealth && isApiUnreachableError(error)) {
      reportApiDown(error.response?.status ? `http_${error.response.status}` : 'network');
    } else if (error.response) {
      reportApiUp();
    }

    if (error.response?.status === 401) {
      const reqUrl = String(error.config?.url || '');
      // Never wipe session because of login/public auth failures
      if (!reqUrl.includes('/auth/login')) {
        localStorage.removeItem(TOKEN_STORAGE_KEY);
        localStorage.removeItem(USER_STORAGE_KEY);
        const path = window.location.pathname || '';
        if (!path.includes('/login')) {
          window.location.href = publicHref(loginPath());
        }
      }
    }
    return Promise.reject(error);
  }
);

export { API_BASE_URL };
export default apiClient;

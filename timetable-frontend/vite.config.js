import { defineConfig, loadEnv } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

function resolveAllowedHosts(env, mode) {
  const nodeEnv = (env.VITE_NODE_ENV || mode || '').toLowerCase()
  const explicit = (env.VITE_ALLOWED_HOSTS || '').trim()

  if (explicit === 'all' || explicit === '*') return true
  if (nodeEnv === 'development' || nodeEnv === 'uat') return true

  const hosts = new Set(['localhost', '127.0.0.1'])
  if (explicit) {
    explicit.split(',').map((h) => h.trim()).filter(Boolean).forEach((h) => hosts.add(h))
  }
  const apiUrl = (env.VITE_API_BASE_URL || '').trim()
  if (apiUrl) {
    try {
      const { hostname } = new URL(apiUrl)
      if (hostname) hosts.add(hostname)
    } catch {
      // ignore invalid VITE_API_BASE_URL
    }
  }
  return [...hosts]
}

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const base = env.VITE_BASE_PATH || '/'
  const port = parseInt(env.VITE_DEV_PORT || '5173', 10)
  // Only set when the browser reaches Vite via a reverse proxy (e.g. nginx :80).
  // Local `npm run dev` on :5173 must leave this unset or HMR connects to the wrong port.
  const hmrClientPort = env.VITE_HMR_CLIENT_PORT
    ? parseInt(env.VITE_HMR_CLIENT_PORT, 10)
    : null

  // When served under /timetable/ (XAMPP drop-in), proxy API like api-proxy.php does
  const apiProxy =
    base && base !== '/'
      ? {
          [`${base.replace(/\/$/, '')}/api`]: {
            target: 'http://127.0.0.1:9000',
            changeOrigin: true,
            rewrite: (p) => p.replace(new RegExp(`^${base.replace(/\/$/, '')}/api`), '/api'),
          },
        }
      : undefined

  return {
    base,
    plugins: [react(), tailwindcss()],
    build: {
      // production → dist/   |   uat → dist-uat/  (nginx serves both)
      outDir: mode === 'uat' ? 'dist-uat' : 'dist',
      emptyOutDir: true,
    },
    server: {
      port,
      strictPort: true,
      host: '127.0.0.1',
      allowedHosts: resolveAllowedHosts(env, mode),
      ...(apiProxy ? { proxy: apiProxy } : {}),
      ...(hmrClientPort
        ? {
            hmr: {
              clientPort: hmrClientPort,
              host: '127.0.0.1',
              protocol: 'ws',
              ...(base && base !== '/' ? { path: base } : {}),
            },
          }
        : {}),
    },
    // Used when SSL_ENABLED=1 (start-frontend.bat → npm run build && preview)
    preview: {
      port,
      strictPort: true,
      host: '127.0.0.1',
      allowedHosts: resolveAllowedHosts(env, mode),
      ...(apiProxy ? { proxy: apiProxy } : {}),
    },
  }
})

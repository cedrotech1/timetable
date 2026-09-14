/** True when built/served under /uat/ (VITE_BASE_PATH=/uat/). */
export function isUatDeploy() {
  const base = import.meta.env.BASE_URL || '/';
  return base.includes('/uat/') || String(import.meta.env.VITE_NODE_ENV || '').toLowerCase() === 'uat';
}

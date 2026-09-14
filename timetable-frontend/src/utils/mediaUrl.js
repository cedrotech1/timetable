import { API_BASE_URL } from '../services/api/config.js';
import { isSafeUrl } from './sanitize.js';
import { TOKEN_STORAGE_KEY } from './appPaths.js';

/**
 * Build a browser URL for authenticated uploads.
 * Always uses site-root /uploads/... (nginx proxies to WARS).
 * Never prefixes /uat — that path is not proxied and returns 404.
 */
export const getMediaUrl = (mediaPath) => {
  if (!mediaPath) return '';
  if (mediaPath.startsWith('http://') || mediaPath.startsWith('https://')) {
    return isSafeUrl(mediaPath) ? mediaPath : '';
  }

  let pathPart = String(mediaPath).replace(/\\/g, '/');
  if (!pathPart.startsWith('/')) {
    pathPart = `/${pathPart}`;
  }

  const uploadsIdx = pathPart.toLowerCase().indexOf('/uploads/');
  if (uploadsIdx >= 0) {
    pathPart = pathPart.slice(uploadsIdx);
  } else if (pathPart.toLowerCase().startsWith('/uat/uploads/')) {
    pathPart = pathPart.slice(4);
  }

  const origin =
    typeof window !== 'undefined'
      ? window.location.origin
      : String(API_BASE_URL || '').replace(/\/(uat\/)?api\/v1\/?$/, '');

  let full = `${origin}${pathPart}`;

  const token =
    typeof localStorage !== 'undefined' ? localStorage.getItem(TOKEN_STORAGE_KEY) : null;
  if (token && pathPart.toLowerCase().includes('/uploads/')) {
    const sep = full.includes('?') ? '&' : '?';
    full = `${full}${sep}token=${encodeURIComponent(token)}`;
  }

  return isSafeUrl(full.split('?')[0]) ? full : '';
};

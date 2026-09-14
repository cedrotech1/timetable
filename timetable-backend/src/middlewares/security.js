const helmet = require('helmet');
const cors = require('cors');
const rateLimit = require('express-rate-limit');
const hpp = require('hpp');
const { URL } = require('url');

function parseAllowedOrigins() {
  return (process.env.CORS_ORIGINS || 'http://localhost,http://127.0.0.1')
    .split(',')
    .map((o) => o.trim())
    .filter(Boolean);
}

function normalizeHost(host) {
  if (!host) return '';
  return host.split(':')[0].toLowerCase();
}

function getRequestHost(req) {
  const forwarded = req.headers['x-forwarded-host'];
  const raw = typeof forwarded === 'string'
    ? forwarded.split(',')[0].trim()
    : (req.headers.host || '').trim();
  return normalizeHost(raw);
}

function originMatchesAllowed(origin, allowed) {
  if (origin === allowed || origin.startsWith(`${allowed}/`)) {
    return true;
  }

  try {
    const originUrl = new URL(origin);
    const allowedUrl = new URL(allowed.includes('://') ? allowed : `http://${allowed}`);
    return originUrl.hostname === allowedUrl.hostname && originUrl.protocol === allowedUrl.protocol;
  } catch {
    return false;
  }
}

function isLocalhostHost(host) {
  return host === 'localhost' || host === '127.0.0.1' || host === '[::1]';
}

function isPrivateLanHost(host) {
  if (isLocalhostHost(host)) return true;
  if (/^10\.\d{1,3}\.\d{1,3}\.\d{1,3}$/.test(host)) return true;
  if (/^192\.168\.\d{1,3}\.\d{1,3}$/.test(host)) return true;
  const match = host.match(/^172\.(\d{1,2})\.\d{1,3}\.\d{1,3}$/);
  if (match) {
    const second = parseInt(match[1], 10);
    return second >= 16 && second <= 31;
  }
  return false;
}

function isSameHostOrigin(origin, req) {
  if (process.env.CORS_ALLOW_SAME_HOST === '0') return false;

  try {
    const originUrl = new URL(origin);
    return normalizeHost(originUrl.hostname) === getRequestHost(req);
  } catch {
    return false;
  }
}

function isOriginAllowed(origin, req) {
  if (!origin) return true;

  const allowedOrigins = parseAllowedOrigins();
  if (allowedOrigins.some((allowed) => originMatchesAllowed(origin, allowed))) {
    return true;
  }

  if (isSameHostOrigin(origin, req)) {
    return true;
  }

  try {
    const host = normalizeHost(new URL(origin).hostname);
    if (isLocalhostHost(host)) return true;
    if (process.env.CORS_ALLOW_LAN === '1' && isPrivateLanHost(host)) return true;
  } catch {
    return false;
  }

  return false;
}

const corsConfig = {
  credentials: true,
  methods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
  allowedHeaders: ['Content-Type', 'Authorization', 'X-CSRF-Token'],
  maxAge: 600,
};

function corsMiddleware(req, res, next) {
  return cors({
    ...corsConfig,
    origin(origin, callback) {
      if (isOriginAllowed(origin, req)) {
        return callback(null, true);
      }
      return callback(new Error('Not allowed by CORS'));
    },
  })(req, res, next);
}

const authLimiter = rateLimit({
  windowMs: 15 * 60 * 1000,
  max: parseInt(process.env.RATE_LIMIT_AUTH_MAX || '25', 10),
  standardHeaders: true,
  legacyHeaders: false,
  message: { success: false, message: 'Too many attempts. Try again later.' },
});

const apiLimiter = rateLimit({
  windowMs: 15 * 60 * 1000,
  // Campus NAT shares one IP — default must survive mass student intake
  max: parseInt(process.env.RATE_LIMIT_API_MAX || '5000', 10),
  standardHeaders: true,
  legacyHeaders: false,
  message: { success: false, message: 'Too many requests. Try again later.' },
  // Never block login / session bootstrap (shared campus IPs + refresh storms)
  skip: (req) => {
    const path = String(req.originalUrl || req.url || '').toLowerCase();
    return (
      path.includes('/auth/login') ||
      path.includes('/auth/reset') ||
      path.includes('/auth/status') ||
      path.includes('/auth/hostel-bridge') ||
      path.includes('/auth/me') ||
      path.includes('/auth/refresh') ||
      path.includes('/auth/logout') ||
      path.includes('/student/me') ||
      path.includes('/student/home')
    );
  },
});

const helmetMiddleware = helmet({
  crossOriginResourcePolicy: { policy: 'cross-origin' },
  contentSecurityPolicy: false,
});

module.exports = {
  helmetMiddleware,
  cors: corsMiddleware,
  hpp: hpp(),
  authLimiter,
  apiLimiter,
};

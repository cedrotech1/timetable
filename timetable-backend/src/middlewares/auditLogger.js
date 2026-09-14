const fs = require('fs');
const path = require('path');

const LOG_DIR = path.join(process.cwd(), 'logs');
const AUDIT_FILE = path.join(LOG_DIR, 'security-audit.log');

function ensureLogDir() {
  if (!fs.existsSync(LOG_DIR)) {
    fs.mkdirSync(LOG_DIR, { recursive: true });
  }
}

function auditSecurityEvent(eventType, message, severity = 'info', req = null, userId = null) {
  ensureLogDir();
  const ip = req?.ip || req?.headers?.['x-forwarded-for']?.split(',')[0]?.trim() || '0.0.0.0';
  const uri = req?.originalUrl || '';
  const line = `[${new Date().toISOString()}] [${severity.toUpperCase()}] [${eventType}] IP=${ip} URI=${uri} user=${userId ?? 'anon'} ${message}\n`;
  fs.appendFileSync(AUDIT_FILE, line, { encoding: 'utf8' });
}

function requestAuditMiddleware(req, res, next) {
  const suspicious = [
    '../', '..\\', 'union select', '<script', 'javascript:', 'eval(',
    '/etc/passwd', 'wp-admin', '.env', 'base64_decode',
  ];
  const hay = `${req.originalUrl || ''} ${JSON.stringify(req.query || {})}`.toLowerCase();
  for (const p of suspicious) {
    if (hay.includes(p)) {
      auditSecurityEvent('REQUEST_BLOCKED', `pattern:${p}`, 'critical', req, req.user?.id);
      return res.status(403).json({ success: false, message: 'Forbidden' });
    }
  }
  next();
}

module.exports = { auditSecurityEvent, requestAuditMiddleware };

/**
 * Global error handler — avoids leaking stack traces in production.
 */
function errorHandler(err, req, res, next) {
  if (res.headersSent) {
    return next(err);
  }

  const isProd = process.env.NODE_ENV === 'production';
  const status = err.status || err.statusCode || 500;

  if (err.message === 'Not allowed by CORS') {
    return res.status(403).json({ success: false, message: 'CORS policy violation' });
  }

  console.error('[API Error]', err.message, isProd ? '' : err.stack);

  return res.status(status).json({
    success: false,
    message: status === 500 && isProd ? 'Internal server error' : err.message || 'Internal server error',
  });
}

module.exports = { errorHandler };

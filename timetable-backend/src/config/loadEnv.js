/**
 * Load env files in order (later files override earlier when override=true).
 * Systemd EnvironmentFile already injects vars; dotenv will not override those
 * unless we set override — we only override from .env.${NODE_ENV} for local CLI.
 */
import fs from 'fs';
import path from 'path';
import dotenv from 'dotenv';

export function loadAppEnv(rootDir = process.cwd()) {
  const nodeEnv = process.env.NODE_ENV || 'development';
  const base = path.resolve(rootDir);

  const files = ['.env', `.env.${nodeEnv}`];
  for (const name of files) {
    const full = path.join(base, name);
    if (fs.existsSync(full)) {
      dotenv.config({ path: full, override: name !== '.env' });
    }
  }

  // Ensure NODE_ENV is set for sequelize config
  if (!process.env.NODE_ENV) {
    process.env.NODE_ENV = nodeEnv;
  }
}

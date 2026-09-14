/**
 * MUST run before other app imports (babel hoists `import`, so use require here).
 */
const path = require('path');
const fs = require('fs');
const dotenv = require('dotenv');

const rootDir = path.resolve(__dirname, '../..');
const nodeEnv = process.env.NODE_ENV || 'development';

for (const name of ['.env', `.env.${nodeEnv}`]) {
  const full = path.join(rootDir, name);
  if (fs.existsSync(full)) {
    dotenv.config({ path: full, override: name !== '.env' });
  }
}

if (!process.env.NODE_ENV) {
  process.env.NODE_ENV = nodeEnv;
}

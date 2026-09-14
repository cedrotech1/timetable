import bcrypt from "bcryptjs";

/**
 * Verify a plain password against a stored bcrypt hash.
 * Supports PHP `$2y$` hashes by normalizing to `$2a$`.
 */
export async function verifyLoginPassword(plainPassword, storedHash) {
  if (!plainPassword || !storedHash) {
    return { ok: false };
  }

  const normalized = String(storedHash).replace(/^\$2y\$/, "$2a$");
  const ok = await bcrypt.compare(plainPassword, normalized);
  return { ok };
}

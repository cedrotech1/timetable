import path from "path";
import fs from "fs";

export const UPLOAD_ROOT = path.resolve(process.cwd(), "uploads");

export const UPLOAD_DIRS = {
  root: "uploads",
  tmp: "uploads/tmp",
  profiles: "uploads/profiles",
};

export const ALL_UPLOAD_SUBDIRS = [
  UPLOAD_DIRS.tmp,
  UPLOAD_DIRS.profiles,
];

export function ensureUploadDirs() {
  fs.mkdirSync(UPLOAD_ROOT, { recursive: true });
  for (const rel of ALL_UPLOAD_SUBDIRS) {
    const abs = path.resolve(process.cwd(), rel);
    fs.mkdirSync(abs, { recursive: true });
    const keep = path.join(abs, ".gitkeep");
    if (!fs.existsSync(keep)) {
      try {
        fs.writeFileSync(keep, "");
      } catch {
        /* ignore */
      }
    }
  }
  return ALL_UPLOAD_SUBDIRS;
}

export default {
  UPLOAD_ROOT,
  UPLOAD_DIRS,
  ALL_UPLOAD_SUBDIRS,
  ensureUploadDirs,
};

export const VALID_ROLES = ['admin', 'dean_office', 'registrar_office', 'lecturer', 'user'];

export const MANAGE_ROLES = ['admin', 'dean_office', 'registrar_office'];

export function canManageOrg(role) {
  return MANAGE_ROLES.includes(role);
}

export function isAdmin(role) {
  return role === 'admin';
}

export function roleLabel(role) {
  const map = {
    admin: 'Administrator',
    dean_office: 'Dean Office',
    registrar_office: 'Registrar Office',
    lecturer: 'Lecturer',
    user: 'User',
  };
  return map[role] || role || '—';
}

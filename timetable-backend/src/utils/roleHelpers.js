/** Timetable system roles (aligned with PHP timetable-v3 users.role) */
export const VALID_USER_ROLES = [
  "admin",
  "dean_office",
  "registrar_office",
  "lecturer",
  "user",
];

export const ADMIN_ROLES = ["admin"];

export const isAdminRole = (role) => ADMIN_ROLES.includes(role);

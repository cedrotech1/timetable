import { useEffect, useState } from 'react';
import { ResourceCrudPage } from '../components/ResourceCrudPage';
import {
  usersService,
  campusesService,
  collegesService,
  schoolsService,
} from '../services/api';
import { VALID_ROLES, roleLabel, isAdmin } from '../utils/roles';
import { useAuth } from '../contexts/AuthContext';

export default function UsersPage() {
  const { user } = useAuth();
  const [campuses, setCampuses] = useState([]);
  const [colleges, setColleges] = useState([]);
  const [schools, setSchools] = useState([]);

  useEffect(() => {
    Promise.all([campusesService.getAll(), collegesService.getAll(), schoolsService.getAll()]).then(
      ([c1, c2, c3]) => {
        if (c1?.success) setCampuses(c1.data || []);
        if (c2?.success) setColleges(c2.data || []);
        if (c3?.success) setSchools(c3.data || []);
      }
    );
  }, []);

  if (!isAdmin(user?.role)) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="text-center">
          <h2 className="text-xl font-bold text-gray-900 mb-2">Access Denied</h2>
          <p className="text-gray-600 text-sm">Only administrators can manage users.</p>
        </div>
      </div>
    );
  }

  return (
    <ResourceCrudPage
      title="Users"
      subtitle="Staff accounts for the timetable system"
      service={usersService}
      canManage
      columns={[
        { key: 'names', label: 'Name', getValue: (r) => r.names },
        { key: 'urEmail', label: 'UR Email', getValue: (r) => r.urEmail },
        { key: 'role', label: 'Role', getValue: (r) => roleLabel(r.role) },
        {
          key: 'active',
          label: 'Status',
          render: (r) => (
            <span
              className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${
                r.active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600'
              }`}
            >
              {r.active ? 'Active' : 'Inactive'}
            </span>
          ),
        },
        {
          key: 'campus',
          label: 'Campus',
          getValue: (r) => r.campus?.name || '—',
        },
      ]}
      buildFormFields={(form, editing) => [
        { name: 'names', label: 'Full name', required: true },
        { name: 'urEmail', label: 'UR email', required: true },
        { name: 'email', label: 'Personal email' },
        {
          name: 'password',
          label: editing ? 'Password (leave blank to keep)' : 'Password',
          type: 'password',
          required: !editing,
        },
        {
          name: 'role',
          label: 'Role',
          type: 'select',
          required: true,
          defaultValue: 'user',
          options: VALID_ROLES.map((r) => ({ value: r, label: roleLabel(r) })),
        },
        { name: 'phone', label: 'Phone' },
        {
          name: 'campusId',
          label: 'Campus',
          type: 'select',
          valueAsNumber: true,
          options: campuses.map((c) => ({ value: c.id, label: c.name })),
        },
        {
          name: 'collegeId',
          label: 'College',
          type: 'select',
          valueAsNumber: true,
          options: colleges.map((c) => ({ value: c.id, label: c.name })),
        },
        {
          name: 'schoolId',
          label: 'School',
          type: 'select',
          valueAsNumber: true,
          options: schools.map((s) => ({ value: s.id, label: s.name })),
        },
      ]}
      toPayload={(form, editing) => {
        const payload = {
          names: form.names,
          urEmail: form.urEmail,
          email: form.email || null,
          role: form.role || 'user',
          phone: form.phone || null,
          campusId: form.campusId ? Number(form.campusId) : null,
          collegeId: form.collegeId ? Number(form.collegeId) : null,
          schoolId: form.schoolId ? Number(form.schoolId) : null,
        };
        if (form.password) payload.password = form.password;
        return payload;
      }}
    />
  );
}

import { useEffect, useState } from 'react';
import { ResourceCrudPage } from '../components/ResourceCrudPage';
import { programsService, schoolsService } from '../services/api';

export default function ProgramsPage() {
  const [schools, setSchools] = useState([]);

  useEffect(() => {
    schoolsService.getAll().then((res) => {
      if (res?.success) setSchools(res.data || []);
    });
  }, []);

  return (
    <ResourceCrudPage
      title="Programs"
      subtitle="Academic programs offered by schools"
      service={programsService}
      columns={[
        { key: 'id', label: 'ID', getValue: (r) => r.id },
        { key: 'name', label: 'Program', getValue: (r) => r.name },
        { key: 'code', label: 'Code', getValue: (r) => r.code || '—' },
        {
          key: 'school',
          label: 'School',
          getValue: (r) => r.school?.name || r.schoolId,
        },
      ]}
      buildFormFields={() => [
        { name: 'name', label: 'Program name', required: true },
        { name: 'code', label: 'Code (optional)' },
        {
          name: 'schoolId',
          label: 'School',
          type: 'select',
          required: true,
          valueAsNumber: true,
          options: schools.map((s) => ({ value: s.id, label: s.name })),
        },
      ]}
      toPayload={(form) => ({
        name: form.name,
        code: form.code || null,
        schoolId: Number(form.schoolId),
      })}
    />
  );
}

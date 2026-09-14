import { useEffect, useState } from 'react';
import { ResourceCrudPage } from '../components/ResourceCrudPage';
import { collegesService, schoolsService } from '../services/api';

export default function SchoolsPage() {
  const [colleges, setColleges] = useState([]);

  useEffect(() => {
    collegesService.getAll().then((res) => {
      if (res?.success) setColleges(res.data || []);
    });
  }, []);

  return (
    <ResourceCrudPage
      title="Schools"
      subtitle="Schools belong to a college"
      service={schoolsService}
      columns={[
        { key: 'id', label: 'ID', getValue: (r) => r.id },
        { key: 'name', label: 'School', getValue: (r) => r.name },
        {
          key: 'college',
          label: 'College',
          getValue: (r) => r.college?.name || r.collegeId,
        },
      ]}
      buildFormFields={() => [
        { name: 'name', label: 'School name', required: true },
        {
          name: 'collegeId',
          label: 'College',
          type: 'select',
          required: true,
          valueAsNumber: true,
          options: colleges.map((c) => ({ value: c.id, label: `${c.name} — ${c.fullName}` })),
        },
      ]}
      toPayload={(form) => ({
        name: form.name,
        collegeId: Number(form.collegeId),
      })}
    />
  );
}

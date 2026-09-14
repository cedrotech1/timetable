import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { CalendarDays } from 'lucide-react';
import { ResourceCrudPage } from '../components/ResourceCrudPage';
import { campusesService, facilitiesService } from '../services/api';
import { appPath } from '../utils/appPaths';

export default function FacilitiesPage() {
  const [campuses, setCampuses] = useState([]);

  useEffect(() => {
    campusesService.getAll().then((res) => {
      if (res?.success) setCampuses(res.data || []);
    });
  }, []);

  return (
    <div className="space-y-4">
      <div className="flex justify-end">
        <Link
          to={appPath('facilities/calendar')}
          className="inline-flex items-center gap-2 rounded-lg border border-[#00628b] text-[#00628b] px-3 py-2 text-sm font-medium hover:bg-[#e8f4f8]"
        >
          <CalendarDays size={16} /> Facility calendar (taken slots)
        </Link>
      </div>
      <ResourceCrudPage
      title="Facilities"
      subtitle="Classrooms, labs and other teaching spaces — open calendar to see booked slots"
      service={facilitiesService}
      columns={[
        { key: 'name', label: 'Name', getValue: (r) => r.name },
        { key: 'type', label: 'Type', getValue: (r) => r.type || '—' },
        { key: 'capacity', label: 'Capacity', getValue: (r) => r.capacity ?? '—' },
        {
          key: 'campus',
          label: 'Campus',
          getValue: (r) => r.campus?.name || r.campusId,
        },
        { key: 'buildName', label: 'Building', getValue: (r) => r.buildName || '—' },
        { key: 'site', label: 'Site', getValue: (r) => r.site || '—' },
        {
          key: 'calendar',
          label: 'Calendar',
          render: (r) => (
            <Link to={appPath(`facilities/${r.id}/calendar`)} className="text-[#00628b] hover:underline text-xs font-medium">
              View slots
            </Link>
          ),
        },
      ]}
      buildFormFields={() => [
        { name: 'name', label: 'Facility name', required: true },
        { name: 'name2', label: 'Alternate name' },
        { name: 'type', label: 'Type', placeholder: 'CLASSROOM / LAB / HALL' },
        { name: 'capacity', label: 'Capacity', type: 'number' },
        {
          name: 'campusId',
          label: 'Campus',
          type: 'select',
          required: true,
          valueAsNumber: true,
          options: campuses.map((c) => ({ value: c.id, label: c.name })),
        },
        { name: 'site', label: 'Site' },
        { name: 'buildName', label: 'Building name' },
        { name: 'buildCode', label: 'Building code' },
      ]}
      toPayload={(form) => ({
        name: form.name,
        name2: form.name2 || form.name,
        type: form.type || null,
        capacity: form.capacity === '' || form.capacity == null ? null : Number(form.capacity),
        campusId: Number(form.campusId),
        site: form.site || null,
        buildName: form.buildName || null,
        buildCode: form.buildCode || null,
      })}
    />
    </div>
  );
}

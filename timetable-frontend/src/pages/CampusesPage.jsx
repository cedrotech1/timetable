import { ResourceCrudPage } from '../components/ResourceCrudPage';
import { campusesService } from '../services/api';
import { capitalizeCampusName } from '../utils/formatDisplay';

export default function CampusesPage() {
  return (
    <ResourceCrudPage
      title="Campuses"
      subtitle="University campuses used by facilities and staff"
      service={campusesService}
      columns={[
        { key: 'id', label: 'ID', getValue: (r) => r.id },
        { key: 'name', label: 'Name', getValue: (r) => capitalizeCampusName(r.name) },
      ]}
      buildFormFields={() => [{ name: 'name', label: 'Campus name', required: true }]}
      toPayload={(form) => ({ name: capitalizeCampusName(form.name) || form.name })}
    />
  );
}

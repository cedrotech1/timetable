import { ResourceCrudPage } from '../components/ResourceCrudPage';
import { collegesService } from '../services/api';

export default function CollegesPage() {
  return (
    <ResourceCrudPage
      title="Colleges"
      subtitle="Colleges that own schools and academic programs"
      service={collegesService}
      columns={[
        { key: 'id', label: 'ID', getValue: (r) => r.id },
        { key: 'name', label: 'Code', getValue: (r) => r.name },
        { key: 'fullName', label: 'Full name', getValue: (r) => r.fullName },
      ]}
      buildFormFields={() => [
        { name: 'name', label: 'Short code (e.g. CASS)', required: true },
        { name: 'fullName', label: 'Full name', required: true },
      ]}
      toPayload={(form) => ({ name: form.name, fullName: form.fullName })}
    />
  );
}

import { useEffect, useState } from 'react';
import { Plus, Pencil, Trash2, Search } from 'lucide-react';
import { useAuth } from '../contexts/AuthContext';
import { useNotification } from '../contexts/NotificationContext';
import { canManageOrg } from '../utils/roles';
import ModalShell, { ModalPrimaryButton, ModalSecondaryButton } from './ModalShell';
import { capitalizePersonName } from '../utils/formatDisplay';

/**
 * Reusable CRUD list page for timetable org entities.
 */
export function ResourceCrudPage({
  title,
  subtitle,
  service,
  columns,
  emptyLabel = 'No records found',
  buildFormFields,
  toPayload,
  canManage: canManageProp,
  listParams = {},
  mapRow,
}) {
  const { user } = useAuth();
  const { showSuccess, showError } = useNotification();
  const canManage = canManageProp ?? canManageOrg(user?.role);

  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState({});
  const [saving, setSaving] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState(null);

  const load = async () => {
    try {
      setLoading(true);
      const response = await service.getAll(listParams);
      if (response?.success) {
        const data = (response.data || []).map((row) => (mapRow ? mapRow(row) : row));
        setRows(data);
      } else {
        showError(response?.message || 'Failed to load data');
      }
    } catch (error) {
      showError(error.response?.data?.message || 'Failed to load data');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [JSON.stringify(listParams)]);

  const filtered = rows.filter((row) => {
    if (!search.trim()) return true;
    const q = search.toLowerCase();
    return columns.some((col) => String(col.getValue?.(row) ?? row[col.key] ?? '').toLowerCase().includes(q));
  });

  const seedForm = (row = {}, isEditing = false) => {
    const seed = {};
    (buildFormFields?.(row, isEditing ? row : null) || []).forEach((field) => {
      const value = row[field.name];
      seed[field.name] = value !== undefined && value !== null ? value : (field.defaultValue ?? '');
    });
    return seed;
  };

  const openCreate = () => {
    setEditing(null);
    setForm(seedForm({}, false));
    setModalOpen(true);
  };

  const openEdit = (row) => {
    setEditing(row);
    setForm(seedForm(row, true));
    setModalOpen(true);
  };

  const handleSave = async (e) => {
    e.preventDefault();
    try {
      setSaving(true);
      const payload = toPayload ? toPayload(form, editing) : form;
      const response = editing
        ? await service.update(editing.id, payload)
        : await service.create(payload);

      if (response?.success) {
        showSuccess(editing ? 'Updated successfully' : 'Created successfully');
        setModalOpen(false);
        setEditing(null);
        await load();
      } else {
        showError(response?.message || 'Save failed');
      }
    } catch (error) {
      showError(error.response?.data?.message || 'Save failed');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async () => {
    if (!deleteTarget) return;
    try {
      setSaving(true);
      const response = await service.remove(deleteTarget.id);
      if (response?.success) {
        showSuccess('Deleted successfully');
        setDeleteTarget(null);
        await load();
      } else {
        showError(response?.message || 'Delete failed');
      }
    } catch (error) {
      showError(error.response?.data?.message || 'Delete failed');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="max-w-7xl mx-auto">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 m-0">{title}</h1>
          {subtitle ? <p className="mt-1 mb-0 text-sm text-gray-500">{subtitle}</p> : null}
        </div>
        {canManage && (
          <button
            type="button"
            onClick={openCreate}
            className="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-[#00628b] text-white text-sm font-medium hover:bg-[#004f70]"
          >
            <Plus size={18} />
            Add new
          </button>
        )}
      </div>

      <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div className="p-4 border-b border-gray-100 flex items-center gap-3">
          <div className="relative flex-1 max-w-md">
            <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
            <input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search…"
              className="w-full pl-9 pr-3 py-2 rounded-lg border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-[#00628b]/30"
            />
          </div>
          <span className="text-xs text-gray-500">{filtered.length} records</span>
        </div>

        <div className="overflow-x-auto">
          <table className="min-w-full text-sm">
            <thead className="bg-gray-50 text-gray-600">
              <tr>
                {columns.map((col) => (
                  <th key={col.key} className="text-left font-semibold px-4 py-3 whitespace-nowrap">
                    {col.label}
                  </th>
                ))}
                {canManage && <th className="text-right font-semibold px-4 py-3">Actions</th>}
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr>
                  <td colSpan={columns.length + (canManage ? 1 : 0)} className="px-4 py-10 text-center text-gray-500">
                    Loading…
                  </td>
                </tr>
              ) : filtered.length === 0 ? (
                <tr>
                  <td colSpan={columns.length + (canManage ? 1 : 0)} className="px-4 py-10 text-center text-gray-500">
                    {emptyLabel}
                  </td>
                </tr>
              ) : (
                filtered.map((row) => (
                  <tr key={row.id} className="border-t border-gray-100 hover:bg-gray-50/80">
                    {columns.map((col) => (
                      <td key={col.key} className="px-4 py-3 text-gray-800 whitespace-nowrap">
                        {col.render ? col.render(row) : col.getValue?.(row) ?? row[col.key] ?? '—'}
                      </td>
                    ))}
                    {canManage && (
                      <td className="px-4 py-3 text-right whitespace-nowrap">
                        <button
                          type="button"
                          className="inline-flex p-2 rounded-lg text-[#00628b] hover:bg-blue-50"
                          onClick={() => openEdit(row)}
                        >
                          <Pencil size={16} />
                        </button>
                        <button
                          type="button"
                          className="inline-flex p-2 rounded-lg text-red-600 hover:bg-red-50"
                          onClick={() => setDeleteTarget(row)}
                        >
                          <Trash2 size={16} />
                        </button>
                      </td>
                    )}
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      <ModalShell
        open={modalOpen}
        onClose={() => setModalOpen(false)}
        size="md"
        title={editing ? `Edit ${title.slice(0, -1) || title}` : `Add ${title.slice(0, -1) || title}`}
        subtitle="Fill in the fields below, then save"
        bodyClassName="px-4 sm:px-5 py-4"
        footer={
          <>
            <ModalSecondaryButton type="button" onClick={() => setModalOpen(false)}>
              Cancel
            </ModalSecondaryButton>
            <ModalPrimaryButton type="submit" form="resource-crud-form" disabled={saving}>
              {saving ? 'Saving…' : 'Save'}
            </ModalPrimaryButton>
          </>
        }
      >
            <form id="resource-crud-form" onSubmit={handleSave} className="space-y-4">
              {(buildFormFields?.(form, editing) || []).map((field) => (
                <div key={field.name}>
                  <label className="block text-[10px] uppercase tracking-wider text-slate-400 font-semibold mb-1">
                    {field.label}
                  </label>
                  {field.type === 'select' ? (
                    <select
                      required={field.required}
                      value={form[field.name] ?? ''}
                      onChange={(e) =>
                        setForm((prev) => ({
                          ...prev,
                          [field.name]: field.valueAsNumber ? Number(e.target.value) : e.target.value,
                        }))
                      }
                      className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#00628b]/25"
                    >
                      <option value="">Select…</option>
                      {(field.options || []).map((opt) => (
                        <option key={opt.value} value={opt.value}>
                          {opt.label}
                        </option>
                      ))}
                    </select>
                  ) : (
                    <input
                      type={field.type || 'text'}
                      required={field.required}
                      value={form[field.name] ?? ''}
                      onChange={(e) =>
                        setForm((prev) => ({
                          ...prev,
                          [field.name]:
                            field.type === 'number'
                              ? e.target.value === ''
                                ? ''
                                : Number(e.target.value)
                              : e.target.value,
                        }))
                      }
                      className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#00628b]/25"
                      placeholder={field.placeholder}
                    />
                  )}
                </div>
              ))}
            </form>
      </ModalShell>

      <ModalShell
        open={Boolean(deleteTarget)}
        onClose={() => setDeleteTarget(null)}
        size="sm"
        accent="danger"
        title="Confirm delete"
        subtitle="This action cannot be undone"
        bodyClassName="px-4 sm:px-5 py-5 text-center"
        footer={
          <>
            <ModalSecondaryButton onClick={() => setDeleteTarget(null)}>Cancel</ModalSecondaryButton>
            <ModalPrimaryButton danger disabled={saving} onClick={handleDelete}>
              {saving ? 'Deleting…' : 'Delete'}
            </ModalPrimaryButton>
          </>
        }
      >
            <p className="m-0 text-sm text-slate-600">
              Delete{' '}
              <span className="font-semibold text-[#031f50]">
                {deleteTarget?.name ||
                  (deleteTarget?.names ? capitalizePersonName(deleteTarget.names) : null) ||
                  `#${deleteTarget?.id}`}
              </span>
              ?
            </p>
      </ModalShell>
    </div>
  );
}

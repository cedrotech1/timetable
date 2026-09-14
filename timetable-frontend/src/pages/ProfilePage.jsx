import { useState } from 'react';
import { useAuth } from '../contexts/AuthContext';
import { useNotification } from '../contexts/NotificationContext';
import { authService } from '../services/api';
import { roleLabel } from '../utils/roles';
import { UserAvatar } from '../components/UserAvatar';

export default function ProfilePage() {
  const { user, refreshUser } = useAuth();
  const { showSuccess, showError } = useNotification();
  const [form, setForm] = useState({
    currentPassword: '',
    newPassword: '',
    confirmPassword: '',
  });
  const [saving, setSaving] = useState(false);

  const handleChange = (e) => {
    setForm((prev) => ({ ...prev, [e.target.name]: e.target.value }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    try {
      setSaving(true);
      const response = await authService.changePassword(form);
      if (response?.success) {
        showSuccess('Password changed successfully');
        setForm({ currentPassword: '', newPassword: '', confirmPassword: '' });
        await refreshUser();
      } else {
        showError(response?.message || 'Failed to change password');
      }
    } catch (error) {
      showError(error.response?.data?.message || 'Failed to change password');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="max-w-3xl mx-auto space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900 m-0">Profile</h1>
        <p className="mt-1 mb-0 text-sm text-gray-500">Your account details and password</p>
      </div>

      <div className="bg-white rounded-xl border border-gray-200 p-6 shadow-sm flex items-center gap-4">
        <UserAvatar user={user} size={56} />
        <div>
          <p className="m-0 text-lg font-semibold text-gray-900">{user?.names}</p>
          <p className="m-0 text-sm text-gray-500">{user?.urEmail}</p>
          <p className="m-0 text-xs text-gray-400 mt-1">{roleLabel(user?.role)}</p>
        </div>
      </div>

      <form onSubmit={handleSubmit} className="bg-white rounded-xl border border-gray-200 p-6 shadow-sm space-y-4">
        <h2 className="m-0 text-base font-semibold text-gray-900">Change password</h2>
        {[
          { name: 'currentPassword', label: 'Current password' },
          { name: 'newPassword', label: 'New password' },
          { name: 'confirmPassword', label: 'Confirm new password' },
        ].map((field) => (
          <div key={field.name}>
            <label className="block text-sm font-medium text-gray-700 mb-1">{field.label}</label>
            <input
              type="password"
              name={field.name}
              required
              value={form[field.name]}
              onChange={handleChange}
              className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#00628b]/30"
            />
          </div>
        ))}
        <button
          type="submit"
          disabled={saving}
          className="px-4 py-2.5 rounded-lg bg-[#00628b] text-white text-sm font-medium hover:bg-[#004f70] disabled:opacity-60"
        >
          {saving ? 'Updating…' : 'Update password'}
        </button>
      </form>
    </div>
  );
}

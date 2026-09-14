import { Trash2 } from 'lucide-react';
import ModalShell, { ModalPrimaryButton, ModalSecondaryButton } from './ModalShell';

export const DeleteModal = ({ isOpen, onClose, onConfirm, userName = '', loading = false }) => {
  return (
    <ModalShell
      open={isOpen}
      onClose={loading ? undefined : onClose}
      size="sm"
      accent="danger"
      title="Delete user"
      subtitle="This action cannot be undone"
      bodyClassName="px-4 sm:px-5 py-5 text-center"
      footer={
        <>
          <ModalSecondaryButton onClick={onClose} disabled={loading}>
            Cancel
          </ModalSecondaryButton>
          <ModalPrimaryButton danger onClick={onConfirm} disabled={loading}>
            {loading ? 'Deleting…' : 'Delete'}
          </ModalPrimaryButton>
        </>
      }
    >
      <div className="mx-auto flex items-center justify-center h-12 w-12 rounded-2xl bg-red-50 mb-3">
        <Trash2 className="h-6 w-6 text-red-600" />
      </div>
      {userName ? (
        <p className="m-0 text-sm text-slate-600">
          Delete <span className="font-semibold text-[#031f50]">{userName}</span>?
        </p>
      ) : null}
    </ModalShell>
  );
};

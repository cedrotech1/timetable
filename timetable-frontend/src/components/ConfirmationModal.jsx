import { AlertTriangle, Eye, EyeOff, Trash2 } from 'lucide-react';
import ModalShell, { ModalPrimaryButton, ModalSecondaryButton } from './ModalShell';

export const ConfirmationModal = ({
  isOpen,
  onClose,
  onConfirm,
  title,
  message,
  type = 'delete',
  userName = '',
  loading = false,
}) => {
  const getIcon = () => {
    switch (type) {
      case 'delete':
        return <Trash2 className="h-6 w-6 text-red-600" />;
      case 'activate':
        return <Eye className="h-6 w-6 text-emerald-600" />;
      case 'deactivate':
        return <EyeOff className="h-6 w-6 text-[#c45c26]" />;
      default:
        return <AlertTriangle className="h-6 w-6 text-amber-500" />;
    }
  };

  const accent = type === 'delete' ? 'danger' : type === 'activate' ? 'success' : type === 'deactivate' ? 'amber' : 'brand';

  const getConfirmText = () => {
    switch (type) {
      case 'delete':
        return 'Delete';
      case 'activate':
        return 'Activate';
      case 'deactivate':
        return 'Deactivate';
      default:
        return 'Confirm';
    }
  };

  return (
    <ModalShell
      open={isOpen}
      onClose={loading ? undefined : onClose}
      size="sm"
      accent={accent}
      title={title}
      bodyClassName="px-4 sm:px-5 py-5 text-center"
      footer={
        <>
          <ModalSecondaryButton onClick={onClose} disabled={loading}>
            Cancel
          </ModalSecondaryButton>
          <ModalPrimaryButton danger={type === 'delete'} onClick={onConfirm} disabled={loading}>
            {loading ? 'Processing…' : getConfirmText()}
          </ModalPrimaryButton>
        </>
      }
    >
      <div className="mx-auto flex items-center justify-center h-12 w-12 rounded-2xl bg-slate-100 mb-3">
        {getIcon()}
      </div>
      {userName ? (
        <p className="m-0 mb-2 text-sm text-slate-600">
          User: <span className="font-semibold text-[#031f50]">{userName}</span>
        </p>
      ) : null}
      <div className="text-sm text-slate-500">{message}</div>
    </ModalShell>
  );
};

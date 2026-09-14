import { Eye, EyeOff } from 'lucide-react';
import ModalShell, { ModalPrimaryButton, ModalSecondaryButton } from './ModalShell';

export const StatusModal = ({
  isOpen,
  onClose,
  onConfirm,
  title,
  message,
  type = 'activate',
  userName = '',
  loading = false,
}) => {
  const isActivate = type === 'activate';

  return (
    <ModalShell
      open={isOpen}
      onClose={loading ? undefined : onClose}
      size="sm"
      accent={isActivate ? 'success' : 'amber'}
      title={title}
      bodyClassName="px-4 sm:px-5 py-5 text-center"
      footer={
        <>
          <ModalSecondaryButton onClick={onClose} disabled={loading}>
            Cancel
          </ModalSecondaryButton>
          <ModalPrimaryButton onClick={onConfirm} disabled={loading}>
            {loading ? 'Processing…' : isActivate ? 'Activate' : 'Deactivate'}
          </ModalPrimaryButton>
        </>
      }
    >
      <div
        className={`mx-auto flex items-center justify-center h-12 w-12 rounded-2xl mb-3 ${
          isActivate ? 'bg-emerald-50' : 'bg-[#fff4eb]'
        }`}
      >
        {isActivate ? (
          <Eye className="h-6 w-6 text-emerald-600" />
        ) : (
          <EyeOff className="h-6 w-6 text-[#c45c26]" />
        )}
      </div>
      {userName ? (
        <p className="m-0 mb-2 text-sm text-slate-600">
          Are you sure you want to {type} <span className="font-semibold text-[#031f50]">{userName}</span>?
        </p>
      ) : null}
      <div className="text-sm text-slate-500">{message}</div>
    </ModalShell>
  );
};

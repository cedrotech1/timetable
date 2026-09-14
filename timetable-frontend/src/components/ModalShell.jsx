import { X } from 'lucide-react';

/**
 * Shared modal chrome used across pickers, forms, and detail dialogs.
 * accent: 'brand' | 'amber' | 'danger' | 'success'
 */
export default function ModalShell({
  open,
  onClose,
  title,
  subtitle = '',
  children,
  footer = null,
  size = 'md', // sm | md | lg | xl
  accent = 'brand',
  zIndex = 60,
  bodyClassName = '',
}) {
  if (!open) return null;

  const maxW =
    size === 'sm' ? 'max-w-md' : size === 'lg' ? 'max-w-2xl' : size === 'xl' ? 'max-w-3xl' : 'max-w-lg';

  const accentBar =
    accent === 'amber'
      ? 'from-[#c45c26] to-[#e8a05c]'
      : accent === 'danger'
        ? 'from-red-600 to-red-400'
        : accent === 'success'
          ? 'from-emerald-700 to-emerald-500'
          : 'from-[#031f50] to-[#00628b]';

  return (
    <div className="fixed inset-0 flex items-center justify-center p-3 sm:p-4" style={{ zIndex }}>
      <div
        className="absolute inset-0 bg-[#031f50]/45 backdrop-blur-[2px]"
        onClick={onClose}
        aria-hidden
      />
      <div
        role="dialog"
        aria-modal="true"
        aria-label={typeof title === 'string' ? title : 'Dialog'}
        className={`relative bg-white rounded-2xl shadow-2xl w-full ${maxW} max-h-[90vh] flex flex-col overflow-hidden border border-[#031f50]/10`}
      >
        <div className={`h-1.5 w-full bg-gradient-to-r ${accentBar} shrink-0`} />
        <div className="flex items-start justify-between gap-3 px-4 sm:px-5 py-3.5 border-b border-slate-100 bg-gradient-to-b from-[#f7fafc] to-white shrink-0">
          <div className="min-w-0">
            {typeof title === 'string' ? (
              <h3 className="m-0 text-base sm:text-lg font-semibold text-[#031f50] tracking-tight">{title}</h3>
            ) : (
              title
            )}
            {subtitle ? <p className="m-0 mt-0.5 text-xs text-slate-500 leading-snug">{subtitle}</p> : null}
          </div>
          {onClose ? (
            <button
              type="button"
              onClick={onClose}
              className="shrink-0 p-1.5 rounded-lg text-slate-400 hover:text-[#031f50] hover:bg-slate-100 transition"
              aria-label="Close"
            >
              <X size={18} />
            </button>
          ) : null}
        </div>

        <div className={`flex-1 overflow-y-auto min-h-0 ${bodyClassName}`}>{children}</div>

        {footer ? (
          <div className="shrink-0 px-4 sm:px-5 py-3 border-t border-slate-100 bg-[#f8fafc] flex flex-wrap items-center justify-end gap-2">
            {footer}
          </div>
        ) : null}
      </div>
    </div>
  );
}

export function ModalPrimaryButton({ children, className = '', danger = false, type = 'button', ...props }) {
  return (
    <button
      type={type}
      className={`px-4 py-2 rounded-lg text-sm font-semibold text-white transition disabled:opacity-50 ${
        danger
          ? 'bg-red-600 hover:bg-red-700'
          : 'bg-[#00628b] hover:bg-[#004f70] shadow-sm shadow-[#00628b]/25'
      } ${className}`}
      {...props}
    >
      {children}
    </button>
  );
}

export function ModalSecondaryButton({ children, className = '', type = 'button', ...props }) {
  return (
    <button
      type={type}
      className={`px-4 py-2 rounded-lg border border-slate-200 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 transition disabled:opacity-50 ${className}`}
      {...props}
    >
      {children}
    </button>
  );
}

import { useEffect, useMemo, useRef, useState } from 'react';
import { Search, X, Check, Users, Building2, BookOpen, GraduationCap, Filter } from 'lucide-react';
import ModalShell, { ModalPrimaryButton, ModalSecondaryButton } from './ModalShell';

const KIND_ICON = {
  lecturer: Users,
  facility: Building2,
  module: BookOpen,
  program: GraduationCap,
  default: Filter,
};

/**
 * Searchable single/multi picker — modules, facilities, lecturers, programs.
 * Shared look: navy header accent, amber selection chips, clear search + filters.
 */
export default function SearchablePicker({
  open,
  onClose,
  title = 'Search',
  subtitle = '',
  items = [],
  value = null,
  multiple = false,
  kind = 'default', // lecturer | facility | module | program | default
  getId = (item) => item.id,
  getLabel = (item) => item.name || String(item.id),
  getMeta = () => '',
  getBadge = null,
  filterItem = (item, q) => {
    const hay = `${getLabel(item)} ${getMeta(item)}`.toLowerCase();
    return hay.includes(q);
  },
  placeholder = 'Type name, code, or email…',
  emptyText = 'No matches — try another search',
  onSelect,
  confirmLabel = 'Apply selection',
  initialQuery = '',
  hint = '',
  accent = 'brand',
}) {
  const [q, setQ] = useState('');
  const [picked, setPicked] = useState(() => new Set());
  const [showSelectedOnly, setShowSelectedOnly] = useState(false);
  const inputRef = useRef(null);
  const Icon = KIND_ICON[kind] || KIND_ICON.default;

  useEffect(() => {
    if (!open) return;
    setQ(initialQuery || '');
    setShowSelectedOnly(false);
    if (multiple) {
      const ids = Array.isArray(value) ? value.map(String) : value != null ? [String(value)] : [];
      setPicked(new Set(ids));
    }
    const t = setTimeout(() => inputRef.current?.focus(), 50);
    return () => clearTimeout(t);
  }, [open, multiple, value, initialQuery]);

  const filtered = useMemo(() => {
    const query = q.trim().toLowerCase();
    let list = !query ? [...items] : items.filter((item) => filterItem(item, query));
    if (multiple && showSelectedOnly) {
      list = list.filter((item) => picked.has(String(getId(item))));
    }
    // Selected items float to the top for multi-select
    if (multiple && picked.size) {
      list = [...list].sort((a, b) => {
        const aOn = picked.has(String(getId(a))) ? 0 : 1;
        const bOn = picked.has(String(getId(b))) ? 0 : 1;
        if (aOn !== bOn) return aOn - bOn;
        return String(getLabel(a)).localeCompare(String(getLabel(b)));
      });
    }
    return list.slice(0, 250);
  }, [items, q, filterItem, multiple, showSelectedOnly, picked, getId, getLabel]);

  const selectedItems = useMemo(() => {
    if (!multiple) return [];
    return items.filter((item) => picked.has(String(getId(item))));
  }, [items, multiple, picked, getId]);

  if (!open) return null;

  const toggle = (id) => {
    setPicked((prev) => {
      const next = new Set(prev);
      const key = String(id);
      if (next.has(key)) next.delete(key);
      else next.add(key);
      return next;
    });
  };

  const applyMultiple = () => {
    const selected = items.filter((item) => picked.has(String(getId(item))));
    onSelect?.(selected);
    onClose?.();
  };

  const clearAll = () => setPicked(new Set());
  const selectVisible = () => {
    setPicked((prev) => {
      const next = new Set(prev);
      filtered.forEach((item) => next.add(String(getId(item))));
      return next;
    });
  };

  const footer = multiple ? (
    <>
      <span className="mr-auto text-xs text-slate-500">
        <span className="inline-flex items-center gap-1 font-semibold text-[#c45c26]">{picked.size}</span> selected
      </span>
      <ModalSecondaryButton onClick={onClose}>Cancel</ModalSecondaryButton>
      <ModalPrimaryButton onClick={applyMultiple}>{confirmLabel}</ModalPrimaryButton>
    </>
  ) : null;

  return (
    <ModalShell
      open={open}
      onClose={onClose}
      accent={accent}
      size="lg"
      zIndex={100}
      title={
        <div className="flex items-center gap-2.5 min-w-0">
          <span className="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-[#031f50]/5 text-[#00628b] border border-[#00628b]/15 shrink-0">
            <Icon size={18} />
          </span>
          <span className="min-w-0">
            <span className="block text-base sm:text-lg font-semibold text-[#031f50] tracking-tight truncate">
              {title}
            </span>
            {(subtitle || hint) && (
              <span className="block text-xs text-slate-500 mt-0.5 truncate">{subtitle || hint}</span>
            )}
          </span>
        </div>
      }
      footer={footer}
      bodyClassName="flex flex-col !overflow-hidden"
    >
      {/* Search */}
      <div className="px-4 sm:px-5 py-3 border-b border-slate-100 bg-white shrink-0 space-y-2.5">
        <div className="relative">
          <Search size={15} className="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#00628b]/70" />
          <input
            ref={inputRef}
            type="search"
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder={placeholder}
            className="w-full rounded-xl border border-slate-200 bg-[#f8fafc] pl-10 pr-10 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:bg-white focus:border-[#00628b] focus:ring-2 focus:ring-[#00628b]/20 transition"
          />
          {q ? (
            <button
              type="button"
              className="absolute right-3 top-1/2 -translate-y-1/2 p-0.5 rounded text-slate-400 hover:text-slate-700"
              onClick={() => setQ('')}
              aria-label="Clear search"
            >
              <X size={14} />
            </button>
          ) : null}
        </div>

        <div className="flex flex-wrap items-center gap-2 text-[11px]">
          <span className="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-slate-100 text-slate-600 font-medium">
            {filtered.length}
            {items.length > filtered.length ? ` / ${items.length}` : ''} shown
          </span>
          {multiple && (
            <>
              <button
                type="button"
                onClick={() => setShowSelectedOnly((v) => !v)}
                className={`px-2.5 py-1 rounded-full border text-[11px] font-semibold transition ${
                  showSelectedOnly
                    ? 'bg-[#fff4eb] text-[#c45c26] border-[#e8a05c]/60'
                    : 'bg-white text-slate-600 border-slate-200 hover:border-[#c45c26]/40'
                }`}
              >
                Selected only
              </button>
              <button
                type="button"
                onClick={selectVisible}
                className="px-2.5 py-1 rounded-full border border-slate-200 bg-white text-slate-600 font-medium hover:border-[#00628b]/40 hover:text-[#00628b]"
              >
                Select visible
              </button>
              <button
                type="button"
                onClick={clearAll}
                disabled={!picked.size}
                className="px-2.5 py-1 rounded-full border border-slate-200 bg-white text-slate-500 font-medium hover:text-red-600 disabled:opacity-40"
              >
                Clear
              </button>
            </>
          )}
        </div>

        {/* Selected chips (multi) */}
        {multiple && selectedItems.length > 0 && (
          <div className="flex flex-wrap gap-1.5 max-h-20 overflow-y-auto pt-0.5">
            {selectedItems.map((item) => {
              const id = getId(item);
              return (
                <button
                  key={id}
                  type="button"
                  onClick={() => toggle(id)}
                  className="inline-flex items-center gap-1 max-w-full px-2 py-1 rounded-lg text-[11px] font-medium bg-[#fff4eb] text-[#9a4518] border border-[#e8a05c]/50 hover:bg-[#ffe8d6] transition"
                  title="Click to remove"
                >
                  <span className="truncate">{getLabel(item)}</span>
                  <X size={11} className="shrink-0 opacity-70" />
                </button>
              );
            })}
          </div>
        )}
      </div>

      {/* Results */}
      <div className="flex-1 overflow-y-auto px-2 sm:px-3 py-2 space-y-1 min-h-[12rem] bg-[#fbfcfe]">
        {filtered.length === 0 ? (
          <div className="text-center py-12 px-4">
            <div className="mx-auto w-12 h-12 rounded-2xl bg-slate-100 flex items-center justify-center text-slate-400 mb-3">
              <Icon size={22} />
            </div>
            <p className="m-0 text-sm font-medium text-slate-600">{emptyText}</p>
            {q ? (
              <button
                type="button"
                className="mt-2 text-xs text-[#00628b] underline"
                onClick={() => setQ('')}
              >
                Clear search
              </button>
            ) : null}
          </div>
        ) : (
          filtered.map((item) => {
            const id = getId(item);
            const active = multiple
              ? picked.has(String(id))
              : value != null && String(value) === String(id);
            const badge = typeof getBadge === 'function' ? getBadge(item) : null;
            const badgeTone =
              badge?.tone === 'ok'
                ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
                : badge?.tone === 'muted'
                  ? 'bg-slate-100 text-slate-600 border-slate-200'
                  : 'bg-[#e8f4f8] text-[#00628b] border-[#00628b]/20';
            const meta = getMeta(item);

            return (
              <button
                key={id}
                type="button"
                onClick={() => {
                  if (multiple) toggle(id);
                  else {
                    onSelect?.(item);
                    onClose?.();
                  }
                }}
                className={`w-full text-left rounded-xl px-3 py-2.5 text-sm transition flex items-start gap-3 border ${
                  active
                    ? 'bg-[#fff8f2] border-[#e8a05c]/70 shadow-sm shadow-[#c45c26]/10'
                    : 'bg-white border-transparent hover:border-slate-200 hover:bg-white hover:shadow-sm'
                }`}
              >
                {multiple ? (
                  <span
                    className={`mt-0.5 shrink-0 w-[18px] h-[18px] rounded-md border-2 flex items-center justify-center transition ${
                      active
                        ? 'bg-[#c45c26] border-[#c45c26] text-white'
                        : 'border-slate-300 bg-white'
                    }`}
                  >
                    {active ? <Check size={11} strokeWidth={3} /> : null}
                  </span>
                ) : (
                  <span
                    className={`mt-1 shrink-0 w-2.5 h-2.5 rounded-full ${
                      active ? 'bg-[#c45c26] ring-2 ring-[#c45c26]/25' : 'bg-slate-200'
                    }`}
                  />
                )}
                <span className="min-w-0 flex-1">
                  <span className={`font-semibold block leading-snug ${active ? 'text-[#031f50]' : 'text-slate-900'}`}>
                    {getLabel(item)}
                  </span>
                  {meta ? <span className="text-xs text-slate-500 block mt-0.5 leading-snug">{meta}</span> : null}
                  {badge?.label ? (
                    <span
                      className={`inline-block mt-1.5 text-[10px] font-semibold px-1.5 py-0.5 rounded-md border ${badgeTone}`}
                    >
                      {badge.label}
                    </span>
                  ) : null}
                </span>
                {active && !multiple ? (
                  <Check size={16} className="shrink-0 text-[#c45c26] mt-0.5" />
                ) : null}
              </button>
            );
          })
        )}
      </div>
    </ModalShell>
  );
}

export function PickerButton({
  label,
  valueLabel,
  placeholder = 'Search…',
  onClick,
  className = '',
  optional = false,
  kind = 'default',
}) {
  const Icon = KIND_ICON[kind] || Search;
  return (
    <button
      type="button"
      onClick={onClick}
      className={`group w-full text-left rounded-xl border px-3 py-2.5 text-sm transition focus:outline-none focus:ring-2 focus:ring-[#00628b]/25 ${
        valueLabel
          ? 'border-slate-200 bg-white hover:border-[#00628b]/45 hover:shadow-sm'
          : 'border-dashed border-slate-300 bg-[#f8fafc] text-slate-500 hover:border-[#c45c26]/50 hover:bg-[#fff8f2]'
      } ${className}`}
    >
      <span className="flex items-center justify-between gap-2 mb-0.5">
        <span className="text-[10px] uppercase tracking-wider text-slate-400 font-semibold">
          {label}
          {optional ? <span className="normal-case tracking-normal font-normal text-slate-400"> · optional</span> : null}
        </span>
        <Icon size={12} className="text-slate-300 group-hover:text-[#00628b] transition" />
      </span>
      <span className={`block truncate ${valueLabel ? 'font-semibold text-[#031f50]' : 'text-slate-500'}`}>
        {valueLabel || (
          <span className="inline-flex items-center gap-1.5 font-medium">
            <Search size={12} className="text-[#c45c26]" /> {placeholder}
          </span>
        )}
      </span>
    </button>
  );
}

export const getUserInitials = (name) => {
  if (!name || typeof name !== 'string') return 'U';
  const words = name.trim().split(/\s+/).filter(Boolean);
  if (!words.length) return 'U';
  if (words.length === 1) return words[0].substring(0, 2).toUpperCase();
  return words
    .slice(0, 2)
    .map((w) => w[0].toUpperCase())
    .join('');
};

export const UserAvatar = ({ user, name, image, className = '', size = 36 }) => {
  const displayName = name || user?.names || 'User';
  const initials = getUserInitials(displayName);
  const px = typeof size === 'number' ? size : 36;

  return (
    <div
      className={`
        bg-gradient-to-br from-[#00628b] to-[#004a6b]
        text-white rounded-full flex items-center justify-center
        font-medium shadow-sm overflow-hidden shrink-0
        ${className}
      `}
      style={{ width: px, height: px, fontSize: Math.max(11, px * 0.32) }}
      title={displayName}
    >
      {initials}
    </div>
  );
};

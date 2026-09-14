import urLogo from '../assets/ur-logo.png';

export const UrLogo = ({ className = 'h-full w-full object-contain', alt = 'University of Rwanda Timetable' }) => (
  <img src={urLogo} alt={alt} className={className} />
);

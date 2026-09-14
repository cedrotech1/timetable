// User roles
const USER_ROLES = {
    ADMIN: "admin",
    DATA_MANAGER: "data_manager",
    HEAD_OF_COMMUNITY_WORKERS: "head_of_community_workers_at_helth_center",
    DOCTOR: "doctor",
    NURSE: "nurse",
  };
  
  // Appointment statuses
  const APPOINTMENT_STATUSES = {
    SCHEDULED: "Scheduled",
    COMPLETED: "Completed",
    CANCELLED: "Cancelled",
  };
  
  module.exports = { USER_ROLES, APPOINTMENT_STATUSES };
  
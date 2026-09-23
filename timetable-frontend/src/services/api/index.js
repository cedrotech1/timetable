import apiClient from './config.js';

function makeCrudService(basePath) {
  return {
    getAll: async (params = {}) => {
      const response = await apiClient.get(basePath, { params });
      return response.data;
    },
    getById: async (id) => {
      const response = await apiClient.get(`${basePath}/${id}`);
      return response.data;
    },
    create: async (data) => {
      const response = await apiClient.post(basePath, data);
      return response.data;
    },
    update: async (id, data) => {
      const response = await apiClient.put(`${basePath}/${id}`, data);
      return response.data;
    },
    remove: async (id) => {
      const response = await apiClient.delete(`${basePath}/${id}`);
      return response.data;
    },
  };
}

export const campusesService = makeCrudService('/campuses');
export const collegesService = makeCrudService('/colleges');
export const schoolsService = makeCrudService('/schools');
export const programsService = makeCrudService('/programs');
export const modulesService = {
  ...makeCrudService('/modules'),
  truncateAll: async () => (await apiClient.post('/modules/truncate')).data,
};
export const facilitiesService = makeCrudService('/facilities');
export const usersService = makeCrudService('/users');

export const organizationService = {
  getStructure: async () => {
    const response = await apiClient.get('/organization/structure');
    return response.data;
  },
};

export const timetableService = {
  getSettings: async () => (await apiClient.get('/timetables/settings')).data,
  updateSettings: async (payload) => (await apiClient.put('/timetables/settings', payload)).data,
  createAcademicYear: async (payload) =>
    (await apiClient.post('/timetables/settings/academic-years', payload)).data,
  deleteAcademicYear: async (id) =>
    (await apiClient.delete(`/timetables/settings/academic-years/${id}`)).data,
  resetTimetables: async () => (await apiClient.post('/timetables/settings/reset-timetables')).data,
  clearIntakesGroups: async () =>
    (await apiClient.post('/timetables/settings/clear-intakes-groups')).data,
  list: async (params = {}) => (await apiClient.get('/timetables', { params })).data,
  getById: async (id) => (await apiClient.get(`/timetables/${id}`)).data,
  listPublic: async (params = {}) => (await apiClient.get('/timetables/public', { params })).data,
  create: async (payload) => (await apiClient.post('/timetables', payload)).data,
  update: async (id, payload) => (await apiClient.put(`/timetables/${id}`, payload)).data,
  remove: async (id) => (await apiClient.delete(`/timetables/${id}`)).data,
  bulk: async (payload) => (await apiClient.post('/timetables/bulk', payload)).data,
  availableFacilities: async (payload) =>
    (await apiClient.post('/timetables/facilities/available', payload)).data,
  listIntakes: async (params = {}) => (await apiClient.get('/timetables/intakes', { params })).data,
  createIntake: async (payload) => (await apiClient.post('/timetables/intakes', payload)).data,
  listGroups: async (params = {}) => (await apiClient.get('/timetables/groups', { params })).data,
  listLecturers: async () => (await apiClient.get('/timetables/lecturers')).data,
  matchImport: async (payload) => (await apiClient.post('/timetables/import/match', payload)).data,
  autoAssignFacilities: async (payload) =>
    (await apiClient.post('/timetables/import/auto-facilities', payload)).data,
  facilityCalendar: async (params = {}) =>
    (await apiClient.get('/timetables/facilities/calendar', { params })).data,
  createIntakesFromImport: async (payload) =>
    (await apiClient.post('/timetables/import/create-intakes', payload)).data,
};

export { authService } from './authService.js';
export { default as apiClient } from './config.js';

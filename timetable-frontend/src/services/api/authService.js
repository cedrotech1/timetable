import apiClient from './config.js';

export const authService = {
  login: async (credentials) => {
    const response = await apiClient.post('/auth/login', credentials);
    return response.data;
  },

  getCurrentUser: async () => {
    const response = await apiClient.get('/auth/me');
    return response.data;
  },

  changePassword: async (payload) => {
    const response = await apiClient.put('/auth/change-password', payload);
    return response.data;
  },
};

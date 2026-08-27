import axios from 'axios';
import { useAuthStore } from '@/stores/auth';

const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1',
    headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json'
    }
});

// Request interceptor - Add token
// api.interceptor.request.use(
//   (config) => {
//     const authStore = useAuthStore();
//     const token = authStore.token || localStorage.getItem("auth_token");

//     if (token) {
//       config.headers.Authorization = `Bearer ${token}`;
//     }

//     //  Add tenant ID if available
//     const tenantId =
//       authStore.currentTenantId || localStorage.getItem("current_tenant_id");
//     if (tenantId) {
//       config.headers["X-Tenant-ID"] = tenantId;
//     }

//     return config;
//   },
//   (error) => {
//     return Promise.reject(error);
//   },
// );

// Response interceptor - Handle errors
// api.interceptor.response.use(
//   (response) => {
//     return response;
//   },
//   async (error) => {
//     const originalRequest = error.config;
//     // const authStore = useAuthStore();

//     // Handle token expiration
//     if (error.response?.status === 401 && !originalRequest._retry) {
//       originalRequest._retry = true;

//       // Prevent infinite loops
//       if (originalRequest._retry) {
//         return Promise.reject(error);
//       }

//       // Handle token expiration (401)
//         if (error.response?.status === 401 && !originalRequest._retry) {
//             originalRequest._retry = true;

//             const authStore = useAuthStore();

//             try {
//                 // Try to refresh the token
//                 const response = await axios.post(
//                     `${import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1'}/auth/refresh`,
//                     {},
//                     {
//                         headers: {
//                             'Authorization': `Bearer ${authStore.token || localStorage.getItem('auth_token')}`,
//                         }
//                     }
//                 );

//                 const newToken = response.data.data.token;

//                 // Update token in store and localStorage
//                 authStore.token = newToken;
//                 localStorage.setItem('auth_token', newToken);

//                 // Update the failed request with new token
//                 originalRequest.headers.Authorization = `Bearer ${newToken}`;

//                 // Retry the original request
//                 return api(originalRequest);
//             } catch (refreshError) {
//                 // If refresh fails, logout user
//                 authStore.clearAuth();
//                 window.location.href = '/login';
//                 return Promise.reject(refreshError);
//             }
//         }

//         // Handle other errors
//         return Promise.reject(error);
//   },
// );

// Request interceptor - Add token
api.interceptors.request.use(
    (config) => {
        // Get token from store or localStorage
        const authStore = useAuthStore();
        let token = authStore.token;

        if (!token) {
            token = localStorage.getItem('auth_token');
        }

        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }

        // Add tenant ID if available
        const tenantId = authStore.currentTenantId || localStorage.getItem('current_tenant_id');
        if (tenantId) {
            config.headers['X-Tenant-ID'] = tenantId;
        }

        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

// Response interceptor - Handle errors
api.interceptors.response.use(
    (response) => {
        return response;
    },
    (error) => {
        // Handle 401 Unauthorized
        if (error.response?.status === 401) {
            const authStore = useAuthStore();
            authStore.clearAuth();

            // Redirect to login if not already there
            if (window.location.pathname !== '/login') {
                window.location.href = '/login';
            }
        }

        return Promise.reject(error);
    }
);

export default api;

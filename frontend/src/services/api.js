import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
// import { showApiError } from '@/utils/apiError';
import { toast } from 'vue3-toastify';

const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1',
    headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json'
    },
    timeout: 30000,
    withCredentials: false
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
        const authStore = useAuthStore();
        const token = authStore.token || localStorage.getItem('auth_token');

        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }

        const tenantId = authStore.currentTenantId || localStorage.getItem('current_tenant_id');
        if (tenantId) {
            config.headers['X-Tenant-ID'] = tenantId;
            config.headers['X-Tenant-Id'] = tenantId;
        }

        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

// Response interceptor - Handle errors
// api.interceptors.response.use(
//     (response) => {
//         return response;
//     },
//     (error) => {
//         // Handle 401 Unauthorized
//         if (error.response?.status === 401) {
//             const authStore = useAuthStore();
//             authStore.clearAuth();

//             // Redirect to login if not already there
//             if (window.location.pathname !== '/login') {
//                 window.location.href = '/login';
//             }
//         } else if (!error.config?.skipErrorToast) {
//             // Any other error: show a toast automatically.
//             // Per-call opt-out: api.get('/foo', { skipErrorToast: true })
//             showApiError(error);
//         }

//         return Promise.reject(error);
//     }
// );

// Response interceptor - Better error handling
api.interceptors.response.use(
    (response) => response,
    async (error) => {
        // Log error for debugging
        console.error('API Error:', {
            url: error.config?.url,
            method: error.config?.method,
            status: error.response?.status,
            data: error.response?.data,
            message: error.message
        });

        // Handle 400 Bad Request - show user-friendly message
        if (error.response?.status === 400) {
            const message = error.response?.data?.message || 'Bad request. Please check your input.';
            toast.error(message);
            return Promise.reject(error);
        }

        // Handle 401 Unauthorized
        if (error.response?.status === 401 && !error.config?._retry) {
            error.config._retry = true;

            const authStore = useAuthStore();

            try {
                const response = await axios.post(
                    `${import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1'}/auth/refresh`,
                    {},
                    {
                        headers: {
                            Authorization: `Bearer ${authStore.token || localStorage.getItem('auth_token')}`
                        }
                    }
                );

                if (response.data.success) {
                    const newToken = response.data.data.token;
                    authStore.token = newToken;
                    localStorage.setItem('auth_token', newToken);
                    error.config.headers.Authorization = `Bearer ${newToken}`;
                    return api(error.config);
                }
            } catch (refreshError) {
                authStore.clearAuth();
                toast.error('Session expired. Please login again.');
                window.location.href = '/login';
                return Promise.reject(refreshError);
            }
        }

        // Handle 404 Not Found
        if (error.response?.status === 404) {
            toast.error(error.response?.data?.message || 'Resource not found.');
        }

        // Handle 422 Validation
        if (error.response?.status === 422) {
            const errors = error.response?.data?.errors;
            if (errors) {
                const firstError = Object.values(errors)[0];
                if (firstError && Array.isArray(firstError)) {
                    toast.error(firstError[0]);
                }
            }
        }

        // Handle 500 Server Error
        if (error.response?.status === 500) {
            toast.error('Server error. Please try again later.');
        }

        return Promise.reject(error);
    }
);

export default api;

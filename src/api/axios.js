import axios from 'axios'

// Create axios instance with default config
const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000/api',
    timeout: 30000,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
})

// Request interceptor - add auth token
api.interceptors.request.use(
    (config) => {
        const token = localStorage.getItem('auth_token')
        if (token) {
            config.headers.Authorization = `Bearer ${token}`
        }

        // Add branch context if available
        const branchId = localStorage.getItem('current_branch_id')
        if (branchId) {
            config.headers['X-Branch-ID'] = branchId
        }

        return config
    },
    (error) => {
        return Promise.reject(error)
    }
)

// Response interceptor - handle errors
api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response) {
            switch (error.response.status) {
                case 401:
                    // Unauthorized - clear auth and redirect to login
                    localStorage.removeItem('auth_token')
                    localStorage.removeItem('user')
                    window.location.href = '/login'
                    break
                case 403:
                    // Forbidden - user does not have permission
                    console.error('Access forbidden:', error.response.data.message)
                    break
                case 422:
                    // Validation error - return as-is for form handling
                    break
                case 500:
                    console.error('Server error:', error.response.data.message)
                    break
            }
        } else if (error.request) {
            console.error('Network error - no response received')
        }

        return Promise.reject(error)
    }
)

export default api

// API endpoints helper functions
export const auth = {
    login: (credentials) => api.post('/login', credentials),
    logout: () => api.post('/logout'),
    me: () => api.get('/me'),
    refresh: () => api.post('/refresh')
}

export const products = {
    list: (params) => api.get('/products', { params }),
    get: (id) => api.get(`/products/${id}`),
    create: (data) => api.post('/products', data),
    update: (id, data) => api.put(`/products/${id}`, data),
    delete: (id) => api.delete(`/products/${id}`),
    search: (query) => api.get('/products/search', { params: { q: query } })
}

export const transactions = {
    list: (params) => api.get('/transactions', { params }),
    get: (id) => api.get(`/transactions/${id}`),
    create: (data) => api.post('/transactions', data),
    void: (id, reason) => api.post(`/transactions/${id}/void`, { reason })
}

export const inventory = {
    list: (params) => api.get('/inventory', { params }),
    update: (id, data) => api.put(`/inventory/${id}`, data),
    transfer: (data) => api.post('/inventory/transfer', data),
    stockIn: (data) => api.post('/inventory/stock-in', data),
    stockOut: (data) => api.post('/inventory/stock-out', data)
}

export const users = {
    list: (params) => api.get('/users', { params }),
    get: (id) => api.get(`/users/${id}`),
    create: (data) => api.post('/users', data),
    update: (id, data) => api.put(`/users/${id}`, data),
    delete: (id) => api.delete(`/users/${id}`)
}

export const branches = {
    list: () => api.get('/branches'),
    get: (id) => api.get(`/branches/${id}`)
}

export const reports = {
    sales: (params) => api.get('/reports/sales', { params }),
    inventory: (params) => api.get('/reports/inventory', { params }),
    profit: (params) => api.get('/reports/profit', { params })
}

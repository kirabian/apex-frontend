import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api, { auth as authApi } from '../api/axios'

export const useAuthStore = defineStore('auth', () => {
    // State
    const user = ref(null)
    const token = ref(localStorage.getItem('auth_token') || null)
    const isLoading = ref(false)
    const error = ref(null)

    // Getters
    const isAuthenticated = computed(() => !!token.value && !!user.value)

    const userRole = computed(() => user.value?.role || null)

    const userName = computed(() => user.value?.name || 'Guest')

    const userBranch = computed(() => user.value?.branch || null)

    const hasPermission = computed(() => (permission) => {
        if (!user.value?.permissions) return false
        return user.value.permissions.includes(permission)
    })

    const hasRole = computed(() => (role) => {
        if (!user.value?.role) return false
        if (Array.isArray(role)) {
            return role.includes(user.value.role)
        }
        return user.value.role === role
    })

    // Actions
    async function login(credentials) {
        isLoading.value = true
        error.value = null

        try {
            // Simulate API call for demo (replace with real API later)
            // const response = await authApi.login(credentials)

            // Demo mode - simulate login with mock data
            await new Promise(resolve => setTimeout(resolve, 800))

            const mockUsers = {
                'admin': {
                    id: 1,
                    name: 'Fabian Syah',
                    username: 'admin',
                    role: 'super_admin',
                    branch: { id: 1, name: 'Pusat Jakarta' },
                    permissions: ['*'] // All permissions
                },
                'kasir': {
                    id: 2,
                    name: 'Ahmad Kasir',
                    username: 'kasir',
                    role: 'inventory_kasir',
                    branch: { id: 2, name: 'Cabang Bandung' },
                    permissions: ['pos.access', 'inventory.view', 'transactions.create']
                },
                'gudang': {
                    id: 3,
                    name: 'Budi Gudang',
                    username: 'gudang',
                    role: 'gudang',
                    branch: { id: 3, name: 'Gudang Pusat' },
                    permissions: ['inventory.manage', 'inventory.transfer']
                }
            }

            const mockUser = mockUsers[credentials.username]

            if (!mockUser) {
                throw { response: { data: { message: 'Username tidak ditemukan' } } }
            }

            // Simple password check (demo)
            if (credentials.password !== 'demo123') {
                throw { response: { data: { message: 'Password salah' } } }
            }

            const mockToken = 'demo_token_' + Date.now()

            // Set auth data
            token.value = mockToken
            user.value = mockUser

            // Persist to localStorage
            localStorage.setItem('auth_token', mockToken)
            localStorage.setItem('user', JSON.stringify(mockUser))

            return { success: true, user: mockUser }
        } catch (err) {
            error.value = err.response?.data?.message || 'Login failed'
            return { success: false, error: error.value }
        } finally {
            isLoading.value = false
        }
    }

    async function logout() {
        isLoading.value = true

        try {
            // await authApi.logout()
        } catch (err) {
            console.error('Logout error:', err)
        } finally {
            // Clear auth state
            token.value = null
            user.value = null
            localStorage.removeItem('auth_token')
            localStorage.removeItem('user')
            localStorage.removeItem('current_branch_id')
            isLoading.value = false
        }
    }

    async function fetchUser() {
        if (!token.value) return null

        isLoading.value = true

        try {
            // Try to load from localStorage first (demo mode)
            const savedUser = localStorage.getItem('user')
            if (savedUser) {
                user.value = JSON.parse(savedUser)
                return user.value
            }

            // const response = await authApi.me()
            // user.value = response.data.user
            return user.value
        } catch (err) {
            // Token might be invalid
            token.value = null
            localStorage.removeItem('auth_token')
            return null
        } finally {
            isLoading.value = false
        }
    }

    function setBranch(branchId) {
        localStorage.setItem('current_branch_id', branchId)
    }

    // Initialize - try to restore user from localStorage
    function initialize() {
        const savedUser = localStorage.getItem('user')
        if (savedUser && token.value) {
            user.value = JSON.parse(savedUser)
        }
    }

    // Call initialize on store creation
    initialize()

    return {
        // State
        user,
        token,
        isLoading,
        error,
        // Getters
        isAuthenticated,
        userRole,
        userName,
        userBranch,
        hasPermission,
        hasRole,
        // Actions
        login,
        logout,
        fetchUser,
        setBranch,
        initialize
    }
})

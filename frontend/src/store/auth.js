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

    // Adapt to flexible role structure (Spatie returns roles array)
    const userRole = computed(() => {
        if (user.value?.roles && user.value.roles.length > 0) {
            return user.value.roles[0].name
        }
        return user.value?.role || null
    })

    const userName = computed(() => user.value?.full_name || user.value?.name || 'Guest')
    const userBranch = computed(() => user.value?.branch || null)

    const hasPermission = computed(() => (permission) => {
        // Implement detailed permission logic if needed
        return true;
    })

    const hasRole = computed(() => (role) => {
        if (!user.value?.roles) return false
        const userRoles = user.value.roles.map(r => r.name);
        if (Array.isArray(role)) {
            return role.some(r => userRoles.includes(r))
        }
        return userRoles.includes(role);
    })

    // Actions
    async function login(credentials) {
        isLoading.value = true
        error.value = null

        try {
            const response = await authApi.login(credentials)
            const { token: authToken, user: userData } = response.data

            if (!authToken || !userData) {
                throw new Error('Invalid response from server')
            }

            // Set auth data
            token.value = authToken
            user.value = userData

            // Persist to localStorage
            localStorage.setItem('auth_token', authToken)
            localStorage.setItem('user', JSON.stringify(userData))

            return { success: true, user: userData }
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
            await authApi.logout()
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
            const response = await authApi.me()
            user.value = response.data.user
            localStorage.setItem('user', JSON.stringify(user.value))
            return user.value
        } catch (err) {
            // Token might be invalid
            token.value = null
            user.value = null
            localStorage.removeItem('auth_token')
            localStorage.removeItem('user')
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

import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../store/auth'

// Layouts
import MainLayout from '../layouts/MainLayout.vue'

// Auth Views
import Login from '../views/auth/Login.vue'

// Lazy-loaded Views
const Dashboard = () => import('../views/admin/Dashboard.vue')
const POS = () => import('../views/cashier/POS.vue')
const Inventory = () => import('../views/inventory/Inventory.vue')
const Products = () => import('../views/products/Products.vue')
const Users = () => import('../views/users/Users.vue')
const Transactions = () => import('../views/transactions/Transactions.vue')
const Audit = () => import('../views/audit/Audit.vue')
const Reports = () => import('../views/reports/Reports.vue')

const routes = [
    // Auth Routes (Public)
    {
        path: '/login',
        name: 'Login',
        component: Login,
        meta: { requiresGuest: true }
    },

    // Main App Routes (Protected)
    {
        path: '/',
        component: MainLayout,
        meta: { requiresAuth: true },
        children: [
            {
                path: '',
                name: 'Dashboard',
                component: Dashboard,
                meta: { title: 'Dashboard', menu: 'dashboard' }
            },
            {
                path: 'pos',
                name: 'POS',
                component: POS,
                meta: {
                    title: 'Kasir (POS)',
                    menu: 'pos',
                    permissions: ['pos.access']
                }
            },
            {
                path: 'inventory',
                name: 'Inventory',
                component: Inventory,
                meta: {
                    title: 'Inventory',
                    menu: 'inventory',
                    permissions: ['inventory.view']
                }
            },
            {
                path: 'products',
                name: 'Products',
                component: Products,
                meta: {
                    title: 'Produk',
                    menu: 'products',
                    permissions: ['products.view']
                }
            },
            {
                path: 'users',
                name: 'Users',
                component: Users,
                meta: {
                    title: 'Staff & Role',
                    menu: 'users',
                    permissions: ['users.view']
                }
            },
            {
                path: 'transactions',
                name: 'Transactions',
                component: Transactions,
                meta: {
                    title: 'Transaksi',
                    menu: 'transactions',
                    permissions: ['transactions.view']
                }
            },
            {
                path: 'audit',
                name: 'Audit',
                component: Audit,
                meta: {
                    title: 'Audit',
                    menu: 'audit',
                    permissions: ['audit.view']
                }
            },
            {
                path: 'reports',
                name: 'Reports',
                component: Reports,
                meta: {
                    title: 'Laporan',
                    menu: 'reports',
                    permissions: ['reports.view']
                }
            },

            // Master Data Routes (Super Admin)
            {
                path: 'locations',
                name: 'Locations',
                component: () => import('../views/master/Locations.vue'),
                meta: {
                    title: 'Lokasi & Zona',
                    menu: 'locations',
                    permissions: ['master.view']
                }
            },
            {
                path: 'distributors',
                name: 'Distributors',
                component: () => import('../views/master/Distributors.vue'),
                meta: {
                    title: 'Distributor',
                    menu: 'distributors',
                    permissions: ['master.view']
                }
            },
            {
                path: 'channels',
                name: 'Channels',
                component: () => import('../views/master/Channels.vue'),
                meta: {
                    title: 'Toko Online',
                    menu: 'channels',
                    permissions: ['master.view']
                }
            },
            {
                path: 'categories',
                name: 'Categories',
                component: () => import('../views/master/Categories.vue'),
                meta: {
                    title: 'Kategori & Jenis',
                    menu: 'categories',
                    permissions: ['master.view']
                }
            }
        ]
    },

    // 404 Catch-all
    {
        path: '/:pathMatch(.*)*',
        redirect: '/'
    }
]

const router = createRouter({
    history: createWebHistory(),
    routes
})

// Navigation Guards
router.beforeEach(async (to, from, next) => {
    const authStore = useAuthStore()

    // Check if route requires authentication
    if (to.meta.requiresAuth) {
        if (!authStore.isAuthenticated) {
            return next({ name: 'Login', query: { redirect: to.fullPath } })
        }

        // If authenticated but user profile is missing (e.g., page refresh)
        // Try to fetch user data
        if (!authStore.user) {
            try {
                await authStore.fetchUser()
                if (!authStore.user) {
                    // If still no user after fetch attempt, force logout/login
                    return next({ name: 'Login' })
                }
            } catch (error) {
                return next({ name: 'Login' })
            }
        }
    }

    // Check if route is for guests only (like login)
    if (to.meta.requiresGuest && authStore.isAuthenticated) {
        return next({ name: 'Dashboard' })
    }

    // Check permissions if defined
    if (to.meta.permissions && to.meta.permissions.length > 0) {
        // Super admin bypass
        if (authStore.userRole === 'super_admin') {
            return next()
        }

        const userPermissions = authStore.user?.permissions || []

        // Super admin has all permissions (legacy check)
        if (!userPermissions.includes('*')) {
            const hasPermission = to.meta.permissions.some(perm =>
                userPermissions.includes(perm)
            )

            if (!hasPermission) {
                return next({ name: 'Dashboard' })
            }
        }
    }

    next()
})

// Update document title
router.afterEach((to) => {
    document.title = to.meta.title
        ? `${to.meta.title} | APEX POS`
        : 'APEX POS'
})

export default router
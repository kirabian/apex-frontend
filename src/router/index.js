import { createRouter, createWebHistory } from 'vue-router'

const routes = [
    {
        path: '/login',
        component: () => import('../views/auth/Login.vue')
    },
    {
        path: '/admin',
        component: () => import('../layouts/AdminLayout.vue'),
        children: [
            { path: 'dashboard', component: () => import('../views/admin/Dashboard.vue') },
            // Role lain tambahkan di sini
        ]
    },
    {
        path: '/cashier',
        component: () => import('../layouts/CashierLayout.vue'),
        children: [
            { path: 'pos', component: () => import('../views/cashier/POS.vue') }
        ]
    }
]

const router = createRouter({
    history: createWebHistory(),
    routes
})

export default router
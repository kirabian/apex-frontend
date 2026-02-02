<script setup>
import { computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuthStore } from "../../store/auth";
import { useThemeStore } from "../../store/theme";
import { getMenuForRole, getRoleLabel } from "../../utils/permissions";
import {
    LayoutDashboard,
    ShoppingCart,
    Package,
    Box,
    Users,
    Receipt,
    ClipboardCheck,
    BarChart3,
    Settings,
    X,
    Building2,
    MapPin,
    Truck,
    Globe,
    Tags,
    ScanBarcode,
    LineChart,
    ChevronRight,
    LogOut
} from "lucide-vue-next";

const props = defineProps({
    isMobileMenuOpen: Boolean,
});

const emit = defineEmits(['close-mobile-menu']);

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const themeStore = useThemeStore();

// Menu configuration
const menuItems = [
    { id: "dashboard", path: "/", label: "Dashboard", icon: LayoutDashboard },

    // Online Shop Modules
    { id: "online_scan", path: "/online-shop/scan", label: "Scan Pesanan", icon: ScanBarcode },
    { id: "online_analysis", path: "/online-shop/analysis", label: "Analisa Shopee", icon: LineChart },

    //   { id: "pos", path: "/pos", label: "Kasir (POS)", icon: ShoppingCart },
    { id: "inventory", path: "/inventory", label: "Inventory", icon: Box },
    { id: "products", path: "/products", label: "Produk", icon: Package },
    { id: "users", path: "/users", label: "Staff & Role", icon: Users },
    {
        id: "transactions",
        path: "/transactions",
        label: "Transaksi",
        icon: Receipt,
    },
    { id: "audit", path: "/audit", label: "Audit", icon: ClipboardCheck },
    { id: "reports", path: "/reports", label: "Laporan", icon: BarChart3 },
    // Master Data
    { id: "locations", path: "/locations", label: "Lokasi & Zona", icon: MapPin },
    {
        id: "distributors",
        path: "/distributors",
        label: "Distributor",
        icon: Truck,
    },
    { id: "online_shops", path: "/online-shops", label: "Toko Online", icon: Globe },
    {
        id: "categories",
        path: "/categories",
        label: "Kategori & Jenis",
        icon: Tags,
    },
    { id: "settings", path: "/settings", label: "Pengaturan", icon: Settings },
];

// Filter menu based on user role
const visibleMenuItems = computed(() => {
    const userRole = authStore.userRole;
    if (!userRole) return menuItems.filter((item) => item.id === "dashboard");

    if (userRole.toLowerCase().replace(/\s+/g, '_') === "super_admin") return menuItems;

    // Get allowed menus for role
    const allowedMenus = getMenuForRole(userRole);
    return menuItems.filter((item) => allowedMenus.includes(item.id));
});

// User info
const userName = computed(() => authStore.user?.name || "Guest");
const userRole = computed(() => getRoleLabel(authStore.userRole));
const userBranch = computed(() => authStore.user?.branch?.name || "-");

// Logout handler
async function handleLogout() {
    await authStore.logout();
    router.push("/login");
}

// Check active route
function isActiveRoute(path) {
    if (path === "/") return route.path === "/";
    return route.path.startsWith(path);
}
</script>

<template>
    <aside
        class="fixed inset-y-0 left-0 z-[100] w-64 bg-surface-800 border-r border-surface-700 flex flex-col transition-transform duration-300 lg:static lg:translate-x-0"
        :class="isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full'">
        <!-- Logo -->
        <div class="p-6 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div
                    class="w-10 h-10 bg-primary-600 rounded-xl shadow-lg shadow-primary-500/20 flex items-center justify-center text-white font-black text-xl">
                    A
                </div>
                <span class="text-xl font-bold tracking-tight text-text-primary">
                    APEX<span class="text-primary-500">POS</span>
                </span>
            </div>
            <!-- Close Button (Mobile Only) -->
            <button @click="emit('close-mobile-menu')" class="lg:hidden text-text-secondary hover:text-text-primary">
                <X :size="24" />
            </button>
        </div>

        <!-- Branch Indicator -->
        <div class="mx-4 px-4 py-3 bg-surface-900 rounded-xl border border-surface-700 mb-4">
            <div class="flex items-center gap-2 text-xs text-text-secondary mb-1">
                <Building2 :size="12" />
                <span>CABANG AKTIF</span>
            </div>
            <p class="text-sm font-medium text-text-primary truncate">
                {{ userBranch }}
            </p>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 px-4 space-y-1 overflow-y-auto custom-scrollbar">
            <p class="px-4 text-[10px] font-bold text-text-secondary uppercase tracking-widest mb-2">
                Menu Utama
            </p>

            <router-link v-for="item in visibleMenuItems" :key="item.id" :to="item.path"
                class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all duration-200 group border"
                :class="isActiveRoute(item.path)
                    ? 'bg-primary-500/10 text-primary-600 dark:text-primary-400 border-primary-500/20'
                    : 'text-text-secondary border-transparent hover:bg-surface-700 hover:text-text-primary'
                    ">
                <component :is="item.icon" :size="18" class="transition-colors" :class="isActiveRoute(item.path)
                    ? 'text-primary-600 dark:text-primary-400'
                    : 'text-text-secondary group-hover:text-primary-500'
                    " />
                <span>{{ item.label }}</span>
                <ChevronRight :size="14" class="ml-auto opacity-0 group-hover:opacity-100 transition-opacity" />
            </router-link>
        </nav>

        <!-- User Section -->
        <div class="p-4 bg-surface-900 border-t border-surface-700">
            <div class="flex items-center gap-3 mb-4 px-2">
                <img :src="`https://ui-avatars.com/api/?name=${encodeURIComponent(
                    userName
                )}&background=${themeStore.isDark ? '3b82f6' : '0f172a'}&color=fff`"
                    class="w-10 h-10 rounded-xl border-2 border-surface-700" :alt="userName" />
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-text-primary truncate">
                        {{ userName }}
                    </p>
                    <p class="text-[10px] text-primary-500 font-medium uppercase tracking-wide">
                        {{ userRole }}
                    </p>
                </div>
            </div>
            <button @click="handleLogout"
                class="flex items-center gap-3 w-full p-3 text-text-secondary hover:text-red-500 hover:bg-red-500/10 rounded-xl transition-all duration-300">
                <LogOut :size="18" />
                <span class="font-medium">Keluar</span>
            </button>
        </div>
    </aside>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: #1e293b;
    border-radius: 9999px;
}
</style>

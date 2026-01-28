<script setup>
import { computed, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuthStore } from "../store/auth";
import { getMenuForRole, getRoleLabel } from "../utils/permissions";
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
  Bell,
  Search,
  LogOut,
  ChevronRight,
  Menu,
  X,
  Building2,
  MapPin,
  Truck,
  Globe,
  Tags,
} from "lucide-vue-next";

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const isMobileMenuOpen = ref(false);

// Close menu on route change
watch(
  () => route.path,
  () => {
    isMobileMenuOpen.value = false;
  }
);

function toggleMobileMenu() {
  isMobileMenuOpen.value = !isMobileMenuOpen.value;
}

// Menu configuration
const menuItems = [
  { id: "dashboard", path: "/", label: "Dashboard", icon: LayoutDashboard },
  { id: "pos", path: "/pos", label: "Kasir (POS)", icon: ShoppingCart },
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
  { id: "channels", path: "/channels", label: "Toko Online", icon: Globe },
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

  // Super admin sees everything
  if (userRole === "super_admin") return menuItems;

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
  <div
    class="flex h-screen bg-surface-900 text-slate-300 font-sans antialiased overflow-hidden"
  >
    <!-- Mobile Backdrop -->
    <div
      v-if="isMobileMenuOpen"
      class="fixed inset-0 bg-black/60 z-40 lg:hidden"
      @click="isMobileMenuOpen = false"
    ></div>

    <!-- Sidebar -->
    <aside
      class="fixed inset-y-0 left-0 z-50 w-64 bg-surface-800 border-r border-surface-700 flex flex-col transition-transform duration-300 lg:static lg:translate-x-0"
      :class="isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <!-- Logo -->
      <div class="p-6 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div
            class="w-10 h-10 bg-primary-600 rounded-xl shadow-lg shadow-blue-500/20 flex items-center justify-center text-white font-black text-xl"
          >
            A
          </div>
          <span class="text-xl font-bold tracking-tight text-white">
            APEX<span class="text-primary-500">POS</span>
          </span>
        </div>
        <!-- Close Button (Mobile Only) -->
        <button
          @click="isMobileMenuOpen = false"
          class="lg:hidden text-slate-400 hover:text-white"
        >
          <X :size="24" />
        </button>
      </div>

      <!-- Branch Indicator -->
      <div
        class="mx-4 px-4 py-3 bg-slate-900/50 rounded-xl border border-surface-700 mb-4"
      >
        <div class="flex items-center gap-2 text-xs text-slate-500 mb-1">
          <Building2 :size="12" />
          <span>CABANG AKTIF</span>
        </div>
        <p class="text-sm font-medium text-white truncate">{{ userBranch }}</p>
      </div>

      <!-- Navigation -->
      <nav class="flex-1 px-4 space-y-1 overflow-y-auto custom-scrollbar">
        <p
          class="px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2"
        >
          Menu Utama
        </p>

        <router-link
          v-for="item in visibleMenuItems"
          :key="item.id"
          :to="item.path"
          class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all duration-200 group border"
          :class="
            isActiveRoute(item.path)
              ? 'bg-primary-500/10 text-primary-400 border-primary-500/20'
              : 'text-slate-400 border-transparent hover:bg-slate-700/50 hover:text-white'
          "
        >
          <component
            :is="item.icon"
            :size="18"
            class="transition-colors"
            :class="
              isActiveRoute(item.path)
                ? 'text-primary-400'
                : 'text-slate-500 group-hover:text-primary-400'
            "
          />
          <span>{{ item.label }}</span>
          <ChevronRight
            :size="14"
            class="ml-auto opacity-0 group-hover:opacity-100 transition-opacity"
          />
        </router-link>
      </nav>

      <!-- User Section -->
      <div class="p-4 bg-surface-900 border-t border-surface-700">
        <div class="flex items-center gap-3 mb-4 px-2">
          <img
            :src="`https://ui-avatars.com/api/?name=${encodeURIComponent(
              userName
            )}&background=3b82f6&color=fff`"
            class="w-10 h-10 rounded-xl border-2 border-surface-700"
            :alt="userName"
          />
          <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-white truncate">
              {{ userName }}
            </p>
            <p
              class="text-[10px] text-primary-400 font-medium uppercase tracking-wide"
            >
              {{ userRole }}
            </p>
          </div>
        </div>
        <button
          @click="handleLogout"
          class="flex items-center gap-3 w-full p-3 text-slate-400 hover:text-red-400 hover:bg-red-500/10 rounded-xl transition-all duration-300"
        >
          <LogOut :size="18" />
          <span class="font-medium">Keluar</span>
        </button>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col min-w-0 bg-surface-900">
      <!-- Header -->
      <header
        class="h-16 border-b border-surface-700 flex items-center justify-between px-4 lg:px-8 bg-surface-900 sticky top-0 z-10"
      >
        <!-- Left Side: Hamburger & Search -->
        <div class="flex items-center gap-4 flex-1">
          <button
            @click="toggleMobileMenu"
            class="lg:hidden p-2 text-slate-400 hover:text-white"
          >
            <Menu :size="24" />
          </button>

          <!-- Search -->
          <div class="relative w-full max-w-xs md:max-w-md group">
            <Search
              class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 group-focus-within:text-blue-500 transition-colors"
              :size="18"
            />
            <input
              type="text"
              placeholder="Cari transaksi, produk..."
              class="w-full bg-slate-800/40 border border-slate-700/50 rounded-full py-2 pl-10 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition-all font-sans"
            />
          </div>
        </div>

        <!-- Right Side -->
        <div class="flex items-center gap-4">
          <!-- Notifications -->
          <button
            class="relative p-2 text-slate-400 hover:text-white transition-colors rounded-lg hover:bg-slate-800/50"
          >
            <Bell :size="20" />
            <span
              class="absolute top-1.5 right-1.5 w-2 h-2 bg-blue-500 rounded-full border-2 border-[#0a0f1a]"
            ></span>
          </button>

          <div class="h-8 w-[1px] bg-slate-800"></div>

          <!-- User Avatar -->
          <div class="flex items-center gap-3">
            <div class="text-right hidden sm:block">
              <p class="text-sm font-semibold text-white leading-none">
                {{ userName }}
              </p>
              <p class="text-[10px] text-blue-400 font-medium uppercase mt-1">
                {{ userRole }}
              </p>
            </div>
            <img
              :src="`https://ui-avatars.com/api/?name=${encodeURIComponent(
                userName
              )}&background=3b82f6&color=fff`"
              class="w-9 h-9 rounded-xl border-2 border-slate-700 shadow-lg"
              :alt="userName"
            />
          </div>
        </div>
      </header>

      <!-- Page Content -->
      <div class="flex-1 overflow-y-auto p-4 lg:p-8 custom-scrollbar">
        <router-view v-slot="{ Component }">
          <transition name="fade" mode="out-in">
            <component :is="Component" />
          </transition>
        </router-view>
      </div>
    </main>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(8px);
}

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
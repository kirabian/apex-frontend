<script setup>
import { computed, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuthStore } from "../store/auth";
import { useThemeStore } from "../store/theme";
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
  Moon,
  Sun,
  Palette,
  Heart,
  Cloud,
  Sparkles,
  PawPrint,
} from "lucide-vue-next";

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const themeStore = useThemeStore();

const isMobileMenuOpen = ref(false);
const isThemeMenuOpen = ref(false);

// Close menus on route change
watch(
  () => route.path,
  () => {
    isMobileMenuOpen.value = false;
    isThemeMenuOpen.value = false;
  }
);

function toggleMobileMenu() {
  isMobileMenuOpen.value = !isMobileMenuOpen.value;
}

// Menu configuration
const menuItems = [
  { id: "dashboard", path: "/", label: "Dashboard", icon: LayoutDashboard },
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
  console.log("Current User Role:", authStore.userRole);
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
            class="w-10 h-10 bg-primary-600 rounded-xl shadow-lg shadow-primary-500/20 flex items-center justify-center text-white font-black text-xl"
          >
            A
          </div>
          <span class="text-xl font-bold tracking-tight text-text-primary">
            APEX<span class="text-primary-500">POS</span>
          </span>
        </div>
        <!-- Close Button (Mobile Only) -->
        <button
          @click="isMobileMenuOpen = false"
          class="lg:hidden text-text-secondary hover:text-text-primary"
        >
          <X :size="24" />
        </button>
      </div>

      <!-- Branch Indicator -->
      <div
        class="mx-4 px-4 py-3 bg-surface-900 rounded-xl border border-surface-700 mb-4"
      >
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
        <p
          class="px-4 text-[10px] font-bold text-text-secondary uppercase tracking-widest mb-2"
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
              ? 'bg-primary-500/10 text-primary-600 dark:text-primary-400 border-primary-500/20'
              : 'text-text-secondary border-transparent hover:bg-surface-700 hover:text-text-primary'
          "
        >
          <component
            :is="item.icon"
            :size="18"
            class="transition-colors"
            :class="
              isActiveRoute(item.path)
                ? 'text-primary-600 dark:text-primary-400'
                : 'text-text-secondary group-hover:text-primary-500'
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
            )}&background=${themeStore.isDark ? '3b82f6' : '0f172a'}&color=fff`"
            class="w-10 h-10 rounded-xl border-2 border-surface-700"
            :alt="userName"
          />
          <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-text-primary truncate">
              {{ userName }}
            </p>
            <p
              class="text-[10px] text-primary-500 font-medium uppercase tracking-wide"
            >
              {{ userRole }}
            </p>
          </div>
        </div>
        <button
          @click="handleLogout"
          class="flex items-center gap-3 w-full p-3 text-text-secondary hover:text-red-500 hover:bg-red-500/10 rounded-xl transition-all duration-300"
        >
          <LogOut :size="18" />
          <span class="font-medium">Keluar</span>
        </button>
      </div>
    </aside>

    <!-- Main Content -->
    <main
      class="flex-1 flex flex-col min-w-0 bg-surface-900 transition-colors duration-300 relative overflow-hidden"
    >
      <!-- Header -->
      <header
        class="h-16 border-b border-surface-700 flex items-center justify-between px-4 lg:px-8 bg-surface-800/50 backdrop-blur-sm z-20 transition-colors duration-300"
      >
        <!-- Left Side: Hamburger & Search -->
        <div class="flex items-center gap-4 flex-1">
          <button
            @click="toggleMobileMenu"
            class="lg:hidden p-2 text-text-secondary hover:text-text-primary"
          >
            <Menu :size="24" />
          </button>

          <!-- Search -->
          <div class="relative w-full max-w-xs md:max-w-md group">
            <Search
              class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary group-focus-within:text-primary-500 transition-colors"
              :size="18"
            />
            <input
              type="text"
              placeholder="Cari transaksi, produk..."
              class="w-full bg-surface-900 border border-surface-700 rounded-full py-2 pl-10 pr-4 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-all font-sans"
            />
          </div>
        </div>

        <!-- Right Side -->
        <div class="flex items-center gap-4">
          <!-- Theme Toggle -->
          <div class="relative">
            <button
              @click="isThemeMenuOpen = !isThemeMenuOpen"
              class="p-2 text-text-secondary hover:text-text-primary transition-colors rounded-lg hover:bg-surface-700"
            >
              <Palette :size="20" />
            </button>

            <!-- Theme Dropdown -->
            <transition
              enter-active-class="transition duration-100 ease-out"
              enter-from-class="transform scale-95 opacity-0"
              enter-to-class="transform scale-100 opacity-100"
              leave-active-class="transition duration-75 ease-in"
              leave-from-class="transform scale-100 opacity-100"
              leave-to-class="transform scale-95 opacity-0"
            >
              <div
                v-if="isThemeMenuOpen"
                class="absolute right-0 mt-2 w-72 bg-surface-800 border border-surface-700 rounded-xl shadow-xl z-50 p-4"
              >
                <div class="flex items-center justify-between mb-4">
                  <h3 class="font-semibold text-text-primary text-sm">
                    Tampilan
                  </h3>
                  <button
                    @click="themeStore.toggleDarkMode"
                    class="p-2 rounded-lg bg-surface-900 border border-surface-700 hover:bg-surface-700 transition-colors text-text-primary"
                    :title="
                      themeStore.isDark
                        ? 'Switch to Light Mode'
                        : 'Switch to Dark Mode'
                    "
                  >
                    <Sun v-if="themeStore.isDark" :size="16" />
                    <Moon v-else :size="16" />
                  </button>
                </div>

                <p
                  class="text-xs text-text-secondary mb-2 font-medium uppercase tracking-wider"
                >
                  Pilih Warna Tema
                </p>
                <div class="grid grid-cols-5 gap-2">
                  <button
                    v-for="theme in themeStore.availableThemes"
                    :key="theme.id"
                    @click="themeStore.setTheme(theme.id)"
                    class="w-9 h-9 rounded-full flex items-center justify-center border-2 transition-transform hover:scale-110 shadow-sm"
                    :class="
                      themeStore.themeName === theme.id
                        ? 'border-text-primary ring-2 ring-offset-2 ring-offset-surface-800 ring-primary-500'
                        : 'border-transparent'
                    "
                    :style="{ backgroundColor: theme.color }"
                    :title="theme.name"
                  ></button>
                </div>
              </div>
            </transition>
          </div>

          <!-- Notifications -->
          <button
            class="relative p-2 text-text-secondary hover:text-text-primary transition-colors rounded-lg hover:bg-surface-700"
          >
            <Bell :size="20" />
            <span
              class="absolute top-1.5 right-1.5 w-2 h-2 bg-primary-500 rounded-full border-2 border-surface-800"
            ></span>
          </button>

          <div class="h-8 w-[1px] bg-surface-700"></div>

          <!-- User Avatar -->
          <div class="flex items-center gap-3">
            <div class="text-right hidden sm:block">
              <p class="text-sm font-semibold text-text-primary leading-none">
                {{ userName }}
              </p>
              <p
                class="text-[10px] text-primary-500 font-medium uppercase mt-1"
              >
                {{ userRole }}
              </p>
            </div>
            <img
              :src="`https://ui-avatars.com/api/?name=${encodeURIComponent(
                userName
              )}&background=${
                themeStore.isDark ? '3b82f6' : '0f172a'
              }&color=fff`"
              class="w-9 h-9 rounded-xl border-2 border-surface-700 shadow-lg"
              :alt="userName"
            />
          </div>
        </div>
      </header>

      <!-- Aesthetic Theme Decorations (Mascot/Watermark) -->
      <div
        v-if="
          ['coquette', 'bear', 'milk', 'white'].includes(themeStore.themeName)
        "
        class="fixed -bottom-12 -right-12 z-0 pointer-events-none opacity-[0.07] select-none transform -rotate-12 text-primary-500"
      >
        <Heart
          v-if="themeStore.themeName === 'coquette'"
          :size="500"
          stroke-width="1.5"
        />
        <PawPrint
          v-if="themeStore.themeName === 'bear'"
          :size="500"
          stroke-width="1.5"
        />
        <Cloud
          v-if="themeStore.themeName === 'milk'"
          :size="500"
          stroke-width="1.5"
        />
        <Sparkles
          v-if="themeStore.themeName === 'white'"
          :size="500"
          stroke-width="1.5"
        />
      </div>

      <!-- Page Content -->
      <div
        class="flex-1 overflow-y-auto custom-scrollbar p-4 md:p-8 pt-6 relative z-10"
      >
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
<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import { useInventoryStore } from "../../store/inventory";
import api from "../../api/axios";
import { formatCurrency, formatNumber } from "../../utils/formatters";
const router = useRouter();
import {
  Search,
  Package,
  AlertTriangle,
  ArrowDownUp,
  Plus,
  Filter,
  Download,
  RefreshCw,
  Eye,
  Edit,
  ChevronDown,
  TrendingUp,
  TrendingDown,
  Box,
  Smartphone
} from "lucide-vue-next";

const inventoryStore = useInventoryStore();

// Local state
const searchQuery = ref("");
const selectedCategory = ref("");
const showStockFilter = ref("all");

onMounted(() => {
  inventoryStore.fetchProducts();
});

// Filtered items (Granular)
const filteredProducts = computed(() => {
  let items = inventoryStore.products; // These are now ProductDetail objects

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    items = items.filter(
      (item) =>
        item.imei?.toLowerCase().includes(query) ||
        item.product?.name?.toLowerCase().includes(query) ||
        item.product?.sku?.toLowerCase().includes(query) ||
        item.product?.brand?.toLowerCase().includes(query)
    );
  }

  if (selectedCategory.value) {
    items = items.filter((item) => item.product?.category === selectedCategory.value);
  }

  // Stock filter logic removed as we list individual units now
  // if (showStockFilter.value === "low") ...

  return items;
});

// Stats
const stats = computed(() => [
  {
    label: "Total Produk",
    value: inventoryStore.totalProducts,
    icon: Package,
    color: "blue",
  },
  {
    label: "Nilai Inventori",
    value: formatCurrency(inventoryStore.totalValue),
    icon: TrendingUp,
    color: "emerald",
  },
  {
    label: "Stok Menipis",
    value: inventoryStore.lowStockProducts.length,
    icon: AlertTriangle,
    color: "amber",
  },
  {
    label: "Habis",
    value: inventoryStore.outOfStockProducts.length,
    icon: TrendingDown,
    color: "red",
  },
]);

import { branches as branchesApi } from "../../api/axios";
import { useAuthStore } from "../../store/auth";
import { useToast } from "../../composables/useToast";

const toast = useToast();
const authStore = useAuthStore();
const currentBranch = ref(null);
const isTogglingReturn = ref(false);

async function fetchCurrentBranch() {
  if (authStore.userBranch?.id) {
    try {
      const response = await branchesApi.show(authStore.userBranch.id);
      currentBranch.value = response.data.data || response.data;
    } catch (e) {
      console.error("Gagal load info branch", e);
    }
  }
}

async function toggleReturn() {
  if (!currentBranch.value || isTogglingReturn.value) return;

  isTogglingReturn.value = true;
  try {
    const response = await api.post(`/branches/${currentBranch.value.id}/toggle-return`);

    // Update the property on the current branch object ensuring reactivity
    const updatedBranch = response.data.data;
    if (currentBranch.value.id === updatedBranch.id) {
      currentBranch.value.can_accept_returns = updatedBranch.can_accept_returns;
    }

    // Also update in the list to keep consistency
    const branchInList = branches.value.find(b => b.id === updatedBranch.id);
    if (branchInList) {
      branchInList.can_accept_returns = updatedBranch.can_accept_returns;
    }

    const status = updatedBranch.can_accept_returns ? 'ON' : 'OFF';
    toast.success(`Terima Retur berhasil diubah ke ${status}`);
  } catch (e) {
    toast.error("Gagal mengubah status retur");
  } finally {
    isTogglingReturn.value = false;
  }
}

onMounted(() => {
  inventoryStore.fetchProducts();
  fetchCurrentBranch();
});

// Stats
// ... (existing code)

const categories = computed(() => inventoryStore.categories);

const branches = ref([]);

// Permission check for toggling return
const canToggleReturn = computed(() => {
  // Allow: ONLY SUPER_ADMIN
  const allowedRoles = ['super_admin'];
  return authStore.hasRole(allowedRoles);
});

async function fetchBranches() {
  if (canToggleReturn.value) {
    try {
      const response = await branchesApi.list();
      branches.value = response.data.data || response.data;

      // If no current branch, default to first one to show something
      if (!currentBranch.value && branches.value.length > 0) {
        currentBranch.value = branches.value[0];
      }
    } catch (e) {
      console.error("Gagal load branches", e);
    }
  }
}

onMounted(() => {
  inventoryStore.fetchProducts();
  fetchCurrentBranch();
  fetchBranches(); // Load branches if super admin
});

function getStockStatus(product) {
  if (product.stock === 0)
    return { label: "Habis", class: "bg-red-500/20 text-red-400" };
  if (product.stock <= product.minStock)
    return { label: "Menipis", class: "bg-amber-500/20 text-amber-400" };
  return { label: "Tersedia", class: "bg-emerald-500/20 text-emerald-400" };
}
</script>

<template>
  <div class="space-y-6 animate-in">
    <!-- Header -->
    <div class="flex justify-between items-end">
      <div>
        <h1 class="text-2xl font-bold text-text-primary tracking-tight">Inventory</h1>
        <p class="text-text-secondary mt-1">Kelola stok produk di semua cabang</p>
      </div>
      <div class="flex gap-3 items-center">
        <!-- Return Toggle (Only for users with branch AND appropriate role) -->
        <div v-if="currentBranch && canToggleReturn"
          class="flex items-center gap-2 bg-surface-800 p-2 rounded-xl border border-surface-700 mr-2">
          <span class="text-sm font-medium text-text-secondary pl-2">Terima Retur</span>
          <button @click="toggleReturn"
            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-surface-900"
            :class="currentBranch.can_accept_returns ? 'bg-emerald-500' : 'bg-surface-600'"
            :disabled="isTogglingReturn">
            <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
              :class="currentBranch.can_accept_returns ? 'translate-x-6' : 'translate-x-1'" />
          </button>
        </div>

        <button class="btn btn-secondary" @click="router.push({ name: 'StockOut' })">
          <ArrowDownUp :size="16" />
          Keluar Stok
        </button>
        <button class="btn btn-primary" @click="router.push({ name: 'StockIn' })">
          <Plus :size="16" />
          Tambah Stok Masuk
        </button>
      </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      <div v-for="(stat, index) in stats" :key="index" class="card flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center" :class="{
          'bg-blue-600': stat.color === 'blue',
          'bg-emerald-600': stat.color === 'emerald',
          'bg-amber-600': stat.color === 'amber',
          'bg-red-600': stat.color === 'red',
        }">
          <component :is="stat.icon" :size="20" class="text-white" />
        </div>
        <div>
          <p class="text-text-secondary text-sm">{{ stat.label }}</p>
          <p class="text-xl font-bold text-text-primary">{{ stat.value }}</p>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="card">
      <div class="flex flex-wrap items-center gap-4">
        <!-- Search -->
        <div class="relative flex-1 min-w-[250px]">
          <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" :size="18" />
          <input v-model="searchQuery" type="text" placeholder="Cari produk, SKU, atau brand..." class="input pl-10" />
        </div>

        <!-- Category Filter -->
        <select v-model="selectedCategory" class="input w-48">
          <option value="">Semua Kategori</option>
          <option v-for="cat in categories" :key="cat.id" :value="cat.name">
            {{ cat.name }}
          </option>
        </select>

        <!-- Stock Filter -->
        <div class="flex rounded-xl bg-surface-800 p-1">
          <button v-for="filter in [
            { id: 'all', label: 'Semua' },
            { id: 'low', label: 'Menipis' },
            { id: 'out', label: 'Habis' },
          ]" :key="filter.id" @click="showStockFilter = filter.id"
            class="px-4 py-2 text-sm font-medium rounded-lg transition-colors" :class="showStockFilter === filter.id
              ? 'bg-blue-600 text-white'
              : 'text-text-secondary hover:text-text-primary'
              ">
            {{ filter.label }}
          </button>
        </div>

        <!-- Export -->
        <button class="btn btn-secondary">
          <Download :size="16" />
          Export
        </button>
      </div>
    </div>

    <!-- Table -->
    <div class="card p-0">
      <div class="table-container">
        <table class="table">
          <thead>
            <tr>
              <th>SKU</th>
              <th>Produk</th>
              <th>Detail (IMEI)</th>
              <th>Lokasi</th>
              <th>Admin</th>
              <th>Distributor</th>
              <th>Harga Jual</th>
              <th>Status</th>
              <th class="text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="inventoryStore.isLoading">
              <td colspan="8" class="text-center py-12">
                <RefreshCw :size="24" class="animate-spin mx-auto text-blue-400 mb-2" />
                <p class="text-text-secondary">Memuat data...</p>
              </td>
            </tr>
            <tr v-else-if="filteredProducts.length === 0">
              <td colspan="8" class="text-center py-12">
                <Box :size="48" class="mx-auto text-text-secondary mb-2" />
                <p class="text-text-secondary">Tidak ada data ditemukan</p>
              </td>
            </tr>
            <tr v-else v-for="item in filteredProducts" :key="item.id">
              <td class="font-mono text-sm text-text-secondary">
                {{ item.product?.sku || '-' }}
              </td>
              <td>
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 bg-surface-700 rounded-lg flex items-center justify-center">
                    <Smartphone :size="16" class="text-text-secondary" />
                  </div>
                  <div>
                    <p class="font-medium text-text-primary">{{ item.product?.name }}</p>
                    <p class="text-xs text-text-secondary">{{ item.product?.brand }}</p>
                  </div>
                </div>
              </td>
              <td>
                <div class="flex flex-col">
                  <span class="font-mono text-xs bg-surface-700 px-2 py-1 rounded w-fit">{{ item.imei }}</span>
                  <span class="text-[10px] text-text-secondary mt-1" v-if="item.ram || item.storage">
                    {{ item.ram }} / {{ item.storage }}
                  </span>
                </div>
              </td>
              <td class="text-sm text-text-secondary">
                <div v-if="item.placement_name" class="font-medium text-text-primary">
                  {{ item.placement_name }}
                  <span class="text-[10px] text-text-secondary block capitalize">
                    {{ item.placement_type?.replace('_', ' ') }}
                  </span>
                </div>
                <div v-else>
                  <span class="capitalize">{{ item.placement_type?.replace('_', ' ') }}</span>
                  <span v-if="item.placement_id" class="text-xs ml-1 text-surface-400">#{{ item.placement_id }}</span>
                </div>
              </td>
              <td class="text-sm text-text-secondary">
                {{ item.user?.username || item.user?.name || '-' }}
              </td>
              <td class="text-sm text-text-secondary">
                {{ item.distributor?.name || '-' }}
              </td>
              <td class="text-text-primary font-medium">
                {{ formatCurrency(item.selling_price) }}
              </td>
              <td>
                <span class="badge"
                  :class="item.status === 'available' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-surface-600 text-surface-300'">
                  {{ item.status }}
                </span>
              </td>
              <td>
                <div class="flex items-center justify-center gap-2">
                  <button class="p-2 hover:bg-surface-700 rounded-lg transition-colors">
                    <Eye :size="16" class="text-text-secondary" />
                  </button>
                  <button class="p-2 hover:bg-surface-700 rounded-lg transition-colors">
                    <Edit :size="16" class="text-text-secondary" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<style scoped>
.animate-in {
  animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>

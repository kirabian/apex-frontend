<script setup>
import { ref, onMounted, computed } from "vue";
import { useAuthStore } from "../../store/auth";
import { formatCurrency, formatNumber } from "../../utils/formatters";
import {
  LayoutDashboard,
  TrendingUp,
  TrendingDown,
  DollarSign,
  ShoppingCart,
  Package,
  Users,
  AlertTriangle,
  ArrowUpRight,
  Activity,
  RefreshCw,
} from "lucide-vue-next";

const authStore = useAuthStore();

// Stats data
const stats = ref([
  {
    id: 1,
    label: "Pendapatan Hari Ini",
    value: 85750000,
    change: 12.5,
    trend: "up",
    icon: DollarSign,
    color: "blue",
  },
  {
    id: 2,
    label: "Total Transaksi",
    value: 342,
    change: 8.2,
    trend: "up",
    icon: ShoppingCart,
    color: "emerald",
  },
  {
    id: 3,
    label: "Produk Terjual",
    value: 1247,
    change: -3.1,
    trend: "down",
    icon: Package,
    color: "violet",
  },
  {
    id: 4,
    label: "Pelanggan Baru",
    value: 28,
    change: 15.8,
    trend: "up",
    icon: Users,
    color: "amber",
  },
]);

// Top products
const topProducts = ref([
  { id: 1, name: "iPhone 15 Pro Max", sold: 45, revenue: 989955000 },
  { id: 2, name: "Samsung S24 Ultra", sold: 38, revenue: 759962000 },
  { id: 3, name: "MacBook Air M3", sold: 22, revenue: 384978000 },
  { id: 4, name: 'iPad Pro 12.9"', sold: 18, revenue: 305982000 },
  { id: 5, name: "AirPods Pro 2", sold: 67, revenue: 267933000 },
]);

// Low stock alerts
const lowStockItems = ref([
  { id: 1, name: "Google Pixel 8 Pro", stock: 3, minStock: 5 },
  { id: 2, name: "Sony WH-1000XM5", stock: 4, minStock: 8 },
  { id: 3, name: "Samsung Galaxy Z Fold 5", stock: 2, minStock: 5 },
]);

// Recent transactions
const recentTransactions = ref([
  {
    id: "TRX-001",
    customer: "Ahmad Rizki",
    total: 21999000,
    time: "2 menit lalu",
    status: "success",
  },
  {
    id: "TRX-002",
    customer: "Budi Santoso",
    total: 3999000,
    time: "15 menit lalu",
    status: "success",
  },
  {
    id: "TRX-003",
    customer: "Citra Dewi",
    total: 18499000,
    time: "32 menit lalu",
    status: "success",
  },
  {
    id: "TRX-004",
    customer: "Dimas Pratama",
    total: 5499000,
    time: "1 jam lalu",
    status: "pending",
  },
]);

// Branch performance
const branchPerformance = ref([
  { name: "Pusat Jakarta", revenue: 125000000, target: 150000000 },
  { name: "Cabang Bandung", revenue: 89000000, target: 100000000 },
  { name: "Cabang Surabaya", revenue: 78000000, target: 80000000 },
  { name: "Cabang Medan", revenue: 45000000, target: 60000000 },
]);

const isLoading = ref(false);

function refreshData() {
  isLoading.value = true;
  setTimeout(() => {
    isLoading.value = false;
  }, 1000);
}

const getColorClasses = (color) => {
  const colors = {
    blue: { bg: "bg-blue-600/20", text: "text-blue-400", icon: "bg-blue-600" },
    emerald: {
      bg: "bg-emerald-600/20",
      text: "text-emerald-400",
      icon: "bg-emerald-600",
    },
    violet: {
      bg: "bg-violet-600/20",
      text: "text-violet-400",
      icon: "bg-violet-600",
    },
    amber: {
      bg: "bg-amber-600/20",
      text: "text-amber-400",
      icon: "bg-amber-600",
    },
  };
  return colors[color] || colors.blue;
};
</script>

<template>
  <div class="space-y-6 animate-in">
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between md:items-end gap-4">
      <div>
        <h1 class="text-2xl font-bold text-white tracking-tight">
          Executive Overview
        </h1>
        <p class="text-slate-500 mt-1">
          Real-time data dari 60+ cabang •
          <span class="text-emerald-400">● Online</span>
        </p>
      </div>
      <button
        @click="refreshData"
        class="btn btn-secondary w-full md:w-auto"
        :disabled="isLoading"
      >
        <RefreshCw :size="16" :class="{ 'animate-spin': isLoading }" />
        Refresh Data
      </button>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      <div v-for="stat in stats" :key="stat.id" class="stat-card">
        <div class="flex items-start justify-between mb-4">
          <div class="stat-icon" :class="getColorClasses(stat.color).icon">
            <component :is="stat.icon" :size="20" class="text-white" />
          </div>
          <div
            class="flex items-center gap-1 text-xs font-semibold px-2 py-1 rounded-full"
            :class="
              stat.trend === 'up'
                ? 'bg-emerald-500/20 text-emerald-400'
                : 'bg-red-500/20 text-red-400'
            "
          >
            <TrendingUp v-if="stat.trend === 'up'" :size="12" />
            <TrendingDown v-else :size="12" />
            {{ Math.abs(stat.change) }}%
          </div>
        </div>
        <p class="text-slate-500 text-sm font-medium">{{ stat.label }}</p>
        <p class="text-2xl font-bold text-white mt-1">
          {{
            stat.label.includes("Pendapatan")
              ? formatCurrency(stat.value)
              : formatNumber(stat.value)
          }}
        </p>
      </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Top Products -->
      <div class="lg:col-span-2 card">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-lg font-semibold text-white">Produk Terlaris</h2>
          <button
            class="text-sm text-blue-400 hover:text-blue-300 transition-colors"
          >
            Lihat Semua
          </button>
        </div>
        <div class="space-y-4">
          <div
            v-for="(product, index) in topProducts"
            :key="product.id"
            class="flex items-center gap-4 p-3 rounded-xl hover:bg-slate-700 transition-colors"
          >
            <div
              class="w-8 h-8 rounded-lg bg-slate-700 flex items-center justify-center text-sm font-bold text-slate-400 shrink-0"
            >
              {{ index + 1 }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-white font-medium truncate">{{ product.name }}</p>
              <p class="text-sm text-slate-500">{{ product.sold }} terjual</p>
            </div>
            <div class="text-right">
              <p class="text-white font-semibold">
                {{ formatCurrency(product.revenue) }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Low Stock Alert -->
      <div class="card border-amber-500/30">
        <div class="flex items-center gap-2 mb-6">
          <AlertTriangle :size="18" class="text-amber-500" />
          <h2 class="text-lg font-semibold text-white">Stok Menipis</h2>
        </div>
        <div class="space-y-3">
          <div
            v-for="item in lowStockItems"
            :key="item.id"
            class="p-3 bg-amber-500/10 border border-amber-500/20 rounded-xl"
          >
            <p class="text-white font-medium text-sm">{{ item.name }}</p>
            <div class="flex items-center justify-between mt-2">
              <span class="text-amber-500 text-xs font-semibold">
                Stok: {{ item.stock }} / {{ item.minStock }}
              </span>
              <button class="text-xs text-blue-400 hover:text-blue-300">
                Restock
              </button>
            </div>
          </div>
        </div>
        <button
          class="w-full mt-4 py-2 text-sm text-amber-500 hover:text-amber-400 border border-amber-500/30 rounded-xl hover:bg-amber-500/10 transition-colors"
        >
          Lihat {{ lowStockItems.length }} Item Lainnya
        </button>
      </div>
    </div>

    <!-- Bottom Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Recent Transactions -->
      <div class="card">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-lg font-semibold text-white">Transaksi Terakhir</h2>
          <button
            class="text-sm text-blue-400 hover:text-blue-300 transition-colors flex items-center gap-1"
          >
            Lihat Semua
            <ArrowUpRight :size="14" />
          </button>
        </div>
        <div class="space-y-3">
          <div
            v-for="trx in recentTransactions"
            :key="trx.id"
            class="flex items-center justify-between p-3 rounded-xl hover:bg-slate-700 transition-colors"
          >
            <div class="flex items-center gap-3">
              <div
                class="w-10 h-10 rounded-xl bg-slate-700 flex items-center justify-center"
              >
                <Activity :size="18" class="text-slate-400" />
              </div>
              <div>
                <p class="text-white font-medium text-sm">{{ trx.customer }}</p>
                <p class="text-xs text-slate-500">
                  {{ trx.id }} • {{ trx.time }}
                </p>
              </div>
            </div>
            <div class="text-right">
              <p class="text-white font-semibold text-sm">
                {{ formatCurrency(trx.total) }}
              </p>
              <span
                class="text-[10px] font-medium px-2 py-0.5 rounded-full"
                :class="
                  trx.status === 'success'
                    ? 'bg-emerald-500/20 text-emerald-400'
                    : 'bg-amber-500/20 text-amber-400'
                "
              >
                {{ trx.status === "success" ? "Sukses" : "Pending" }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Branch Performance -->
      <div class="card">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-lg font-semibold text-white">Performa Cabang</h2>
          <span class="text-xs text-slate-500">Target Bulanan</span>
        </div>
        <div class="space-y-4">
          <div v-for="branch in branchPerformance" :key="branch.name">
            <div class="flex items-center justify-between mb-2">
              <span class="text-sm text-white">{{ branch.name }}</span>
              <span class="text-xs text-slate-400">
                {{ formatCurrency(branch.revenue) }} /
                {{ formatCurrency(branch.target) }}
              </span>
            </div>
            <div class="h-2 bg-slate-800 rounded-full overflow-hidden">
              <div
                class="h-full rounded-full transition-all duration-500"
                :class="
                  branch.revenue / branch.target >= 0.8
                    ? 'bg-emerald-500'
                    : branch.revenue / branch.target >= 0.5
                    ? 'bg-amber-500'
                    : 'bg-red-500'
                "
                :style="{
                  width: `${Math.min(
                    (branch.revenue / branch.target) * 100,
                    100
                  )}%`,
                }"
              ></div>
            </div>
          </div>
        </div>
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
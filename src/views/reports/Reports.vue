<script setup>
import { ref, computed } from "vue";
import { formatCurrency, formatNumber } from "../../utils/formatters";
import {
  BarChart3,
  TrendingUp,
  TrendingDown,
  DollarSign,
  ShoppingCart,
  Package,
  Users,
  Calendar,
  Download,
  Filter,
  Building2,
  ArrowUpRight,
  ArrowDownRight,
} from "lucide-vue-next";

// Date range
const dateRange = ref("week");
const dateRanges = [
  { id: "today", label: "Hari Ini" },
  { id: "week", label: "Minggu Ini" },
  { id: "month", label: "Bulan Ini" },
  { id: "quarter", label: "Kuartal" },
  { id: "year", label: "Tahun Ini" },
];

// Selected branch
const selectedBranch = ref("");
const branches = [
  "Pusat Jakarta",
  "Cabang Bandung",
  "Cabang Surabaya",
  "Cabang Medan",
  "Gudang Pusat",
];

// Summary stats
const summaryStats = computed(() => [
  {
    label: "Total Pendapatan",
    value: formatCurrency(1250000000),
    change: 12.5,
    trend: "up",
    icon: DollarSign,
    color: "blue",
  },
  {
    label: "Total Transaksi",
    value: "2,847",
    change: 8.3,
    trend: "up",
    icon: ShoppingCart,
    color: "emerald",
  },
  {
    label: "Produk Terjual",
    value: "15,492",
    change: -2.1,
    trend: "down",
    icon: Package,
    color: "violet",
  },
  {
    label: "Customer Baru",
    value: "342",
    change: 15.7,
    trend: "up",
    icon: Users,
    color: "amber",
  },
]);

// Branch performance data
const branchData = ref([
  {
    name: "Pusat Jakarta",
    revenue: 450000000,
    transactions: 1024,
    growth: 15.2,
  },
  {
    name: "Cabang Bandung",
    revenue: 320000000,
    transactions: 756,
    growth: 8.4,
  },
  {
    name: "Cabang Surabaya",
    revenue: 280000000,
    transactions: 612,
    growth: 12.1,
  },
  { name: "Cabang Medan", revenue: 200000000, transactions: 455, growth: -3.2 },
]);

// Top products
const topProducts = ref([
  {
    rank: 1,
    name: "iPhone 15 Pro Max",
    sold: 145,
    revenue: 3189855000,
    share: 25.5,
  },
  {
    rank: 2,
    name: "Samsung S24 Ultra",
    sold: 98,
    revenue: 1959804000,
    share: 15.7,
  },
  {
    rank: 3,
    name: "MacBook Air M3",
    sold: 67,
    revenue: 1172433000,
    share: 9.4,
  },
  { rank: 4, name: 'iPad Pro 12.9"', sold: 54, revenue: 917946000, share: 7.3 },
  { rank: 5, name: "AirPods Pro 2", sold: 189, revenue: 755811000, share: 6.0 },
]);

// Sales by category
const categoryData = ref([
  { name: "Smartphone", value: 65, color: "bg-blue-500" },
  { name: "Laptop", value: 20, color: "bg-emerald-500" },
  { name: "Audio", value: 8, color: "bg-violet-500" },
  { name: "Wearable", value: 5, color: "bg-amber-500" },
  { name: "Tablet", value: 2, color: "bg-red-500" },
]);

// Daily revenue (mock chart data)
const dailyRevenue = ref([
  { day: "Sen", value: 180000000 },
  { day: "Sel", value: 220000000 },
  { day: "Rab", value: 195000000 },
  { day: "Kam", value: 240000000 },
  { day: "Jum", value: 280000000 },
  { day: "Sab", value: 320000000 },
  { day: "Min", value: 210000000 },
]);

const maxDailyRevenue = computed(() =>
  Math.max(...dailyRevenue.value.map((d) => d.value))
);
</script>

<template>
  <div class="space-y-6 animate-in">
    <!-- Header -->
    <div class="flex justify-between items-end">
      <div>
        <h1 class="text-2xl font-bold text-white tracking-tight">
          Laporan & Analytics
        </h1>
        <p class="text-slate-500 mt-1">
          Analisis performa penjualan dan operasional
        </p>
      </div>
      <div class="flex gap-3">
        <select v-model="selectedBranch" class="input w-40">
          <option value="">Semua Cabang</option>
          <option v-for="branch in branches" :key="branch" :value="branch">
            {{ branch }}
          </option>
        </select>
        <button class="btn btn-secondary">
          <Download :size="16" />
          Export PDF
        </button>
      </div>
    </div>

    <!-- Date Range Tabs -->
    <div class="flex gap-2 bg-slate-800/50 p-1 rounded-xl w-fit">
      <button
        v-for="range in dateRanges"
        :key="range.id"
        @click="dateRange = range.id"
        class="px-4 py-2 text-sm font-medium rounded-lg transition-colors"
        :class="
          dateRange === range.id
            ? 'bg-blue-600 text-white'
            : 'text-slate-400 hover:text-white'
        "
      >
        {{ range.label }}
      </button>
    </div>

    <!-- Summary Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      <div v-for="stat in summaryStats" :key="stat.label" class="stat-card">
        <div class="flex items-start justify-between mb-4">
          <div
            class="stat-icon"
            :class="{
              'bg-blue-600': stat.color === 'blue',
              'bg-emerald-600': stat.color === 'emerald',
              'bg-violet-600': stat.color === 'violet',
              'bg-amber-600': stat.color === 'amber',
            }"
          >
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
            <ArrowUpRight v-if="stat.trend === 'up'" :size="12" />
            <ArrowDownRight v-else :size="12" />
            {{ Math.abs(stat.change) }}%
          </div>
        </div>
        <p class="text-slate-500 text-sm font-medium">{{ stat.label }}</p>
        <p class="text-2xl font-bold text-white mt-1">{{ stat.value }}</p>
      </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Revenue Chart -->
      <div class="lg:col-span-2 card">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-lg font-semibold text-white">Pendapatan Harian</h2>
          <span class="text-sm text-slate-500">7 hari terakhir</span>
        </div>

        <!-- Simple bar chart -->
        <div class="flex items-end justify-between h-48 gap-3">
          <div
            v-for="(day, index) in dailyRevenue"
            :key="index"
            class="flex-1 flex flex-col items-center gap-2"
          >
            <div
              class="w-full bg-gradient-to-t from-blue-600 to-blue-400 rounded-t-lg transition-all duration-300 hover:from-blue-500 hover:to-blue-300"
              :style="{ height: `${(day.value / maxDailyRevenue) * 100}%` }"
            ></div>
            <span class="text-xs text-slate-500">{{ day.day }}</span>
          </div>
        </div>
      </div>

      <!-- Category Distribution -->
      <div class="card">
        <h2 class="text-lg font-semibold text-white mb-6">
          Penjualan per Kategori
        </h2>
        <div class="space-y-4">
          <div v-for="cat in categoryData" :key="cat.name">
            <div class="flex items-center justify-between mb-1">
              <span class="text-sm text-slate-400">{{ cat.name }}</span>
              <span class="text-sm text-white font-medium"
                >{{ cat.value }}%</span
              >
            </div>
            <div class="h-2 bg-slate-800 rounded-full overflow-hidden">
              <div
                class="h-full rounded-full transition-all"
                :class="cat.color"
                :style="{ width: `${cat.value}%` }"
              ></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Bottom Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Branch Performance -->
      <div class="card">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-lg font-semibold text-white">Performa Cabang</h2>
          <button class="text-sm text-blue-400 hover:text-blue-300">
            Lihat Semua
          </button>
        </div>
        <div class="space-y-4">
          <div
            v-for="branch in branchData"
            :key="branch.name"
            class="flex items-center justify-between p-3 rounded-xl hover:bg-slate-800/50 transition-colors"
          >
            <div class="flex items-center gap-3">
              <div
                class="w-10 h-10 bg-slate-700 rounded-lg flex items-center justify-center"
              >
                <Building2 :size="18" class="text-slate-400" />
              </div>
              <div>
                <p class="text-white font-medium">{{ branch.name }}</p>
                <p class="text-xs text-slate-500">
                  {{ branch.transactions }} transaksi
                </p>
              </div>
            </div>
            <div class="text-right">
              <p class="text-white font-semibold">
                {{ formatCurrency(branch.revenue) }}
              </p>
              <p
                class="text-xs font-medium"
                :class="
                  branch.growth >= 0 ? 'text-emerald-400' : 'text-red-400'
                "
              >
                {{ branch.growth >= 0 ? "+" : "" }}{{ branch.growth }}%
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Top Products -->
      <div class="card">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-lg font-semibold text-white">Produk Terlaris</h2>
          <button class="text-sm text-blue-400 hover:text-blue-300">
            Lihat Semua
          </button>
        </div>
        <div class="space-y-3">
          <div
            v-for="product in topProducts"
            :key="product.rank"
            class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-800/50 transition-colors"
          >
            <div
              class="w-8 h-8 rounded-lg flex items-center justify-center text-sm font-bold"
              :class="
                product.rank <= 3
                  ? 'bg-amber-500/20 text-amber-400'
                  : 'bg-slate-700 text-slate-400'
              "
            >
              {{ product.rank }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-white font-medium truncate">{{ product.name }}</p>
              <p class="text-xs text-slate-500">{{ product.sold }} terjual</p>
            </div>
            <div class="text-right">
              <p class="text-white font-semibold text-sm">
                {{ formatCurrency(product.revenue) }}
              </p>
              <p class="text-xs text-slate-500">{{ product.share }}%</p>
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

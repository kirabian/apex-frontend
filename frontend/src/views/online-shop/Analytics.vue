<script setup>
import { ref, onMounted } from 'vue'
import { onlineShop } from '../../api/axios'
import { useToast } from '../../composables/useToast'
import { BarChart3, TrendingUp, Package, ShoppingBag } from 'lucide-vue-next'

const stats = ref(null)
const isLoading = ref(true)
const toast = useToast()

onMounted(async () => {
    try {
        const response = await onlineShop.analysis()
        stats.value = response.data
    } catch (error) {
        toast.error('Gagal memuat data analisa')
    } finally {
        isLoading.value = false
    }
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-white flex items-center gap-2">
          <BarChart3 class="text-primary-500" />
          Analisa Shopee
        </h1>
        <p class="text-slate-400">Overview performa toko online</p>
      </div>
    </div>

    <div v-if="isLoading" class="flex justify-center p-12">
        <span class="loading loading-spinner text-primary-500"></span>
    </div>

    <div v-else-if="stats" class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Cards -->
        <div class="card p-6 bg-gradient-to-br from-surface-800 to-surface-700">
            <div class="flex items-center gap-4">
                <div class="p-3 bg-blue-500/20 rounded-xl text-blue-500">
                    <ShoppingBag :size="24" />
                </div>
                <div>
                    <p class="text-slate-400 text-sm">Pesanan Hari Ini</p>
                    <p class="text-2xl font-bold text-white">{{ stats.orders_today }}</p>
                </div>
            </div>
        </div>

        <div class="card p-6 bg-gradient-to-br from-surface-800 to-surface-700">
            <div class="flex items-center gap-4">
                <div class="p-3 bg-emerald-500/20 rounded-xl text-emerald-500">
                    <TrendingUp :size="24" />
                </div>
                <div>
                    <p class="text-slate-400 text-sm">Total Bulan Ini</p>
                    <p class="text-2xl font-bold text-white">{{ stats.orders_this_month }}</p>
                </div>
            </div>
        </div>
        
        <!-- Platform Dist -->
        <div class="card p-6 col-span-1 md:col-span-3">
            <h3 class="text-lg font-bold text-white mb-4">Distribusi Platform</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div v-for="p in stats.platform_distribution" :key="p.platform" class="bg-surface-900 p-4 rounded-xl border border-surface-700">
                    <p class="text-slate-400 capitalize">{{ p.platform }}</p>
                    <p class="text-xl font-bold text-white">{{ p.total }}</p>
                </div>
            </div>
        </div>
    </div>
  </div>
</template>

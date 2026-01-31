<script setup>
import { ref, onMounted, nextTick } from 'vue'
import { onlineShop } from '../../api/axios'
import { useToast } from '../../composables/useToast'
import { ScanBarcode, Package, Search, CheckCircle, XCircle, ArrowRight } from 'lucide-vue-next'

const toast = useToast()
const scanInput = ref(null)
const scanCode = ref('')
const isLoading = ref(false)
const scanResult = ref(null)

// Focus input on mount
onMounted(() => {
    focusInput()
})

const focusInput = () => {
    nextTick(() => {
        if (scanInput.value) scanInput.value.focus()
    })
}

const handleScan = async () => {
    if (!scanCode.value) return

    isLoading.value = true
    try {
        const response = await onlineShop.scan(scanCode.value)
        scanResult.value = response.data
        toast.success(`Ditemukan: ${response.data.type === 'order' ? 'Pesanan' : 'Produk'}`)
    } catch (error) {
        console.error(error)
        toast.error('Data tidak ditemukan atau error server')
        scanResult.value = null
    } finally {
        isLoading.value = false
        scanCode.value = '' // Clear input for next scan
        focusInput() // Refocus
    }
}

const updateStatus = async (status) => {
    if (!scanResult.value || scanResult.value.type !== 'order') return
    
    try {
        const order = scanResult.value.data
        await onlineShop.updateOrder({
            order_number: order.order_number,
            status: status,
            platform: order.platform
        })
        
        toast.success(`Status updated to ${status}`)
        // Update local state
        scanResult.value.data.status = status
    } catch (error) {
        toast.error('Gagal update status')
    }
}

const formatCurrency = (val) => {
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0
    }).format(val || 0)
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-white flex items-center gap-2">
          <ScanBarcode class="text-primary-500" />
          Scan Pesanan / Produk
        </h1>
        <p class="text-slate-400">Scan barcode resi atau produk untuk memproses</p>
      </div>
    </div>

    <!-- Scan Input -->
    <div class="card p-6">
      <div class="relative">
        <Search class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500" />
        <input
            ref="scanInput"
            v-model="scanCode"
            @keyup.enter="handleScan"
            type="text"
            class="w-full bg-slate-900 border border-slate-700 rounded-xl py-4 pl-12 pr-4 text-lg text-white focus:outline-none focus:ring-2 focus:ring-primary-500 shadow-inner"
            placeholder="Scan barcode disini... (Tekan Enter)"
            :disabled="isLoading"
        />
        <div v-if="isLoading" class="absolute right-4 top-1/2 -translate-y-1/2">
            <span class="loading loading-spinner text-primary-500"></span>
        </div>
      </div>
      <p class="mt-2 text-xs text-slate-500">
          Support OCR Barcode Scanner & Manual Input
      </p>
    </div>

    <!-- Result Area -->
    <div v-if="scanResult" class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Details Card -->
        <div class="card p-6 border-l-4" :class="scanResult.type === 'order' ? 'border-l-blue-500' : 'border-l-emerald-500'">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <span class="badge mb-2" :class="scanResult.type === 'order' ? 'badge-info' : 'badge-success'">
                        {{ scanResult.type === 'order' ? 'PESANAN' : 'PRODUK' }}
                    </span>
                    <h2 class="text-xl font-bold text-white">
                        {{ scanResult.type === 'order' ? scanResult.data.order_number : scanResult.data.name }}
                    </h2>
                    <p v-if="scanResult.type === 'order'" class="text-slate-400 text-sm">
                        {{ scanResult.data.platform }}
                    </p>
                    <p v-else class="text-slate-400 text-sm">
                        SKU: {{ scanResult.data.sku }}
                    </p>
                </div>
            </div>

            <!-- Order Specific Actions -->
            <div v-if="scanResult.type === 'order'" class="space-y-4">
                <div class="grid grid-cols-2 gap-4 text-sm bg-slate-800/50 p-4 rounded-lg">
                    <div>
                        <p class="text-slate-500">Customer</p>
                        <p class="text-white">{{ scanResult.data.customer_name || '-' }}</p>
                    </div>
                    <div>
                        <p class="text-slate-500">Status</p>
                        <p class="font-bold text-white uppercase">{{ scanResult.data.status }}</p>
                    </div>
                </div>

                <div class="flex gap-2 mt-4">
                    <button @click="updateStatus('packed')" class="btn btn-secondary flex-1">
                        <Package :size="16" /> Packed
                    </button>
                    <button @click="updateStatus('shipped')" class="btn btn-primary flex-1">
                        <ArrowRight :size="16" /> Shipped
                    </button>
                </div>
            </div>

             <!-- Product Specific Actions -->
            <div v-if="scanResult.type === 'product'" class="space-y-4">
                 <div class="grid grid-cols-2 gap-4 text-sm bg-slate-800/50 p-4 rounded-lg">
                    <div>
                        <p class="text-slate-500">Harga</p>
                        <p class="text-white font-mono">{{ formatCurrency(scanResult.data.price) }}</p>
                    </div>
                    <div>
                        <p class="text-slate-500">Stock</p>
                        <p class="font-bold text-white">TODO: Get Stock</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Empty State -->
    <div v-else-if="!isLoading" class="flex flex-col items-center justify-center p-12 text-slate-500 opacity-50">
        <ScanBarcode :size="64" class="mb-4" />
        <p>Belum ada data di-scan</p>
    </div>

  </div>
</template>

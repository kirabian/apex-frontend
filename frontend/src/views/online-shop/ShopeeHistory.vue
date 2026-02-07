<script setup>
import { ref, onMounted, watch } from "vue";
import api from "../../api/axios";
import { useToast } from "../../composables/useToast";
import {
    Search,
    Loader2,
    Calendar,
    User,
    Smartphone,
    MapPin,
    Package,
    ChevronLeft,
    ChevronRight,
    ShoppingBag
} from "lucide-vue-next";

const toast = useToast();

// State
const isLoading = ref(false);
const history = ref([]);
const pagination = ref({
    currentPage: 1,
    lastPage: 1,
    total: 0,
    perPage: 15
});

const search = ref("");
let searchTimeout = null;

// Fetch history
const fetchHistory = async (page = 1) => {
    isLoading.value = true;
    try {
        const response = await api.get("/stock-outs/shopee-history", {
            params: {
                page,
                q: search.value
            }
        });

        const data = response.data;
        history.value = data.data;
        pagination.value = {
            currentPage: data.current_page,
            lastPage: data.last_page,
            total: data.total,
            perPage: data.per_page
        };
    } catch (e) {
        toast.error("Gagal memuat history Shopee");
        console.error(e);
    } finally {
        isLoading.value = false;
    }
};

// Search handler with debounce
const handleSearch = () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        fetchHistory(1);
    }, 500);
};

watch(search, handleSearch);

// Formatters
const formatDate = (dateString) => {
    if (!dateString) return "-";
    return new Date(dateString).toLocaleDateString("id-ID", {
        day: "2-digit",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit"
    });
};

const formatAddress = (stockOut, detailAddress) => {
    const parts = [];
    if (detailAddress) parts.push(detailAddress);
    
    if (stockOut.shopee_village) parts.push(stockOut.shopee_village);
    if (stockOut.shopee_district) parts.push(stockOut.shopee_district);
    if (stockOut.shopee_city) parts.push(stockOut.shopee_city);
    if (stockOut.shopee_province) parts.push(stockOut.shopee_province);
    if (stockOut.shopee_postal_code) parts.push(stockOut.shopee_postal_code);
    
    return parts.join(', ');
};

// Initial load
onMounted(() => {
    fetchHistory();
});
</script>

<template>
    <div class="space-y-6 animate-in fade-in">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-text-primary flex items-center gap-2">
                    <ShoppingBag class="text-primary-500" />
                    History Shopee
                </h1>
                <p class="text-text-secondary mt-1">Riwayat pengeluaran stok untuk pesanan Shopee</p>
            </div>

            <!-- Search -->
            <div class="relative w-full md:w-64">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" :size="20" />
                <input v-model="search" type="text" placeholder="Cari Resi / Nama..."
                    class="pl-10 w-full bg-surface-800 border-surface-700 rounded-xl focus:ring-primary-500 focus:border-primary-500 transition-all text-text-primary h-10" />
            </div>
        </div>

        <!-- Content -->
        <div class="card overflow-hidden">
            <div v-if="isLoading" class="p-12 text-center text-text-secondary">
                <Loader2 :size="32" class="animate-spin mx-auto mb-2" />
                Memuat data...
            </div>

            <div v-else-if="history.length === 0" class="p-12 text-center text-text-secondary">
                <Package :size="48" class="mx-auto mb-2 opacity-50" />
                <p>Belum ada data history Shopee</p>
            </div>

            <div v-else class="divide-y divide-surface-700">
                <div v-for="item in history" :key="item.id" class="p-4 hover:bg-surface-700/30 transition-colors">
                    <div class="flex flex-col md:flex-row gap-4">
                        <!-- Left: Info Utama -->
                        <div class="flex-1 space-y-3">
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="font-mono font-bold text-primary-400 bg-primary-500/10 px-2 py-0.5 rounded text-sm">
                                        {{ item.receipt_id }}
                                    </span>
                                    <span class="text-xs text-text-secondary flex items-center gap-1">
                                        <Calendar :size="12" />
                                        {{ formatDate(item.created_at) }}
                                    </span>
                                </div>
                                <div class="text-xs bg-surface-700 px-2 py-0.5 rounded text-text-secondary">
                                    {{ item.user?.name || item.user?.username || 'Unknown' }}
                                </div>
                            </div>

                            <!-- List Penerima (Per-Item) -->
                            <div class="space-y-2">
                                <template v-if="item.shopee_items_data && item.shopee_items_data.length > 0">
                                    <div v-for="(shopeeItem, idx) in item.shopee_items_data" :key="idx"
                                        class="bg-surface-900/50 rounded-lg p-3 border border-surface-700/50 text-sm">
                                        <div class="flex flex-wrap gap-x-6 gap-y-1 mb-1 items-center">
                                            <span class="text-primary-500 font-bold text-xs">#{{ idx + 1 }}</span>
                                            <span class="font-medium text-text-primary flex items-center gap-1">
                                                <User :size="12" class="text-text-secondary" />
                                                {{ shopeeItem.receiver }}
                                            </span>
                                            <span
                                                class="font-mono bg-surface-800 px-1.5 rounded text-xs text-text-primary border border-surface-700">
                                                {{ shopeeItem.tracking_no }}
                                            </span>
                                            <span v-if="shopeeItem.phone" class="text-text-secondary text-xs">
                                                {{ shopeeItem.phone }}
                                            </span>
                                        </div>
                                        <div v-if="shopeeItem.address"
                                            class="text-xs text-text-secondary pl-5 flex items-start gap-1">
                                            <MapPin :size="10" class="mt-0.5 shrink-0" />
                                            <span>{{ formatAddress(item, shopeeItem.address) }}</span>
                                        </div>
                                        <div v-if="shopeeItem.product_detail_id" class="mt-2 pl-5">
                                            <!-- We need to match product detail from items relation properly if possible. 
                                                  But here we loop shopee_items_data.
                                                  Ideally we cross reference `item.items` using product_detail_id -->
                                            <div
                                                class="flex items-center gap-2 text-xs text-text-primary bg-surface-700/50 px-2 py-1 rounded inline-flex">
                                                <Smartphone :size="12" />
                                                <span>Item ID: {{ shopeeItem.product_detail_id }}</span>
                                                <!-- Finding the matching item from relation -->
                                                <span
                                                    v-for="relItem in item.items.filter(i => i.id == shopeeItem.product_detail_id)"
                                                    :key="relItem.id">
                                                    - {{ relItem.product?.name }} ({{ relItem.imei }})
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                                <!-- Legacy Fallback -->
                                <template v-else>
                                    <div class="bg-surface-900/50 rounded-lg p-3 border border-surface-700/50 text-sm">
                                        <div class="flex flex-wrap gap-4">
                                            <div>
                                                <p class="text-xs text-text-secondary">Penerima</p>
                                                <p class="font-medium text-text-primary">{{ item.shopee_receiver || '-'
                                                    }}</p>
                                            </div>
                                            <div>
                                                <p class="text-xs text-text-secondary">No. Resi</p>
                                                <p class="font-mono text-text-primary bg-surface-800 px-1.5 rounded">{{
                                                    item.shopee_tracking_no || '-' }}</p>
                                            </div>
                                        </div>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            <div v-for="prod in item.items" :key="prod.id"
                                                class="flex items-center gap-1.5 text-xs bg-surface-700 px-2 py-1 rounded text-text-primary">
                                                <Smartphone :size="12" />
                                                {{ prod.product?.name }}
                                                <span class="text-text-secondary font-mono">{{ prod.imei }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div class="p-4 border-t border-surface-700 flex items-center justify-between" v-if="pagination.total > 0">
                <p class="text-sm text-text-secondary">
                    Total {{ pagination.total }} data
                </p>
                <div class="flex gap-2">
                    <button @click="fetchHistory(pagination.currentPage - 1)" :disabled="pagination.currentPage <= 1"
                        class="btn-icon">
                        <ChevronLeft :size="18" />
                    </button>
                    <span
                        class="flex items-center px-4 text-sm font-medium text-text-primary bg-surface-800 rounded-lg">
                        {{ pagination.currentPage }} / {{ pagination.lastPage }}
                    </span>
                    <button @click="fetchHistory(pagination.currentPage + 1)"
                        :disabled="pagination.currentPage >= pagination.lastPage" class="btn-icon">
                        <ChevronRight :size="18" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@reference "../../style.css";

.card {
    @apply bg-surface-800 rounded-xl border border-surface-700;
}

.btn-icon {
    @apply w-9 h-9 flex items-center justify-center rounded-lg bg-surface-800 hover:bg-surface-700 text-text-secondary hover:text-text-primary transition-colors disabled:opacity-50 disabled:cursor-not-allowed border border-surface-700;
}
</style>

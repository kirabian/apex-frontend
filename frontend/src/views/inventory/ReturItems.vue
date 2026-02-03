<script setup>
import { ref, computed, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import { useAuthStore } from '../../store/auth';
import api from '../../api/axios';
import {
    ArrowDownRight,
    Package,
    Loader2,
    Smartphone,
    User,
    Calendar,
    FileText,
    CheckCircle,
    AlertTriangle,
    Search,
    RefreshCw
} from 'lucide-vue-next';

const toast = useToast();
const authStore = useAuthStore();

// State
const returItems = ref([]);
const isLoading = ref(false);
const searchQuery = ref('');

// Fetch returned items (status = 'returned', placement_type = 'warehouse')
async function fetchReturItems() {
    isLoading.value = true;
    try {
        const response = await api.get('/inventory', {
            params: {
                status: 'returned',
                placement_type: 'warehouse'
            }
        });
        returItems.value = response.data.data || response.data;
    } catch (e) {
        toast.error("Gagal memuat barang retur");
        console.error(e);
    } finally {
        isLoading.value = false;
    }
}

// Filtered items
const filteredItems = computed(() => {
    if (!searchQuery.value) return returItems.value;
    const q = searchQuery.value.toLowerCase();
    return returItems.value.filter(item =>
        item.imei?.toLowerCase().includes(q) ||
        item.product?.name?.toLowerCase().includes(q) ||
        item.sku?.toLowerCase().includes(q)
    );
});

// Accept return (change status to 'available')
async function acceptReturn(item) {
    try {
        await api.patch(`/inventory/${item.id}/status`, { status: 'available' });
        toast.success("Barang berhasil diterima ke gudang");
        fetchReturItems();
    } catch (e) {
        toast.error("Gagal menerima barang");
    }
}

onMounted(() => {
    fetchReturItems();
});
</script>

<template>
    <div class="space-y-6 animate-in fade-in max-w-6xl mx-auto pb-20">
        <!-- Header -->
        <div class="flex justify-between items-end">
            <div>
                <h1 class="text-2xl font-bold text-text-primary flex items-center gap-2">
                    <ArrowDownRight :size="28" class="text-amber-500" /> Retur Masuk
                </h1>
                <p class="text-text-secondary mt-1">Daftar barang retur yang masuk ke gudang</p>
            </div>
            <button @click="fetchReturItems" class="btn btn-secondary h-12 px-4 rounded-xl">
                <RefreshCw :size="18" :class="{ 'animate-spin': isLoading }" />
            </button>
        </div>

        <!-- Search -->
        <div class="card">
            <div class="relative">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" :size="18" />
                <input v-model="searchQuery" type="text" placeholder="Cari IMEI, produk, SKU..." class="input pl-10" />
            </div>
        </div>

        <!-- Stats -->
        <div class="card bg-amber-500/10 border-amber-500/30">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-full bg-amber-500/20 flex items-center justify-center">
                    <Package :size="24" class="text-amber-500" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-text-primary">{{ filteredItems.length }}</p>
                    <p class="text-text-secondary text-sm">Barang Retur Menunggu</p>
                </div>
            </div>
        </div>

        <!-- Items List -->
        <div class="space-y-4">
            <div v-if="isLoading" class="text-center py-12 text-text-secondary">
                <Loader2 :size="32" class="animate-spin mx-auto mb-2" />
                Memuat barang retur...
            </div>
            <div v-else-if="filteredItems.length === 0" class="text-center py-12 text-text-secondary">
                <Package :size="48" class="mx-auto mb-2 opacity-50" />
                Tidak ada barang retur yang menunggu
            </div>
            <div v-else v-for="item in filteredItems" :key="item.id"
                class="card p-5 border-l-4 border-l-amber-500 hover:bg-surface-700/50 transition-colors">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-start gap-4">
                        <!-- Product Image/Icon -->
                        <div class="w-16 h-16 rounded-xl bg-surface-700 flex items-center justify-center shrink-0">
                            <Package :size="28" class="text-text-secondary" />
                        </div>

                        <!-- Info -->
                        <div class="space-y-2">
                            <div>
                                <h3 class="font-bold text-text-primary">{{ item.product?.name || 'Produk' }}</h3>
                                <p class="text-xs text-text-secondary">SKU: {{ item.sku || '-' }}</p>
                            </div>
                            <div class="flex items-center gap-2 text-sm text-text-secondary">
                                <Smartphone :size="14" />
                                <span class="font-mono">{{ item.imei }}</span>
                            </div>
                            <div class="flex flex-wrap gap-3 text-xs">
                                <span class="flex items-center gap-1 text-text-secondary">
                                    <Calendar :size="12" />
                                    {{ new Date(item.updated_at).toLocaleDateString('id-ID') }}
                                </span>
                                <span v-if="item.ram && item.storage"
                                    class="px-2 py-0.5 rounded bg-surface-700 text-text-secondary">
                                    {{ item.ram }}/{{ item.storage }}
                                </span>
                                <span class="px-2 py-0.5 rounded bg-amber-500/20 text-amber-400">
                                    {{ item.condition || 'Second' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Action -->
                    <button @click="acceptReturn(item)"
                        class="btn bg-green-600 hover:bg-green-700 text-white px-4 h-10 rounded-xl text-sm font-medium shrink-0">
                        <CheckCircle :size="16" class="mr-1" />
                        Terima
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@reference "../../style.css";

.card {
    @apply bg-surface-800 rounded-2xl p-6 border border-surface-700;
}

.input {
    @apply w-full border border-surface-700 rounded-xl px-4 py-3 bg-surface-800 text-text-primary focus:outline-none focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 transition-all placeholder:text-surface-500;
}

.btn {
    @apply inline-flex items-center justify-center font-semibold transition-all;
}

.btn-secondary {
    @apply bg-surface-700 text-text-primary hover:bg-surface-600;
}
</style>

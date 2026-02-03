<script setup>
import { ref, onMounted } from "vue";
import api from "../../api/axios";
import { useToast } from "../../composables/useToast";
import { useRouter } from "vue-router";
import {
    Package,
    Loader2,
    ArrowDownRight,
    Calendar,
    User,
    Smartphone,
    CheckCircle2,
    Clock,
    Building2,
    RefreshCw
} from "lucide-vue-next";

const toast = useToast();
const router = useRouter();

// State
const isLoading = ref(true);
const isConfirming = ref(null);
const transfers = ref([]);

// Fetch pending transfers
async function fetchPending() {
    isLoading.value = true;
    try {
        const response = await api.get('/transfers/pending');
        transfers.value = response.data.data || [];
    } catch (e) {
        toast.error(e.response?.data?.message || "Gagal memuat data transfer");
    } finally {
        isLoading.value = false;
    }
}

// Confirm receipt
async function confirmTransfer(transfer) {
    isConfirming.value = transfer.id;
    try {
        const response = await api.post(`/transfers/${transfer.id}/confirm`);
        toast.success(response.data.message || "Transfer berhasil dikonfirmasi!");

        // Remove from list
        transfers.value = transfers.value.filter(t => t.id !== transfer.id);
    } catch (e) {
        toast.error(e.response?.data?.message || "Gagal mengkonfirmasi transfer");
    } finally {
        isConfirming.value = null;
    }
}

// Format date
function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

onMounted(fetchPending);
</script>

<template>
    <div class="space-y-6 animate-in fade-in">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-text-primary flex items-center gap-3">
                    <div class="w-12 h-12 bg-blue-500/20 rounded-2xl flex items-center justify-center">
                        <ArrowDownRight :size="24" class="text-blue-500" />
                    </div>
                    Barang Masuk Transfer
                </h1>
                <p class="text-text-secondary mt-1">
                    Konfirmasi penerimaan barang dari cabang lain
                </p>
            </div>
            <button @click="fetchPending" :disabled="isLoading" class="btn btn-secondary gap-2">
                <RefreshCw :size="16" :class="{ 'animate-spin': isLoading }" />
                Refresh
            </button>
        </div>

        <!-- Loading State -->
        <div v-if="isLoading" class="text-center py-20 text-text-secondary">
            <Loader2 :size="40" class="animate-spin mx-auto mb-4" />
            <p>Memuat transfer masuk...</p>
        </div>

        <!-- Empty State -->
        <div v-else-if="transfers.length === 0" class="text-center py-20">
            <div class="w-24 h-24 mx-auto bg-surface-700/50 rounded-3xl flex items-center justify-center mb-6">
                <CheckCircle2 :size="48" class="text-green-500" />
            </div>
            <h2 class="text-xl font-bold text-text-primary mb-2">Tidak Ada Transfer Masuk</h2>
            <p class="text-text-secondary">Semua transfer sudah dikonfirmasi 🎉</p>
        </div>

        <!-- Transfer List -->
        <div v-else class="space-y-4">
            <p class="text-text-secondary text-sm">
                <Clock :size="14" class="inline mr-1" />
                {{ transfers.length }} transfer menunggu konfirmasi
            </p>

            <div v-for="transfer in transfers" :key="transfer.id"
                class="card border-l-4 border-l-blue-500 hover:bg-surface-700/30 transition-all">
                <!-- Header -->
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-blue-500/20 text-blue-500">
                            <Building2 :size="24" />
                        </div>
                        <div>
                            <p class="font-bold text-text-primary">{{ transfer.receipt_id }}</p>
                            <p class="text-sm text-text-secondary flex items-center gap-1">
                                <User :size="12" />
                                Dari: {{ transfer.sender_name }}
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="flex items-center gap-2 text-text-secondary text-sm">
                            <Calendar :size="14" />
                            {{ formatDate(transfer.created_at) }}
                        </div>
                        <p class="text-xs text-amber-500 font-medium mt-1">
                            <Clock :size="12" class="inline mr-1" />
                            Menunggu Konfirmasi
                        </p>
                    </div>
                </div>

                <!-- Items -->
                <div class="mb-4">
                    <p class="text-xs uppercase font-bold text-text-secondary mb-2">
                        Barang ({{ transfer.items?.length || 0 }})
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <div v-for="item in transfer.items" :key="item.id"
                            class="bg-surface-700 px-3 py-2 rounded-lg text-sm flex items-center gap-2">
                            <Smartphone :size="14" />
                            <span>{{ item.product_name }}</span>
                            <span class="font-mono text-xs text-text-secondary">{{ item.imei }}</span>
                        </div>
                    </div>
                </div>

                <!-- Info -->
                <div class="grid grid-cols-2 gap-3 text-sm mb-4">
                    <div>
                        <p class="text-text-secondary text-xs">Penerima (dari pengirim)</p>
                        <p class="text-text-primary">{{ transfer.receiver_name || '-' }}</p>
                    </div>
                    <div v-if="transfer.notes">
                        <p class="text-text-secondary text-xs">Catatan</p>
                        <p class="text-text-primary">{{ transfer.notes }}</p>
                    </div>
                </div>

                <!-- Actions -->
                <div class="pt-4 border-t border-surface-700 flex justify-end">
                    <button @click="confirmTransfer(transfer)" :disabled="isConfirming === transfer.id"
                        class="btn btn-success gap-2 px-6">
                        <Loader2 v-if="isConfirming === transfer.id" :size="16" class="animate-spin" />
                        <CheckCircle2 v-else :size="16" />
                        Konfirmasi Terima
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@reference "../../style.css";

.btn {
    @apply font-bold transition-all duration-300 disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center rounded-xl px-4 py-2.5;
}

.btn-secondary {
    @apply bg-surface-700 hover:bg-surface-600 text-text-primary;
}

.btn-success {
    @apply bg-green-600 hover:bg-green-500 text-white;
}

.card {
    @apply bg-surface-800 rounded-2xl p-6 border border-surface-700;
}
</style>

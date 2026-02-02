<script setup>
import { ref, computed, onMounted } from "vue";
import { useToast } from "../../composables/useToast";
import { distributors as distributorsApi, inventory as inventoryApi } from "../../api/axios";
import { useAuthStore } from "../../store/auth";
import {
    Package,
    Save,
    Plus,
    Trash2,
    Smartphone,
    Box,
    Truck,
    Building,
    Loader2,
    ScanBarcode
} from "lucide-vue-next";

const toast = useToast();
const authStore = useAuthStore();

// State
const isLoading = ref(false);
const isSubmitting = ref(false);
const distributors = ref([]);

// Form
const placementType = ref("branch"); // Default, will be overriden by role logic
const placementId = ref(null);
const selectedDistributor = ref("");
const itemType = ref("hp"); // 'hp' or 'non-hp'

// Product Selection (Mock for now, normally would be select2 / search)
const selectedProduct = ref(null);
const products = ref([]); // Load from API

// Dynamic Rows for HP
const imeiRows = ref([
    { imei: "", condition: "new", cost_price: 0, selling_price: 0, color: "", ram: "", storage: "" }
]);

// Single Row for Non-HP
const nonHpForm = ref({
    quantity: 1,
});

// Computed
const canSubmit = computed(() => {
    if (!selectedDistributor.value || !selectedProduct.value) return false;
    if (itemType.value === 'hp') {
        return imeiRows.value.length > 0 && imeiRows.value.every(r => r.imei && r.condition && r.cost_price > 0);
    } else {
        return nonHpForm.value.quantity > 0;
    }
});

// Methods
function addImeiRow() {
    const lastRow = imeiRows.value[imeiRows.value.length - 1];
    imeiRows.value.push({
        imei: "",
        condition: lastRow ? lastRow.condition : "new",
        cost_price: lastRow ? lastRow.cost_price : 0,
        selling_price: lastRow ? lastRow.selling_price : 0,
        color: lastRow ? lastRow.color : "",
        ram: lastRow ? lastRow.ram : "",
        storage: lastRow ? lastRow.storage : ""
    });
}

function removeImeiRow(index) {
    if (imeiRows.value.length > 1) {
        imeiRows.value.splice(index, 1);
    }
}

async function fetchInitialData() {
    isLoading.value = true;
    try {
        const distResponse = await distributorsApi.list();
        distributors.value = distResponse.data.data;

        // Load products lookup
        const prodResponse = await inventoryApi.getProductsLookup({ type: itemType.value });
        products.value = prodResponse.data;

        // Detect User Role and set Placement
        const role = authStore.userRole;
        // Logic to set placementType and ID based on User
        // For now, hardcode based on assumption or use user's branch
        if (authStore.user?.branch_id) {
            placementType.value = 'branch';
            placementId.value = authStore.user.branch_id;
        }
    } catch (error) {
        console.error(error);
        toast.error("Gagal memuat data awal");
    } finally {
        isLoading.value = false;
    }
}

async function submitStockIn() {
    if (!canSubmit.value) return;
    isSubmitting.value = true;

    try {
        const payload = {
            product_id: selectedProduct.value,
            distributor_id: selectedDistributor.value,
            type: itemType.value,
            placement_type: placementType.value,
            placement_id: placementId.value,
        };

        if (itemType.value === 'hp') {
            payload.imeis = imeiRows.value;
        } else {
            payload.quantity = nonHpForm.value.quantity;
        }

        await inventoryApi.stockIn(payload);
        toast.success("Stok berhasil ditambahkan!");

        // Reset form
        imeiRows.value = [{ imei: "", condition: "new", cost_price: 0, selling_price: 0 }];
        nonHpForm.value.quantity = 1;
        selectedProduct.value = null;

    } catch (error) {
        console.error(error);
        toast.error(error.response?.data?.message || "Gagal input stok");
    } finally {
        isSubmitting.value = false;
    }
}

// Watch item type changes to reload products
import { watch } from 'vue';
watch(itemType, async () => {
    const prodResponse = await inventoryApi.getProductsLookup({ type: itemType.value });
    products.value = prodResponse.data;
    selectedProduct.value = null;
});

onMounted(() => {
    fetchInitialData();
});
</script>

<template>
    <div class="space-y-6 animate-in fade-in">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold text-text-primary tracking-tight flex items-center gap-2">
                    <Box :size="28" class="text-blue-500" /> Input Barang Masuk
                </h1>
                <p class="text-text-secondary mt-1">Tambah stok barang ke inventory</p>
            </div>
        </div>

        <!-- Main Card -->
        <div class="card p-6 border-t-4 border-t-primary-500">

            <!-- Account & Distributor -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div>
                    <label class="label">Distributor</label>
                    <div class="flex gap-2">
                        <select v-model="selectedDistributor" class="input flex-1">
                            <option value="" disabled>Pilih Distributor</option>
                            <option v-for="d in distributors" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                        <button class="btn btn-outline" title="Tambah Distributor Baru">
                            <Plus :size="18" />
                        </button>
                    </div>
                </div>

                <div>
                    <label class="label">Tipe Barang</label>
                    <div class="grid grid-cols-2 gap-2 p-1 bg-surface-900 rounded-xl">
                        <button @click="itemType = 'hp'" class="px-4 py-2 rounded-lg text-sm font-medium transition-all"
                            :class="itemType === 'hp' ? 'bg-primary-500 text-white shadow-lg' : 'text-text-secondary hover:text-text-primary'">
                            <div class="flex items-center justify-center gap-2">
                                <Smartphone :size="16" /> Handphone / IMEI
                            </div>
                        </button>
                        <button @click="itemType = 'non-hp'"
                            class="px-4 py-2 rounded-lg text-sm font-medium transition-all"
                            :class="itemType === 'non-hp' ? 'bg-primary-500 text-white shadow-lg' : 'text-text-secondary hover:text-text-primary'">
                            <div class="flex items-center justify-center gap-2">
                                <Box :size="16" /> Produk Biasa / Non-HP
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Product Selection -->
            <div class="mb-8">
                <label class="label">Pilih Produk</label>
                <select v-model="selectedProduct" class="input w-full">
                    <option :value="null">-- Pilih Produk --</option>
                    <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} ({{ p.sku }})</option>
                </select>
                <p v-if="!products.length" class="text-xs text-orange-400 mt-1">Tidak ada produk untuk tipe ini.</p>
            </div>

            <!-- HP / IMEI Input Form -->
            <div v-if="itemType === 'hp'" class="space-y-4">
                <div v-for="(row, index) in imeiRows" :key="index"
                    class="p-4 bg-surface-900/50 rounded-xl border border-surface-700 relative group animate-in slide-in-from-bottom-2">
                    <!-- Remove Button -->
                    <button @click="removeImeiRow(index)" v-if="imeiRows.length > 1"
                        class="absolute top-2 right-2 text-text-secondary hover:text-red-500 opacity-0 group-hover:opacity-100 transition-opacity">
                        <Trash2 :size="18" />
                    </button>

                    <h4 class="text-sm font-bold text-text-secondary mb-3">Unit ke-{{ index + 1 }}</h4>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="text-xs text-text-secondary mb-1 block">IMEI / Serial Number</label>
                            <div class="relative">
                                <ScanBarcode :size="16" class="absolute left-3 top-3 text-text-secondary" />
                                <input v-model="row.imei" placeholder="Scan atau ketik IMEI"
                                    class="input pl-9 font-mono" />
                            </div>
                        </div>
                        <div>
                            <label class="text-xs text-text-secondary mb-1 block">Kondisi</label>
                            <select v-model="row.condition" class="input">
                                <option value="new">Baru (New)</option>
                                <option value="second">Bekas (Second)</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-text-secondary mb-1 block">Specs (RAM/ROM)</label>
                            <div class="flex gap-2">
                                <input v-model="row.ram" placeholder="RAM" class="input w-1/2" />
                                <input v-model="row.storage" placeholder="ROM" class="input w-1/2" />
                            </div>
                        </div>
                        <div>
                            <label class="text-xs text-text-secondary mb-1 block">Warna</label>
                            <input v-model="row.color" placeholder="Warna unit" class="input" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mt-3 pt-3 border-t border-surface-700/30">
                        <div>
                            <label class="text-xs text-text-secondary mb-1 block">Harga Modal (Beli)</label>
                            <input v-model="row.cost_price" type="number" class="input" />
                        </div>
                        <div>
                            <label class="text-xs text-text-secondary mb-1 block">Harga Jual (SRP)</label>
                            <input v-model="row.selling_price" type="number" class="input" />
                        </div>
                    </div>
                </div>

                <button @click="addImeiRow"
                    class="btn btn-outline w-full border-dashed border-2 py-3 text-text-secondary hover:text-primary-500 hover:border-primary-500 hover:bg-primary-500/10">
                    <Plus :size="20" class="mr-2" /> Tambah Unit Lain (Batch Input)
                </button>
            </div>

            <!-- Non-HP Input Form -->
            <div v-else class="p-6 bg-surface-900/50 rounded-xl border border-surface-700 text-center space-y-4">
                <div>
                    <label class="label text-center">Jumlah Stok Masuk</label>
                    <div class="flex justify-center items-center gap-4">
                        <button @click="nonHpForm.quantity > 1 ? nonHpForm.quantity-- : null"
                            class="w-12 h-12 rounded-xl bg-surface-800 hover:bg-surface-700 flex items-center justify-center text-xl font-bold">-</button>
                        <input v-model="nonHpForm.quantity" type="number"
                            class="w-32 text-center text-2xl font-bold bg-transparent border-none focus:ring-0 text-text-primary"
                            min="1" />
                        <button @click="nonHpForm.quantity++"
                            class="w-12 h-12 rounded-xl bg-surface-800 hover:bg-surface-700 flex items-center justify-center text-xl font-bold">+</button>
                    </div>
                </div>
            </div>

            <!-- Submit Footer -->
            <div class="mt-8 pt-6 border-t border-surface-700 flex justify-end">
                <button @click="submitStockIn" :disabled="!canSubmit || isSubmitting"
                    class="btn btn-primary w-full md:w-auto px-8 py-3 text-lg shadow-lg shadow-primary-500/20">
                    <Loader2 v-if="isSubmitting" class="animate-spin mr-2" />
                    {{ isSubmitting ? 'Menyimpan...' : 'Simpan Stok Masuk' }}
                </button>
            </div>

        </div>
    </div>
</template>

<style scoped>
@reference "../../style.css";

.label {
    @apply block text-sm font-medium text-text-secondary mb-2;
}

.input {
    @apply w-full bg-surface-800 border border-surface-600 rounded-xl px-4 py-2.5 text-text-primary focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition-colors placeholder:text-text-secondary/50;
}
</style>

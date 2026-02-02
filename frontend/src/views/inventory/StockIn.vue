<script setup>
import { ref, computed, onMounted, watch } from "vue";
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
    ScanBarcode,
    ChevronRight,
    ChevronLeft,
    CheckCircle2
} from "lucide-vue-next";

const toast = useToast();
const authStore = useAuthStore();

// State
const isLoading = ref(false);
const isSubmitting = ref(false);
const distributors = ref([]);
const currentStep = ref(1);

// Step 1: Placement / Account
const placementType = ref("branch"); // 'branch', 'warehouse', 'online_shop'
const placementId = ref(null);
const placementOptions = ref([]); // To populate if user can choose
// For now we'll simulate options or just show current user's location
const placementName = computed(() => {
    // Logic to get friendly name
    if (placementId.value) return `${placementType.value.toUpperCase()} #${placementId.value}`;
    return "Lokasi Saya";
});

// Step 2: Item Type
const itemType = ref("hp"); // 'hp' or 'non-hp'

// Step 3: Distributor
const selectedDistributor = ref("");
const selectedDistributorName = computed(() => {
    const d = distributors.value.find(x => x.id === selectedDistributor.value);
    return d ? d.name : '-';
});

// Step 4: Product & Details
const selectedProduct = ref(null);
const products = ref([]);
const imeiRows = ref([
    { imei: "", condition: "new", cost_price: 0, selling_price: 0, color: "", ram: "", storage: "" }
]);
const nonHpForm = ref({ quantity: 1 });

// Computed
const canNext = computed(() => {
    if (currentStep.value === 1) return !!placementId.value;
    if (currentStep.value === 2) return !!itemType.value;
    if (currentStep.value === 3) return !!selectedDistributor.value;
    return false;
});

const canSubmit = computed(() => {
    if (!selectedProduct.value) return false;
    if (itemType.value === 'hp') {
        return imeiRows.value.length > 0 && imeiRows.value.every(r => r.imei && r.condition && r.cost_price > 0);
    } else {
        return nonHpForm.value.quantity > 0;
    }
});

// Methods
function nextStep() {
    if (canNext.value) currentStep.value++;
}

function prevStep() {
    if (currentStep.value > 1) currentStep.value--;
}

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

        // Detect User Role and set Placement
        if (authStore.user?.branch_id) {
            placementType.value = 'branch';
            placementId.value = authStore.user.branch_id;
        } else {
            // Fallback for Admin / Warehouse who might not have branch_id on user directly
            // For this UI demo, let's just default to ID 1 or current user logic
            placementType.value = 'warehouse';
            placementId.value = 1; // Default
        }
    } catch (error) {
        console.error(error);
        toast.error("Gagal memuat data awal");
    } finally {
        isLoading.value = false;
    }
}

// Watch item type changes to reload products when entering step 4 (or earlier)
watch(itemType, async () => {
    const prodResponse = await inventoryApi.getProductsLookup({ type: itemType.value });
    products.value = prodResponse.data;
    selectedProduct.value = null;
});

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

        // Reset or Redirect?
        // Let's reset to Step 1
        currentStep.value = 1;
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

onMounted(() => {
    fetchInitialData();
});
</script>

<template>
    <div class="space-y-6 animate-in fade-in max-w-4xl mx-auto">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold text-text-primary tracking-tight flex items-center gap-2">
                    <Box :size="28" class="text-blue-500" /> Input Barang Masuk
                </h1>
                <p class="text-text-secondary mt-1">Tambah stok barang ke inventory (Step {{ currentStep }}/4)</p>
            </div>
        </div>

        <!-- Progress Indicator -->
        <div class="flex items-center justify-between mb-8 px-4">
            <div v-for="step in [1, 2, 3, 4]" :key="step" class="flex items-center">
                <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm transition-colors duration-300"
                    :class="currentStep >= step ? 'bg-primary-500 text-white' : 'bg-surface-800 text-text-secondary'">
                    {{ step }}
                </div>
                <div v-if="step < 4" class="w-16 h-1 mx-2 rounded-full transition-colors duration-300"
                    :class="currentStep > step ? 'bg-primary-500' : 'bg-surface-800'"></div>
            </div>
        </div>

        <!-- Main Card -->
        <div class="card p-8 border-t-4 border-t-primary-500 min-h-[400px] flex flex-col">

            <!-- Step 1: Account Selection -->
            <div v-if="currentStep === 1" class="space-y-6 animate-in slide-in-from-right">
                <h2 class="text-xl font-bold text-text-primary mb-4">Pilih Akun / User Target</h2>

                <div v-if="isLoading" class="flex justify-center py-8">
                    <Loader2 class="animate-spin text-primary-500" :size="32" />
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div v-for="user in targetUsers" :key="user.id" @click="selectUserPlacement(user)"
                        class="p-4 rounded-xl border border-surface-700 bg-surface-800 cursor-pointer hover:border-primary-500 hover:bg-surface-800/80 transition-all group relative overflow-hidden">

                        <!-- Active Indicator -->
                        <div v-if="placementLabel && placementLabel.includes(user.username)"
                            class="absolute top-2 right-2 text-primary-500">
                            <CheckCircle2 :size="20" />
                        </div>

                        <div class="flex items-center gap-4">
                            <div
                                class="w-12 h-12 rounded-full bg-gradient-to-br from-primary-500 to-indigo-600 flex items-center justify-center text-white font-bold text-lg">
                                {{ user.name.charAt(0).toUpperCase() }}
                            </div>
                            <div>
                                <h3 class="font-bold text-text-primary group-hover:text-primary-400 transition-colors">
                                    {{ user.name }}</h3>
                                <div class="flex items-center gap-2 mt-1">
                                    <span
                                        class="text-xs px-2 py-0.5 rounded-full bg-surface-700 text-text-secondary border border-surface-600">
                                        {{ user.role || (user.roles && user.roles[0]?.name) || 'User' }}
                                    </span>
                                </div>
                                <p class="text-xs text-text-secondary mt-1 truncate max-w-[150px]">
                                    {{ user.username }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div v-if="targetUsers.length === 0" class="col-span-full text-center py-8 text-text-secondary">
                        Tidak ada akun Toko Online / User yang ditemukan.
                    </div>
                </div>
            </div>

            <!-- Step 2: Item Type -->
            <div v-if="currentStep === 2" class="space-y-6 animate-in slide-in-from-right">
                <h2 class="text-xl font-bold text-text-primary mb-4">Pilih Tipe Produk</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <button @click="itemType = 'hp'"
                        class="p-6 rounded-xl border-2 transition-all flex flex-col items-center gap-4 hover:scale-105"
                        :class="itemType === 'hp' ? 'border-primary-500 bg-primary-500/10' : 'border-surface-700 bg-surface-800 hover:border-primary-500/50'">
                        <Smartphone :size="48"
                            :class="itemType === 'hp' ? 'text-primary-500' : 'text-text-secondary'" />
                        <span class="text-lg font-bold"
                            :class="itemType === 'hp' ? 'text-primary-500' : 'text-text-secondary'">Handphone /
                            IMEI</span>
                    </button>

                    <button @click="itemType = 'non-hp'"
                        class="p-6 rounded-xl border-2 transition-all flex flex-col items-center gap-4 hover:scale-105"
                        :class="itemType === 'non-hp' ? 'border-primary-500 bg-primary-500/10' : 'border-surface-700 bg-surface-800 hover:border-primary-500/50'">
                        <Box :size="48" :class="itemType === 'non-hp' ? 'text-primary-500' : 'text-text-secondary'" />
                        <span class="text-lg font-bold"
                            :class="itemType === 'non-hp' ? 'text-primary-500' : 'text-text-secondary'">Produk Biasa /
                            Non-HP</span>
                    </button>
                </div>
            </div>

            <!-- Step 3: Distributor -->
            <div v-if="currentStep === 3" class="space-y-6 animate-in slide-in-from-right">
                <h2 class="text-xl font-bold text-text-primary mb-4">Pilih Distributor</h2>
                <div>
                    <label class="label">Distributor Supply</label>
                    <div class="flex gap-2">
                        <select v-model="selectedDistributor" class="input flex-1 h-12 text-lg">
                            <option value="" disabled>-- Pilih Distributor --</option>
                            <option v-for="d in distributors" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                        <button class="btn btn-outline h-12 w-12 flex items-center justify-center">
                            <Plus :size="20" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Step 4: Input Form & Summary -->
            <div v-if="currentStep === 4" class="space-y-6 animate-in slide-in-from-right">

                <!-- Summary Header (Read-Only) -->
                <div
                    class="grid grid-cols-1 md:grid-cols-3 gap-2 bg-surface-900 rounded-xl p-3 border border-surface-700/50 text-sm">
                    <div class="flex items-center gap-2 px-3">
                        <Building :size="14" class="text-text-secondary" />
                        <span class="text-text-secondary">Akun:</span>
                        <span class="font-bold text-text-primary">{{ placementName }}</span>
                    </div>
                    <div class="flex items-center gap-2 px-3 border-l border-surface-700/50">
                        <Box :size="14" class="text-text-secondary" />
                        <span class="text-text-secondary">Tipe:</span>
                        <span class="font-bold text-text-primary">{{ itemType === 'hp' ? 'Handphone (IMEI)' : 'Non-HP'
                        }}</span>
                    </div>
                    <div class="flex items-center gap-2 px-3 border-l border-surface-700/50">
                        <Truck :size="14" class="text-text-secondary" />
                        <span class="text-text-secondary">Dist:</span>
                        <span class="font-bold text-text-primary">{{ selectedDistributorName }}</span>
                    </div>
                </div>

                <!-- Product Selection in Form -->
                <div>
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
            </div>

            <!-- Navigation Buttons -->
            <div class="mt-auto pt-8 border-t border-surface-700 flex justify-between">
                <button v-if="currentStep > 1" @click="prevStep" class="btn btn-secondary px-6">
                    <ChevronLeft :size="20" /> Kembali
                </button>
                <div v-else></div> <!-- Spacer -->

                <button v-if="currentStep < 4" @click="nextStep" :disabled="!canNext" class="btn btn-primary px-8">
                    Lanjut
                    <ChevronRight :size="20" />
                </button>

                <button v-if="currentStep === 4" @click="submitStockIn" :disabled="!canSubmit || isSubmitting"
                    class="btn btn-primary px-8 shadow-lg shadow-primary-500/20">
                    <Loader2 v-if="isSubmitting" class="animate-spin mr-2" />
                    {{ isSubmitting ? 'Simpan Data' : 'Selesai & Simpan' }}
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

<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { useToast } from "../../composables/useToast";
import { distributors as distributorsApi, inventory as inventoryApi, users as usersApi, brands as brandsApi, productTypes as productTypesApi } from "../../api/axios";
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
    CheckCircle2,
    List
} from "lucide-vue-next";

const toast = useToast();
const authStore = useAuthStore();

// State
const isLoading = ref(false);
const isSubmitting = ref(false);
const distributors = ref([]);
const currentStep = ref(1);
const isManualDistributor = ref(false);
const newDistributorName = ref("");

const targetUsers = ref([]);
const placementLabel = ref("");

// Step 1: Placement / Account
const placementType = ref("branch");
const placementId = ref(null);
const placementOptions = ref([]);

const placementName = computed(() => {
    return placementLabel.value || "Lokasi Belum Terpilih";
});

// Step 2: Item Type
const itemType = ref("hp");

// Step 3: Distributor
const selectedDistributor = ref("");
const selectedDistributorName = computed(() => {
    if (isManualDistributor.value) return newDistributorName.value || 'Distributor Baru';
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

// Step 4: Hierarchical Selection State
const brands = ref([]);
const allowedTypes = ref([]);
const selectedBrand = ref(null);
const selectedTypeName = ref("");
const selectedRam = ref("");
const selectedStorage = ref("");

// Computed for Selection Logic
const filteredTypes = computed(() => {
    if (!selectedBrand.value) return [];
    return allowedTypes.value.filter(t => t.brand_id === selectedBrand.value);
});

const uniqueTypeNames = computed(() => {
    const names = new Set(filteredTypes.value.map(t => t.name));
    return Array.from(names);
});

const availableSpecs = computed(() => {
    if (!selectedTypeName.value) return { rams: [], storages: [] };
    const matching = allowedTypes.value.filter(t => t.name === selectedTypeName.value);
    const rams = new Set(matching.map(t => t.ram).filter(Boolean));
    const storages = new Set(matching.map(t => t.storage).filter(Boolean));
    return {
        rams: Array.from(rams).sort((a, b) => a - b),
        storages: Array.from(storages).sort((a, b) => a - b)
    };
});

// Logic to find product_id from choices
const autoSelectedProduct = computed(() => {
    if (!selectedBrand.value || !selectedTypeName.value) return null;
    const found = products.value.find(p => {
        const matchBrand = p.brand_id === selectedBrand.value;
        const matchName = p.name.includes(selectedTypeName.value);
        const matchRam = !selectedRam.value || p.ram == selectedRam.value;
        const matchStorage = !selectedStorage.value || p.storage == selectedStorage.value;
        return matchBrand && matchName && matchRam && matchStorage;
    });
    return found ? found.id : null;
});

// Watchers
watch(autoSelectedProduct, (newId) => { selectedProduct.value = newId; });
watch(selectedBrand, () => { selectedTypeName.value = ""; selectedRam.value = ""; selectedStorage.value = ""; });
watch(selectedTypeName, () => { selectedRam.value = ""; selectedStorage.value = ""; });
watch(itemType, async () => {
    const prodResponse = await inventoryApi.getProductsLookup({ type: itemType.value });
    products.value = prodResponse.data;
    selectedProduct.value = null;
});

// Navigation Computed
const canNext = computed(() => {
    if (currentStep.value === 1) return !!placementId.value;
    if (currentStep.value === 2) return !!itemType.value;
    if (currentStep.value === 3) {
        if (isManualDistributor.value) return newDistributorName.value.length > 2;
        return !!selectedDistributor.value;
    }
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
function nextStep() { if (canNext.value) currentStep.value++; }
function prevStep() { if (currentStep.value > 1) currentStep.value--; }

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
    if (imeiRows.value.length > 1) imeiRows.value.splice(index, 1);
}

async function fetchInitialData() {
    isLoading.value = true;
    try {
        const [distResp, userResp, brandsResp, typesResp, prodResp] = await Promise.all([
            distributorsApi.list(),
            usersApi.list(),
            brandsApi.list(),
            productTypesApi.list(),
            inventoryApi.getProductsLookup({ type: 'hp' })
        ]);

        distributors.value = distResp.data.data;
        brands.value = brandsResp.data.data || brandsResp.data;
        allowedTypes.value = typesResp.data.data || typesResp.data;
        products.value = prodResp.data;

        const allUsers = userResp.data.data || userResp.data;
        const { user: loggedInUser } = authStore;
        targetUsers.value = allUsers.filter(u => {
            const hasOnlineRole = u.roles && u.roles.some(r => r.name === 'toko_online');
            const isSelf = u.id === loggedInUser?.id;
            return hasOnlineRole || isSelf || u.online_shop_id;
        });
    } catch (error) {
        console.error(error);
        toast.error("Gagal memuat data awal");
    } finally {
        isLoading.value = false;
    }
}

function selectUserPlacement(user) {
    if (user.online_shop_id) {
        placementType.value = 'online_shop';
        placementId.value = user.online_shop_id;
    } else if (user.warehouse_id) {
        placementType.value = 'warehouse';
        placementId.value = user.warehouse_id;
    } else if (user.branch_id) {
        placementType.value = 'branch';
        placementId.value = user.branch_id;
    } else {
        if (user.roles?.some(r => r.name === 'toko_online')) {
            placementType.value = 'online_shop';
            placementId.value = user.online_shop_id || 1;
        } else {
            toast.error("Akun ini tidak memiliki lokasi inventory yang valid.");
            return;
        }
    }
    placementLabel.value = user.full_name || user.name || user.username;
    nextStep();
}

async function submitStockIn() {
    if (!canSubmit.value) return;
    isSubmitting.value = true;
    try {
        const payload = {
            product_id: selectedProduct.value,
            distributor_id: isManualDistributor.value ? null : selectedDistributor.value,
            new_distributor_name: isManualDistributor.value ? newDistributorName.value : null,
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
        currentStep.value = 1;
        isManualDistributor.value = false;
        newDistributorName.value = "";
        selectedDistributor.value = "";
        selectedBrand.value = null;
        selectedTypeName.value = "";
    } catch (error) {
        console.error(error);
        toast.error(error.response?.data?.message || "Gagal input stok");
    } finally {
        isSubmitting.value = false;
    }
}

onMounted(() => { fetchInitialData(); });
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

        <div class="card p-8 border-t-4 border-t-primary-500 min-h-[400px] flex flex-col">
            <div v-if="currentStep === 1" class="space-y-6 animate-in slide-in-from-right">
                <h2 class="text-xl font-bold text-text-primary mb-4">Pilih Akun / User Target</h2>
                <div v-if="isLoading" class="flex justify-center py-8">
                    <Loader2 class="animate-spin text-primary-500" :size="32" />
                </div>
                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div v-for="user in targetUsers" :key="user.id" @click="selectUserPlacement(user)"
                        class="p-4 rounded-xl border border-surface-700 bg-surface-800 cursor-pointer hover:border-primary-500 hover:bg-surface-800/80 transition-all group relative overflow-hidden">
                        <div v-if="placementLabel === (user.full_name || user.name)"
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
                                <span
                                    class="text-xs px-2 py-0.5 rounded-full bg-surface-700 text-text-secondary border border-surface-600">
                                    {{ user.roles?.[0]?.name || 'User' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="currentStep === 2" class="space-y-6 animate-in slide-in-from-right">
                <h2 class="text-xl font-bold text-text-primary mb-4">Pilih Tipe Produk</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <button @click="itemType = 'hp'"
                        class="p-6 rounded-xl border-2 transition-all flex flex-col items-center gap-4"
                        :class="itemType === 'hp' ? 'border-primary-500 bg-primary-500/10' : 'border-surface-700 bg-surface-800'">
                        <Smartphone :size="48"
                            :class="itemType === 'hp' ? 'text-primary-500' : 'text-text-secondary'" />
                        <span class="text-lg font-bold">Handphone / IMEI</span>
                    </button>
                    <button @click="itemType = 'non-hp'"
                        class="p-6 rounded-xl border-2 transition-all flex flex-col items-center gap-4"
                        :class="itemType === 'non-hp' ? 'border-primary-500 bg-primary-500/10' : 'border-surface-700 bg-surface-800'">
                        <Box :size="48" :class="itemType === 'non-hp' ? 'text-primary-500' : 'text-text-secondary'" />
                        <span class="text-lg font-bold">Produk Biasa / Non-HP</span>
                    </button>
                </div>
            </div>

            <div v-if="currentStep === 3" class="space-y-6 animate-in slide-in-from-right">
                <h2 class="text-xl font-bold text-text-primary mb-4">Pilih Distributor</h2>
                <div class="bg-surface-900/50 p-6 rounded-xl border border-surface-700">
                    <label class="label">Distributor Supply</label>
                    <div class="flex gap-2">
                        <select v-if="!isManualDistributor" v-model="selectedDistributor"
                            class="input flex-1 h-12 text-lg">
                            <option value="" disabled>-- Pilih Distributor --</option>
                            <option v-for="d in distributors" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                        <input v-else v-model="newDistributorName" placeholder="Ketikan nama distributor baru..."
                            class="input flex-1 h-12 text-lg" />
                        <button type="button" @click="isManualDistributor = !isManualDistributor"
                            class="btn btn-outline h-12 px-4 flex items-center gap-2"
                            :class="isManualDistributor ? 'border-primary-500 text-primary-500' : ''">
                            <component :is="isManualDistributor ? List : Plus" :size="20" />
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="currentStep === 4" class="space-y-6 animate-in slide-in-from-right">
                <div
                    class="grid grid-cols-1 md:grid-cols-3 gap-2 bg-surface-900 rounded-xl p-3 border border-surface-700/50 text-sm">
                    <div class="flex items-center gap-2 px-3">
                        <Building :size="14" class="text-text-secondary" /><span class="font-bold text-text-primary">{{
                            placementName }}</span>
                    </div>
                    <div class="flex items-center gap-2 px-3 border-l border-surface-700/50">
                        <Box :size="14" class="text-text-secondary" /><span class="font-bold text-text-primary">{{
                            itemType === 'hp' ? 'HP' : 'Non-HP' }}</span>
                    </div>
                    <div class="flex items-center gap-2 px-3 border-l border-surface-700/50">
                        <Truck :size="14" class="text-text-secondary" /><span class="font-bold text-text-primary">{{
                            selectedDistributorName }}</span>
                    </div>
                </div>

                <div
                    class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-surface-900/30 p-6 rounded-2xl border border-surface-700">
                    <div>
                        <label class="label">Pilih Merk</label>
                        <select v-model="selectedBrand" class="input">
                            <option :value="null">-- Pilih Merk --</option>
                            <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Pilih Tipe</label>
                        <select v-model="selectedTypeName" :disabled="!selectedBrand" class="input disabled:opacity-50">
                            <option value="">-- Pilih Tipe --</option>
                            <option v-for="name in uniqueTypeNames" :key="name" :value="name">{{ name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">RAM</label>
                        <select v-model="selectedRam" :disabled="!selectedTypeName" class="input disabled:opacity-50">
                            <option value="">-- Semua RAM --</option>
                            <option v-for="ram in availableSpecs.rams" :key="ram" :value="ram">{{ ram }} GB</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">ROM</label>
                        <select v-model="selectedStorage" :disabled="!selectedTypeName"
                            class="input disabled:opacity-50">
                            <option value="">-- Semua ROM --</option>
                            <option v-for="st in availableSpecs.storages" :key="st" :value="st">{{ st }} GB</option>
                        </select>
                    </div>
                </div>

                <div v-if="itemType === 'hp'" class="space-y-4">
                    <div v-for="(row, index) in imeiRows" :key="index"
                        class="p-4 bg-surface-900/50 rounded-xl border border-surface-700 relative group">
                        <button @click="removeImeiRow(index)" v-if="imeiRows.length > 1"
                            class="absolute top-2 right-2 text-text-secondary hover:text-red-500 opacity-0 group-hover:opacity-100 transition-opacity">
                            <Trash2 :size="18" />
                        </button>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div><label class="text-xs text-text-secondary mb-1 block">IMEI</label><input
                                    v-model="row.imei" class="input font-mono" /></div>
                            <div><label class="text-xs text-text-secondary mb-1 block">Kondisi</label><select
                                    v-model="row.condition" class="input">
                                    <option value="new">Baru</option>
                                    <option value="second">Bekas</option>
                                </select></div>
                            <div><label class="text-xs text-text-secondary mb-1 block">Harga Modal</label><input
                                    v-model="row.cost_price" type="number" class="input" /></div>
                            <div><label class="text-xs text-text-secondary mb-1 block">Harga Jual</label><input
                                    v-model="row.selling_price" type="number" class="input" /></div>
                        </div>
                    </div>
                    <button @click="addImeiRow"
                        class="btn btn-outline w-full border-dashed border-2 py-3 text-text-secondary hover:text-primary-500">
                        <Plus :size="20" class="mr-2" /> Tambah Unit
                    </button>
                </div>

                <div v-else class="p-6 bg-surface-900/50 rounded-xl border border-surface-700 text-center">
                    <label class="label">Jumlah Stok</label>
                    <div class="flex justify-center items-center gap-4">
                        <button @click="nonHpForm.quantity > 1 ? nonHpForm.quantity-- : null"
                            class="w-12 h-12 rounded-xl bg-surface-800">-</button>
                        <input v-model="nonHpForm.quantity" type="number"
                            class="w-32 text-center text-2xl font-bold bg-transparent border-none text-text-primary"
                            min="1" />
                        <button @click="nonHpForm.quantity++" class="w-12 h-12 rounded-xl bg-surface-800">+</button>
                    </div>
                </div>
            </div>

            <div class="mt-auto pt-8 border-t border-surface-700 flex justify-between">
                <button v-if="currentStep > 1" @click="prevStep" class="btn btn-secondary px-6">
                    <ChevronLeft :size="20" /> Kembali
                </button>
                <div v-else></div>
                <button v-if="currentStep < 4" @click="nextStep" :disabled="!canNext"
                    class="btn btn-primary px-8">Lanjut
                    <ChevronRight :size="20" />
                </button>
                <button v-if="currentStep === 4" @click="submitStockIn" :disabled="!canSubmit || isSubmitting"
                    class="btn btn-primary px-8 shadow-lg shadow-primary-500/20">
                    <Loader2 v-if="isSubmitting" class="animate-spin mr-2" /> {{ isSubmitting ? 'Menyimpan...' :
                    'Selesai & Simpan' }}
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
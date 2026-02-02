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
    XCircle,
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

// Format Rupiah Helper
function formatRupiah(value) {
    if (!value) return "Rp 0";
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0
    }).format(value);
}

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
        rams: Array.from(rams).sort((a, b) => parseInt(a) - parseInt(b)),
        storages: Array.from(storages).sort((a, b) => parseInt(a) - parseInt(b))
    };
});

// FIX LOGIKA PENCARIAN PRODUK: Lebih fleksibel agar tombol Selesai bisa aktif
const autoSelectedProduct = computed(() => {
    if (!selectedBrand.value || !selectedTypeName.value) return null;

    // 1. Cari produk yang Merk dan Namanya pas
    const matches = products.value.filter(p => {
        const matchBrand = p.brand_id === selectedBrand.value;
        const matchName = p.name.toLowerCase().trim() === selectedTypeName.value.toLowerCase().trim();
        return matchBrand && matchName;
    });

    if (matches.length === 0) return null;

    // 2. Filter berdasarkan RAM/ROM jika staff sudah memilih (bukan empty string)
    const found = matches.find(p => {
        // Membersihkan string "8GB GB" menjadi "8"
        const cleanSelectedRam = selectedRam.value ? String(selectedRam.value).replace(/[^0-9]/g, '') : null;
        const cleanSelectedStorage = selectedStorage.value ? String(selectedStorage.value).replace(/[^0-9]/g, '') : null;

        const matchRam = !selectedRam.value || String(p.ram) === cleanSelectedRam;
        const matchStorage = !selectedStorage.value || String(p.storage) === cleanSelectedStorage;

        return matchRam && matchStorage;
    });

    // 3. Jika spek belum dipilih, ambil item pertama dari tipe tersebut agar tombol tetap bisa aktif
    return found ? found.id : (matches.length > 0 && !selectedRam.value && !selectedStorage.value ? matches[0].id : null);
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
        if (isManualDistributor.value) return newDistributorName.value.trim().length >= 2;
        return !!selectedDistributor.value;
    }
    return false;
});

// FIX TOMBOL SELESAI & SIMPAN
const canSubmit = computed(() => {
    if (!selectedProduct.value) return false;

    if (itemType.value === 'hp') {
        return imeiRows.value.length > 0 && imeiRows.value.every(r => r.imei.trim().length >= 5 && r.cost_price > 0);
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
    <div class="space-y-6 animate-in fade-in max-w-4xl mx-auto pb-20 font-sans">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold text-text-primary tracking-tight flex items-center gap-2">
                    <Box :size="28" class="text-blue-500" /> Input Barang Masuk
                </h1>
                <p class="text-text-secondary mt-1 text-sm">Input persediaan stok baru (Step {{ currentStep }}/4)</p>
            </div>
        </div>

        <div class="flex items-center justify-between mb-8 px-4">
            <div v-for="step in [1, 2, 3, 4]" :key="step" class="flex items-center">
                <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm transition-all duration-300"
                    :class="currentStep >= step ? 'bg-primary-500 text-white ring-4 ring-primary-500/20' : 'bg-surface-800 text-text-secondary'">
                    {{ step }}
                </div>
                <div v-if="step < 4" class="w-16 h-1 mx-2 rounded-full transition-colors duration-300"
                    :class="currentStep > step ? 'bg-primary-500' : 'bg-surface-800'"></div>
            </div>
        </div>

        <div
            class="card p-8 border-t-4 border-t-primary-500 min-h-[400px] flex flex-col shadow-2xl bg-surface-800 rounded-2xl">
            <div v-if="currentStep === 1" class="space-y-6 animate-in slide-in-from-right">
                <h2 class="text-xl font-bold text-text-primary">Pilih Akun / User Target</h2>
                <div v-if="isLoading" class="flex justify-center py-12">
                    <Loader2 class="animate-spin text-primary-500" :size="40" />
                </div>
                <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div v-for="user in targetUsers" :key="user.id" @click="selectUserPlacement(user)"
                        class="p-5 rounded-2xl border border-surface-700 bg-surface-900 cursor-pointer hover:border-primary-500 hover:bg-surface-800 transition-all group relative overflow-hidden">
                        <div v-if="placementLabel === (user.full_name || user.name)"
                            class="absolute top-3 right-3 text-primary-500">
                            <CheckCircle2 :size="24" />
                        </div>
                        <div class="flex items-center gap-4">
                            <div
                                class="w-14 h-14 rounded-2xl bg-gradient-to-br from-primary-500 to-indigo-600 flex items-center justify-center text-white font-bold text-xl">
                                {{ user.name.charAt(0).toUpperCase() }}
                            </div>
                            <div>
                                <h3 class="font-bold text-text-primary group-hover:text-primary-400 text-lg">{{
                                    user.full_name || user.name }}</h3>
                                <span
                                    class="text-xs px-2.5 py-1 rounded-lg bg-surface-700 text-primary-400 border border-surface-600 font-medium uppercase tracking-wider">
                                    {{ user.roles?.[0]?.name || 'Staff' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="currentStep === 2" class="space-y-6 animate-in slide-in-from-right">
                <h2 class="text-xl font-bold text-text-primary">Pilih Tipe Produk</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <button @click="itemType = 'hp'"
                        class="p-8 rounded-3xl border-2 transition-all flex flex-col items-center gap-5 hover:scale-[1.02]"
                        :class="itemType === 'hp' ? 'border-primary-500 bg-primary-500/10 shadow-lg shadow-primary-500/10' : 'border-surface-700 bg-surface-900'">
                        <Smartphone :size="56"
                            :class="itemType === 'hp' ? 'text-primary-500' : 'text-text-secondary'" />
                        <span class="text-xl font-bold"
                            :class="itemType === 'hp' ? 'text-white' : 'text-text-secondary'">Handphone / IMEI</span>
                    </button>
                    <button @click="itemType = 'non-hp'"
                        class="p-8 rounded-3xl border-2 transition-all flex flex-col items-center gap-5 hover:scale-[1.02]"
                        :class="itemType === 'non-hp' ? 'border-primary-500 bg-primary-500/10 shadow-lg shadow-primary-500/10' : 'border-surface-700 bg-surface-900'">
                        <Box :size="56" :class="itemType === 'non-hp' ? 'text-primary-500' : 'text-text-secondary'" />
                        <span class="text-xl font-bold"
                            :class="itemType === 'non-hp' ? 'text-white' : 'text-text-secondary'">Produk Biasa</span>
                    </button>
                </div>
            </div>

            <div v-if="currentStep === 3" class="space-y-6 animate-in slide-in-from-right">
                <h2 class="text-xl font-bold text-text-primary">Pilih Distributor</h2>
                <div class="bg-surface-900 p-8 rounded-3xl border border-surface-700 shadow-inner">
                    <label class="label text-xs uppercase font-black tracking-widest text-text-secondary mb-4">Pemasok
                        Barang</label>
                    <div class="flex gap-3">
                        <select v-if="!isManualDistributor" v-model="selectedDistributor"
                            class="input flex-1 h-14 text-lg bg-surface-800">
                            <option value="" disabled>-- Pilih dari Daftar --</option>
                            <option v-for="d in distributors" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                        <input v-else v-model="newDistributorName" placeholder="Nama distributor baru..."
                            class="input flex-1 h-14 text-lg bg-surface-800" />
                        <button type="button" @click="isManualDistributor = !isManualDistributor"
                            class="btn btn-outline h-14 w-14 border-surface-700 flex-shrink-0"
                            :class="isManualDistributor ? 'border-primary-500 text-primary-500 bg-primary-500/10' : ''">
                            <component :is="isManualDistributor ? List : Plus" :size="24" />
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="currentStep === 4" class="space-y-6 animate-in slide-in-from-right">
                <div
                    class="grid grid-cols-1 md:grid-cols-3 gap-3 bg-surface-900 rounded-2xl p-4 border border-surface-700 shadow-inner">
                    <div class="flex items-center gap-3 px-3">
                        <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center text-blue-500">
                            <Building :size="16" />
                        </div>
                        <div class="flex flex-col"><span
                                class="text-[10px] text-text-secondary uppercase font-bold">Akun</span><span
                                class="font-bold text-text-primary text-sm truncate uppercase">{{ placementName
                                }}</span></div>
                    </div>
                    <div class="flex items-center gap-3 px-3 border-l border-surface-700">
                        <div
                            class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center text-emerald-500">
                            <Box :size="16" />
                        </div>
                        <div class="flex flex-col"><span
                                class="text-[10px] text-text-secondary uppercase font-bold">Tipe</span><span
                                class="font-bold text-text-primary text-sm truncate uppercase">{{ itemType }}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 px-3 border-l border-surface-700">
                        <div
                            class="w-8 h-8 rounded-lg bg-orange-500/10 flex items-center justify-center text-orange-500">
                            <Truck :size="16" />
                        </div>
                        <div class="flex flex-col"><span
                                class="text-[10px] text-text-secondary uppercase font-bold">Dist</span><span
                                class="font-bold text-text-primary text-sm truncate uppercase">{{
                                selectedDistributorName }}</span></div>
                    </div>
                </div>

                <div
                    class="grid grid-cols-1 md:grid-cols-2 gap-5 bg-surface-900/50 p-8 rounded-3xl border border-surface-700">
                    <div class="space-y-2">
                        <label class="label text-xs uppercase font-bold text-text-secondary">Merk <span
                                class="text-red-500">*</span></label>
                        <select v-model="selectedBrand" class="input bg-surface-900 h-12">
                            <option :value="null">-- Pilih Merk --</option>
                            <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <label class="label text-xs uppercase font-bold text-text-secondary">Tipe Model <span
                                class="text-red-500">*</span></label>
                        <select v-model="selectedTypeName" :disabled="!selectedBrand"
                            class="input bg-surface-900 h-12 disabled:opacity-30">
                            <option value="">-- Pilih Tipe --</option>
                            <option v-for="name in uniqueTypeNames" :key="name" :value="name">{{ name }}</option>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <label class="label text-xs uppercase font-bold text-text-secondary">RAM</label>
                        <select v-model="selectedRam" :disabled="!selectedTypeName"
                            class="input bg-surface-900 h-12 disabled:opacity-30">
                            <option value="">-- Semua RAM --</option>
                            <option v-for="ram in availableSpecs.rams" :key="ram" :value="ram">{{ ram }}</option>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <label class="label text-xs uppercase font-bold text-text-secondary">ROM (Penyimpanan)</label>
                        <select v-model="selectedStorage" :disabled="!selectedTypeName"
                            class="input bg-surface-900 h-12 disabled:opacity-30">
                            <option value="">-- Semua ROM --</option>
                            <option v-for="st in availableSpecs.storages" :key="st" :value="st">{{ st }}</option>
                        </select>
                    </div>
                    <div v-if="!selectedProduct && selectedTypeName"
                        class="col-span-full py-2 px-4 bg-red-500/10 border border-red-500/20 rounded-xl text-red-400 text-xs flex items-center gap-2 animate-pulse">
                        <XCircle :size="14" /> Kombinasi spek belum terdaftar di Master Data.
                    </div>
                </div>

                <div v-if="itemType === 'hp'" class="space-y-4">
                    <div v-for="(row, index) in imeiRows" :key="index"
                        class="p-6 bg-surface-900/50 rounded-3xl border border-surface-700 relative group animate-in zoom-in-95 duration-300">
                        <button @click="removeImeiRow(index)" v-if="imeiRows.length > 1"
                            class="absolute -top-2 -right-2 h-8 w-8 bg-surface-700 hover:bg-red-600 text-white rounded-full flex items-center justify-center shadow-lg transition-all opacity-0 group-hover:opacity-100 z-10">
                            <Trash2 :size="14" />
                        </button>
                        <h4 class="text-[10px] font-black text-surface-500 mb-4 uppercase tracking-widest text-center">
                            Unit ke-{{ index + 1 }}</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
                            <div class="space-y-1.5"><label
                                    class="text-[10px] font-bold text-text-secondary uppercase">IMEI</label><input
                                    v-model="row.imei" class="input bg-surface-900 font-mono text-sm"
                                    placeholder="Scan..." /></div>
                            <div class="space-y-1.5"><label
                                    class="text-[10px] font-bold text-text-secondary uppercase">Kondisi</label><select
                                    v-model="row.condition" class="input bg-surface-900">
                                    <option value="new">Baru</option>
                                    <option value="second">Bekas</option>
                                </select></div>
                            <div class="space-y-1.5"><label
                                    class="text-[10px] font-bold text-emerald-500 uppercase">Modal ({{
                                    formatRupiah(row.cost_price) }})</label><input v-model="row.cost_price"
                                    type="number" class="input bg-surface-900" /></div>
                            <div class="space-y-1.5"><label class="text-[10px] font-bold text-blue-500 uppercase">Jual
                                    ({{ formatRupiah(row.selling_price) }})</label><input v-model="row.selling_price"
                                    type="number" class="input bg-surface-900" /></div>
                        </div>
                    </div>
                    <button @click="addImeiRow"
                        class="btn btn-outline w-full border-dashed border-2 py-4 text-text-secondary hover:text-primary-500 hover:bg-primary-500/5 border-surface-700 rounded-3xl">
                        <Plus :size="20" class="mr-2" /> Tambah Unit Lain (Batch)
                    </button>
                </div>

                <div v-else
                    class="p-10 bg-surface-900/50 rounded-3xl border border-surface-700 text-center shadow-inner">
                    <label class="label text-sm uppercase font-bold text-text-secondary mb-6 block">Jumlah Stok
                        Masuk</label>
                    <div class="flex justify-center items-center gap-6">
                        <button @click="nonHpForm.quantity > 1 ? nonHpForm.quantity-- : null"
                            class="w-16 h-16 rounded-2xl bg-surface-900 hover:bg-surface-700 text-2xl font-bold border border-surface-700 shadow-lg transition-all">-</button>
                        <input v-model="nonHpForm.quantity" type="number"
                            class="w-40 text-center text-5xl font-black bg-transparent border-none text-text-primary focus:ring-0"
                            min="1" />
                        <button @click="nonHpForm.quantity++"
                            class="w-16 h-16 rounded-2xl bg-surface-900 hover:bg-surface-700 text-2xl font-bold border border-surface-700 shadow-lg transition-all">+</button>
                    </div>
                </div>
            </div>

            <div class="mt-auto pt-8 border-t border-surface-700 flex justify-between gap-4">
                <button v-if="currentStep > 1" @click="prevStep"
                    class="btn btn-secondary px-8 bg-surface-900 hover:bg-surface-700 h-14 rounded-2xl flex-1 md:flex-none uppercase text-xs tracking-widest font-black">
                    <ChevronLeft :size="18" class="mr-2" /> Kembali
                </button>
                <div v-else class="flex-1 md:flex-none"></div>

                <button v-if="currentStep < 4" @click="nextStep" :disabled="!canNext"
                    class="btn btn-primary px-10 h-14 rounded-2xl flex-1 md:flex-none uppercase text-xs tracking-widest font-black shadow-xl shadow-primary-600/20 disabled:grayscale">Lanjut
                    <ChevronRight :size="18" class="ml-2" />
                </button>

                <button v-if="currentStep === 4" @click="submitStockIn" :disabled="!canSubmit || isSubmitting"
                    class="btn btn-primary px-10 h-14 rounded-2xl flex-1 md:flex-none uppercase text-xs tracking-widest font-black shadow-xl shadow-emerald-600/20 disabled:grayscale">
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
    @apply block text-text-secondary mb-2 font-semibold;
}

.input {
    @apply w-full border border-surface-700 rounded-2xl px-5 text-text-primary focus:outline-none focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 transition-all placeholder:text-surface-600 shadow-inner;
}

.btn {
    @apply font-bold transition-all duration-300 disabled:opacity-20 disabled:cursor-not-allowed flex items-center justify-center;
}

.btn-primary {
    @apply bg-primary-600 hover:bg-primary-500 text-white shadow-lg;
}

.btn-secondary {
    @apply bg-surface-700 hover:bg-surface-600 text-text-secondary hover:text-white border border-surface-600;
}

.btn-outline {
    @apply border border-surface-700 hover:border-primary-500/50 text-text-secondary hover:text-primary-500 shadow-sm;
}

/* Chrome, Safari, Edge, Opera */
input::-webkit-outer-spin-button,
input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

/* Firefox */
input[type=number] {
    -moz-appearance: textfield;
}
</style>
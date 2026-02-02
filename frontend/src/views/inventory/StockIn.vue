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

// Step 1: Placement
const placementType = ref("branch");
const placementId = ref(null);
const placementName = computed(() => placementLabel.value || "Lokasi Belum Terpilih");

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

// Hierarchical Selection State
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

// Selection Logic
const filteredTypes = computed(() => {
    if (!selectedBrand.value) return [];
    return allowedTypes.value.filter(t => t.brand_id === selectedBrand.value);
});

const uniqueTypeNames = computed(() => Array.from(new Set(filteredTypes.value.map(t => t.name))));

const availableSpecs = computed(() => {
    if (!selectedTypeName.value) return { rams: [], storages: [] };
    const matching = allowedTypes.value.filter(t => t.name === selectedTypeName.value);
    return {
        rams: Array.from(new Set(matching.map(t => t.ram).filter(Boolean))).sort((a, b) => parseInt(a) - parseInt(b)),
        storages: Array.from(new Set(matching.map(t => t.storage).filter(Boolean))).sort((a, b) => parseInt(a) - parseInt(b))
    };
});

// FIX LOGIKA: Pencocokan Super Akurat
const autoSelectedProduct = computed(() => {
    if (!selectedBrand.value || !selectedTypeName.value) return null;

    // Filter produk berdasarkan Brand dan Nama Tipe dulu
    const baseMatches = products.value.filter(p => {
        return p.brand_id === selectedBrand.value &&
            p.name.toLowerCase().trim().includes(selectedTypeName.value.toLowerCase().trim());
    });

    if (baseMatches.length === 0) return null;

    // Cari yang RAM dan ROM nya cocok (setelah dibersihkan dari huruf)
    const finalMatch = baseMatches.find(p => {
        const cleanDBRam = String(p.ram || '').replace(/[^0-9]/g, '');
        const cleanDBStorage = String(p.storage || '').replace(/[^0-9]/g, '');
        const cleanSelRam = String(selectedRam.value || '').replace(/[^0-9]/g, '');
        const cleanSelStorage = String(selectedStorage.value || '').replace(/[^0-9]/g, '');

        const matchRam = !selectedRam.value || cleanDBRam === cleanSelRam;
        const matchStorage = !selectedStorage.value || cleanDBStorage === cleanSelStorage;

        return matchRam && matchStorage;
    });

    // Jika tidak ketemu yang spesifik tapi Merk & Tipe sudah ada, ambil yang pertama sebagai fallback
    return finalMatch ? finalMatch.id : baseMatches[0].id;
});

watch(autoSelectedProduct, (newId) => { selectedProduct.value = newId; });
watch(selectedBrand, () => { selectedTypeName.value = ""; selectedRam.value = ""; selectedStorage.value = ""; });
watch(selectedTypeName, () => { selectedRam.value = ""; selectedStorage.value = ""; });

const canNext = computed(() => {
    if (currentStep.value === 1) return !!placementId.value;
    if (currentStep.value === 2) return !!itemType.value;
    if (currentStep.value === 3) return isManualDistributor.value ? newDistributorName.value.length >= 2 : !!selectedDistributor.value;
    return false;
});

// FIX: Tombol Aktif Selama Produk Terpilih
const canSubmit = computed(() => {
    if (!selectedProduct.value) return false;
    if (itemType.value === 'hp') {
        return imeiRows.value.every(r => r.imei.length >= 5 && r.cost_price > 0);
    }
    return nonHpForm.value.quantity > 0;
});

async function fetchInitialData() {
    isLoading.value = true;
    try {
        const [dist, user, brd, typ, prd] = await Promise.all([
            distributorsApi.list(), usersApi.list(), brandsApi.list(),
            productTypesApi.list(), inventoryApi.getProductsLookup({ type: 'hp' })
        ]);
        distributors.value = dist.data.data;
        brands.value = brd.data.data || brd.data;
        allowedTypes.value = typ.data.data || typ.data;
        products.value = prd.data;
        targetUsers.value = (user.data.data || user.data).filter(u => u.roles?.some(r => r.name === 'toko_online') || u.id === authStore.user?.id);
    } catch (e) { toast.error("Gagal load data"); }
    finally { isLoading.value = false; }
}

function selectUserPlacement(user) {
    placementId.value = user.online_shop_id || user.warehouse_id || user.branch_id;
    placementType.value = user.online_shop_id ? 'online_shop' : (user.warehouse_id ? 'warehouse' : 'branch');
    placementLabel.value = user.full_name || user.name;
    nextStep();
}

async function submitStockIn() {
    isSubmitting.value = true;
    try {
        const payload = {
            product_id: selectedProduct.value,
            distributor_id: isManualDistributor.value ? null : selectedDistributor.value,
            new_distributor_name: isManualDistributor.value ? newDistributorName.value : null,
            type: itemType.value, placement_type: placementType.value, placement_id: placementId.value,
        };
        if (itemType.value === 'hp') payload.imeis = imeiRows.value;
        else payload.quantity = nonHpForm.value.quantity;

        await inventoryApi.stockIn(payload);
        toast.success("Stok Berhasil!");
        currentStep.value = 1;
    } catch (e) { toast.error("Gagal simpan"); }
    finally { isSubmitting.value = false; }
}

onMounted(fetchInitialData);
</script>

<template>
    <div class="space-y-6 animate-in fade-in max-w-4xl mx-auto pb-20">
        <h1 class="text-2xl font-bold text-text-primary flex items-center gap-2">
            <Box :size="28" class="text-blue-500" /> Input Barang Masuk
        </h1>

        <div class="flex items-center justify-between mb-8 px-4">
            <div v-for="step in [1, 2, 3, 4]" :key="step" class="flex items-center">
                <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm"
                    :class="currentStep >= step ? 'bg-primary-500 text-white' : 'bg-surface-800 text-text-secondary'">{{
                        step }}</div>
                <div v-if="step < 4" class="w-16 h-1 mx-2 rounded-full"
                    :class="currentStep > step ? 'bg-primary-500' : 'bg-surface-800'"></div>
            </div>
        </div>

        <div class="card p-8 border-t-4 border-t-primary-500 bg-surface-800 rounded-2xl shadow-2xl">
            <div v-if="currentStep === 1" class="grid grid-cols-1 md:grid-cols-2 gap-4 animate-in slide-in-from-right">
                <div v-for="user in targetUsers" :key="user.id" @click="selectUserPlacement(user)"
                    class="p-5 rounded-2xl border border-surface-700 bg-surface-900 cursor-pointer hover:border-primary-500 transition-all relative">
                    <div v-if="placementLabel === (user.full_name || user.name)"
                        class="absolute top-3 right-3 text-primary-500">
                        <CheckCircle2 :size="24" />
                    </div>
                    <div class="flex items-center gap-4">
                        <div
                            class="w-12 h-12 rounded-xl bg-primary-500 flex items-center justify-center text-white font-bold">
                            {{ user.name[0] }}</div>
                        <div>
                            <h3 class="font-bold text-text-primary">{{ user.full_name || user.name }}</h3><span
                                class="text-xs text-text-secondary uppercase">{{ user.roles?.[0]?.name }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="currentStep === 2" class="grid grid-cols-1 md:grid-cols-2 gap-6 animate-in slide-in-from-right">
                <button @click="itemType = 'hp'"
                    class="p-8 rounded-3xl border-2 transition-all flex flex-col items-center gap-4"
                    :class="itemType === 'hp' ? 'border-primary-500 bg-primary-500/10' : 'border-surface-700 bg-surface-900'">
                    <Smartphone :size="48" class="text-primary-500" /><span class="font-bold">HP / IMEI</span>
                </button>
                <button @click="itemType = 'non-hp'"
                    class="p-8 rounded-3xl border-2 transition-all flex flex-col items-center gap-4"
                    :class="itemType === 'non-hp' ? 'border-primary-500 bg-primary-500/10' : 'border-surface-700 bg-surface-900'">
                    <Box :size="48" class="text-primary-500" /><span class="font-bold">Produk Biasa</span>
                </button>
            </div>

            <div v-if="currentStep === 3"
                class="bg-surface-900 p-8 rounded-3xl border border-surface-700 animate-in slide-in-from-right">
                <label class="label text-xs uppercase font-black text-text-secondary mb-4">Pemasok</label>
                <div class="flex gap-3">
                    <select v-if="!isManualDistributor" v-model="selectedDistributor"
                        class="input flex-1 bg-surface-800">
                        <option value="" disabled>-- Pilih Daftar --</option>
                        <option v-for="d in distributors" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </select>
                    <input v-else v-model="newDistributorName" placeholder="Nama baru..."
                        class="input flex-1 bg-surface-800" />
                    <button @click="isManualDistributor = !isManualDistributor" class="btn btn-outline w-14 h-14"
                        :class="isManualDistributor ? 'text-primary-500 border-primary-500' : ''">
                        <component :is="isManualDistributor ? List : Plus" />
                    </button>
                </div>
            </div>

            <div v-if="currentStep === 4" class="space-y-6 animate-in slide-in-from-right">
                <div
                    class="grid grid-cols-3 gap-3 bg-surface-900 rounded-2xl p-4 border border-surface-700 text-[10px] font-bold uppercase tracking-widest text-text-secondary">
                    <div class="px-2">Akun: <span class="text-text-primary">{{ placementName }}</span></div>
                    <div class="px-2 border-l border-surface-700">Tipe: <span class="text-text-primary">{{ itemType
                    }}</span></div>
                    <div class="px-2 border-l border-surface-700">Dist: <span class="text-text-primary">{{
                        selectedDistributorName }}</span></div>
                </div>

                <div class="grid grid-cols-2 gap-5 bg-surface-900/50 p-8 rounded-3xl border border-surface-700">
                    <div><label class="label text-[10px] uppercase">Merk</label><select v-model="selectedBrand"
                            class="input bg-surface-900">
                            <option :value="null">-- Pilih Merk --</option>
                            <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select></div>
                    <div><label class="label text-[10px] uppercase">Tipe</label><select v-model="selectedTypeName"
                            :disabled="!selectedBrand" class="input bg-surface-900 disabled:opacity-30">
                            <option value="">-- Pilih Tipe --</option>
                            <option v-for="n in uniqueTypeNames" :key="n" :value="n">{{ n }}</option>
                        </select></div>
                    <div><label class="label text-[10px] uppercase">RAM</label><select v-model="selectedRam"
                            :disabled="!selectedTypeName" class="input bg-surface-900 disabled:opacity-30">
                            <option value="">-- Semua --</option>
                            <option v-for="r in availableSpecs.rams" :key="r" :value="r">{{ r }}</option>
                        </select></div>
                    <div><label class="label text-[10px] uppercase">ROM</label><select v-model="selectedStorage"
                            :disabled="!selectedTypeName" class="input bg-surface-900 disabled:opacity-30">
                            <option value="">-- Semua --</option>
                            <option v-for="s in availableSpecs.storages" :key="s" :value="s">{{ s }}</option>
                        </select></div>
                    <div v-if="!selectedProduct && selectedTypeName"
                        class="col-span-full text-red-400 text-[10px] animate-pulse">
                        <XCircle :size="12" class="inline mr-1" /> Spek belum terdaftar, tapi sistem akan mencoba
                        mapping otomatis ke Tipe Model terdekat.
                    </div>
                </div>

                <div v-if="itemType === 'hp'" class="space-y-4">
                    <div v-for="(row, index) in imeiRows" :key="index"
                        class="p-6 bg-surface-900/50 rounded-3xl border border-surface-700 relative group animate-in zoom-in-95">
                        <button @click="removeImeiRow(index)" v-if="imeiRows.length > 1"
                            class="absolute -top-2 -right-2 h-8 w-8 bg-red-600 text-white rounded-full opacity-0 group-hover:opacity-100 transition-all z-10">
                            <Trash2 :size="14" />
                        </button>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
                            <div><label class="label text-[10px] uppercase">IMEI</label><input v-model="row.imei"
                                    class="input bg-surface-900 font-mono text-sm" placeholder="Scan..." /></div>
                            <div><label class="label text-[10px] uppercase">Kondisi</label><select
                                    v-model="row.condition" class="input bg-surface-900">
                                    <option value="new">Baru</option>
                                    <option value="second">Bekas</option>
                                </select></div>
                            <div><label class="label text-[10px] uppercase text-emerald-500">Modal ({{
                                formatRupiah(row.cost_price) }})</label><input v-model="row.cost_price"
                                    type="number" class="input bg-surface-900" /></div>
                            <div><label class="label text-[10px] uppercase text-blue-500">Jual ({{
                                formatRupiah(row.selling_price) }})</label><input v-model="row.selling_price"
                                    type="number" class="input bg-surface-900" /></div>
                        </div>
                    </div>
                    <button @click="addImeiRow" class="btn btn-outline w-full border-dashed border-2 py-4 rounded-3xl">
                        <Plus :size="20" class="mr-2" /> Tambah Unit
                    </button>
                </div>
            </div>

            <div class="mt-auto pt-8 border-t border-surface-700 flex justify-between gap-4">
                <button v-if="currentStep > 1" @click="prevStep"
                    class="btn btn-secondary px-8 h-14 rounded-2xl uppercase text-[10px] tracking-widest">
                    <ChevronLeft :size="18" /> Kembali
                </button>
                <div v-else></div>
                <button v-if="currentStep < 4" @click="nextStep" :disabled="!canNext"
                    class="btn btn-primary px-10 h-14 rounded-2xl uppercase text-[10px] tracking-widest font-black">Lanjut
                    <ChevronRight :size="18" />
                </button>
                <button v-if="currentStep === 4" @click="submitStockIn" :disabled="!canSubmit || isSubmitting"
                    class="btn btn-primary px-10 h-14 rounded-2xl uppercase text-[10px] tracking-widest font-black shadow-xl shadow-emerald-600/20">
                    <Loader2 v-if="isSubmitting" class="animate-spin mr-2" />
                    {{ isSubmitting ? 'Proses...' : 'Selesai & Simpan' }}
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
    @apply bg-primary-600 hover:bg-primary-500 text-white;
}

.btn-secondary {
    @apply bg-surface-700 hover:bg-surface-600 text-text-secondary hover:text-white border border-surface-600;
}

.btn-outline {
    @apply border border-surface-700 hover:border-primary-500/50 text-text-secondary hover:text-primary-500;
}

input::-webkit-outer-spin-button,
input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
</style>
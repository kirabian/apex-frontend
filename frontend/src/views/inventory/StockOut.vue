<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { useRouter } from "vue-router";
import { useToast } from "../../composables/useToast";
import { inventory as inventoryApi, branches as branchesApi, warehouses as warehousesApi } from "../../api/axios";
import api from "../../api/axios";
import {
    Package,
    ArrowRightFromLine,
    Building2,
    AlertTriangle,
    RotateCcw,
    ShoppingBag,
    CheckCircle2,
    Loader2,
    ScanBarcode,
    ChevronLeft,
    Smartphone,
    X,
    Search,
    Gift,
    Trophy,
    UserCheck,
    Calendar,
    Percent,
    Archive,
    Upload,
    Warehouse
} from "lucide-vue-next";
import { useAuthStore } from "../../store/auth";

const toast = useToast();
const router = useRouter();
const authStore = useAuthStore();

// State
const isLoading = ref(false);
const isSubmitting = ref(false);
const showForm = ref(false);
const searchQuery = ref("");
const inventoryItems = ref([]);
const selectedItems = ref([]);
const branches = ref([]);
const warehouses = ref([]);
const currentBranch = ref(null);

// Categories
const allCategories = [
    { id: 'pindah_cabang', name: 'Pindah Cabang', icon: Building2, color: 'blue' },
    { id: 'kesalahan_input', name: 'Kesalahan Input', icon: AlertTriangle, color: 'amber' },
    { id: 'retur', name: 'Retur', icon: RotateCcw, color: 'purple' },
    { id: 'shopee', name: 'Shopee', icon: ShoppingBag, color: 'orange' },
    { id: 'giveaway', name: 'Giveaway Customer', icon: Gift, color: 'pink' },
    { id: 'hadiah', name: 'Hadiah', icon: Trophy, color: 'yellow' },
    { id: 'brand_ambassador', name: 'Brand Ambassador', icon: UserCheck, color: 'indigo' },
    { id: 'event', name: 'Event / Sponsorship', icon: Calendar, color: 'cyan' },
    { id: 'promo', name: 'Promo', icon: Percent, color: 'red' },
    { id: 'inventaris', name: 'Inventaris', icon: Archive, color: 'slate' },
];

const categories = computed(() => {
    // Always show all categories, handled by click event
    return allCategories;
});

const selectedCategory = ref(null);
const showReturnBlockedAlert = ref(false);

function selectCategory(category) {
    if (category.id === 'retur') {
        // Check warehouses for return policy.
        // If "Universal" limit applies, we check if *any* warehouse accepts returns, or specifically the first one 
        // as a proxy for the "Global Switch".
        // Assuming fetchWarehouses() called on mount or we check here.
        if (warehouses.value.length > 0) {
            // For safety, assume if the first warehouse (Representative) is OFF, then ALL are OFF.
            if (!warehouses.value[0].can_accept_returns) {
                showReturnBlockedAlert.value = true;
                return;
            }
        }
    }
    selectedCategory.value = category.id;
    // Reset form fields specifically if needed or leave as is
}

// Form Fields
const form = ref({
    // Pindah Cabang
    destination_branch_id: null,
    receiver_name: '',
    transfer_notes: '',
    // Kesalahan Input
    deletion_reason: '',
    // Retur
    retur_officer: '',
    retur_seal: '',
    retur_issue: '',
    customer_name: '',
    customer_phone: '',
    return_destination_id: null, // Warehouse ID
    proof_image: null, // Will be handled by separate ref for preview
    // Shopee
    shopee_receiver: '',
    shopee_phone: '',
    shopee_address: '',
    shopee_notes: '',
    shopee_tracking_no: '',
    // Generic Note (for other categories)
    notes: '',
});

// Barcode Scanner State
const isScanning = ref(false);
const videoRef = ref(null);
let barcodeDetector = null;
let scanStream = null;

// Fetch inventory items
async function fetchInventory() {
    isLoading.value = true;
    try {
        const response = await inventoryApi.list({ status: 'available' });
        inventoryItems.value = response.data.data || response.data;
    } catch (e) {
        toast.error("Gagal memuat data inventory");
    } finally {
        isLoading.value = false;
    }
}

// Fetch branches and warehouses
async function fetchBranches() {
    try {
        const response = await branchesApi.list();
        branches.value = response.data.data || response.data;
    } catch (e) {
        console.error("Gagal memuat cabang", e);
    }
}

async function fetchWarehouses() {
    try {
        const response = await warehousesApi.list();
        warehouses.value = response.data.data || response.data;
    } catch (e) {
        console.error("Gagal memuat gudang", e);
    }
}

// File Upload State
const proofImageFile = ref(null);
const proofImagePreview = ref(null);

function handleFileChange(event) {
    const file = event.target.files[0];
    if (file) {
        // Validate size 10MB
        if (file.size > 10 * 1024 * 1024) {
            toast.error("Ukuran file maksimal 10MB");
            event.target.value = '';
            return;
        }
        proofImageFile.value = file;
        proofImagePreview.value = URL.createObjectURL(file);
    }
}

// Filtered items
const filteredItems = computed(() => {
    if (!searchQuery.value) return inventoryItems.value;
    const q = searchQuery.value.toLowerCase();
    return inventoryItems.value.filter(item =>
        item.imei?.toLowerCase().includes(q) ||
        item.product?.name?.toLowerCase().includes(q) ||
        item.product?.sku?.toLowerCase().includes(q)
    );
});

// Toggle selection
function toggleSelect(item) {
    const idx = selectedItems.value.findIndex(i => i.id === item.id);
    if (idx === -1) {
        selectedItems.value.push(item);
    } else {
        selectedItems.value.splice(idx, 1);
    }
}

function isSelected(item) {
    return selectedItems.value.some(i => i.id === item.id);
}

// Open form
async function openStockOutForm() {
    if (selectedItems.value.length === 0) {
        toast.error("Pilih minimal 1 barang");
        return;
    }

    // Attempt to determine branch context from selected items
    // Assuming all selected items belong to the same branch for a single transaction
    // If mixed, we might default to the first one or error out. For now, take the first.
    const firstItem = selectedItems.value[0];
    if (firstItem && firstItem.placement_id) {
        // If we don't have a currentBranch set (e.g. Super Admin), or it's different
        if (!currentBranch.value || currentBranch.value.id !== firstItem.placement_id) {
            try {
                // Fetch the branch of the item
                const response = await branchesApi.get(firstItem.placement_id);
                currentBranch.value = response.data.data || response.data;
            } catch (e) {
                console.error("Failed to load item context branch", e);
            }
        }
    }

    showForm.value = true;

    // Fetch dependencies based on category (lazy load if needed)
    fetchWarehouses();
}



// Reset form
function resetForm() {
    form.value = {
        destination_branch_id: null,
        receiver_name: '',
        transfer_notes: '',
        deletion_reason: '',
        retur_officer: '',
        retur_seal: '',
        retur_issue: '',
        customer_name: '',
        customer_phone: '',
        shopee_receiver: '',
        shopee_phone: '',
        shopee_address: '',
        shopee_notes: '',
        shopee_tracking_no: '',
    };
    selectedCategory.value = null;
}

// Close form
function closeForm() {
    showForm.value = false;
    resetForm();
}

// Start barcode scanner
async function startScanner() {
    isScanning.value = true;
    try {
        scanStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment' }
        });

        await new Promise(resolve => setTimeout(resolve, 100));

        if (videoRef.value) {
            videoRef.value.srcObject = scanStream;
            await videoRef.value.play();
        }
        if ('BarcodeDetector' in window) {
            barcodeDetector = new BarcodeDetector({ formats: ['code_128', 'code_39', 'ean_13', 'ean_8', 'qr_code'] });
            detectBarcode();
        } else {
            toast.error("Browser tidak support barcode scanner");
            stopScanner();
        }
    } catch (e) {
        toast.error("Gagal akses kamera");
        stopScanner();
    }
}

async function detectBarcode() {
    if (!isScanning.value || !videoRef.value || !barcodeDetector) return;

    try {
        const barcodes = await barcodeDetector.detect(videoRef.value);
        if (barcodes.length > 0) {
            form.value.shopee_tracking_no = barcodes[0].rawValue;
            toast.success("Barcode terdeteksi!");
            stopScanner();
            return;
        }
    } catch (e) {
        // ignore detection errors
    }

    requestAnimationFrame(detectBarcode);
}

function stopScanner() {
    isScanning.value = false;
    if (scanStream) {
        scanStream.getTracks().forEach(track => track.stop());
        scanStream = null;
    }
}

// Fetch current branch info to check return settings
async function fetchCurrentBranch() {
    if (authStore.userBranch?.id) {
        try {
            const response = await branchesApi.get(authStore.userBranch.id);
            currentBranch.value = response.data.data || response.data;
        } catch (e) {
            console.error("Gagal load info branch", e);
        }
    }
}

// Validation
const canSubmit = computed(() => {
    if (!selectedCategory.value || selectedItems.value.length === 0) return false;

    switch (selectedCategory.value) {
        case 'pindah_cabang':
            return form.value.destination_branch_id && form.value.receiver_name;
        case 'kesalahan_input':
            return form.value.deletion_reason.length >= 5;
        case 'retur':
            return form.value.retur_officer && form.value.retur_issue && form.value.customer_name && form.value.customer_phone;
        case 'shopee':
            return form.value.shopee_receiver && form.value.shopee_phone && form.value.shopee_address && form.value.shopee_tracking_no;
        default:
            // For simple categories (Giveaway, Hadiah, etc), maybe just require items selected?
            // Or maybe a note is needed? User didn't specify. Assuming no extra fields for now.
            return true;
    }
});

// Submit
async function submitStockOut() {
    if (!canSubmit.value) return;

    isSubmitting.value = true;
    try {
        const payload = {
            category: selectedCategory.value,
            product_detail_ids: selectedItems.value.map(i => i.id),
            ...form.value
        };

        // Map generic notes if used
        if (form.value.notes) {
            // payload.notes = form.value.notes; // Only if backend accepts it
            // For now backend doesn't seem to have a generic note field for 'out' categories.
            // We'll leave it as is.
        }

        const response = await api.post('/stock-outs', payload);
        toast.success(`Stok berhasil dikeluarkan! ID: ${response.data.data.receipt_id}`);

        // Reset and redirect
        selectedItems.value = [];
        closeForm();
        router.push('/inventory');

    } catch (e) {
        toast.error(e.response?.data?.message || "Gagal keluar stok");
    } finally {
        isSubmitting.value = false;
    }
}

onMounted(() => {
    fetchInventory();
    fetchBranches();
    fetchCurrentBranch();
});
</script>

<template>
    <div class="space-y-6 animate-in fade-in max-w-6xl mx-auto pb-20">
        <!-- Header -->
        <div class="flex justify-between items-end">
            <div>
                <h1 class="text-2xl font-bold text-text-primary flex items-center gap-2">
                    <ArrowRightFromLine :size="28" class="text-orange-500" /> Pengeluaran Stok
                </h1>
                <p class="text-text-secondary mt-1">Pilih barang untuk dikeluarkan dari stok</p>
            </div>
            <button v-if="!showForm" @click="openStockOutForm" :disabled="selectedItems.length === 0"
                class="btn btn-primary px-6 h-12 rounded-2xl font-bold disabled:opacity-30">
                <Package :size="18" class="mr-2" />
                Keluar Stok ({{ selectedItems.length }})
            </button>
        </div>

        <!-- Main Content -->
        <div v-if="!showForm">
            <!-- Search -->
            <div class="card mb-4">
                <div class="relative">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" :size="18" />
                    <input v-model="searchQuery" type="text" placeholder="Cari IMEI, produk, SKU..."
                        class="input pl-10" />
                </div>
            </div>

            <!-- Inventory Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-if="isLoading" class="col-span-full text-center py-12 text-text-secondary">
                    <Loader2 :size="32" class="animate-spin mx-auto mb-2" />
                    Memuat inventory...
                </div>
                <div v-else-if="filteredItems.length === 0" class="col-span-full text-center py-12 text-text-secondary">
                    <Package :size="48" class="mx-auto mb-2 opacity-50" />
                    Tidak ada barang tersedia
                </div>
                <div v-else v-for="item in filteredItems" :key="item.id" @click="toggleSelect(item)"
                    class="card p-4 cursor-pointer border-2 transition-all hover:border-primary-500/50"
                    :class="isSelected(item) ? 'border-primary-500 bg-primary-500/10 ring-2 ring-primary-500/30' : 'border-transparent'">
                    <div class="flex items-start gap-3">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center"
                            :class="isSelected(item) ? 'bg-primary-500 text-white' : 'bg-surface-700'">
                            <CheckCircle2 v-if="isSelected(item)" :size="24" />
                            <Smartphone v-else :size="20" class="text-text-secondary" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-text-primary truncate">{{ item.product?.name }}</p>
                            <p class="text-xs text-text-secondary">{{ item.product?.brand }}</p>
                            <p class="font-mono text-xs bg-surface-700 px-2 py-1 rounded mt-2 w-fit">{{ item.imei }}</p>
                            <p class="text-xs text-text-secondary mt-1" v-if="item.ram || item.storage">
                                {{ item.ram }} / {{ item.storage }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stock Out Form -->
        <div v-else class="space-y-6">
            <button @click="closeForm" class="btn btn-secondary mb-4">
                <ChevronLeft :size="18" class="mr-1" /> Kembali
            </button>

            <!-- Category Selection -->
            <div v-if="!selectedCategory" class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <button v-for="cat in categories" :key="cat.id" @click="selectCategory(cat)"
                    class="card p-6 text-center hover:border-primary-500 border-2 border-transparent transition-all">
                    <component :is="cat.icon" :size="40" class="mx-auto mb-3" :class="`text-${cat.color}-500`" />
                    <p class="font-bold text-text-primary">{{ cat.name }}</p>
                </button>
            </div>

            <!-- Form Based on Category -->
            <div v-else class="card p-8 border-t-4" :class="{
                'border-t-blue-500': selectedCategory === 'pindah_cabang',
                'border-t-amber-500': selectedCategory === 'kesalahan_input',
                'border-t-purple-500': selectedCategory === 'retur',
                'border-t-orange-500': selectedCategory === 'shopee',
                'border-t-pink-500': selectedCategory === 'giveaway',
                'border-t-yellow-500': selectedCategory === 'hadiah',
                'border-t-indigo-500': selectedCategory === 'brand_ambassador',
                'border-t-cyan-500': selectedCategory === 'event',
                'border-t-red-500': selectedCategory === 'promo',
                'border-t-slate-500': selectedCategory === 'inventaris',
            }">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-text-primary">
                        {{categories.find(c => c.id === selectedCategory)?.name}}
                    </h2>
                    <button @click="selectedCategory = null" class="text-text-secondary hover:text-text-primary">
                        <X :size="24" />
                    </button>
                </div>

                <!-- Pindah Cabang Form -->
                <div v-if="selectedCategory === 'pindah_cabang'" class="space-y-4">
                    <div>
                        <label class="label">Cabang Tujuan *</label>
                        <select v-model="form.destination_branch_id" class="input">
                            <option :value="null">-- Pilih Cabang --</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Nama Penerima *</label>
                        <input v-model="form.receiver_name" class="input" placeholder="Nama yang menerima barang" />
                    </div>
                    <div>
                        <label class="label">Catatan</label>
                        <textarea v-model="form.transfer_notes" class="input" rows="3"
                            placeholder="Catatan tambahan..."></textarea>
                    </div>
                </div>

                <!-- Kesalahan Input Form -->
                <div v-if="selectedCategory === 'kesalahan_input'" class="space-y-4">
                    <div>
                        <label class="label">Alasan Hapus *</label>
                        <textarea v-model="form.deletion_reason" class="input" rows="4"
                            placeholder="Jelaskan alasan penghapusan data..."></textarea>
                    </div>
                </div>

                <!-- Retur Form -->
                <div v-if="selectedCategory === 'retur'" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="label">Nama Petugas *</label>
                            <input v-model="form.retur_officer" class="input" placeholder="Nama petugas retur" />
                        </div>
                        <div>
                            <label class="label">Pilih Gudang *</label>
                            <select v-model="form.return_destination_id" class="input">
                                <option :value="null">-- Pilih Gudang Retur --</option>
                                <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="label">Foto Bukti / Kondisi (Max 10MB) *</label>
                        <input type="file" accept="image/*" @change="handleFileChange"
                            class="w-full text-sm text-text-secondary file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-bold file:bg-surface-700 file:text-primary-400 hover:file:bg-surface-600 transition-all cursor-pointer border border-surface-700 rounded-xl bg-surface-800" />
                        <div v-if="proofImagePreview" class="mt-3">
                            <img :src="proofImagePreview"
                                class="h-32 rounded-xl object-cover border border-surface-600" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="label">Segel</label>
                            <input v-model="form.retur_seal" class="input" placeholder="Nomor segel (opsional)" />
                        </div>
                        <div>
                            <label class="label">Nama Customer *</label>
                            <input v-model="form.customer_name" class="input" placeholder="Nama customer" />
                        </div>
                    </div>

                    <div>
                        <label class="label">Kendala / Masalah *</label>
                        <textarea v-model="form.retur_issue" class="input" rows="3"
                            placeholder="Jelaskan kendala atau masalah..."></textarea>
                    </div>

                    <div>
                        <label class="label">No. WA Customer *</label>
                        <input v-model="form.customer_phone" class="input" placeholder="08xxxxxxxxxx" />
                    </div>
                </div>

                <!-- Shopee Form -->
                <div v-if="selectedCategory === 'shopee'" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="label">Nama Penerima *</label>
                            <input v-model="form.shopee_receiver" class="input" placeholder="Nama penerima" />
                        </div>
                        <div>
                            <label class="label">No. WA *</label>
                            <input v-model="form.shopee_phone" class="input" placeholder="08xxxxxxxxxx" />
                        </div>
                    </div>
                    <div>
                        <label class="label">Alamat Tujuan *</label>
                        <textarea v-model="form.shopee_address" class="input" rows="2"
                            placeholder="Alamat lengkap..."></textarea>
                    </div>
                    <div>
                        <label class="label">Catatan</label>
                        <textarea v-model="form.shopee_notes" class="input" rows="2"
                            placeholder="Catatan pengiriman..."></textarea>
                    </div>
                    <div>
                        <label class="label">No. Resi Shopee *</label>
                        <div class="flex gap-2">
                            <input v-model="form.shopee_tracking_no" class="input flex-1 font-mono"
                                placeholder="Scan atau ketik manual..." />
                            <button @click="startScanner" type="button" class="btn btn-secondary px-4"
                                :class="isScanning ? 'bg-orange-500 text-white' : ''">
                                <ScanBarcode :size="20" />
                            </button>
                        </div>
                    </div>

                    <!-- Scanner Modal -->
                    <div v-if="isScanning" class="fixed inset-0 bg-black/90 z-50 flex items-center justify-center">
                        <div class="relative w-full max-w-lg">
                            <video ref="videoRef" class="w-full rounded-2xl" autoplay playsinline muted></video>
                            <div class="absolute inset-0 border-4 border-orange-500/50 rounded-2xl pointer-events-none">
                            </div>
                            <button @click="stopScanner"
                                class="absolute top-4 right-4 bg-white/10 p-3 rounded-full text-white">
                                <X :size="24" />
                            </button>
                            <p class="text-center text-white mt-4 animate-pulse">Arahkan kamera ke barcode resi...</p>
                        </div>
                    </div>
                </div>

                <!-- Selected Items Preview -->
                <div class="mt-6 pt-6 border-t border-surface-700">
                    <p class="text-xs uppercase font-bold text-text-secondary mb-3">Barang yang akan dikeluarkan ({{
                        selectedItems.length }})</p>
                    <div class="flex flex-wrap gap-2">
                        <div v-for="item in selectedItems" :key="item.id"
                            class="bg-surface-700 px-3 py-2 rounded-xl text-sm flex items-center gap-2">
                            <Smartphone :size="14" />
                            <span class="font-mono text-xs">{{ item.imei }}</span>
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <div class="mt-8 flex justify-end">
                    <button @click="submitStockOut" :disabled="!canSubmit || isSubmitting"
                        class="btn btn-primary px-10 h-14 rounded-2xl font-bold text-sm disabled:opacity-30">
                        <Loader2 v-if="isSubmitting" :size="20" class="animate-spin mr-2" />
                        {{ isSubmitting ? 'Memproses...' : 'Konfirmasi Keluar Stok' }}
                    </button>
                </div>
            </div>
        </div>
    </div>


    </button>
    </div>
    </div>
    </div>

    <!-- Alert Modal for Blocked Returns -->
    <div v-if="showReturnBlockedAlert" class="fixed inset-0 bg-black/80 z-[60] flex items-center justify-center p-4">
        <div
            class="bg-surface-800 rounded-2xl max-w-md w-full p-6 border border-red-500/30 shadow-2xl animate-in zoom-in duration-200">
            <div class="flex flex-col items-center text-center">
                <div class="w-16 h-16 rounded-full bg-red-500/10 flex items-center justify-center mb-4">
                    <AlertTriangle :size="32" class="text-red-500" />
                </div>
                <h3 class="text-xl font-bold text-white mb-2">Retur Tidak Diterima</h3>
                <p class="text-text-secondary mb-6">
                    Mohon maaf, gudang saat ini sedang <strong>TIDAK MENERIMA RETUR</strong>.
                    Silakan coba lagi nanti atau hubungi Admin Gudang.
                </p>
                <button @click="showReturnBlockedAlert = false"
                    class="btn bg-surface-700 hover:bg-surface-600 text-white w-full h-12 rounded-xl">
                    Mengerti
                </button>
            </div>
        </div>
    </div>
    </div>
</template>

<style scoped>
@reference "../../style.css";

.label {
    @apply block text-text-secondary mb-2 font-semibold text-sm;
}

.input {
    @apply w-full border border-surface-700 rounded-xl px-4 py-3 bg-surface-800 text-text-primary focus:outline-none focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 transition-all placeholder:text-surface-500;
}

.btn {
    @apply font-bold transition-all duration-300 disabled:opacity-20 disabled:cursor-not-allowed flex items-center justify-center rounded-xl;
}

.btn-primary {
    @apply bg-primary-600 hover:bg-primary-500 text-white;
}

.btn-secondary {
    @apply bg-surface-700 hover:bg-surface-600 text-text-secondary hover:text-white border border-surface-600;
}

.card {
    @apply bg-surface-800 rounded-2xl p-6 border border-surface-700;
}
</style>

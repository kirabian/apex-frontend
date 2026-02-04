<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
import { useRouter } from "vue-router";
import { useToast } from "../../composables/useToast";
import { inventory as inventoryApi, branches as branchesApi, warehouses as warehousesApi } from "../../api/axios";
import api from "../../api/axios";
import { Html5Qrcode } from "html5-qrcode";
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
    return allCategories;
});

const selectedCategory = ref(null);
const showReturnBlockedAlert = ref(false);

function selectCategory(category) {
    if (category.id === 'retur') {
        if (warehouses.value.length > 0) {
            if (!warehouses.value[0].can_accept_returns) {
                showReturnBlockedAlert.value = true;
                return;
            }
        }
    }
    selectedCategory.value = category.id;
}

// Form Fields
const form = ref({
    destination_branch_id: null,
    receiver_name: '',
    transfer_notes: '',
    deletion_reason: '',
    retur_officer: '',
    retur_seal: '',
    retur_issue: '',
    customer_name: '',
    customer_phone: '',
    return_destination_id: null,
    proof_image: null,
    shopee_receiver: '',
    shopee_phone: '',
    shopee_address: '',
    shopee_notes: '',
    shopee_tracking_no: '',
    notes: '',
});

// Barcode Scanner State
const isScanning = ref(false);
const scannerContainerId = 'barcode-scanner-container';
let html5QrCode = null;

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

const proofImageFile = ref(null);
const proofImagePreview = ref(null);

function handleFileChange(event) {
    const file = event.target.files[0];
    if (file) {
        if (file.size > 10 * 1024 * 1024) {
            toast.error("Ukuran file maksimal 10MB");
            event.target.value = '';
            return;
        }
        proofImageFile.value = file;
        proofImagePreview.value = URL.createObjectURL(file);
    }
}

const filteredItems = computed(() => {
    if (!searchQuery.value) return inventoryItems.value;
    const q = searchQuery.value.toLowerCase();
    return inventoryItems.value.filter(item =>
        item.imei?.toLowerCase().includes(q) ||
        item.product?.name?.toLowerCase().includes(q) ||
        item.product?.sku?.toLowerCase().includes(q)
    );
});

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

async function openStockOutForm() {
    if (selectedItems.value.length === 0) {
        toast.error("Pilih minimal 1 barang");
        return;
    }

    const firstItem = selectedItems.value[0];
    if (firstItem && firstItem.placement_id) {
        if (!currentBranch.value || currentBranch.value.id !== firstItem.placement_id) {
            try {
                const response = await branchesApi.get(firstItem.placement_id);
                currentBranch.value = response.data.data || response.data;
            } catch (e) {
                console.error("Failed to load item context branch", e);
            }
        }
    }

    showForm.value = true;
    fetchWarehouses();
}

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

function closeForm() {
    showForm.value = false;
    resetForm();
}

async function startScanner() {
    isScanning.value = true;

    // Wait for DOM to render the container
    await new Promise(resolve => setTimeout(resolve, 100));

    try {
        html5QrCode = new Html5Qrcode(scannerContainerId);

        const config = {
            fps: 10,
            qrbox: { width: 300, height: 150 },
            formatsToSupport: [
                0,  // QR_CODE
                4,  // CODE_128
                2,  // CODE_39
                11, // EAN_13
                10, // EAN_8
            ],
            aspectRatio: 1.7777778, // 16:9
        };

        await html5QrCode.start(
            { facingMode: "environment" },
            config,
            (decodedText) => {
                // On successful scan
                form.value.shopee_tracking_no = decodedText;
                toast.success(`Barcode terdeteksi: ${decodedText}`);
                stopScanner();
            },
            (errorMessage) => {
                // Ignore scan errors (normal when no barcode in view)
            }
        );
    } catch (e) {
        console.error("Scanner error:", e);
        toast.error("Gagal akses kamera. Silakan ketik manual nomor resi.");
        stopScanner();
    }
}

async function stopScanner() {
    isScanning.value = false;
    if (html5QrCode) {
        try {
            await html5QrCode.stop();
            html5QrCode.clear();
        } catch (e) {
            console.error("Error stopping scanner:", e);
        }
        html5QrCode = null;
    }
}

// Cleanup on unmount
onUnmounted(() => {
    if (html5QrCode) {
        html5QrCode.stop().catch(() => { });
    }
});

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
            return true;
    }
});

async function submitStockOut() {
    if (!canSubmit.value) return;

    isSubmitting.value = true;
    try {
        const formData = new FormData();
        formData.append('category', selectedCategory.value);

        // Product IDs
        selectedItems.value.forEach(item => {
            formData.append('product_detail_ids[]', item.id);
        });

        // Form fields
        Object.keys(form.value).forEach(key => {
            if (key !== 'proof_image' && form.value[key] !== null && form.value[key] !== '') {
                formData.append(key, form.value[key]);
            }
        });

        // File upload for retur
        if (selectedCategory.value === 'retur' && proofImageFile.value) {
            formData.append('proof_image', proofImageFile.value);
        }

        const response = await api.post('/stock-outs', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
        toast.success(`Stok berhasil dikeluarkan! ID: ${response.data.data.receipt_id}`);

        selectedItems.value = [];
        closeForm();
        router.push('/inventory');

    } catch (e) {
        toast.error(e.response?.data?.message || "Gagal keluar stok");
        console.error(e);
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

        <div v-if="!showForm">
            <div class="card mb-4">
                <div class="relative">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" :size="18" />
                    <input v-model="searchQuery" type="text" placeholder="Cari IMEI, produk, SKU..."
                        class="input pl-10" />
                </div>
            </div>

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

        <div v-else class="space-y-6">
            <button @click="closeForm" class="btn btn-secondary mb-4">
                <ChevronLeft :size="18" class="mr-1" /> Kembali
            </button>

            <div v-if="!selectedCategory" class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <button v-for="cat in categories" :key="cat.id" @click="selectCategory(cat)"
                    class="card p-6 text-center hover:border-primary-500 border-2 border-transparent transition-all">
                    <component :is="cat.icon" :size="40" class="mx-auto mb-3" :class="`text-${cat.color}-500`" />
                    <p class="font-bold text-text-primary">{{ cat.name }}</p>
                </button>
            </div>

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

                <div v-if="selectedCategory === 'kesalahan_input'" class="space-y-4">
                    <div>
                        <label class="label">Alasan Hapus *</label>
                        <textarea v-model="form.deletion_reason" class="input" rows="4"
                            placeholder="Jelaskan alasan penghapusan data..."></textarea>
                    </div>
                </div>

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
                    <div v-if="isScanning"
                        class="fixed inset-0 bg-black/95 z-50 flex flex-col items-center justify-center p-4">
                        <div class="relative w-full max-w-lg bg-surface-800 rounded-2xl overflow-hidden">
                            <!-- Scanner Header -->
                            <div class="flex items-center justify-between p-4 border-b border-surface-700">
                                <h3 class="text-white font-bold flex items-center gap-2">
                                    <ScanBarcode :size="20" class="text-orange-500" />
                                    Scan Barcode Resi
                                </h3>
                                <button @click="stopScanner"
                                    class="text-text-secondary hover:text-white transition-colors">
                                    <X :size="24" />
                                </button>
                            </div>

                            <!-- Scanner Container -->
                            <div :id="scannerContainerId" class="w-full aspect-video bg-black"></div>

                            <!-- Instructions -->
                            <div class="p-4 text-center space-y-3">
                                <p class="text-text-secondary text-sm animate-pulse">
                                    Arahkan kamera ke barcode resi...
                                </p>
                                <div class="text-xs text-text-secondary">
                                    <p>Atau ketik manual nomor resi di form lalu tutup scanner</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

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

                <div class="mt-8 flex justify-end">
                    <button @click="submitStockOut" :disabled="!canSubmit || isSubmitting"
                        class="btn btn-primary px-10 h-14 rounded-2xl font-bold text-sm disabled:opacity-30">
                        <Loader2 v-if="isSubmitting" :size="20" class="animate-spin mr-2" />
                        {{ isSubmitting ? 'Memproses...' : 'Konfirmasi Keluar Stok' }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="showReturnBlockedAlert"
            class="fixed inset-0 bg-black/80 z-[60] flex items-center justify-center p-4">
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
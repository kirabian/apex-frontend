<script setup>
import { ref, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode'
import { useRouter } from 'vue-router'
import { onlineShop } from '../../api/axios'
import { useToast } from '../../composables/useToast'
// Icons
import { QrCode, ScanBarcode, X, CheckCircle, Package, Truck, Search, RefreshCw, CameraOff, Box } from 'lucide-vue-next'

const router = useRouter()
const toast = useToast()

// State
const step = ref('scan') // 'scan', 'result'
const isLoading = ref(false)
const html5QrCode = ref(null)
const scannerId = "reader"
const cameraError = ref(null)

// Data
const scanCode = ref('')
const scanResult = ref(null) // { type: 'order'|'product', data: ... }

const isMobile = () => /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)

onMounted(() => {
    startScanner()
})

onBeforeUnmount(() => {
    stopScanner()
})

// --- SCANNER LOGIC ---

const startScanner = async () => {
    step.value = 'scan'
    scanResult.value = null
    scanCode.value = ''
    cameraError.value = null
    await nextTick()

    // Cleanup first
    if (html5QrCode.value) {
        try {
            await html5QrCode.value.stop()
            html5QrCode.value.clear()
        } catch (e) { }
    }

    // Config for hybrid scanning (QR + Barcodes)
    const config = {
        fps: 20,
        qrbox: (viewfinderWidth, viewfinderHeight) => {
            // Rectangular box suitable for both
            const minEdgePercentage = 0.85;
            const qrboxWidth = Math.floor(viewfinderWidth * minEdgePercentage);
            const qrboxHeight = Math.floor(viewfinderHeight * 0.5);
            return { width: qrboxWidth, height: qrboxHeight };
        },
        aspectRatio: 1.0,
        formatsToSupport: [
            Html5QrcodeSupportedFormats.QR_CODE,
            Html5QrcodeSupportedFormats.CODE_128,
            Html5QrcodeSupportedFormats.EAN_13,
            Html5QrcodeSupportedFormats.CODE_39
        ]
    }

    html5QrCode.value = new Html5Qrcode(scannerId, {
        experimentalFeatures: { useBarCodeDetectorIfSupported: true },
        verbose: false
    })

    try {
        await html5QrCode.value.start(
            { facingMode: "environment" }, // FORCE BACK CAMERA
            config,
            onScanSuccess,
            (err) => { /* ignore failures */ }
        )
    } catch (err) {
        console.error("Scanner Error:", err)
        cameraError.value = "Gagal mengakses kamera. Pastikan izin diberikan."
    }
}

const stopScanner = async () => {
    if (html5QrCode.value) {
        try {
            await html5QrCode.value.stop()
            html5QrCode.value.clear()
        } catch (e) { }
        html5QrCode.value = null
    }
}

const onScanSuccess = async (decodedText, decodedResult) => {
    // Prevent multiple triggers
    if (isLoading.value) return;

    // Stop scanner temporarly
    await stopScanner()

    scanCode.value = decodedText
    if (navigator.vibrate) navigator.vibrate(200);

    handleScan()
}

const handleScan = async () => {
    const code = scanCode.value.trim();
    if (!code) return;

    isLoading.value = true
    try {
        const response = await onlineShop.scan(code)
        scanResult.value = response.data
        toast.success(`Ditemukan: ${response.data.type === 'order' ? 'Pesanan' : 'Produk'}`)
        step.value = 'result'
    } catch (error) {
        console.error(error)
        toast.error('Data tidak ditemukan / Error')
        scanResult.value = null

        // Resume scanning after error
        setTimeout(() => {
            startScanner()
        }, 1500)
    } finally {
        isLoading.value = false
    }
}

const resetScan = () => {
    startScanner()
}

// --- ORDER ACTIONS ---

const updateStatus = async (status) => {
    if (!scanResult.value || scanResult.value.type !== 'order') return

    isLoading.value = true
    try {
        const order = scanResult.value.data
        await onlineShop.updateOrder({
            order_number: order.order_number,
            status: status,
            platform: order.platform
        })

        toast.success(`Status updated to ${status}`)
        // Update local data
        scanResult.value.data.status = status
    } catch (error) {
        toast.error('Gagal update status')
    } finally {
        isLoading.value = false
    }
}

const formatCurrency = (val) => {
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0
    }).format(val || 0)
}
</script>

<template>
    <div class="scanner-page bg-black min-h-screen text-white overflow-hidden relative font-sans">

        <!-- HEADER -->
        <div
            class="absolute top-0 left-0 w-full p-6 pt-12 z-20 bg-gradient-to-b from-black/90 to-transparent flex justify-between items-start pointer-events-none">
            <div>
                <h1 class="text-2xl font-bold flex items-center gap-2 drop-shadow-md">
                    <ScanBarcode class="w-6 h-6 text-primary-500" /> Scan Pesanan
                </h1>
                <p class="text-gray-300 text-sm mt-1 drop-shadow-sm">QR Code & Barcode Resi</p>
            </div>
            <div v-if="step === 'scan'" class="animate-pulse">
                <span class="badge badge-error badge-sm">LIVE</span>
            </div>
        </div>

        <!-- STEP 1: SCANNER VIEW -->
        <div v-show="step === 'scan'" class="w-full h-full absolute inset-0 flex flex-col items-center justify-center">

            <div id="reader" class="w-full h-full bg-black relative"></div>

            <!-- Permission Error -->
            <div v-if="cameraError"
                class="absolute inset-0 z-30 flex flex-col items-center justify-center bg-black/90 p-6 text-center">
                <CameraOff class="w-16 h-16 text-red-500 mb-4" />
                <h3 class="text-xl font-bold mb-2">Akses Ditolak</h3>
                <p class="text-gray-400 mb-6">{{ cameraError }}</p>
                <button @click="startScanner" class="btn btn-primary rounded-full px-6">
                    <RefreshCw class="w-4 h-4 mr-2" /> Coba Lagi
                </button>
            </div>

            <!-- Manual Input Overlay (Bottom) -->
            <div class="absolute bottom-6 left-0 w-full px-6 z-30">
                <div class="relative group">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <Search class="h-5 w-5 text-gray-400" />
                    </div>
                    <input v-model="scanCode" @keyup.enter="handleScan" type="text"
                        class="block w-full pl-11 pr-4 py-4 rounded-2xl bg-surface-800/80 border border-surface-600 backdrop-blur-md text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 shadow-xl transition-all"
                        placeholder="Ketik manual kode resi..." />
                    <div v-if="isLoading" class="absolute right-4 top-1/2 -translate-y-1/2">
                        <span class="loading loading-spinner text-primary-500"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- STEP 2: RESULT VIEW -->
        <div v-if="step === 'result' && scanResult"
            class="w-full h-full absolute inset-0 bg-surface-900 z-40 flex flex-col overflow-y-auto animate-in slide-in-from-bottom-4">
            <!-- Header -->
            <div
                class="p-6 pt-12 flex justify-between items-center bg-surface-800 border-b border-surface-700 sticky top-0 z-10 shadow-lg">
                <h2 class="font-bold flex items-center gap-2 text-lg">
                    <CheckCircle class="w-5 h-5 text-green-500" /> Hasil Scan
                </h2>
                <button @click="resetScan" class="btn btn-sm btn-ghost btn-circle text-gray-400">
                    <X class="w-5 h-5" />
                </button>
            </div>

            <div class="p-6 flex-1 flex flex-col pb-24">

                <!-- ORDER CARD -->
                <div v-if="scanResult.type === 'order'" class="space-y-6">
                    <div class="card bg-surface-800 border border-surface-700 shadow-xl overflow-hidden">
                        <div
                            class="bg-primary-500/10 p-4 border-b border-primary-500/20 flex justify-between items-center">
                            <span class="badge badge-primary">ORDER</span>
                            <span class="font-mono font-bold text-primary-400">{{ scanResult.data.order_number }}</span>
                        </div>
                        <div class="p-6 text-center">
                            <h3 class="text-2xl font-bold text-white mb-2">{{ scanResult.data.customer_name ||
                                'Customer' }}</h3>
                            <p class="text-gray-400 mb-4">{{ scanResult.data.platform }}</p>
                            <div class="badge badge-lg" :class="{
                                'badge-secondary': scanResult.data.status === 'packed',
                                'badge-success': scanResult.data.status === 'shipped',
                                'badge-ghost': !['packed', 'shipped'].includes(scanResult.data.status)
                            }">
                                {{ scanResult.data.status?.toUpperCase() || 'UNKNOWN' }}
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="grid grid-cols-2 gap-4">
                        <button @click="updateStatus('packed')" :disabled="isLoading"
                            class="btn btn-lg bg-surface-700 hover:bg-surface-600 border-0 text-white h-auto py-4 flex flex-col gap-2 rounded-2xl">
                            <Package class="w-8 h-8 text-pink-500" />
                            <span>Set Packed</span>
                        </button>
                        <button @click="updateStatus('shipped')" :disabled="isLoading"
                            class="btn btn-lg bg-surface-700 hover:bg-surface-600 border-0 text-white h-auto py-4 flex flex-col gap-2 rounded-2xl">
                            <Truck class="w-8 h-8 text-green-500" />
                            <span>Kirim (Resi)</span>
                        </button>
                    </div>
                </div>

                <!-- PRODUCT CARD -->
                <div v-else class="card bg-surface-800 border border-surface-700 shadow-xl">
                    <div
                        class="bg-secondary-500/10 p-4 border-b border-secondary-500/20 flex justify-between items-center">
                        <span class="badge badge-secondary">PRODUK</span>
                        <span class="font-mono text-secondary-400">{{ scanResult.data.sku || '-' }}</span>
                    </div>
                    <div class="p-6">
                        <h3 class="text-xl font-bold text-white mb-2">{{ scanResult.data.name }}</h3>
                        <p class="text-2xl font-bold text-primary-400 mb-4">{{ formatCurrency(scanResult.data.price) }}
                        </p>
                        <div class="flex justify-between text-sm text-gray-400 border-t border-surface-700 pt-4">
                            <span>Stok:</span>
                            <span class="text-white font-bold">{{ scanResult.data.stock }}</span>
                        </div>
                    </div>
                </div>

                <!-- Reset Button -->
                <button @click="resetScan"
                    class="btn btn-outline btn-block mt-auto rounded-xl border-surface-600 text-gray-400">
                    Scan Lagi
                </button>
            </div>
        </div>

        <!-- Loading Overlay -->
        <div v-if="isLoading"
            class="absolute inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
            <span class="loading loading-spinner loading-lg text-primary-500"></span>
        </div>

    </div>
</template>

<style scoped>
/* Force video to not flip/mirror */
video {
    transform: none !important;
}

/* Override html5-qrcode's default injected styles */
:deep(#reader video) {
    object-fit: cover !important;
    width: 100% !important;
    height: 100% !important;
    border-radius: 0 !important;
}

:deep(#reader) {
    border: none !important;
}
</style>

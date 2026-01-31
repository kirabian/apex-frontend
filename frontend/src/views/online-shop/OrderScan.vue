<script setup>
import { ref, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode'
import { useRouter } from 'vue-router'
import { onlineShop } from '../../api/axios'
import { useToast } from '../../composables/useToast'
import { QrCode, ScanBarcode, X, CheckCircle, Package, Truck, Search, RefreshCw, CameraOff, ZoomIn, ZoomOut } from 'lucide-vue-next'

const router = useRouter()
const toast = useToast()

// State
const step = ref('scan')
const isLoading = ref(false)
const html5QrCode = ref(null)
const scannerId = "reader"
const cameraError = ref(null)

// Zoom State
const hasZoom = ref(false)
const zoomLevel = ref(1)
const zoomCapabilities = ref({ min: 1, max: 5, step: 0.1 })
const videoTrack = ref(null)

// Data
const scanCode = ref('')
const scanResult = ref(null)

const isMobile = () => /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)

onMounted(() => {
    startScanner()
})

onBeforeUnmount(() => {
    stopScanner()
})

const startScanner = async () => {
    if (isLoading.value) return;

    step.value = 'scan'
    scanResult.value = null
    scanCode.value = ''
    cameraError.value = null
    // Reset zoom state
    zoomLevel.value = 1
    hasZoom.value = false
    videoTrack.value = null

    await nextTick()

    // 1. Cleanup existing instance FORCEFULLY
    if (html5QrCode.value) {
        try {
            if (html5QrCode.value.isScanning) {
                await html5QrCode.value.stop();
            }
            html5QrCode.value.clear();
        } catch (e) { }
        html5QrCode.value = null;
    }

    // 2. Config - ALL FORMATS SUPPORT & WIDE AREA
    const config = {
        fps: 20,
        qrbox: (viewfinderWidth, viewfinderHeight) => {
            // Very wide box for long barcodes
            const minEdgePercentage = 0.95;
            const qrboxWidth = Math.floor(viewfinderWidth * minEdgePercentage);
            // Taller height to allow some angle flexibility
            const qrboxHeight = Math.floor(viewfinderHeight * 0.45);
            return { width: qrboxWidth, height: qrboxHeight };
        },
        aspectRatio: 1.0,
        formatsToSupport: [
            Html5QrcodeSupportedFormats.CODE_128,
            Html5QrcodeSupportedFormats.QR_CODE,
            Html5QrcodeSupportedFormats.EAN_13,
            Html5QrcodeSupportedFormats.EAN_8,
            Html5QrcodeSupportedFormats.CODE_39,
            Html5QrcodeSupportedFormats.CODE_93,
            Html5QrcodeSupportedFormats.UPC_A,
            Html5QrcodeSupportedFormats.UPC_E,
            Html5QrcodeSupportedFormats.ITF,
            Html5QrcodeSupportedFormats.RSS_14,
            Html5QrcodeSupportedFormats.RSS_EXPANDED
        ]
    }

    html5QrCode.value = new Html5Qrcode(scannerId, {
        experimentalFeatures: { useBarCodeDetectorIfSupported: true },
        verbose: false
    })

    const onCameraReady = () => {
        // Init Zoom Capabilities after camera start
        setTimeout(() => {
            try {
                // HACK: Access the video element directly to get the track
                const videoElement = document.querySelector(`#${scannerId} video`);
                if (videoElement && videoElement.srcObject) {
                    const track = videoElement.srcObject.getVideoTracks()[0];
                    if (track) {
                        videoTrack.value = track;
                        const caps = track.getCapabilities();
                        if (caps.zoom) {
                            hasZoom.value = true;
                            zoomCapabilities.value = {
                                min: caps.zoom.min || 1,
                                max: caps.zoom.max || 5, // Limit to 5x to prevent super grain
                                step: caps.zoom.step || 0.1
                            };
                            // Set initial zoom to 1.5x for "Distance" readiness if supported
                            try {
                                track.applyConstraints({
                                    advanced: [{ zoom: 1.2 }]
                                }).then(() => { zoomLevel.value = 1.2 }).catch(e => { })
                            } catch (e) { }
                        }
                    }
                }
            } catch (e) { console.log("Zoom init failed", e) }
        }, 500);
    }

    try {
        await html5QrCode.value.start(
            { facingMode: "environment" },
            {
                ...config,
                videoConstraints: {
                    facingMode: "environment",
                    width: { min: 720, ideal: 1280 }, // Balanced HD
                    height: { min: 480, ideal: 720 },
                    focusMode: "continuous"
                }
            },
            onScanSuccess,
            (err) => { }
        );
        onCameraReady();

    } catch (err) {
        console.warn("Standard start failed, retrying strict...", err);
        cameraError.value = "Gagal. Coba refresh halaman."
    }
}

const stopScanner = async () => {
    if (html5QrCode.value) {
        try {
            if (html5QrCode.value.isScanning) {
                await html5QrCode.value.stop();
            }
            html5QrCode.value.clear();
        } catch (e) { }
        html5QrCode.value = null
    }
}

const setZoom = async (val) => {
    if (!videoTrack.value || !hasZoom.value) return;
    try {
        zoomLevel.value = Number(val);
        await videoTrack.value.applyConstraints({
            advanced: [{ zoom: zoomLevel.value }]
        });
    } catch (e) { console.log("Zoom failed", e) }
}

const onScanSuccess = async (decodedText) => {
    if (isLoading.value) return;
    if (decodedText.length < 3) return;

    // Stop first
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
        toast.success('Ditemukan!')
        step.value = 'result'
    } catch (error) {
        console.error(error)
        toast.error('Gagal scan / Tidak ditemukan')
        scanResult.value = null

        // Auto restart scan if failed
        setTimeout(() => {
            resetScan() // Use resetScan to ensure clean restart
        }, 2000)
    } finally {
        isLoading.value = false
    }
}

const resetScan = async () => {
    // Force full cleanup and restart
    step.value = 'scan'
    await nextTick() // Update UI first
    startScanner()
}

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
        scanResult.value.data.status = status
    } catch (error) { toast.error('Gagal update status') }
    finally { isLoading.value = false }
}
const formatCurrency = (val) => new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", minimumFractionDigits: 0 }).format(val || 0)

</script>

<template>
    <div class="scanner-page bg-black min-h-screen text-white overflow-hidden relative font-sans">

        <!-- HEADER -->
        <div
            class="absolute top-0 left-0 w-full p-6 pt-12 z-20 bg-gradient-to-b from-black/80 to-transparent flex justify-between items-start pointer-events-none">
            <div>
                <h1 class="text-2xl font-bold flex items-center gap-2 drop-shadow-md">
                    <ScanBarcode class="w-6 h-6 text-primary-500" /> Scan Pesanan
                </h1>
                <p class="text-gray-300 text-xs mt-1">Supports All Formats (QR, Barcode, etc)</p>
            </div>
        </div>

        <!-- SCANNER VIEW -->
        <div v-show="step === 'scan'" class="w-full h-full absolute inset-0 bg-black">
            <div id="reader" class="w-full h-full bg-black relative"></div>

            <!-- ZOOM CONTROLS -->
            <div v-if="hasZoom"
                class="absolute right-4 top-1/2 -translate-y-1/2 z-30 flex flex-col items-center gap-4 bg-black/40 backdrop-blur-md p-2 rounded-full border border-white/10">
                <button @click="setZoom(Math.min(zoomLevel + 0.5, zoomCapabilities.max))"
                    class="p-2 -rotate-0 text-white hover:text-primary-500">
                    <ZoomIn class="w-6 h-6" />
                </button>
                <div class="h-32 w-2 relative">
                    <input type="range" :min="zoomCapabilities.min" :max="zoomCapabilities.max"
                        :step="zoomCapabilities.step" v-model="zoomLevel" @input="setZoom($event.target.value)"
                        class="zoom-range" />
                </div>
                <button @click="setZoom(Math.min(zoomLevel - 0.5, zoomCapabilities.min))"
                    class="p-2 text-white hover:text-primary-500">
                    <ZoomOut class="w-6 h-6" />
                </button>
            </div>

            <!-- SCAN GUIDE -->
            <div class="absolute inset-0 pointer-events-none flex items-center justify-center z-10 opacity-70">
                <div class="w-[95%] h-56 border-2 border-primary-500/50 rounded-lg relative">
                    <div
                        class="absolute top-0 left-1/2 -translate-x-1/2 -mt-3 bg-primary-600 text-white text-[10px] px-3 py-1 rounded-full font-bold shadow-lg">
                        SCAN AREA</div>
                    <div class="absolute top-1/2 w-full h-0.5 bg-red-500/80 animate-pulse"></div>
                </div>
            </div>

            <!-- MANUAL INPUT -->
            <div class="absolute bottom-6 left-0 w-full px-6 z-30">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <Search class="h-5 w-5 text-gray-400" />
                    </div>
                    <input v-model="scanCode" @keyup.enter="handleScan" type="text"
                        class="block w-full pl-12 pr-4 py-4 rounded-2xl bg-surface-800/90 border border-surface-600/50 text-white shadow-xl focus:ring-2 focus:ring-primary-500 focus:outline-none"
                        placeholder="Ketik kode manual..." />
                </div>
            </div>

            <!-- Error Retry -->
            <div v-if="cameraError"
                class="absolute inset-0 z-40 bg-black/95 flex flex-col items-center justify-center p-8 text-center">
                <CameraOff class="w-16 h-16 text-red-500 mb-4" />
                <p class="mb-6">{{ cameraError }}</p>
                <button @click="startScanner" class="btn btn-primary rounded-full">Coba Lagi</button>
            </div>
        </div>

        <!-- RESULT VIEW (Overlay) -->
        <div v-if="step === 'result'" class="absolute inset-0 z-50 bg-black/50 backdrop-blur-sm">
            <div
                class="absolute bottom-0 w-full bg-surface-900 rounded-t-3xl min-h-[50vh] max-h-[90vh] overflow-y-auto p-6 animate-in slide-in-from-bottom-5">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold flex items-center gap-2">
                        <CheckCircle class="text-green-500" /> Hasil Scan
                    </h2>
                    <button @click="resetScan" class="btn btn-circle btn-sm btn-ghost bg-surface-800">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- CONTENT -->
                <div v-if="scanResult" class="space-y-6">
                    <div v-if="scanResult.type === 'order'" class="text-center">
                        <div class="badge badge-primary mb-2">ORDER</div>
                        <h3 class="text-2xl font-bold font-mono text-primary-400 mb-1">{{ scanResult.data.order_number
                            }}</h3>
                        <p class="text-gray-400 mb-4">{{ scanResult.data.customer_name }}</p>

                        <div class="grid grid-cols-2 gap-3">
                            <button @click="updateStatus('packed')"
                                class="btn btn-lg bg-surface-800 hover:bg-surface-700 text-white border- surface-700 flex flex-col h-auto py-3 rounded-2xl">
                                <Package class="text-pink-500 mb-1" /> Packed
                            </button>
                            <button @click="updateStatus('shipped')"
                                class="btn btn-lg bg-surface-800 hover:bg-surface-700 text-white border-surface-700 flex flex-col h-auto py-3 rounded-2xl">
                                <Truck class="text-green-500 mb-1" /> Kirim
                            </button>
                        </div>
                    </div>

                    <div v-else class="text-center">
                        <div class="badge badge-secondary mb-2">PRODUK</div>
                        <h3 class="text-xl font-bold mb-2">{{ scanResult.data.name }}</h3>
                        <p class="text-2xl text-primary-500 font-bold mb-4">{{ formatCurrency(scanResult.data.price) }}
                        </p>
                        <div class="stats bg-surface-800 w-full">
                            <div class="stat place-items-center">
                                <div class="stat-title">Stok</div>
                                <div class="stat-value text-white">{{ scanResult.data.stock }}</div>
                            </div>
                        </div>
                    </div>

                    <button @click="resetScan" class="btn btn-primary w-full rounded-xl mt-4">Scan Lagi</button>
                </div>
            </div>
        </div>

        <div v-if="isLoading" class="absolute inset-0 z-[60] flex items-center justify-center bg-black/60">
            <span class="loading loading-spinner loading-lg text-primary-500"></span>
        </div>
    </div>
</template>

<style scoped>
/* Standard fixes */
video {
    transform: none !important;
}

:deep(#reader video) {
    object-fit: cover !important;
    width: 100% !important;
    height: 100% !important;
}

:deep(#reader) {
    border: none !important;
}

/* Vertical Zoom Slider */
.zoom-range {
    writing-mode: bt-lr;
    /* IE/Edge */
    -webkit-appearance: slider-vertical;
    /* Webkit */
    width: 8px;
    height: 100%;
    /* Standard appearance for range input */
    appearance: slider-vertical;
}
</style>

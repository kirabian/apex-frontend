<script setup>
import { ref, onMounted, nextTick, onBeforeUnmount, computed } from 'vue'
import { onlineShop } from '../../api/axios'
import { useToast } from '../../composables/useToast'
import { ScanBarcode, QrCode, Package, Search, Camera, X, RefreshCw, Type, ArrowRight, StopCircle, Zap, Image as ImageIcon } from 'lucide-vue-next'
import { Html5Qrcode } from 'html5-qrcode'
import Tesseract from 'tesseract.js'

const toast = useToast()
const scanInput = ref(null)
const scanCode = ref('')
const isLoading = ref(false)
const scanResult = ref(null)

// Camera State
const isCameraOpen = ref(false) // Default false (manual start)
const isInitializing = ref(false) // Loading state
const cameraMode = ref(null) // 'qr', 'barcode', 'ocr'
const scannerId = 'html5qr-code-full-region'
let html5QrCode = null
let isScanning = false

onBeforeUnmount(() => {
    stopCamera()
})

const focusInput = () => {
    nextTick(() => {
        if (scanInput.value) scanInput.value.focus()
    })
}

const handleScan = async () => {
    const code = scanCode.value.trim();
    if (!code) return;

    if (isLoading.value) return

    isLoading.value = true
    try {
        if (navigator.vibrate) navigator.vibrate(200);

        const response = await onlineShop.scan(scanCode.value)
        scanResult.value = response.data
        toast.success(`Ditemukan: ${response.data.type === 'order' ? 'Pesanan' : 'Produk'}`)

        if (html5QrCode && isScanning) {
            await html5QrCode.pause()
        }
    } catch (error) {
        console.error(error)
        toast.error('Data tidak ditemukan / Error')
        scanResult.value = null

        if (html5QrCode && isScanning) {
            setTimeout(() => {
                html5QrCode.resume()
            }, 2000)
        }
    } finally {
        isLoading.value = false
    }
}

const resetScan = async () => {
    scanResult.value = null
    scanCode.value = ''
    if (html5QrCode && isScanning) {
        try {
            await html5QrCode.resume()
        } catch (e) { console.log("resume failed") }
    } else {
        // If we were scanning, restart the last mode
        if (cameraMode.value) startScanner(cameraMode.value)
    }
}

const updateStatus = async (status) => {
    if (!scanResult.value || scanResult.value.type !== 'order') return

    try {
        const order = scanResult.value.data
        await onlineShop.updateOrder({
            order_number: order.order_number,
            status: status,
            platform: order.platform
        })

        toast.success(`Status updated to ${status}`)
        scanResult.value.data.status = status
    } catch (error) {
        toast.error('Gagal update status')
    }
}

const formatCurrency = (val) => {
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0
    }).format(val || 0)
}

// --- SCANNER LOGIC ---

const videoDevices = ref([]);
const currentDeviceIndex = ref(0);

const switchCamera = async () => {
    try {
        const devices = await Html5Qrcode.getCameras();
        if (devices && devices.length > 1) {
            // Cari kamera yang namanya ada "back", "rear", atau "belakang"
            const backCameras = devices.filter(d =>
                d.label.toLowerCase().includes('back') ||
                d.label.toLowerCase().includes('rear') ||
                d.label.toLowerCase().includes('0') // Biasanya ID 0 atau terakhir adalah kamera utama
            );

            // Ganti index berdasarkan daftar semua kamera
            currentDeviceIndex.value = (currentDeviceIndex.value + 1) % devices.length;
            const nextCameraId = devices[currentDeviceIndex.value].id;

            console.log("Switching to camera:", devices[currentDeviceIndex.value].label);

            await stopCamera();
            await startScanner(cameraMode.value, nextCameraId);
            toast.success(`Kamera: ${devices[currentDeviceIndex.value].label}`);
        } else {
            toast.warning("Hanya 1 kamera terdeteksi");
        }
    } catch (err) {
        toast.error("Gagal ganti kamera");
    }
};

const startScanner = async (mode, specificCameraId = null) => {
    if (html5QrCode && isScanning) {
        await stopCamera();
    }

    isCameraOpen.value = true
    cameraMode.value = mode
    scanResult.value = null

    await nextTick()

    html5QrCode = new Html5Qrcode(scannerId)

    // Config based on mode
    let qrboxConfig;
    let formats;

    if (mode === 'qr') {
        qrboxConfig = { width: 250, height: 250 };
        formats = [0];
    } else {
        qrboxConfig = (viewfinderWidth, viewfinderHeight) => {
            const minEdgePercentage = 0.85;
            const qrboxWidth = Math.floor(viewfinderWidth * minEdgePercentage);
            const qrboxHeight = Math.floor(viewfinderHeight * 0.4);
            return {
                width: qrboxWidth,
                height: qrboxHeight
            };
        };
        formats = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16];
    }

    const config = {
        fps: 20,
        qrbox: qrboxConfig,
        rememberLastUsedCamera: true,
        aspectRatio: 1.0,
        disableFlip: true,
        videoConstraints: {
            focusMode: "continuous",
            advanced: mode === 'barcode' ? [{ zoom: 2.0 }] : []
        }
    }

    try {
        isInitializing.value = true

        let cameraIdOrConfig = specificCameraId;

        // Jika tidak ada ID spesifik, kita coba DETEKSI kamera belakang secara manual
        if (!cameraIdOrConfig) {
            try {
                const devices = await Html5Qrcode.getCameras();
                if (devices && devices.length > 0) {
                    // 1. Cari yang labelnya "back" / "rear" / "environment"
                    const backCamera = devices.find(d =>
                        d.label.toLowerCase().includes('back') ||
                        d.label.toLowerCase().includes('rear') ||
                        d.label.toLowerCase().includes('environment')
                    );

                    if (backCamera) {
                        cameraIdOrConfig = backCamera.id;
                        console.log("Auto-detected back camera:", backCamera.label);
                    } else if (devices.length > 1) {
                        // 2. Jika tidak ada label jelas, biasanya kamera terakhir adalah belakang
                        cameraIdOrConfig = devices[devices.length - 1].id;
                        console.log("Assuming last camera is back:", devices[devices.length - 1].label);
                    } else {
                        // 3. Hanya 1 kamera, ya pake itu aja
                        cameraIdOrConfig = devices[0].id;
                    }
                }
            } catch (e) {
                console.warn("Failed to enumerate cameras, falling back to constraint");
            }
        }

        // Fallback terakhir jika deteksi gagal: gunakan constraint strict
        if (!cameraIdOrConfig) {
            cameraIdOrConfig = { facingMode: { exact: "environment" } };
        }

        await html5QrCode.start(
            cameraIdOrConfig,
            config,
            (decodedText) => {
                scanCode.value = decodedText
                if (navigator.vibrate) navigator.vibrate(200);
                handleScan()
            },
            (errorMessage) => { /* ignore */ }
        ).catch(err => {
            // Retry dengan loose constraint jika exact gagal
            console.warn("Start failed, retrying with loose environment...");
            return html5QrCode.start({ facingMode: "environment" }, config, (decodedText) => {
                scanCode.value = decodedText
                handleScan()
            }, () => { });
        });

        isScanning = true
    } catch (err) {
        console.error("Error starting scanner", err)
        toast.error("Gagal membuka kamera: " + (err.message || err))
        isCameraOpen.value = false
    } finally {
        isInitializing.value = false
    }
}

const startOCR = async () => {
    if (cameraMode.value === 'ocr') return;

    await stopCamera()
    isCameraOpen.value = true
    cameraMode.value = 'ocr'
    scanResult.value = null

    await nextTick()

    html5QrCode = new Html5Qrcode(scannerId)
    try {
        await html5QrCode.start(
            { facingMode: "environment" },
            { fps: 20, qrbox: { width: 300, height: 100 } },
            () => { }, () => { }
        )
        isScanning = true
    } catch (err) {
        toast.error("Gagal membuka kamera.")
        isCameraOpen.value = false
    }
}

const captureAndOCR = async () => {
    const video = document.querySelector(`#${scannerId} video`)
    if (!video) return

    isLoading.value = true
    const canvas = document.createElement("canvas")
    canvas.width = video.videoWidth
    canvas.height = video.videoHeight
    const ctx = canvas.getContext('2d')
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height)

    const image = canvas.toDataURL('image/png')

    try {
        const { data: { text } } = await Tesseract.recognize(image, 'eng')
        const candidate = text.split(/\s+/).find(w => w.length > 5 && /\d/.test(w))

        if (candidate) {
            scanCode.value = candidate
            toast.success(`Text: ${candidate}`)
            handleScan()
        } else {
            toast.error("Tidak terbaca.")
        }
    } catch (err) {
        toast.error("Gagal OCR.")
    } finally {
        isLoading.value = false
    }
}

const stopCamera = async () => {
    if (html5QrCode) {
        try {
            if (isScanning) await html5QrCode.stop()
            html5QrCode.clear()
        } catch (e) { }
        html5QrCode = null
        isScanning = false
    }
}

const toggleCamera = () => {
    if (isScanning) {
        stopCamera()
        isCameraOpen.value = false
        cameraMode.value = null
    }
}
</script>

<template>
    <div class="space-y-4 max-w-xl mx-auto pb-24">
        <!-- Header -->
        <div class="flex items-center justify-between px-2">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <ScanBarcode class="text-primary-500" />
                    Scan Pesanan
                </h1>
                <p class="text-gray-500 dark:text-slate-400 text-sm">Pilih mode scan di bawah.</p>
            </div>
            <div v-if="isCameraOpen" class="animate-pulse">
                <span class="badge badge-error badge-sm">LIVE</span>
            </div>
        </div>

        <!-- Camera Section -->
        <div
            class="relative overflow-hidden bg-black rounded-3xl shadow-2xl aspect-[3/4] md:aspect-video mb-4 ring-1 ring-white/10">
            <!-- Camera Feed -->
            <div id="html5qr-code-full-region" class="w-full h-full"></div>

            <!-- Idle State (Camera Off) -->
            <div v-if="!isCameraOpen && !isInitializing"
                class="absolute inset-0 flex flex-col items-center justify-center bg-surface-900/90 text-center p-8 space-y-6 z-10">
                <div
                    class="w-20 h-20 rounded-full bg-surface-800 flex items-center justify-center mb-4 ring-4 ring-surface-700 animate-pulse">
                    <Camera :size="40" class="text-slate-500" />
                </div>
                <div>
                    <h3 class="text-xl font-bold text-white mb-2">Kamera Nonaktif</h3>
                    <p class="text-slate-400 text-sm max-w-xs mx-auto mb-6">Pilih mode scan di bawah atau tekan tombol
                        untuk mulai.</p>

                    <button @click="startScanner('barcode')"
                        class="btn btn-primary btn-lg rounded-full px-8 shadow-lg shadow-primary-500/30">
                        <ScanBarcode class="mr-2" /> Mulai Scan
                    </button>
                </div>
            </div>

            <!-- Initializing State -->
            <div v-if="isInitializing" class="absolute inset-0 flex flex-col items-center justify-center bg-black z-20">
                <span class="loading loading-spinner loading-lg text-primary-500 mb-4"></span>
                <p class="text-white font-medium animate-pulse">Membuka Kamera...</p>
            </div>

            <!-- Scanning Overlay -->
            <div v-if="isScanning" class="absolute inset-0 pointer-events-none">
                <!-- QR Guide (Square) -->
                <div v-if="cameraMode === 'qr'" class="absolute inset-0 flex items-center justify-center">
                    <div
                        class="w-64 h-64 border-2 border-primary-500 rounded-2xl relative shadow-[0_0_100px_rgba(var(--primary-500),0.3)]">
                        <div
                            class="absolute inset-0 border-2 border-primary-500/50 scale-110 rounded-3xl opacity-50 animate-pulse">
                        </div>
                        <!-- Corner Decorations -->
                        <div
                            class="absolute -top-1 -left-1 w-6 h-6 border-t-4 border-l-4 border-primary-500 rounded-tl-lg">
                        </div>
                        <div
                            class="absolute -top-1 -right-1 w-6 h-6 border-t-4 border-r-4 border-primary-500 rounded-tr-lg">
                        </div>
                        <div
                            class="absolute -bottom-1 -left-1 w-6 h-6 border-b-4 border-l-4 border-primary-500 rounded-bl-lg">
                        </div>
                        <div
                            class="absolute -bottom-1 -right-1 w-6 h-6 border-b-4 border-r-4 border-primary-500 rounded-br-lg">
                        </div>
                    </div>
                </div>

                <!-- Barcode Guide (Wide) -->
                <div v-else-if="cameraMode === 'barcode'" class="absolute inset-0 flex items-center justify-center">
                    <div
                        class="w-[85%] h-32 border-2 border-red-500 rounded-lg relative shadow-[0_0_50px_rgba(239,68,68,0.3)]">
                        <div
                            class="absolute top-1/2 left-0 right-0 h-0.5 bg-red-500 animate-pulse shadow-[0_0_10px_red]">
                        </div>
                    </div>
                    <div class="absolute bottom-8 left-0 right-0 text-center">
                        <span class="bg-black/50 text-white px-3 py-1 rounded-full text-xs backdrop-blur-md">
                            Arahkan garis merah ke Barcode
                        </span>
                    </div>
                </div>
            </div>

            <!-- Close Button & Switcher -->
            <div v-if="isCameraOpen" class="absolute top-4 right-4 z-20 flex gap-2">
                <button @click="switchCamera"
                    class="btn btn-circle btn-sm bg-black/40 text-white border-0 hover:bg-primary-500 transition-colors">
                    <RefreshCw :size="18" />
                </button>

                <button @click="toggleCamera"
                    class="btn btn-circle btn-sm bg-black/40 text-white border-0 hover:bg-red-500 transition-colors">
                    <X :size="18" />
                </button>
            </div>

            <!-- OCR Capture Button -->
            <div v-if="cameraMode === 'ocr' && isScanning"
                class="absolute bottom-6 left-0 right-0 flex justify-center z-20">
                <button @click="captureAndOCR" class="btn btn-lg btn-circle bg-white text-black ring-4 ring-black/20"
                    :disabled="isLoading">
                    <span v-if="isLoading" class="loading loading-spinner"></span>
                    <Camera v-else :size="28" />
                </button>
            </div>
        </div>

        <!-- Manual Input (Always Visible) -->
        <div class="p-2 mb-4">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <Search class="h-5 w-5 text-gray-400" />
                </div>
                <input ref="scanInput" v-model="scanCode" @keyup.enter="handleScan" type="text"
                    class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-surface-700 rounded-xl leading-5 bg-white dark:bg-surface-800 placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 sm:text-sm text-gray-900 dark:text-white"
                    placeholder="Scan atau ketik kode resi/produk..." />
                <div v-if="isLoading" class="absolute right-3 top-1/2 -translate-y-1/2">
                    <span class="loading loading-spinner loading-xs text-primary-500"></span>
                </div>
            </div>
        </div>

        <!-- Mode Selectors (Bottom Bar) -->
        <div class="grid grid-cols-4 gap-2">
            <button @click="startScanner('qr')"
                class="flex flex-col items-center justify-center p-3 rounded-xl transition-all border"
                :class="cameraMode === 'qr' ? 'bg-primary-500 text-white border-primary-600 shadow-lg shadow-primary-500/30' : 'bg-white dark:bg-surface-800 text-slate-500 border-gray-200 dark:border-surface-700 hover:bg-gray-50 dark:hover:bg-surface-700'">
                <QrCode :size="24" class="mb-1" />
                <span class="text-[10px] font-bold">QR Code</span>
            </button>

            <button @click="startScanner('barcode')"
                class="flex flex-col items-center justify-center p-3 rounded-xl transition-all border"
                :class="cameraMode === 'barcode' ? 'bg-red-500 text-white border-red-600 shadow-lg shadow-red-500/30' : 'bg-white dark:bg-surface-800 text-slate-500 border-gray-200 dark:border-surface-700 hover:bg-gray-50 dark:hover:bg-surface-700'">
                <ScanBarcode :size="24" class="mb-1" />
                <span class="text-[10px] font-bold">Barcode Resi</span>
            </button>

            <button @click="startOCR()"
                class="flex flex-col items-center justify-center p-3 rounded-xl transition-all border"
                :class="cameraMode === 'ocr' ? 'bg-blue-500 text-white border-blue-600 shadow-lg shadow-blue-500/30' : 'bg-white dark:bg-surface-800 text-slate-500 border-gray-200 dark:border-surface-700 hover:bg-gray-50 dark:hover:bg-surface-700'">
                <ImageIcon :size="24" class="mb-1" />
                <span class="text-[10px] font-bold">Foto/OCR</span>
            </button>

            <button @click="focusInput"
                class="flex flex-col items-center justify-center p-3 rounded-xl transition-all border bg-white dark:bg-surface-800 text-slate-500 border-gray-200 dark:border-surface-700 hover:bg-gray-50 dark:hover:bg-surface-700">
                <Type :size="24" class="mb-1" />
                <span class="text-[10px] font-bold">Manual</span>
            </button>
        </div>


        <!-- Result Card -->
        <div v-if="scanResult"
            class="card p-6 bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-700 shadow-lg animate-in slide-in-from-bottom-4">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <span class="badge mb-2" :class="scanResult.type === 'order' ? 'badge-info' : 'badge-success'">
                        {{ scanResult.type === 'order' ? 'PESANAN' : 'PRODUK' }}
                    </span>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ scanResult.type === 'order' ?
                        scanResult.data.order_number : scanResult.data.name }}</h2>
                </div>
                <button @click="resetScan" class="btn btn-ghost btn-sm btn-circle">
                    <X :size="20" />
                </button>
            </div>

            <div v-if="scanResult.type === 'order'" class="flex gap-2 mt-4">
                <button @click="updateStatus('packed')" class="btn btn-secondary flex-1">Packed</button>
                <button @click="updateStatus('shipped')" class="btn btn-primary flex-1">Kirim</button>
            </div>
            <button @click="resetScan" class="btn btn-outline btn-block mt-4">Scan Lagi</button>
        </div>
    </div>
</template>

<style>
/* CSS Override for html5-qrcode video */
#html5qr-code-full-region video {
    object-fit: cover !important;
    border-radius: 0 !important;
    width: 100% !important;
    height: 100% !important;
}

#html5qr-code-full-region {
    border: none !important;
}
</style>

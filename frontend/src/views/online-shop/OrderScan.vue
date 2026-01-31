<script setup>
import { ref, onMounted, nextTick, onBeforeUnmount } from 'vue'
import { onlineShop } from '../../api/axios'
import { useToast } from '../../composables/useToast'
import { ScanBarcode, QrCode, Package, Search, Camera, X, RefreshCw, Type } from 'lucide-vue-next'
import { Html5Qrcode } from 'html5-qrcode'

const toast = useToast()
const scanInput = ref(null)
const scanCode = ref('')
const isLoading = ref(false)
const scanResult = ref(null)

// Camera State
const isCameraOpen = ref(false)
const isInitializing = ref(false)
const cameraMode = ref(null)
const scannerId = 'html5qr-code-full-region'
let html5QrCode = null
let isScanning = false

// Camera devices
const availableCameras = ref([])
const currentCameraId = ref(null)
const currentCameraLabel = ref('')
const isFrontCamera = ref(false)

onMounted(async () => {
    await getCameraDevices()
})

onBeforeUnmount(() => {
    stopCamera()
})

const getCameraDevices = async () => {
    try {
        const devices = await Html5Qrcode.getCameras()
        availableCameras.value = devices
        console.log('Available cameras:', devices)

        // Simpan devices untuk debug
        localStorage.setItem('debug_cameras', JSON.stringify(devices.map(d => ({
            label: d.label,
            id: d.id.substring(0, 30) + '...'
        }))))

        // Langsung gunakan environment mode, tidak perlu heuristic yang rumit
        // Biarkan browser yang menentukan kamera mana yang environment
        currentCameraId.value = null; // Gunakan null untuk menggunakan facingMode
        currentCameraLabel.value = 'Kamera Belakang (Environment)'
        isFrontCamera.value = false

        console.log('Using environment facingMode as default')
    } catch (error) {
        console.error('Error getting cameras:', error)
    }
}

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

// --- SCANNER LOGIC ---

const switchCamera = async () => {
    try {
        if (availableCameras.value.length <= 1) {
            toast.warning("Hanya 1 kamera terdeteksi")
            return
        }

        // Cari index kamera saat ini
        let currentIndex = -1
        if (currentCameraId.value) {
            currentIndex = availableCameras.value.findIndex(cam => cam.id === currentCameraId.value)
        }

        // Jika menggunakan facingMode environment, cari kamera belakang
        if (currentIndex === -1) {
            const backCamera = availableCameras.value.find(device => {
                const label = device.label.toLowerCase()
                return (
                    label.includes('back') ||
                    label.includes('rear') ||
                    label.includes('environment')
                )
            })
            if (backCamera) {
                currentIndex = availableCameras.value.findIndex(cam => cam.id === backCamera.id)
            }
        }

        // Cari kamera berikutnya
        let nextIndex = 0
        if (currentIndex !== -1) {
            nextIndex = (currentIndex + 1) % availableCameras.value.length
        }
        const nextCamera = availableCameras.value[nextIndex]

        // Update state - gunakan cameraId langsung
        currentCameraId.value = nextCamera.id
        currentCameraLabel.value = nextCamera.label

        // Tentukan apakah ini kamera depan
        const label = nextCamera.label.toLowerCase()
        isFrontCamera.value =
            label.includes('front') ||
            label.includes('user') ||
            label.includes('selfie')

        console.log("Switching to camera:", nextCamera.label, "front:", isFrontCamera.value)

        // Restart scanner dengan kamera baru
        const mode = cameraMode.value
        await stopCamera()
        await startScanner(mode)

        toast.success(`Beralih ke kamera ${isFrontCamera.value ? 'depan' : 'belakang'}`)
    } catch (err) {
        console.error("Switch camera error:", err)
        toast.error("Gagal ganti kamera")
    }
}

const startScanner = async (mode, forceCameraId = null) => {
    // Stop jika sedang scanning
    if (html5QrCode && isScanning) {
        await stopCamera()
    }

    isCameraOpen.value = true
    cameraMode.value = mode
    scanResult.value = null

    await nextTick()

    html5QrCode = new Html5Qrcode(scannerId)

    // Config berdasarkan mode
    let qrboxConfig;
    let formats;

    if (mode === 'qr') {
        qrboxConfig = { width: 250, height: 250 };
        formats = [0]; // QR_CODE
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
        formats = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16]; // Semua barcode format
    }

    const config = {
        fps: 20,
        qrbox: qrboxConfig,
        rememberLastUsedCamera: false,
        aspectRatio: 1.0,
        disableFlip: true,
        supportedScanTypes: formats,
        videoConstraints: {
            focusMode: "continuous",
            width: { ideal: 1280 },
            height: { ideal: 720 }
        }
    }

    try {
        isInitializing.value = true

        // Tentukan kamera yang akan digunakan
        let cameraToUse;

        if (forceCameraId) {
            cameraToUse = forceCameraId
            console.log('Using forced camera:', forceCameraId)
        } else if (currentCameraId.value) {
            cameraToUse = currentCameraId.value
            console.log('Using saved camera ID:', currentCameraId.value)
        } else {
            // DEFAULT: Gunakan environment (kamera belakang) seperti security scanner
            cameraToUse = { facingMode: "environment" }
            console.log('Using DEFAULT environment facingMode for back camera')
        }

        await html5QrCode.start(
            cameraToUse,
            config,
            (decodedText) => {
                scanCode.value = decodedText
                if (navigator.vibrate) navigator.vibrate(200);
                handleScan()
            },
            (errorMessage) => {
                // Ignore scanning errors
                console.log("Scan error:", errorMessage)
            }
        ).catch(async (err) => {
            console.error("Scanner start failed:", err)

            // Fallback 1: Jika pakai cameraId gagal, coba environment
            if (typeof cameraToUse === 'string') {
                console.log("Retrying with facingMode environment...")
                try {
                    return await html5QrCode.start(
                        { facingMode: "environment" },
                        config,
                        (decodedText) => {
                            scanCode.value = decodedText
                            handleScan()
                        },
                        () => { }
                    )
                } catch (envErr) {
                    console.error("Environment camera failed:", envErr)
                }
            }

            // Fallback 2: Coba kamera pertama
            if (availableCameras.value.length > 0) {
                console.log("Trying first available camera...")
                try {
                    return await html5QrCode.start(
                        availableCameras.value[0].id,
                        config,
                        (decodedText) => {
                            scanCode.value = decodedText
                            handleScan()
                        },
                        () => { }
                    )
                } catch (firstErr) {
                    console.error("First camera failed:", firstErr)
                }
            }

            throw err
        })

        isScanning = true

        // Setelah berhasil start, update info kamera
        if (typeof cameraToUse === 'string') {
            // Cari kamera di availableCameras
            const camera = availableCameras.value.find(cam => cam.id === cameraToUse)
            if (camera) {
                currentCameraLabel.value = camera.label
                const label = camera.label.toLowerCase()
                isFrontCamera.value =
                    label.includes('front') ||
                    label.includes('user') ||
                    label.includes('selfie')
            }
        } else if (cameraToUse?.facingMode === 'environment') {
            isFrontCamera.value = false
            currentCameraLabel.value = 'Kamera Belakang (Environment)'
        }

        console.log('Scanner started successfully with:', {
            camera: cameraToUse,
            isFrontCamera: isFrontCamera.value,
            label: currentCameraLabel.value
        })

    } catch (err) {
        console.error("Error starting scanner", err)

        // Fallback terakhir: coba user (depan) jika environment gagal
        if (availableCameras.value.length > 0) {
            try {
                toast.warning("Coba kamera depan...")
                await html5QrCode.start(
                    { facingMode: "user" }, // Kamera depan
                    config,
                    (decodedText) => {
                        scanCode.value = decodedText
                        handleScan()
                    },
                    () => { }
                )
                isScanning = true
                isFrontCamera.value = true
                currentCameraLabel.value = 'Kamera Depan (User)'

                toast.success("Menggunakan kamera depan")
            } catch (fallbackErr) {
                console.error("All fallbacks failed:", fallbackErr)
                toast.error("Gagal membuka kamera: " + (err.message || err))
                isCameraOpen.value = false
            }
        } else {
            toast.error("Gagal membuka kamera: " + (err.message || err))
            isCameraOpen.value = false
        }
    } finally {
        isInitializing.value = false
    }
}

const stopCamera = async () => {
    if (html5QrCode) {
        try {
            if (isScanning) {
                await html5QrCode.stop()
                isScanning = false
            }
            html5QrCode.clear()
        } catch (e) {
            console.log("Stop camera error:", e)
        }
        html5QrCode = null
    }
    isCameraOpen.value = false
    cameraMode.value = null
}

// Fungsi untuk force menggunakan kamera belakang (environment)
const useBackCamera = async () => {
    try {
        // Reset ke environment mode
        currentCameraId.value = null
        currentCameraLabel.value = 'Kamera Belakang (Environment)'
        isFrontCamera.value = false

        if (isScanning && cameraMode.value) {
            await stopCamera()
            await startScanner(cameraMode.value)
        }

        toast.success("Menggunakan kamera belakang")
        console.log('Forced back camera (environment mode)')
    } catch (err) {
        console.error("Force back camera error:", err)
        toast.error("Gagal mengatur kamera belakang")
    }
}

// Fungsi untuk force menggunakan kamera depan (user)
const useFrontCamera = async () => {
    try {
        // Cari kamera depan di devices
        const frontCamera = availableCameras.value.find(device => {
            const label = device.label.toLowerCase()
            return (
                label.includes('front') ||
                label.includes('user') ||
                label.includes('selfie')
            )
        })

        if (frontCamera) {
            currentCameraId.value = frontCamera.id
            currentCameraLabel.value = frontCamera.label
            isFrontCamera.value = true

            if (isScanning && cameraMode.value) {
                await stopCamera()
                await startScanner(cameraMode.value)
            }
            toast.success("Menggunakan kamera depan")
        } else {
            // Gunakan user facingMode
            currentCameraId.value = null
            currentCameraLabel.value = 'Kamera Depan (User)'
            isFrontCamera.value = true

            if (isScanning && cameraMode.value) {
                await stopCamera()
                await startScanner(cameraMode.value, { facingMode: "user" })
            }
            toast.success("Menggunakan kamera depan (user mode)")
        }
    } catch (err) {
        console.error("Force front camera error:", err)
        toast.error("Gagal mengatur kamera depan")
    }
}

// Gunakan langsung kamera belakang saat start
const startWithBackCamera = async (mode) => {
    // Reset ke environment mode
    currentCameraId.value = null
    currentCameraLabel.value = 'Kamera Belakang (Environment)'
    isFrontCamera.value = false

    // Start scanner
    await startScanner(mode)
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
            <div v-if="isCameraOpen" class="flex items-center gap-2">
                <span class="badge badge-error badge-sm animate-pulse">LIVE</span>
                <span class="text-xs text-gray-500 dark:text-slate-400">
                    {{ isFrontCamera ? 'Depan' : 'Belakang' }}
                </span>
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

                    <div class="flex flex-col gap-2">
                        <!-- Gunakan startWithBackCamera untuk memastikan kamera belakang -->
                        <button @click="startWithBackCamera('barcode')"
                            class="btn btn-primary btn-lg rounded-full px-8 shadow-lg shadow-primary-500/30">
                            <ScanBarcode class="mr-2" /> Mulai Scan Barcode
                        </button>
                        <button @click="startWithBackCamera('qr')"
                            class="btn btn-secondary btn-lg rounded-full px-8 shadow-lg shadow-secondary-500/30">
                            <QrCode class="mr-2" /> Mulai Scan QR
                        </button>
                    </div>
                </div>
            </div>

            <!-- Initializing State -->
            <div v-if="isInitializing" class="absolute inset-0 flex flex-col items-center justify-center bg-black z-20">
                <span class="loading loading-spinner loading-lg text-primary-500 mb-4"></span>
                <p class="text-white font-medium animate-pulse">Membuka Kamera...</p>
                <p class="text-gray-400 text-sm mt-2">{{ isFrontCamera ? 'Kamera depan' : 'Kamera belakang' }}</p>
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

            <!-- Camera Controls -->
            <div v-if="isCameraOpen" class="absolute top-4 right-4 z-20 flex gap-2">
                <button v-if="availableCameras.length > 1" @click="switchCamera"
                    class="btn btn-circle btn-sm bg-black/50 text-white border-0 backdrop-blur-md hover:bg-primary-500 transition-all"
                    :title="`Ganti kamera (${isFrontCamera ? 'ke belakang' : 'ke depan'})`">
                    <RefreshCw :size="18" />
                </button>

                <button @click="stopCamera"
                    class="btn btn-circle btn-sm bg-black/50 text-white border-0 backdrop-blur-md hover:bg-red-500 transition-all">
                    <X :size="18" />
                </button>
            </div>

            <!-- Camera Info -->
            <div v-if="isScanning" class="absolute bottom-4 left-4 z-20 flex flex-col gap-1">
                <div class="bg-black/50 text-white text-xs px-3 py-1 rounded-full backdrop-blur-md">
                    {{ availableCameras.length }} kamera tersedia
                </div>
                <div v-if="currentCameraLabel"
                    class="bg-black/50 text-white text-xs px-3 py-1 rounded-full backdrop-blur-md">
                    {{ currentCameraLabel.substring(0, 30) }}{{ currentCameraLabel.length > 30 ? '...' : '' }}
                </div>
            </div>
        </div>

        <!-- Force Camera Buttons (Debug) -->
        <div v-if="true && availableCameras.length > 1" class="flex gap-2 p-2">
            <button @click="useBackCamera" class="btn btn-xs btn-success flex-1">
                <Camera class="mr-1" size="12" /> Belakang
            </button>
            <button @click="useFrontCamera" class="btn btn-xs btn-outline flex-1">
                <Camera class="mr-1" size="12" /> Depan
            </button>
        </div>

        <!-- Manual Input -->
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

        <!-- Mode Selectors -->
        <div class="grid grid-cols-3 gap-2">
            <button @click="startWithBackCamera('qr')"
                class="flex flex-col items-center justify-center p-3 rounded-xl transition-all border"
                :class="cameraMode === 'qr' ? 'bg-primary-500 text-white border-primary-600 shadow-lg shadow-primary-500/30' : 'bg-white dark:bg-surface-800 text-slate-500 border-gray-200 dark:border-surface-700 hover:bg-gray-50 dark:hover:bg-surface-700'">
                <QrCode :size="24" class="mb-1" />
                <span class="text-[10px] font-bold">QR Code</span>
            </button>

            <button @click="startWithBackCamera('barcode')"
                class="flex flex-col items-center justify-center p-3 rounded-xl transition-all border"
                :class="cameraMode === 'barcode' ? 'bg-red-500 text-white border-red-600 shadow-lg shadow-red-500/30' : 'bg-white dark:bg-surface-800 text-slate-500 border-gray-200 dark:border-surface-700 hover:bg-gray-50 dark:hover:bg-surface-700'">
                <ScanBarcode :size="24" class="mb-1" />
                <span class="text-[10px] font-bold">Barcode Resi</span>
            </button>

            <button @click="focusInput"
                class="flex flex-col items-center justify-center p-3 rounded-xl transition-all border bg-white dark:bg-surface-800 text-slate-500 border-gray-200 dark:border-surface-700 hover:bg-gray-50 dark:hover:bg-surface-700">
                <Type :size="24" class="mb-1" />
                <span class="text-[10px] font-bold">Manual</span>
            </button>
        </div>

        <!-- Camera Info Card -->
        <div v-if="availableCameras.length > 0 && isCameraOpen"
            class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-3">
            <div class="flex items-center gap-2">
                <Camera :size="16" class="text-blue-500" />
                <span class="text-xs text-blue-700 dark:text-blue-300">
                    Kamera: {{ isFrontCamera ? 'Depan' : 'Belakang' }} |
                    {{ currentCameraLabel.substring(0, 40) }}{{ currentCameraLabel.length > 40 ? '...' : '' }}
                </span>
            </div>
        </div>

        <!-- Debug Info (Untuk troubleshooting) -->
        <div v-if="true" class="mt-4 p-4 bg-gray-100 dark:bg-gray-800 rounded-lg">
            <h3 class="text-sm font-bold mb-2">Debug Info:</h3>
            <pre class="text-xs overflow-auto max-h-40">{{JSON.stringify({
                availableCameras: availableCameras.value.map(cam => ({
                    id: cam.id.substring(0, 20) + '...',
                    label: cam.label
                })),
                currentCameraId: currentCameraId ? currentCameraId.substring(0, 30) + '...' : 'environment',
                currentCameraLabel: currentCameraLabel,
                isFrontCamera: isFrontCamera,
                isScanning: isScanning,
                cameraMode: cameraMode
            }, null, 2)}}</pre>
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
/* CSS Override untuk html5-qrcode */
#html5qr-code-full-region {
    border: none !important;
    width: 100% !important;
    height: 100% !important;
    position: relative !important;
}

#html5qr-code-full-region video {
    object-fit: cover !important;
    width: 100% !important;
    height: 100% !important;
    transform: scaleX(1) !important;
    /* Pastikan tidak mirror */
}

/* Scanner overlay styling */
.html5-qrcode-element {
    display: none !important;
}
</style>
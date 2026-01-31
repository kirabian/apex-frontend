<script setup>
import { ref, onMounted, nextTick, onBeforeUnmount } from 'vue'
import { onlineShop } from '../../api/axios'
import { useToast } from '../../composables/useToast'
import { ScanBarcode, Package, Search, Camera, X, RefreshCw, Type, ArrowRight, StopCircle, Zap } from 'lucide-vue-next'
import { Html5Qrcode } from 'html5-qrcode'
import Tesseract from 'tesseract.js'

const toast = useToast()
const scanInput = ref(null)
const scanCode = ref('')
const isLoading = ref(false)
const scanResult = ref(null)

// Camera State
const isCameraOpen = ref(true) // Default true for auto-start
const cameraMode = ref('barcode') // 'barcode' or 'ocr'
const scannerId = 'html5qr-code-full-region'
let html5QrCode = null
let isScanning = false

// Focus input on mount & start scanner
onMounted(() => {
    // delay slightly to ensure DOM is ready
    setTimeout(() => {
        startBarcodeScanner()
    }, 500)
})

onBeforeUnmount(() => {
    stopCamera()
})

const focusInput = () => {
    nextTick(() => {
        if (scanInput.value) scanInput.value.focus()
    })
}

const handleScan = async () => {
    if (!scanCode.value) return

    // Debounce: if already loading, skip
    if (isLoading.value) return

    isLoading.value = true
    try {
        // Haptic feedback
        if (navigator.vibrate) navigator.vibrate(200);

        const response = await onlineShop.scan(scanCode.value)
        scanResult.value = response.data
        toast.success(`Ditemukan: ${response.data.type === 'order' ? 'Pesanan' : 'Produk'}`)
        
        // Pause scanning momentarily so user can see result
        if (html5QrCode && isScanning) {
            await html5QrCode.pause()
        }
    } catch (error) {
        console.error(error)
        toast.error('Data tidak ditemukan atau error server')
        scanResult.value = null
        
        // If error, resume scanning after 2 seconds
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
        } catch (e) { console.log("resume failed/already running") }
    } else {
        startBarcodeScanner()
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

// --- CAMERA LOGIC ---

const startBarcodeScanner = async () => {
    // If already running, don't restart
    if (html5QrCode && isScanning) return

    stopCamera() // Ensure clean state
    isCameraOpen.value = true
    cameraMode.value = 'barcode'
    scanResult.value = null 
    
    await nextTick()
    
    html5QrCode = new Html5Qrcode(scannerId)
    
    // Config specifically tuned for Shipping Labels (Wide Barcodes)
    // We use a function for qrbox to make it responsive and wide
    const qrboxFunction = (viewfinderWidth, viewfinderHeight) => {
        const minEdgePercentage = 0.85; // 85% width
        const minEdgeSize = Math.min(viewfinderWidth, viewfinderHeight);
        const qrboxWidth = Math.floor(viewfinderWidth * minEdgePercentage);
        const qrboxHeight = Math.floor(viewfinderHeight * 0.4); // 40% height (wide rectangle)
        return {
            width: qrboxWidth,
            height: qrboxHeight
        };
    }

    const config = { 
        fps: 20, // Higher FPS for faster scanning
        qrbox: qrboxFunction,
        // experimentalFeatures: { useBarCodeDetectorIfSupported: true }, // Try browser native if available
        rememberLastUsedCamera: true,
        aspectRatio: 1.0
    }
    
    try {
        await html5QrCode.start(
            { 
                facingMode: "environment",
                focusMode: "continuous", // Critical for text/barcodes
                advanced: [ { zoom: 2.0 } ] // Optional: start with slight zoom for text if supported? maybe risky.
            },
            {
                ...config,
                formatsToSupport: [ 
                    0, // QR_CODE
                    1, // AZTEC
                    2, // CODABAR
                    3, // CODE_39
                    4, // CODE_93
                    5, // CODE_128 (Most common for Resi)
                    6, // DATA_MATRIX
                    7, // EAN_8
                    8, // EAN_13
                    9, // ITF
                    10, // MAXICODE
                    11, // PDF_417
                    12, // RSS_14
                    13, // RSS_EXPANDED
                    14, // UPC_A
                    15, // UPC_E
                    16  // UPC_EAN_EXTENSION
                ]
            },
            (decodedText) => {
                scanCode.value = decodedText
                handleScan()
            },
            (errorMessage) => { /* ignore */ }
        )
        isScanning = true
    } catch (err) {
        console.error("Error starting scanner", err)
        toast.error("Gagal membuka kamera.")
        isCameraOpen.value = false
    }
}

const startOCR = async () => {
    if (cameraMode.value === 'ocr') return;
    
    stopCamera()
    isCameraOpen.value = true
    cameraMode.value = 'ocr'
    scanResult.value = null
    
    await nextTick()
    
    html5QrCode = new Html5Qrcode(scannerId)
    try {
        await html5QrCode.start(
            { facingMode: "environment" },
            { fps: 15, qrbox: { width: 300, height: 100 } },
            (text) => {}, 
            (err) => {}
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
            toast.success(`Text terdeteksi: ${candidate}`)
            handleScan()
        } else {
            toast.error("Tidak dapat membaca Text/Resi dari gambar.")
        }
    } catch (err) {
        toast.error("Gagal memproses OCR.")
    } finally {
        isLoading.value = false
    }
}

const stopCamera = async () => {
    if (html5QrCode) {
        try {
            if (isScanning) await html5QrCode.stop()
            html5QrCode.clear()
        } catch (e) {
            // ignore cleanup errors
        }
        html5QrCode = null
        isScanning = false
    }
    // isCameraOpen.value = false // Don't hide the container, just stop feed if needed
}

const toggleCamera = () => {
    if (isScanning) {
        stopCamera()
        isCameraOpen.value = false
    } else {
        startBarcodeScanner()
    }
}
</script>

<template>
  <div class="space-y-4 max-w-xl mx-auto pb-20">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <ScanBarcode class="text-primary-500" />
          Scan Pesanan
        </h1>
        <p class="text-gray-500 dark:text-slate-400 text-sm">Input data via Barcode, OCR, atau Manual.</p>
      </div>
    </div>

    <!-- Camera Section -->
    <div class="relative overflow-hidden bg-black rounded-2xl shadow-2xl aspect-3/4 md:aspect-video mb-4">
        <!-- Camera Feed Container -->
        <div id="html5qr-code-full-region" class="w-full h-full"></div>
        
        <!-- Overlays -->
        <div v-if="!isCameraOpen" class="absolute inset-0 flex items-center justify-center bg-surface-900 border-2 border-surface-700 rounded-2xl">
            <div class="text-center p-6">
                <StopCircle :size="48" class="mx-auto text-slate-500 mb-2" />
                <p class="text-slate-400">Kamera Nonaktif</p>
                <button @click="startBarcodeScanner" class="btn btn-primary mt-4">
                    <Camera :size="20" class="mr-2" /> Aktifkan Kamera
                </button>
            </div>
        </div>

        <!-- Scanning Overlay UI -->
        <div v-if="isScanning" class="absolute inset-0 pointer-events-none">
            <!-- Scan Guide Line -->
            <div class="absolute top-1/2 left-0 right-0 h-0.5 bg-red-500/50 shadow-[0_0_10px_rgba(239,68,68,0.8)] px-10">
                <div class="w-full h-full bg-red-500 animate-pulse"></div>
            </div>
            
            <!-- Corner Markers -->
            <div class="absolute top-1/4 left-1/4 w-8 h-8 border-t-2 border-l-2 border-primary-500 rounded-tl-lg"></div>
            <div class="absolute top-1/4 right-1/4 w-8 h-8 border-t-2 border-r-2 border-primary-500 rounded-tr-lg"></div>
            <div class="absolute bottom-1/4 left-1/4 w-8 h-8 border-b-2 border-l-2 border-primary-500 rounded-bl-lg"></div>
            <div class="absolute bottom-1/4 right-1/4 w-8 h-8 border-b-2 border-r-2 border-primary-500 rounded-br-lg"></div>
        </div>
        
        <!-- Controls -->
        <div class="absolute bottom-4 left-0 right-0 flex justify-center gap-4 px-4 pointer-events-auto z-20">
             <button 
                @click="cameraMode === 'barcode' ? startOCR() : startBarcodeScanner()" 
                class="btn btn-sm btn-circle bg-black/50 text-white hover:bg-black/70 border-none backdrop-blur-md w-12 h-12"
            >
                <div class="flex flex-col items-center text-[10px]">
                     <component :is="cameraMode === 'barcode' ? Type : ScanBarcode" :size="20" />
                </div>
            </button>
            
            <button 
                v-if="cameraMode === 'ocr'"
                @click="captureAndOCR" 
                class="btn btn-lg btn-circle bg-white text-black border-4 border-slate-300 w-16 h-16 shadow-lg"
                :disabled="isLoading"
            >
                <div v-if="isLoading" class="loading loading-spinner"></div>
                <div v-else class="w-12 h-12 rounded-full bg-transparent border-2 border-black" />
            </button>
            
             <button @click="toggleCamera" class="btn btn-sm btn-circle bg-black/50 text-white hover:bg-black/70 border-none backdrop-blur-md w-12 h-12">
                <StopCircle v-if="isScanning" :size="20" />
                <Zap v-else :size="20" />
            </button>
        </div>
        
        <!-- Mode Indicator -->
        <div class="absolute top-4 left-0 right-0 flex justify-center pointer-events-none">
            <span class="px-3 py-1 bg-black/50 backdrop-blur-md text-white/90 rounded-full text-xs font-medium uppercase tracking-wider border border-white/10">
                {{ cameraMode === 'barcode' ? 'Auto Scan Mode' : 'OCR / Text Mode' }}
            </span>
        </div>
    </div>

    <!-- Manual Input & Result -->
    <div class="card p-0 overflow-hidden bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-700 shadow-sm">
      
      <!-- Result View -->
      <div v-if="scanResult" class="p-6 bg-gray-50 dark:bg-surface-800 animate-in slide-in-from-top-4 duration-300">
           <div class="flex justify-between items-start mb-4">
                <div>
                    <span class="badge mb-2" :class="scanResult.type === 'order' ? 'badge-info' : 'badge-success'">
                         {{ scanResult.type === 'order' ? 'PESANAN' : 'PRODUK' }}
                    </span>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white leading-tight">
                        {{ scanResult.type === 'order' ? scanResult.data.order_number : scanResult.data.name }}
                    </h2>
                    <p class="text-gray-500 dark:text-slate-400 text-sm mt-1">
                        {{ scanResult.type === 'order' ? scanResult.data.platform : `SKU: ${scanResult.data.sku}` }}
                    </p>
                </div>
                <button @click="resetScan" class="btn btn-sm btn-ghost btn-circle text-gray-500 hover:text-gray-900 dark:text-slate-400 dark:hover:text-white">
                    <X :size="20" />
                </button>
           </div>
           
           <!-- Order Actions -->
            <div v-if="scanResult.type === 'order'" class="space-y-4">
                 <div class="grid grid-cols-2 gap-3 text-sm bg-white dark:bg-surface-900/50 p-3 rounded-xl border border-gray-200 dark:border-surface-700/50">
                    <div>
                        <p class="text-gray-500 dark:text-slate-500 text-xs">Customer</p>
                        <p class="text-gray-900 dark:text-white font-medium truncate">{{ scanResult.data.customer_name || '-' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500 dark:text-slate-500 text-xs">Status</p>
                        <span class="badge badge-sm font-bold uppercase mt-1" :class="{
                            'badge-warning': scanResult.data.status === 'pending',
                            'badge-info': scanResult.data.status === 'packed',
                            'badge-success': scanResult.data.status === 'shipped'
                        }">{{ scanResult.data.status }}</span>
                    </div>
                </div>

                <div class="flex gap-2">
                    <button @click="updateStatus('packed')" class="btn btn-secondary flex-1 h-12">
                        <Package :size="18" class="mr-1" /> Packed
                    </button>
                    <button @click="updateStatus('shipped')" class="btn btn-primary flex-1 h-12">
                        <ArrowRight :size="18" class="mr-1" /> Kirim
                    </button>
                </div>
            </div>
            
            <!-- Product Info -->
            <div v-if="scanResult.type === 'product'" class="space-y-4">
                 <div class="grid grid-cols-2 gap-4 text-sm bg-white dark:bg-surface-900/50 p-4 rounded-xl border border-gray-200 dark:border-surface-700/50">
                    <div>
                        <p class="text-gray-500 dark:text-slate-500">Harga</p>
                        <p class="text-gray-900 dark:text-white font-mono">{{ formatCurrency(scanResult.data.price) }}</p>
                    </div>
                </div>
            </div>
            
             <div class="mt-4 pt-4 border-t border-gray-200 dark:border-surface-700 text-center">
                <button @click="resetScan" class="btn btn-outline btn-sm w-full">
                    Scan Berikutnya
                </button>
            </div>
      </div>

       <!-- Manual Input (Fallback) -->
      <div v-else class="p-4">
         <div class="relative">
            <Search class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 dark:text-slate-500" :size="18" />
            <input
                ref="scanInput"
                v-model="scanCode"
                @keyup.enter="handleScan"
                type="text"
                class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl py-3 pl-10 pr-4 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500 transition-all font-mono placeholder:text-gray-400 dark:placeholder:text-slate-600"
                placeholder="Ketuk untuk input manual..."
                :disabled="isLoading"
            />
             <div v-if="isLoading" class="absolute right-4 top-1/2 -translate-y-1/2">
                <span class="loading loading-spinner loading-sm text-primary-500"></span>
            </div>
        </div>
      </div>
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

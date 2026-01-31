<script setup>
import { ref, onMounted, nextTick, onBeforeUnmount } from 'vue'
import { onlineShop } from '../../api/axios'
import { useToast } from '../../composables/useToast'
import { ScanBarcode, Package, Search, Camera, X, RefreshCw, Type, ArrowRight } from 'lucide-vue-next'
import { Html5Qrcode } from 'html5-qrcode'
import Tesseract from 'tesseract.js'

const toast = useToast()
const scanInput = ref(null)
const scanCode = ref('')
const isLoading = ref(false)
const scanResult = ref(null)

// Camera State
const isCameraOpen = ref(false)
const cameraMode = ref('barcode') // 'barcode' or 'ocr'
const scannerId = 'html5qr-code-full-region'
let html5QrCode = null

// Focus input on mount
onMounted(() => {
    focusInput()
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

    isLoading.value = true
    try {
        const response = await onlineShop.scan(scanCode.value)
        scanResult.value = response.data
        toast.success(`Ditemukan: ${response.data.type === 'order' ? 'Pesanan' : 'Produk'}`)
        
        // If successful and camera is open, close it (optional, but good UX)
        if (isCameraOpen.value && cameraMode.value === 'barcode') {
             stopCamera()
        }
    } catch (error) {
        console.error(error)
        toast.error('Data tidak ditemukan atau error server')
        scanResult.value = null
    } finally {
        isLoading.value = false
        // Don't auto-clear scanCode immediately so user can see what they scanned
        // scanCode.value = '' 
        // focusInput()
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
        // Update local state
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
    isCameraOpen.value = true
    cameraMode.value = 'barcode'
    scanResult.value = null // Clear previous result
    
    await nextTick()
    
    html5QrCode = new Html5Qrcode(scannerId)
    
    const config = { fps: 10, qrbox: { width: 250, height: 250 } }
    
    try {
        await html5QrCode.start(
            { facingMode: "environment" },
            config,
            (decodedText, decodedResult) => {
                // Success callback
                scanCode.value = decodedText
                handleScan()
                // Stop scanning after success? User usually wants to scan continuously or stop.
                // Let's pause or just show feedback.
                // For now, we keep it open for continuous scanning, 
                // but handleScan handles the API call.
            },
            (errorMessage) => {
                // Parse error, ignore
            }
        )
    } catch (err) {
        console.error("Error starting scanner", err)
        toast.error("Gagal membuka kamera. Pastikan izin diberikan.")
        isCameraOpen.value = false
    }
}

const startOCR = async () => {
    isCameraOpen.value = true
    cameraMode.value = 'ocr'
    scanResult.value = null
    
    await nextTick()
    
    // Use Html5Qrcode just to get the camera stream view, 
    // but we will capture snapshot for Tesseract
    // Actually, Tesseract doesn't need a live stream, but we need to show the user what they are capturing.
    // simpler: Use a file input (Take Photo) or simple video element.
    // Let's stick to using the same video element from html5qrcode but purely for display, then capture frame.
    
    // OR: simpler mobile approach: <input type="file" capture="environment"> and run OCR on that.
    // However, user asked for "fitur scan". A live view is better.
    // Let's reuse Html5Qrcode purely for the camera feed, but we WON'T use its scanning capabilities.
    
    html5QrCode = new Html5Qrcode(scannerId)
    try {
        await html5QrCode.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: { width: 300, height: 100 } }, // wider box for text
            (text) => {}, // Ignore barcode results in OCR mode
            (err) => {}
        )
    } catch (err) {
         toast.error("Gagal membuka kamera.")
         isCameraOpen.value = false
    }
}

const captureAndOCR = async () => {
    // Capture details from the video feed
    // Note: html5-qrcode doesn't easily expose "capture frame".
    // We might need to access the video element directly.
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
        const { data: { text } } = await Tesseract.recognize(image, 'eng', {
           // logger: m => console.log(m)
        })
        
        // Simple heuristic: Look for something that looks like an Order ID
        // e.g., uppercase letters + numbers, no spaces, length > 5
        const candidate = text.split(/\s+/).find(w => w.length > 5 && /\d/.test(w))
        
        if (candidate) {
            scanCode.value = candidate
            toast.success(`Text terdeteksi: ${candidate}`)
            handleScan()
        } else {
            toast.error("Tidak dapat membaca Text/Resi dari gambar.")
        }
    } catch (err) {
        console.error(err)
        toast.error("Gagal memproses OCR.")
    } finally {
        isLoading.value = false
    }
}

const stopCamera = async () => {
    if (html5QrCode) {
        try {
            await html5QrCode.stop()
            html5QrCode.clear()
        } catch (e) {
            console.error("Failed to stop scanner", e)
        }
        html5QrCode = null
    }
    isCameraOpen.value = false
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-white flex items-center gap-2">
          <ScanBarcode class="text-primary-500" />
          Scan Pesanan
        </h1>
        <p class="text-slate-400 text-sm">Input data via Barcode, OCR, atau Manual.</p>
      </div>
    </div>

    <!-- Camera View (Modal-ish) -->
    <div v-if="isCameraOpen" class="fixed inset-0 z-[100] bg-black flex flex-col">
        <div class="p-4 flex justify-between items-center bg-black/50 absolute top-0 w-full z-10">
            <span class="text-white font-bold">
                {{ cameraMode === 'barcode' ? 'Arahkan ke Barcode' : 'Arahkan ke Nomor Resi' }}
            </span>
            <button @click="stopCamera" class="p-2 bg-white/10 rounded-full text-white">
                <X :size="24" />
            </button>
        </div>
        
        <div class="flex-1 flex items-center justify-center bg-black relative">
            <div id="html5qr-code-full-region" class="w-full"></div>
            
            <!-- OCR Trigger -->
            <div v-if="cameraMode === 'ocr'" class="absolute bottom-20 left-0 right-0 flex justify-center pb-8">
                 <button 
                    @click="captureAndOCR" 
                    class="btn btn-primary btn-lg rounded-full w-20 h-20 flex items-center justify-center border-4 border-white shadow-xl"
                    :disabled="isLoading"
                >
                    <div v-if="isLoading" class="loading loading-spinner text-white"></div>
                    <Camera v-else :size="32" />
                </button>
            </div>
        </div>
    </div>

    <!-- Main Input Area -->
    <div class="card p-6">
       <!-- Action Buttons -->
       <div class="grid grid-cols-2 gap-4 mb-6">
            <button @click="startBarcodeScanner" class="btn bg-surface-700 hover:bg-surface-600 border-none text-white h-auto py-4 flex flex-col gap-2 rounded-xl">
                <ScanBarcode :size="32" class="text-primary-400" />
                <span class="font-bold">Scan Barcode</span>
            </button>
            <button @click="startOCR" class="btn bg-surface-700 hover:bg-surface-600 border-none text-white h-auto py-4 flex flex-col gap-2 rounded-xl">
                 <Type :size="32" class="text-secondary-400" />
                 <span class="font-bold">Scan OCR / Text</span>
            </button>
       </div>

      <div class="relative">
        <Search class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500" />
        <input
            ref="scanInput"
            v-model="scanCode"
            @keyup.enter="handleScan"
            type="text"
            class="w-full bg-slate-900 border border-slate-700 rounded-xl py-4 pl-12 pr-4 text-lg text-white focus:outline-none focus:ring-2 focus:ring-primary-500 shadow-inner"
            placeholder="Ketik manual..."
            :disabled="isLoading"
        />
        <div v-if="isLoading" class="absolute right-4 top-1/2 -translate-y-1/2">
            <span class="loading loading-spinner text-primary-500"></span>
        </div>
      </div>
    </div>

    <!-- Result Area -->
    <div v-if="scanResult" class="grid grid-cols-1 gap-6 animate-in fade-in slide-in-from-bottom-4 duration-300">
        <!-- Result Card -->
        <div class="card p-6 border-l-4" :class="scanResult.type === 'order' ? 'border-l-blue-500' : 'border-l-emerald-500'">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <span class="badge mb-2" :class="scanResult.type === 'order' ? 'badge-info' : 'badge-success'">
                        {{ scanResult.type === 'order' ? 'PESANAN' : 'PRODUK' }}
                    </span>
                    <h2 class="text-xl font-bold text-white break-all">
                        {{ scanResult.type === 'order' ? scanResult.data.order_number : scanResult.data.name }}
                    </h2>
                    <p class="text-slate-400 text-sm mt-1">
                        {{ scanResult.type === 'order' ? scanResult.data.platform : `SKU: ${scanResult.data.sku}` }}
                    </p>
                </div>
                <button @click="scanResult = null; scanCode = ''; focusInput()" class="btn btn-ghost btn-sm btn-circle text-slate-400">
                    <X :size="20" />
                </button>
            </div>

            <!-- Order Actions -->
            <div v-if="scanResult.type === 'order'" class="space-y-4">
                 <div class="grid grid-cols-2 gap-4 text-sm bg-slate-800/50 p-4 rounded-lg">
                    <div>
                        <p class="text-slate-500">Customer</p>
                        <p class="text-white font-medium">{{ scanResult.data.customer_name || '-' }}</p>
                    </div>
                    <div>
                        <p class="text-slate-500">Status</p>
                        <span class="badge badge-sm font-bold uppercase mt-1" :class="{
                            'badge-warning': scanResult.data.status === 'pending',
                            'badge-info': scanResult.data.status === 'packed',
                            'badge-success': scanResult.data.status === 'shipped'
                        }">{{ scanResult.data.status }}</span>
                    </div>
                </div>

                <div class="flex gap-2">
                    <button @click="updateStatus('packed')" class="btn btn-secondary flex-1">
                        <Package :size="16" /> Packet
                    </button>
                    <button @click="updateStatus('shipped')" class="btn btn-primary flex-1">
                        <ArrowRight :size="16" /> Kirim
                    </button>
                </div>
            </div>

             <!-- Product Info -->
            <div v-if="scanResult.type === 'product'" class="space-y-4">
                 <div class="grid grid-cols-2 gap-4 text-sm bg-slate-800/50 p-4 rounded-lg">
                    <div>
                        <p class="text-slate-500">Harga</p>
                        <p class="text-white font-mono">{{ formatCurrency(scanResult.data.price) }}</p>
                    </div>
                    <div>
                        <p class="text-slate-500">Stock</p>
                        <p class="font-bold text-white">
                            {{ scanResult.data.stock_count || '-' }}
                            <span class="text-xs font-normal text-slate-500">units</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

  </div>
</template>

<style>
/* Custom override for html5-qrcode video to fill container */
#html5qr-code-full-region video {
    object-fit: cover;
    border-radius: 0;
    width: 100% !important;
}
#html5qr-code-full-region {
    border: none !important;
}
</style>

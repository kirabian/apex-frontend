<script setup>
import { ref, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode'
import { useRouter } from 'vue-router'
import { useToast } from '../../composables/useToast'
import axios from 'axios'
// Icons
import { QrCode, Camera, X, CheckCircle, AlertCircle, RefreshCw, LogIn, LogOut, CameraOff, Repeat, ArrowLeft } from 'lucide-vue-next'

const router = useRouter()
const toast = useToast()

// State
const step = ref('scan') // 'scan', 'verify', 'result'
const isLoading = ref(false)
const html5QrCode = ref(null)
const scannerId = "reader"
const streamRef = ref(null)
const cameraError = ref(null)

// Data
const user = ref(null)
const capturedPhoto = ref(null)
const scanNotes = ref('')
const attendanceResult = ref(null)
const scanMode = ref('scan') // 'scan' | 'verify'

// Refs for DOM elements
const videoRef = ref(null)
const canvasRef = ref(null)

const isMobile = () => /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)

onMounted(() => {
    // Set safe height logic if needed, usually CSS 'dwh' or '100vh' works well in Vue
    startQRScanner()
})

onBeforeUnmount(() => {
    stopScanner()
    stopCameraStream()
})

// --- QR SCANNER LOGIC ---

const startQRScanner = async () => {
    step.value = 'scan'
    cameraError.value = null
    await nextTick()

    // Cleanup first
    if (html5QrCode.value) {
        try {
            await html5QrCode.value.stop()
            html5QrCode.value.clear()
        } catch (e) { }
    }

    const config = {
        fps: 20,
        qrbox: { width: 250, height: 250 },
        aspectRatio: 1.0,
        formatsToSupport: [0] // QR_CODE
    }

    html5QrCode.value = new Html5Qrcode(scannerId, {
        experimentalFeatures: { useBarCodeDetectorIfSupported: true },
        verbose: false
    })

    try {
        await html5QrCode.value.start(
            { facingMode: "environment" },
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
    // Stop scanner logic
    await stopScanner()

    // Process User
    await checkUser(decodedText)
}

const checkUser = async (qrCode) => {
    isLoading.value = true
    try {
        // MOCK API for testing camera flow (Remove this block when backend is ready)
        // Simulate success
        // await new Promise(r => setTimeout(r, 1000))
        // user.value = {
        //     id: 999,
        //     name: "Test User",
        //     role: "Security",
        //     division: "General",
        //     branch: "Pusat",
        //     photo_url: "https://ui-avatars.com/api/?name=Test+User"
        // }
        // showVerificationPage()
        // return

        // REAL API CALL
        // Note: You need to create this endpoint in backend
        const response = await axios.post('/api/security/check-user', { qr_code: qrCode })

        if (response.data.status === 'success') {
            user.value = response.data.data
            showVerificationPage()
        } else {
            toast.error(response.data.message || 'User tidak ditemukan')
            startQRScanner() // Restart
        }

    } catch (err) {
        console.error(err)
        // toast.error("Gagal cek user: " + (err.response?.data?.message || err.message))

        // FALLBACK MOCK for testing without backend
        toast.info("Backend error, menggunakan MOCK data untuk test kamera")
        user.value = {
            id: 123,
            name: "MOCK USER",
            role: "Security",
            division: "IT",
            branch: "Pusat",
            photo_url: "https://ui-avatars.com/api/?name=Mock+User"
        }
        showVerificationPage()
    } finally {
        isLoading.value = false
    }
}

// --- VERIFICATION & CAMERA STREAM LOGIC ---

const showVerificationPage = () => {
    step.value = 'verify'
    scanMode.value = 'stream' // Start with stream view
    capturedPhoto.value = null
    scanNotes.value = ''

    nextTick(() => {
        startCameraStream()
    })
}

const startCameraStream = async () => {
    // Stop existing
    stopCameraStream()

    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: "environment", // STRICTLY BACK CAMERA
                    width: { ideal: 640 },
                    height: { ideal: 480 }
                },
                audio: false
            })

            streamRef.value = stream
            if (videoRef.value) {
                videoRef.value.srcObject = stream
                await videoRef.value.play()
            }
        } catch (err) {
            console.error("Camera Stream Error:", err)
            toast.error("Gagal buka kamera verifikasi")
        }
    }
}

const stopCameraStream = () => {
    if (streamRef.value) {
        streamRef.value.getTracks().forEach(track => track.stop())
        streamRef.value = null
    }
}

const capturePhoto = () => {
    if (!videoRef.value || !canvasRef.value) return

    const video = videoRef.value
    const canvas = canvasRef.value

    // Set canvas dimensions
    canvas.width = video.videoWidth
    canvas.height = video.videoHeight

    const context = canvas.getContext('2d')
    context.drawImage(video, 0, 0, canvas.width, canvas.height)

    // Convert to base64
    capturedPhoto.value = canvas.toDataURL('image/jpeg', 0.8)

    // Switch mode to 'captured' to show canvas instead of video
    scanMode.value = 'captured'
}

const retakePhoto = () => {
    capturedPhoto.value = null
    scanMode.value = 'stream'
    // Ensure video is playing
    if (videoRef.value && videoRef.value.paused) {
        videoRef.value.play().catch(console.error)
    }
}

const submitAttendance = async (type) => { // 'masuk' | 'pulang'
    if (!capturedPhoto.value) {
        toast.warning("Foto wajib diambil!")
        return
    }

    isLoading.value = true
    try {
        // MOCK SUCCESS
        // await new Promise(r => setTimeout(r, 1000))
        // attendanceResult.value = {
        //     type: type,
        //     time: new Date().toLocaleTimeString(),
        //     date: new Date().toLocaleDateString(),
        //     is_late: false,
        //     is_early_checkout: false,
        //     notes: scanNotes.value,
        //     photo: capturedPhoto.value
        // }
        // step.value = 'result'
        // return

        // REAL API
        const response = await axios.post('/api/security/attendance', {
            user_id: user.value.id,
            type: type,
            image: capturedPhoto.value,
            notes: scanNotes.value
        })

        if (response.data.status === 'success') {
            attendanceResult.value = { ...response.data.data, type }
            step.value = 'result'
        } else {
            toast.error(response.data.message)
        }

    } catch (err) {
        console.error(err)
        // toast.error("Gagal submit absen")

        // FALLBACK MOCK
        toast.success("Absen Berhasil (Mock)")
        attendanceResult.value = {
            type: type,
            time: new Date().toLocaleTimeString('id-ID'),
            date: new Date().toLocaleDateString('id-ID'),
            is_late: false,
            is_early_checkout: false,
            notes: scanNotes.value,
            photo: capturedPhoto.value
        }
        step.value = 'result'
    } finally {
        isLoading.value = false
        stopCameraStream() // Stop camera when done
    }
}

const resetScan = () => {
    stopCameraStream()
    user.value = null
    capturedPhoto.value = null
    attendanceResult.value = null
    scanNotes.value = ''
    startQRScanner()
}

</script>

<template>
    <div class="scanner-page bg-black min-h-screen text-white overflow-hidden relative font-sans">

        <!-- STEP 1: SCANNER -->
        <div v-show="step === 'scan'" class="w-full h-full absolute inset-0 flex flex-col items-center justify-center">

            <div
                class="absolute top-0 left-0 w-full p-6 pt-12 z-20 bg-gradient-to-b from-black/90 to-transparent text-center">
                <h1 class="text-2xl font-bold flex items-center justify-center gap-2">
                    <QrCode class="w-6 h-6 text-green-500" /> Scan Absensi
                </h1>
                <p class="text-gray-400 text-sm mt-1">Arahkan kamera ke QR Code</p>
            </div>

            <div id="reader" class="w-full h-full bg-black relative"></div>

            <!-- Permission Error -->
            <div v-if="cameraError"
                class="absolute inset-0 z-30 flex flex-col items-center justify-center bg-black/90 p-6 text-center">
                <CameraOff class="w-16 h-16 text-red-500 mb-4" />
                <h3 class="text-xl font-bold mb-2">Akses Ditolak</h3>
                <p class="text-gray-400 mb-6">{{ cameraError }}</p>
                <button @click="startQRScanner" class="btn btn-primary rounded-full px-6">
                    <RefreshCw class="w-4 h-4 mr-2" /> Coba Lagi
                </button>
            </div>

            <!-- Loading Indicator -->
            <div v-if="isLoading"
                class="absolute inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
                <span class="loading loading-spinner loading-lg text-green-500"></span>
            </div>
        </div>

        <!-- STEP 2: VERIFY & CAPTURE -->
        <div v-if="step === 'verify'"
            class="w-full h-full absolute inset-0 bg-surface-900 flex flex-col z-40 overflow-y-auto">
            <!-- Header -->
            <div class="p-6 pt-12 flex justify-between items-center bg-surface-800 border-b border-surface-700">
                <h2 class="font-bold flex items-center gap-2">
                    <CheckCircle class="w-5 h-5 text-green-500" /> Konfirmasi
                </h2>
                <button @click="resetScan" class="btn btn-sm btn-ghost btn-circle text-gray-400">
                    <X class="w-5 h-5" />
                </button>
            </div>

            <div class="p-6 flex-1 flex flex-col pb-24"> <!-- pb-24 for fixed bottom buttons -->
                <!-- Profile Card -->
                <div class="bg-surface-800 rounded-2xl p-6 text-center border border-surface-700 mb-6">
                    <img :src="user?.photo_url || 'https://via.placeholder.com/150'"
                        class="w-20 h-20 rounded-full mx-auto mb-3 border-2 border-green-500 object-cover" />
                    <h3 class="text-xl font-bold">{{ user?.name }}</h3>
                    <p class="text-gray-400 text-sm">{{ user?.role }} - {{ user?.division }}</p>
                    <div class="badge badge-primary mt-2">{{ user?.branch }}</div>
                </div>

                <!-- Camera Preview / Capture -->
                <div class="mb-2 text-sm font-bold uppercase text-gray-400 flex items-center gap-2">
                    <Camera class="w-4 h-4" /> Bukti Foto (Wajib)
                </div>

                <div
                    class="relative bg-black rounded-2xl overflow-hidden aspect-[4/3] border-2 border-surface-600 mb-6 shadow-lg">
                    <video ref="videoRef" v-show="scanMode === 'stream'" autoplay playsinline muted
                        class="w-full h-full object-cover"></video>
                    <canvas ref="canvasRef" v-show="scanMode === 'captured'"
                        class="w-full h-full object-cover"></canvas>
                </div>

                <!-- Capture Actions -->
                <div v-if="scanMode === 'stream'">
                    <button @click="capturePhoto"
                        class="btn w-full btn-lg bg-white text-black hover:bg-gray-200 border-0 rounded-xl font-bold shadow-lg">
                        <Camera class="w-6 h-6 mr-2" /> AMBIL FOTO
                    </button>
                </div>

                <div v-else class="space-y-4 animate-in slide-in-from-bottom-4 fade-in">
                    <!-- Notes -->
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text text-gray-400 text-xs font-bold uppercase">Catatan (Opsional)</span>
                        </label>
                        <textarea v-model="scanNotes" class="textarea textarea-bordered bg-surface-800 h-20"
                            placeholder="Lembur, tugas luar, dll..."></textarea>
                    </div>

                    <!-- Confirm Actions -->
                    <button @click="retakePhoto"
                        class="btn btn-outline w-full rounded-xl border-surface-600 text-gray-300">
                        <Repeat class="w-4 h-4 mr-2" /> Foto Ulang
                    </button>

                    <div class="grid grid-cols-2 gap-3 mt-2">
                        <button @click="submitAttendance('masuk')"
                            class="btn btn-success text-white border-0 bg-gradient-to-r from-green-500 to-emerald-600 rounded-xl py-4 h-auto flex flex-col gap-1">
                            <LogIn class="w-6 h-6" /> <span>MASUK</span>
                        </button>
                        <button @click="submitAttendance('pulang')"
                            class="btn btn-warning text-white border-0 bg-gradient-to-r from-orange-500 to-red-500 rounded-xl py-4 h-auto flex flex-col gap-1">
                            <LogOut class="w-6 h-6" /> <span>PULANG</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- STEP 3: RESULT -->
        <div v-if="step === 'result'"
            class="w-full h-full absolute inset-0 bg-black/95 backdrop-blur-md z-50 flex flex-col overflow-y-auto animate-in fade-in zoom-in-95">
            <div class="flex-1 flex flex-col items-center justify-center p-8 text-center">
                <div class="mb-6">
                    <CheckCircle class="w-24 h-24 text-green-500 animate-bounce" />
                </div>

                <h1 class="text-3xl font-bold text-green-500 mb-2">ABSEN BERHASIL</h1>
                <p class="text-gray-400 mb-8">{{ attendanceResult?.date }}</p>

                <!-- Ticket Card -->
                <div
                    class="bg-surface-800/50 rounded-2xl p-6 w-full max-w-sm border border-surface-700 backdrop-blur-sm shadow-2xl">
                    <img :src="user?.photo_url" class="w-16 h-16 rounded-full mx-auto mb-4 border-2 border-white/20" />
                    <h2 class="text-xl font-bold">{{ user?.name }}</h2>
                    <p class="text-sm text-gray-400 mb-4">{{ user?.role }}</p>

                    <div class="divider my-2"></div>

                    <div class="flex justify-between text-sm py-2">
                        <span class="text-gray-400">Tipe</span>
                        <span class="font-bold uppercase"
                            :class="attendanceResult?.type === 'masuk' ? 'text-green-400' : 'text-orange-400'">{{
                            attendanceResult?.type }}</span>
                    </div>
                    <div class="flex justify-between text-sm py-2">
                        <span class="text-gray-400">Waktu</span>
                        <span class="font-bold text-white text-lg">{{ attendanceResult?.time }}</span>
                    </div>
                    <div class="flex justify-between text-sm py-2">
                        <span class="text-gray-400">Status</span>
                        <span class="badge badge-success font-bold text-white">TEPAT WAKTU</span>
                    </div>

                    <div v-if="attendanceResult?.notes"
                        class="mt-4 bg-surface-900/50 p-3 rounded-lg text-left text-xs text-gray-300">
                        <span class="block text-gray-500 mb-1">Catatan:</span>
                        "{{ attendanceResult?.notes }}"
                    </div>
                </div>

                <div class="mt-8 w-full max-w-sm space-y-3">
                    <button @click="resetScan" class="btn btn-primary w-full rounded-full btn-lg">
                        Scan Selanjutnya
                    </button>
                    <button @click="router.push('/dashboard')" class="btn btn-ghost w-full rounded-full text-gray-400">
                        Kembali ke Dashboard
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Force video to not flip/mirror */
video {
    transform: none !important;
    /* The user specifically wanted NO mirroring for back camera */
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

<script setup>
import { ref, computed } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "../../store/auth";
import { Eye, EyeOff, Lock, User, Loader2 } from "lucide-vue-next";

const router = useRouter();
const authStore = useAuthStore();

const form = ref({
  username: "",
  password: "",
});

const showPassword = ref(false);
const rememberMe = ref(false);
const isLoading = ref(false);
const error = ref("");

const isFormValid = computed(() => form.value.username && form.value.password);

async function handleLogin() {
  if (!isFormValid.value) return;

  isLoading.value = true;
  error.value = "";

  try {
    const result = await authStore.login({
      username: form.value.username,
      password: form.value.password,
    });

    if (result.success) {
      router.push("/");
    } else {
      error.value =
        result.error || "Login gagal. Periksa username dan password.";
    }
  } catch (err) {
    error.value = "Terjadi kesalahan. Silakan coba lagi.";
  } finally {
    isLoading.value = false;
  }
}

// Demo login shortcuts
function demoLogin(role) {
  const demos = {
    admin: { username: "admin", password: "demo123" },
    kasir: { username: "kasir", password: "demo123" },
    gudang: { username: "gudang", password: "demo123" },
  };
  form.value.username = demos[role].username;
  form.value.password = demos[role].password;
}
</script>

<template>
  <div class="min-h-screen flex bg-[#0a0f1a] relative overflow-hidden">
    <!-- Background Effects -->
    <div class="absolute inset-0 overflow-hidden">
      <div
        class="absolute -top-1/2 -left-1/2 w-full h-full bg-gradient-radial from-blue-600/20 to-transparent rounded-full blur-3xl"
      ></div>
      <div
        class="absolute -bottom-1/2 -right-1/2 w-full h-full bg-gradient-radial from-indigo-600/20 to-transparent rounded-full blur-3xl"
      ></div>
    </div>

    <!-- Left Section - Branding -->
    <div
      class="hidden lg:flex lg:w-1/2 relative z-10 items-center justify-center p-12"
    >
      <div class="max-w-lg text-center">
        <div class="inline-flex items-center gap-4 mb-8">
          <div
            class="w-16 h-16 bg-blue-600 rounded-2xl shadow-2xl shadow-blue-500/30 flex items-center justify-center text-white font-black text-3xl"
          >
            A
          </div>
          <span class="text-4xl font-bold tracking-tight text-white">
            APEX<span class="text-blue-500">POS</span>
          </span>
        </div>

        <h1 class="text-3xl font-bold text-white mb-4">
          Enterprise Point of Sale
        </h1>
        <p class="text-slate-400 text-lg leading-relaxed">
          Sistem POS modern untuk mengelola 60+ cabang dengan real-time sync,
          multi-role access, dan analytics terintegrasi.
        </p>

        <div class="mt-12 grid grid-cols-3 gap-6">
          <div class="text-center">
            <div class="text-3xl font-bold text-white">60+</div>
            <div class="text-sm text-slate-500">Cabang</div>
          </div>
          <div class="text-center">
            <div class="text-3xl font-bold text-white">12</div>
            <div class="text-sm text-slate-500">Role Akses</div>
          </div>
          <div class="text-center">
            <div class="text-3xl font-bold text-white">24/7</div>
            <div class="text-sm text-slate-500">Real-time</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Right Section - Login Form -->
    <div
      class="w-full lg:w-1/2 flex items-center justify-center p-8 relative z-10"
    >
      <div class="w-full max-w-md">
        <!-- Mobile Logo -->
        <div class="lg:hidden text-center mb-8">
          <div class="inline-flex items-center gap-3 mb-4">
            <div
              class="w-12 h-12 bg-blue-600 rounded-xl shadow-lg shadow-blue-500/20 flex items-center justify-center text-white font-black text-xl"
            >
              A
            </div>
            <span class="text-2xl font-bold tracking-tight text-white">
              APEX<span class="text-blue-500">POS</span>
            </span>
          </div>
        </div>

        <!-- Login Card -->
        <div class="glass rounded-3xl p-8 shadow-2xl">
          <div class="text-center mb-8">
            <h2 class="text-2xl font-bold text-white">Selamat Datang</h2>
            <p class="text-slate-400 mt-2">
              Masuk ke akun Anda untuk melanjutkan
            </p>
          </div>

          <!-- Error Alert -->
          <div
            v-if="error"
            class="mb-6 p-4 bg-red-500/10 border border-red-500/30 rounded-xl text-red-400 text-sm"
          >
            {{ error }}
          </div>

          <form @submit.prevent="handleLogin" class="space-y-5">
            <!-- Username -->
            <div>
              <label class="block text-sm font-medium text-slate-400 mb-2"
                >ID Login / Username</label
              >
              <div class="relative">
                <User
                  class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500"
                  :size="18"
                />
                <input
                  v-model="form.username"
                  type="text"
                  placeholder="Masukkan ID atau username"
                  class="input pl-12"
                  required
                />
              </div>
            </div>

            <!-- Password -->
            <div>
              <label class="block text-sm font-medium text-slate-400 mb-2"
                >Password</label
              >
              <div class="relative">
                <Lock
                  class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500"
                  :size="18"
                />
                <input
                  v-model="form.password"
                  :type="showPassword ? 'text' : 'password'"
                  placeholder="••••••••"
                  class="input pl-12 pr-12"
                  required
                />
                <button
                  type="button"
                  @click="showPassword = !showPassword"
                  class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-500 hover:text-white transition-colors"
                >
                  <Eye v-if="!showPassword" :size="18" />
                  <EyeOff v-else :size="18" />
                </button>
              </div>
            </div>

            <!-- Remember Me -->
            <div class="flex items-center justify-between">
              <label class="flex items-center gap-2 cursor-pointer">
                <input
                  v-model="rememberMe"
                  type="checkbox"
                  class="w-4 h-4 rounded border-slate-600 bg-slate-800 text-blue-600 focus:ring-blue-500"
                />
                <span class="text-sm text-slate-400">Ingat saya</span>
              </label>
              <a
                href="#"
                class="text-sm text-blue-500 hover:text-blue-400 transition-colors"
              >
                Lupa password?
              </a>
            </div>

            <!-- Submit Button -->
            <button
              type="submit"
              :disabled="!isFormValid || isLoading"
              class="btn btn-primary w-full py-4 text-base"
            >
              <Loader2 v-if="isLoading" :size="20" class="animate-spin" />
              <span v-else>Masuk</span>
            </button>
          </form>

          <!-- Demo Login Section -->
          <div class="mt-8 pt-6 border-t border-slate-700/50">
            <p class="text-center text-sm text-slate-500 mb-4">
              Demo Login (Klik untuk isi otomatis):
            </p>
            <div class="flex gap-2 justify-center flex-wrap">
              <button
                @click="demoLogin('admin')"
                class="px-3 py-1.5 text-xs font-medium bg-blue-600/20 text-blue-400 rounded-lg hover:bg-blue-600/30 transition-colors"
              >
                Super Admin
              </button>
              <button
                @click="demoLogin('kasir')"
                class="px-3 py-1.5 text-xs font-medium bg-emerald-600/20 text-emerald-400 rounded-lg hover:bg-emerald-600/30 transition-colors"
              >
                Kasir
              </button>
              <button
                @click="demoLogin('gudang')"
                class="px-3 py-1.5 text-xs font-medium bg-amber-600/20 text-amber-400 rounded-lg hover:bg-amber-600/30 transition-colors"
              >
                Gudang
              </button>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <p class="text-center text-slate-600 text-sm mt-8">
          © 2026 APEX POS. All rights reserved.
        </p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.bg-gradient-radial {
  background: radial-gradient(
    circle,
    var(--tw-gradient-from),
    var(--tw-gradient-to)
  );
}
</style>

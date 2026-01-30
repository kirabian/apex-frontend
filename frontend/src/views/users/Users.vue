<script setup>
import { ref, computed } from "vue";
import { ROLE_LABELS, ROLES } from "../../utils/permissions";
import { formatDate } from "../../utils/formatters";
import {
  Search,
  Plus,
  Filter,
  Download,
  Edit,
  Trash2,
  Users,
  Shield,
  Building2,
  Mail,
  Phone,
  Calendar,
  X,
  Save,
  Eye,
  EyeOff,
  UserPlus,
  Check,
  Clock,
  RotateCcw,
  ArchiveRestore,
} from "lucide-vue-next";

// Mock users data
const users = ref([
  {
    id: 1,
    name: "Fabian Syah",
    email: "fabian@apexpos.com",
    phone: "081234567890",
    role: "super_admin",
    branch: "Pusat Jakarta",
    status: "active",
    createdAt: "2024-01-15",
    lastSeen: "2024-05-28T10:30:00",
    timezone: "WIB",
  },
  {
    id: 2,
    name: "Ahmad Kasir",
    email: "ahmad@apexpos.com",
    phone: "081234567891",
    role: "inventory_kasir",
    branch: "Cabang Bandung",
    status: "active",
    createdAt: "2024-02-20",
    lastSeen: "2024-05-28T09:15:00",
    timezone: "WIB",
  },
  {
    id: 3,
    name: "Budi Gudang",
    email: "budi@apexpos.com",
    phone: "081234567892",
    role: "gudang",
    branch: "Gudang Pusat",
    status: "active",
    createdAt: "2024-03-10",
    lastSeen: "2024-05-27T16:00:00",
    timezone: "WIB",
  },
  {
    id: 4,
    name: "Citra Analis",
    email: "citra@apexpos.com",
    phone: "081234567893",
    role: "analist",
    branch: "Pusat Jakarta",
    status: "active",
    createdAt: "2024-03-15",
    lastSeen: "2024-05-28T11:00:00",
    timezone: "WIB",
  },
  {
    id: 5,
    name: "Dimas Sales",
    email: "dimas@apexpos.com",
    phone: "081234567894",
    role: "sales",
    branch: "Cabang Surabaya",
    status: "inactive",
    createdAt: "2024-04-01",
    lastSeen: "2024-05-20T14:20:00",
    timezone: "WIB",
  },
  {
    id: 6,
    name: "Eka Papua",
    email: "eka@apexpos.com",
    phone: "081234567895",
    role: "inventory_kasir",
    branch: "Cabang Papua",
    status: "active",
    createdAt: "2024-05-01",
    lastSeen: "2024-05-28T13:00:00", // Will show as 15:00 WIT
    timezone: "WIT",
  },
  {
    id: 7,
    name: "Putu Bali",
    email: "putu@apexpos.com",
    phone: "081234567896",
    role: "inventory",
    branch: "Cabang Bali",
    status: "deleted",
    createdAt: "2024-01-20",
    lastSeen: "2024-04-15T10:00:00",
    timezone: "WITA",
  },
]);

// Branches
const branches = [
  { id: 1, name: "Pusat Jakarta" },
  { id: 2, name: "Cabang Bandung" },
  { id: 3, name: "Cabang Surabaya" },
  { id: 4, name: "Cabang Medan" },
  { id: 5, name: "Gudang Pusat" },
  { id: 6, name: "Cabang Bali" },
  { id: 7, name: "Cabang Papua" },
];

const timezones = [
  { value: "WIB", label: "WIB (GMT+7)" },
  { value: "WITA", label: "WITA (GMT+8)" },
  { value: "WIT", label: "WIT (GMT+9)" },
];

// Roles list
const rolesList = Object.entries(ROLE_LABELS).map(([value, label]) => ({
  value,
  label,
}));

// Local state
const activeTab = ref("active"); // active, deleted
const searchQuery = ref("");
const selectedRole = ref("");
const selectedBranch = ref("");
const showModal = ref(false);
const editingUser = ref(null);
const showPassword = ref(false);

// Form
const form = ref({
  name: "",
  email: "",
  phone: "",
  password: "",
  role: "",
  branch: "",
  timezone: "WIB",
  status: "active",
});

// Format Last Seen
function formatLastSeen(dateString, timezone) {
  if (!dateString) return "-";

  const date = new Date(dateString);
  const options = {
    day: "numeric",
    month: "short",
    hour: "2-digit",
    minute: "2-digit",
    hour12: false,
  };

  // Convert to specific timezone logic (simplified for mockup)
  // In a real app, we would use date-fns-tz or dayjs with timezone support
  let tzLabel = timezone || "WIB";

  return `${date.toLocaleDateString("id-ID", options)} ${tzLabel}`;
}

// Filtered users
const filteredUsers = computed(() => {
  let result = users.value;

  // Filter by Tab (Active/Inactive vs Deleted)
  if (activeTab.value === "active") {
    result = result.filter((u) => u.status !== "deleted");
  } else {
    result = result.filter((u) => u.status === "deleted");
  }

  // Search
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    result = result.filter(
      (u) =>
        u.name.toLowerCase().includes(query) ||
        u.email.toLowerCase().includes(query)
    );
  }

  // Filter Role
  if (selectedRole.value) {
    result = result.filter((u) => u.role === selectedRole.value);
  }

  // Filter Branch
  if (selectedBranch.value) {
    result = result.filter((u) => u.branch === selectedBranch.value);
  }

  return result;
});

// Stats
const stats = computed(() => [
  {
    label: "Total User",
    value: users.value.filter((u) => u.status !== "deleted").length,
    icon: Users,
    color: "blue",
  },
  {
    label: "User Aktif",
    value: users.value.filter((u) => u.status === "active").length,
    icon: Check,
    color: "emerald",
  },
  {
    label: "Role Berbeda",
    value: new Set(
      users.value.filter((u) => u.status !== "deleted").map((u) => u.role)
    ).size,
    icon: Shield,
    color: "violet",
  },
  {
    label: "User Terhapus",
    value: users.value.filter((u) => u.status === "deleted").length,
    icon: Trash2,
    color: "amber",
  },
]);

// Actions
function openAddModal() {
  editingUser.value = null;
  form.value = {
    name: "",
    email: "",
    phone: "",
    password: "",
    role: "",
    branch: "",
    timezone: "WIB",
    status: "active",
  };
  showModal.value = true;
}

function openEditModal(user) {
  editingUser.value = user;
  form.value = { ...user, password: "" };
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
  editingUser.value = null;
}

function saveUser() {
  if (editingUser.value) {
    const index = users.value.findIndex((u) => u.id === editingUser.value.id);
    if (index > -1) {
      users.value[index] = { ...users.value[index], ...form.value };
    }
  } else {
    users.value.push({
      id: users.value.length + 1,
      ...form.value,
      createdAt: new Date().toISOString().split("T")[0],
      lastSeen: null, // New user hasn't logged in yet
    });
  }
  closeModal();
}

// Soft Delete
function softDeleteUser(id) {
  if (
    confirm("Apakah Anda yakin ingin menonaktifkan akun ini? (Soft Delete)")
  ) {
    const index = users.value.findIndex((u) => u.id === id);
    if (index !== -1) {
      users.value[index].status = "deleted";
    }
  }
}

// Restore User
function restoreUser(id) {
  if (confirm("Kembalikan akun ini menjadi aktif?")) {
    const index = users.value.findIndex((u) => u.id === id);
    if (index !== -1) {
      users.value[index].status = "active";
    }
  }
}

// Permanent Delete
function permanentDeleteUser(id) {
  if (confirm("HAPUS PERMANEN? Data tidak dapat dikembalikan!")) {
    users.value = users.value.filter((u) => u.id !== id);
  }
}

function toggleStatus(user) {
  if (user.status === "deleted") return;
  user.status = user.status === "active" ? "inactive" : "active";
}
</script>

<template>
  <div class="space-y-6 animate-in">
    <!-- Header -->
    <div class="flex justify-between items-end">
      <div>
        <h1 class="text-2xl font-bold text-white tracking-tight">
          Staff & Role
        </h1>
        <p class="text-slate-500 mt-1">Kelola pengguna dan hak akses</p>
      </div>
      <button @click="openAddModal" class="btn btn-primary">
        <UserPlus :size="16" />
        Tambah User
      </button>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      <div
        v-for="(stat, index) in stats"
        :key="index"
        class="card flex items-center gap-4"
      >
        <div
          class="w-12 h-12 rounded-xl flex items-center justify-center"
          :class="{
            'bg-blue-600': stat.color === 'blue',
            'bg-emerald-600': stat.color === 'emerald',
            'bg-violet-600': stat.color === 'violet',
            'bg-amber-600': stat.color === 'amber',
          }"
        >
          <component :is="stat.icon" :size="20" class="text-white" />
        </div>
        <div>
          <p class="text-slate-500 text-sm">{{ stat.label }}</p>
          <p class="text-xl font-bold text-white">{{ stat.value }}</p>
        </div>
      </div>
    </div>

    <!-- Filters & Tabs -->
    <div class="card space-y-4">
      <!-- Tabs -->
      <div
        class="flex border-b border-slate-700/50 overflow-x-auto hide-scrollbar"
      >
        <button
          @click="activeTab = 'active'"
          class="px-4 py-2 text-sm font-medium border-b-2 transition-colors flex items-center gap-2 whitespace-nowrap"
          :class="
            activeTab === 'active'
              ? 'border-blue-500 text-blue-400'
              : 'border-transparent text-slate-400 hover:text-white'
          "
        >
          <Users :size="16" />
          User Aktif & Inaktif
        </button>
        <button
          @click="activeTab = 'deleted'"
          class="px-4 py-2 text-sm font-medium border-b-2 transition-colors flex items-center gap-2 whitespace-nowrap"
          :class="
            activeTab === 'deleted'
              ? 'border-red-500 text-red-400'
              : 'border-transparent text-slate-400 hover:text-white'
          "
        >
          <Trash2 :size="16" />
          Terhapus (Soft Delete)
        </button>
      </div>

      <div class="flex flex-col md:flex-row items-start md:items-center gap-4">
        <div class="relative w-full md:flex-1">
          <Search
            class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500"
            :size="18"
          />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Cari nama atau email..."
            class="input pl-10 w-full"
          />
        </div>
        <div class="flex gap-4 w-full md:w-auto">
          <select v-model="selectedRole" class="input w-full md:w-48">
            <option value="">Semua Role</option>
            <option
              v-for="role in rolesList"
              :key="role.value"
              :value="role.value"
            >
              {{ role.label }}
            </option>
          </select>
          <select v-model="selectedBranch" class="input w-full md:w-48">
            <option value="">Semua Cabang</option>
            <option
              v-for="branch in branches"
              :key="branch.id"
              :value="branch.name"
            >
              {{ branch.name }}
            </option>
          </select>
        </div>
      </div>
    </div>

    <!-- Table (Desktop) -->
    <div class="card p-0 hidden md:block">
      <div class="table-container">
        <table class="table">
          <thead>
            <tr>
              <th>User</th>
              <th>Role</th>
              <th>Cabang & Zona</th>
              <th>Terakhir Dilihat</th>
              <th>Status</th>
              <th class="text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in filteredUsers" :key="user.id">
              <td>
                <div class="flex items-center gap-3">
                  <img
                    :src="`https://ui-avatars.com/api/?name=${encodeURIComponent(
                      user.name
                    )}&background=3b82f6&color=fff`"
                    class="w-10 h-10 rounded-xl"
                    :alt="user.name"
                  />
                  <div>
                    <p class="font-medium text-white">{{ user.name }}</p>
                    <p class="text-xs text-slate-500">{{ user.email }}</p>
                  </div>
                </div>
              </td>
              <td>
                <span class="badge badge-info">{{
                  ROLE_LABELS[user.role] || user.role
                }}</span>
              </td>
              <td>
                <p class="text-slate-300">{{ user.branch }}</p>
                <span
                  class="text-[10px] text-slate-500 font-mono bg-slate-800 px-1 rounded"
                  >{{ user.timezone || "WIB" }}</span
                >
              </td>
              <td class="text-slate-400 text-sm font-mono">
                {{ formatLastSeen(user.lastSeen, user.timezone) }}
              </td>
              <td>
                <div
                  v-if="user.status !== 'deleted'"
                  class="flex items-center gap-2"
                >
                  <button
                    @click="toggleStatus(user)"
                    class="w-10 h-5 rounded-full transition-colors relative focus:outline-none focus:ring-2 focus:ring-blue-500/50"
                    :class="
                      user.status === 'active'
                        ? 'bg-emerald-600'
                        : 'bg-slate-700'
                    "
                    title="Klik untuk mengubah status"
                  >
                    <span
                      class="absolute top-1 left-1 bg-white w-3 h-3 rounded-full transition-transform"
                      :class="
                        user.status === 'active'
                          ? 'translate-x-5'
                          : 'translate-x-0'
                      "
                    ></span>
                  </button>
                  <span
                    class="text-xs font-medium"
                    :class="
                      user.status === 'active'
                        ? 'text-emerald-400'
                        : 'text-slate-400'
                    "
                  >
                    {{ user.status === "active" ? "Aktif" : "Nonaktif" }}
                  </span>
                </div>
                <span v-else class="badge bg-slate-700 text-slate-400"
                  >Terhapus</span
                >
              </td>
              <td>
                <div class="flex items-center justify-center gap-2">
                  <template v-if="user.status !== 'deleted'">
                    <button
                      @click="openEditModal(user)"
                      class="p-2 hover:bg-slate-700 rounded-lg transition-colors"
                      title="Edit User"
                    >
                      <Edit :size="16" class="text-blue-400" />
                    </button>
                    <button
                      @click="softDeleteUser(user.id)"
                      class="p-2 hover:bg-red-500/20 rounded-lg transition-colors"
                      title="Hapus (Soft Delete)"
                    >
                      <Trash2 :size="16" class="text-red-400" />
                    </button>
                  </template>
                  <template v-else>
                    <button
                      @click="restoreUser(user.id)"
                      class="p-2 hover:bg-emerald-500/20 rounded-lg transition-colors"
                      title="Kembalikan User"
                    >
                      <RotateCcw :size="16" class="text-emerald-400" />
                    </button>
                    <button
                      @click="permanentDeleteUser(user.id)"
                      class="p-2 hover:bg-red-500/20 rounded-lg transition-colors"
                      title="Hapus Permanen"
                    >
                      <XCircle :size="16" class="text-red-600" />
                    </button>
                  </template>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Mobile Card View -->
    <div class="md:hidden space-y-4 pb-20">
      <div v-for="user in filteredUsers" :key="user.id" class="card space-y-4">
        <div class="flex items-start justify-between gap-3">
          <div class="flex items-center gap-3 min-w-0 flex-1">
            <img
              :src="`https://ui-avatars.com/api/?name=${encodeURIComponent(
                user.name
              )}&background=3b82f6&color=fff`"
              class="w-10 h-10 rounded-xl shrink-0"
              :alt="user.name"
            />
            <div class="min-w-0 flex-1">
              <p class="font-medium text-white truncate">{{ user.name }}</p>
              <p class="text-xs text-slate-500 truncate">{{ user.email }}</p>
            </div>
          </div>
          <span
            class="badge badge-info text-[10px] shrink-0 whitespace-nowrap"
            >{{ ROLE_LABELS[user.role] || user.role }}</span
          >
        </div>

        <div
          class="grid grid-cols-2 gap-3 text-sm border-t border-slate-700/30 pt-3"
        >
          <div class="min-w-0">
            <p class="text-slate-500 text-xs mb-1">Cabang</p>
            <p class="text-slate-300 truncate text-xs">{{ user.branch }}</p>
            <span
              class="text-[10px] font-mono text-slate-500 bg-slate-800 px-1.5 py-0.5 rounded mt-1 inline-block"
              >{{ user.timezone || "WIB" }}</span
            >
          </div>
          <div class="text-right min-w-0">
            <p class="text-slate-500 text-xs mb-1">Terakhir Dilihat</p>
            <p class="text-slate-300 font-mono text-xs truncate">
              {{ formatLastSeen(user.lastSeen, user.timezone) }}
            </p>
          </div>
        </div>

        <div
          class="flex items-center justify-between border-t border-slate-700/50 pt-3"
        >
          <!-- Status Toggle -->
          <div v-if="user.status !== 'deleted'" class="flex items-center gap-2">
            <button
              @click="toggleStatus(user)"
              class="w-10 h-5 rounded-full transition-colors relative focus:outline-none focus:ring-2 focus:ring-blue-500/50 shrink-0"
              :class="
                user.status === 'active' ? 'bg-emerald-600' : 'bg-slate-700'
              "
            >
              <span
                class="absolute top-1 left-1 bg-white w-3 h-3 rounded-full transition-transform"
                :class="
                  user.status === 'active' ? 'translate-x-5' : 'translate-x-0'
                "
              ></span>
            </button>
            <span
              class="text-xs font-medium"
              :class="
                user.status === 'active' ? 'text-emerald-400' : 'text-slate-400'
              "
            >
              {{ user.status === "active" ? "Aktif" : "Nonaktif" }}
            </span>
          </div>
          <span v-else class="badge bg-slate-700 text-slate-400">Terhapus</span>

          <!-- Actions -->
          <div class="flex items-center gap-1">
            <template v-if="user.status !== 'deleted'">
              <button
                @click="openEditModal(user)"
                class="p-2 hover:bg-slate-700 rounded-lg transition-colors"
              >
                <Edit :size="16" class="text-blue-400" />
              </button>
              <button
                @click="softDeleteUser(user.id)"
                class="p-2 hover:bg-red-500/20 rounded-lg transition-colors"
              >
                <Trash2 :size="16" class="text-red-400" />
              </button>
            </template>
            <template v-else>
              <button
                @click="restoreUser(user.id)"
                class="p-2 hover:bg-emerald-500/20 rounded-lg transition-colors"
              >
                <RotateCcw :size="16" class="text-emerald-400" />
              </button>
              <button
                @click="permanentDeleteUser(user.id)"
                class="p-2 hover:bg-red-500/20 rounded-lg transition-colors"
                title="Hapus Permanen"
              >
                <XCircle :size="16" class="text-red-600" />
              </button>
            </template>
          </div>
        </div>
      </div>
    </div>

    <!-- Add/Edit Modal -->
    <Teleport to="body">
      <div
        v-if="showModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
      >
        <div
          class="absolute inset-0 bg-black/60 backdrop-blur-sm"
          @click="closeModal"
        ></div>

        <div
          class="relative bg-slate-900 rounded-2xl border border-slate-700 w-full max-w-lg p-6 shadow-2xl"
        >
          <button
            @click="closeModal"
            class="absolute top-4 right-4 p-2 text-slate-400 hover:text-white transition-colors"
          >
            <X :size="20" />
          </button>

          <h3 class="text-xl font-bold text-white mb-6">
            {{ editingUser ? "Edit User" : "Tambah User Baru" }}
          </h3>

          <form @submit.prevent="saveUser" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-slate-400 mb-2"
                >Nama Lengkap</label
              >
              <input
                v-model="form.name"
                type="text"
                class="input"
                placeholder="John Doe"
                required
              />
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-slate-400 mb-2"
                  >Email</label
                >
                <input
                  v-model="form.email"
                  type="email"
                  class="input"
                  placeholder="john@example.com"
                  required
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-400 mb-2"
                  >No. HP</label
                >
                <input
                  v-model="form.phone"
                  type="tel"
                  class="input"
                  placeholder="081234567890"
                />
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-400 mb-2"
                >Password
                {{ editingUser ? "(Kosongkan jika tidak diubah)" : "" }}</label
              >
              <div class="relative">
                <input
                  v-model="form.password"
                  :type="showPassword ? 'text' : 'password'"
                  class="input pr-12"
                  placeholder="••••••••"
                  :required="!editingUser"
                />
                <button
                  type="button"
                  @click="showPassword = !showPassword"
                  class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-500"
                >
                  <Eye v-if="!showPassword" :size="18" />
                  <EyeOff v-else :size="18" />
                </button>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-slate-400 mb-2"
                  >Role</label
                >
                <select v-model="form.role" class="input" required>
                  <option value="">Pilih Role</option>
                  <option
                    v-for="role in rolesList"
                    :key="role.value"
                    :value="role.value"
                  >
                    {{ role.label }}
                  </option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-400 mb-2"
                  >Zona Waktu</label
                >
                <select v-model="form.timezone" class="input" required>
                  <option
                    v-for="tz in timezones"
                    :key="tz.value"
                    :value="tz.value"
                  >
                    {{ tz.label }}
                  </option>
                </select>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-400 mb-2"
                >Cabang Utama</label
              >
              <select v-model="form.branch" class="input" required>
                <option value="">Pilih Cabang</option>
                <option
                  v-for="branch in branches"
                  :key="branch.id"
                  :value="branch.name"
                >
                  {{ branch.name }}
                </option>
              </select>
            </div>

            <div class="flex gap-3 pt-4">
              <button
                type="button"
                @click="closeModal"
                class="btn btn-secondary flex-1"
              >
                Batal
              </button>
              <button type="submit" class="btn btn-primary flex-1">
                <Save :size="16" />
                Simpan
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.animate-in {
  animation: fadeIn 0.3s ease-out;
}
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>

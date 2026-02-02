<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { ROLE_LABELS } from "../../utils/permissions";
import { formatDate } from "../../utils/formatters";
import { users as usersApi, branches as branchesApi } from "../../api/axios"; // Import API
import { useToast } from "../../composables/useToast"; // Import Toast
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
  XCircle,
  Loader2 // Import Loader
} from "lucide-vue-next";

// Toast
const toast = useToast();

// State
const users = ref([]);
const branches = ref([]);
const isLoading = ref(false);
const isSaving = ref(false);

const timezones = [
  { value: "WIB", label: "WIB (GMT+7)" },
  { value: "WITA", label: "WITA (GMT+8)" },
  { value: "WIT", label: "WIT (GMT+9)" },
  { value: "Asia/Jakarta", label: "Asia/Jakarta" },
  { value: "Asia/Makassar", label: "Asia/Makassar" },
  { value: "Asia/Jayapura", label: "Asia/Jayapura" },
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
  full_name: "", // Match backend field
  username: "",
  email: "", // Although not in backend fillable shown above, keeping it if needed or should map to something
  role: "",
  branch_id: "", // Match backend field
  timezone: "WIB",
  address: "",
  password: "",
  is_active: true,
});

// Helper to reset form
function resetForm() {
  form.value = {
    full_name: "",
    username: "",
    code_id: "", // Add code_id
    email: "", // Optional or remove if not used in backend logic explicitly
    role: "",
    branch_id: "",
    timezone: "WIB",
    address: "",
    password: "",
    is_active: true,
    created_at: null, // Add to prevent reactive issues if needed
  };
  showPassword.value = false;
}

// Fetch Data
async function fetchData() {
  isLoading.value = true;
  try {
    const [usersRes, branchesRes] = await Promise.all([
      usersApi.list(),
      branchesApi.list()
    ]);
    users.value = usersRes.data.data || [];
    branches.value = branchesRes.data.data || [];
  } catch (error) {
    console.error("Failed to fetch data", error);
    toast.error("Gagal memuat data user.");
  } finally {
    isLoading.value = false;
  }
}

onMounted(() => {
  fetchData();
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

  // Simplified timezone label
  const tzMap = {
    'Asia/Jakarta': 'WIB',
    'Asia/Makassar': 'WITA',
    'Asia/Jayapura': 'WIT'
  }
  let tzLabel = tzMap[timezone] || timezone || "WIB";

  return `${date.toLocaleDateString("id-ID", options)} ${tzLabel}`;
}

// Compute available branches based on selected role
const availableBranches = computed(() => {
  if (!form.value.role) return branches.value;

  const onlineRoles = ['toko_online', 'leader_shopee'];
  // Check if selected role is an online role
  const isOnlineRole = onlineRoles.includes(form.value.role);

  if (isOnlineRole) {
    // Only show online shops
    return branches.value.filter(b => b.type === 'online');
  } else {
    // Show physical branches (or all physical)
    // Adjust logic if "Leader" -> "Physical Leader" should only see physical?
    // User said: "leader pun sama tapi leader kan ada leader yang cabang fisik... bikin juga deh pilihan gitu"
    // This implies for normal roles, show physical branches.
    return branches.value.filter(b => b.type !== 'online');
  }
});

// Filtered users
const filteredUsers = computed(() => {
  let result = users.value;

  // Filter by Tab/Status
  // Note: Backend might return all users, or we filter locally.
  // Assuming backend returns all unless filtered by params.
  // We'll do local filtering for now since the list is likely small.
  // Backend "is_active" is boolean.
  // "Deleted" usually means SoftDeleted if supported, OR we can use is_active=false as "Inactive".
  // The UI has "Active & Inactive" vs "Deleted".
  // If no SoftDeletes, "Deleted" tab might be empty or specific logic.
  // Let's assume:
  // Tab Active: is_active = true
  // Tab Inactive/Deleted: We'll stick to is_active logic for now.
  // Wait, UI says "User Aktif & Inaktif" in one tab, and "Terhapus (Soft Delete)" in another.
  // Since we don't have SoftDeletes yet, "Terhapus" will be empty or unused.
  // Let's modify logic:
  // Tab 1: All non-deleted users (both active and inactive status)
  // Tab 2: Actually deleted users (if SoftDeletes implemented) or just hide it?
  // For now let's just show all in Tab 1, and maybe ignore Tab 2 or make "Nonaktif" users appear there?
  // Promoting "Nonaktif" to "Active Tab" as existing UI suggests.

  if (activeTab.value === 'deleted') {
    return []; // Placeholder for now untill soft delete is implemented
  }

  // Search
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    result = result.filter(
      (u) =>
        (u.full_name?.toLowerCase() || '').includes(query) ||
        (u.username?.toLowerCase() || '').includes(query)
    );
  }

  // Filter Role
  if (selectedRole.value) {
    result = result.filter((u) => {
      if (u.roles && u.roles.length > 0) {
        return u.roles.some(r => r.name === selectedRole.value);
      }
      return false;
    });
  }

  // Filter Branch
  if (selectedBranch.value) {
    result = result.filter((u) => u.branch_id === selectedBranch.value || u.branch?.name === selectedBranch.value);
  }

  return result;
});

// Stats (Computed from fetched users)
const stats = computed(() => [
  {
    label: "Total User",
    value: users.value.length,
    icon: Users,
    color: "blue",
  },
  {
    label: "User Aktif",
    value: users.value.filter((u) => u.is_active).length,
    icon: Check,
    color: "emerald",
  },
  {
    label: "User Nonaktif",
    value: users.value.filter((u) => !u.is_active).length,
    icon: Shield,
    color: "amber", // Changed to match Inactive/Warning
  },
  {
    label: "Role Terdaftar",
    value: new Set(users.value.flatMap(u => u.roles ? u.roles.map(r => r.name) : [])).size,
    icon: Shield,
    color: "violet",
  },
]);

// Actions
function openAddModal() {
  editingUser.value = null;
  resetForm();
  showModal.value = true;
}

function openEditModal(user) {
  editingUser.value = user;

  // Populate form
  form.value = {
    full_name: user.full_name,
    username: user.username,
    code_id: user.code_id || "", // Populate code_id
    email: user.email,
    // Handle roles array from Spatie
    role: user.roles && user.roles.length > 0 ? user.roles[0].name : '',
    branch_id: user.branch_id,
    timezone: user.timezone || "WIB",
    address: user.address,
    is_active: !!user.is_active,
    password: "", // Leave empty
  };

  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
  editingUser.value = null;
  resetForm();
}

async function saveUser() {
  isSaving.value = true;
  try {
    if (editingUser.value) {
      // Update
      const payload = { ...form.value };
      if (!payload.password) delete payload.password; // Don't send empty password

      const res = await usersApi.update(editingUser.value.id, payload);

      // Update local list
      const index = users.value.findIndex(u => u.id === editingUser.value.id);
      if (index !== -1) {
        users.value[index] = res.data.data;
      }
      toast.success("User berhasil diperbarui!");
    } else {
      // Create
      const res = await usersApi.create(form.value);
      users.value.unshift(res.data.data);
      toast.success("User baru berhasil ditambahkan!");
    }
    closeModal();
  } catch (error) {
    console.error("Save error", error);
    const msg = error.response?.data?.message || "Gagal menyimpan user.";
    toast.error(msg);
  } finally {
    isSaving.value = false;
  }
}

// Toggle Status (Active/Inactive)
async function toggleStatus(user) {
  try {
    const newStatus = !user.is_active;
    // Optimistic update
    user.is_active = newStatus;

    await usersApi.update(user.id, { is_active: newStatus });

    toast.info(newStatus ? "User diaktifkan." : "User dinonaktifkan.");
  } catch (error) {
    // Revert on failure
    user.is_active = !user.is_active;
    toast.error("Gagal mengubah status user.");
  }
}

// Soft Delete (Simulated as Deactivate or just Delete for now if backend doesn't support SoftDeletes)
function softDeleteUser(id) {
  // Since backend doesn't have SoftDeletes trait yet, we treat "Soft Delete" UI as "Deactivate" logic?
  // OR we interpret as "Delete" safely?
  // Plan said: "Interpret 'Hapus' as hard delete and 'Nonaktifkan' as setting is_active = false"
  // UI has two buttons: Trash (Soft Delete) and X (Permanent).
  // I will map Trash -> Deactivate (is_active=false)
  // X -> Persistent Delete

  const user = users.value.find(u => u.id === id);
  if (user) {
    toggleStatus(user);
  }
}

// Restore User
function restoreUser(id) {
  const user = users.value.find(u => u.id === id);
  if (user && !user.is_active) {
    toggleStatus(user);
  }
}

// Permanent Delete
async function permanentDeleteUser(id) {
  if (!confirm("HAPUS PERMANEN? Data tidak dapat dikembalikan!")) return;

  try {
    await usersApi.delete(id);
    users.value = users.value.filter(u => u.id !== id);
    toast.success("User berhasil dihapus permanen.");
  } catch (error) {
    toast.error("Gagal menghapus user.");
  }
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
      <div v-for="(stat, index) in stats" :key="index" class="card flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center" :class="{
          'bg-blue-600': stat.color === 'blue',
          'bg-emerald-600': stat.color === 'emerald',
          'bg-violet-600': stat.color === 'violet',
          'bg-amber-600': stat.color === 'amber',
        }">
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
      <div class="flex border-b border-slate-700/50 overflow-x-auto hide-scrollbar">
        <button @click="activeTab = 'active'"
          class="px-4 py-2 text-sm font-medium border-b-2 transition-colors flex items-center gap-2 whitespace-nowrap"
          :class="activeTab === 'active'
            ? 'border-blue-500 text-blue-400'
            : 'border-transparent text-slate-400 hover:text-white'
            ">
          <Users :size="16" />
          User Aktif & Inaktif
        </button>
        <button @click="activeTab = 'deleted'"
          class="px-4 py-2 text-sm font-medium border-b-2 transition-colors flex items-center gap-2 whitespace-nowrap"
          :class="activeTab === 'deleted'
            ? 'border-red-500 text-red-400'
            : 'border-transparent text-slate-400 hover:text-white'
            ">
          <Trash2 :size="16" />
          Terhapus (Soft Delete)
        </button>
      </div>

      <div class="flex flex-col md:flex-row items-start md:items-center gap-4">
        <div class="relative w-full md:flex-1">
          <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500" :size="18" />
          <input v-model="searchQuery" type="text" placeholder="Cari nama atau email..." class="input pl-10 w-full" />
        </div>
        <div class="flex gap-4 w-full md:w-auto">
          <select v-model="selectedRole" class="input w-full md:w-48">
            <option value="">Semua Role</option>
            <option v-for="role in rolesList" :key="role.value" :value="role.value">
              {{ role.label }}
            </option>
          </select>
          <select v-model="selectedBranch" class="input w-full md:w-48">
            <option value="">Semua Cabang</option>
            <option v-for="branch in branches" :key="branch.id" :value="branch.id">
              {{ branch.name }}
            </option>
          </select>
        </div>
      </div>
    </div>

    <!-- Table (Desktop) -->
    <div class="card p-0 hidden md:block">
      <div v-if="isLoading" class="p-8 flex justify-center items-center">
        <Loader2 class="animate-spin text-blue-500" :size="32" />
        <span class="ml-2 text-slate-400">Memuat data user...</span>
      </div>
      <div v-else class="table-container">
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
            <tr v-if="filteredUsers.length === 0">
              <td colspan="6" class="text-center py-8 text-slate-500">
                Tidak ada user ditemukan
              </td>
            </tr>
            <tr v-for="user in filteredUsers" :key="user.id">
              <td>
                <div class="flex items-center gap-3">
                  <img :src="`https://ui-avatars.com/api/?name=${encodeURIComponent(
                    user.full_name || user.username
                  )}&background=3b82f6&color=fff`" class="w-10 h-10 rounded-xl" :alt="user.full_name" />
                  <div>
                    <p class="font-medium text-white">{{ user.full_name }}</p>
                    <p class="text-xs text-slate-500">{{ user.username }}</p>
                  </div>
                </div>
              </td>
              <td>
                <span v-for="role in user.roles" :key="role.id" class="badge badge-info mr-1">{{
                  ROLE_LABELS[role.name] || role.name
                }}</span>
                <span v-if="!user.roles || user.roles.length === 0" class="badge bg-slate-700 text-slate-400">No
                  Role</span>
              </td>
              <td>
                <p class="text-slate-300">{{ user.branch ? user.branch.name : (user.branch_id === null ? 'Semua Cabang'
                  : '-') }}</p>
                <span class="text-[10px] text-slate-500 font-mono bg-slate-800 px-1 rounded">{{ user.timezone || "WIB"
                }}</span>
              </td>
              <td class="text-slate-400 text-sm font-mono">
                {{ formatLastSeen(user.last_seen || user.lastSeen, user.timezone) }}
              </td>
              <td>
                <div v-if="activeTab !== 'deleted'" class="flex items-center gap-2">
                  <button @click="toggleStatus(user)"
                    class="w-10 h-5 rounded-full transition-colors relative focus:outline-none focus:ring-2 focus:ring-blue-500/50"
                    :class="user.is_active
                      ? 'bg-emerald-600'
                      : 'bg-slate-700'
                      " title="Klik untuk mengubah status">
                    <span class="absolute top-1 left-1 bg-white w-3 h-3 rounded-full transition-transform" :class="user.is_active
                      ? 'translate-x-5'
                      : 'translate-x-0'
                      "></span>
                  </button>
                  <span class="text-xs font-medium" :class="user.is_active
                    ? 'text-emerald-400'
                    : 'text-slate-400'
                    ">
                    {{ user.is_active ? "Aktif" : "Nonaktif" }}
                  </span>
                </div>
                <span v-else class="badge bg-slate-700 text-slate-400">Terhapus</span>
              </td>
              <td>
                <div class="flex items-center justify-center gap-2">
                  <template v-if="activeTab !== 'deleted'">
                    <button @click="openEditModal(user)" class="p-2 hover:bg-slate-700 rounded-lg transition-colors"
                      title="Edit User">
                      <Edit :size="16" class="text-blue-400" />
                    </button>
                    <!-- Soft delete maps to non-active for now, or use permanent delete if you want -->
                    <!-- Let's keep Trash2 as "Deactivate" visual helper if active, or actual delete if integrated -->
                    <button v-if="user.is_active" @click="softDeleteUser(user.id)"
                      class="p-2 hover:bg-yellow-500/20 rounded-lg transition-colors" title="Nonaktifkan">
                      <Shield :size="16" class="text-yellow-400" />
                    </button>
                    <button @click="permanentDeleteUser(user.id)"
                      class="p-2 hover:bg-red-500/20 rounded-lg transition-colors" title="Hapus Permanen">
                      <Trash2 :size="16" class="text-red-400" />
                    </button>
                  </template>
                  <template v-else>
                    <!-- Restore Logic if soft deletes implemented -->
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
      <div v-if="isLoading" class="p-8 flex justify-center">
        <Loader2 class="animate-spin text-blue-500" :size="32" />
      </div>
      <div v-for="user in filteredUsers" :key="user.id" class="card space-y-4">
        <div class="flex items-start justify-between gap-3">
          <div class="flex items-center gap-3 min-w-0 flex-1">
            <img :src="`https://ui-avatars.com/api/?name=${encodeURIComponent(
              user.full_name
            )}&background=3b82f6&color=fff`" class="w-10 h-10 rounded-xl shrink-0" :alt="user.full_name" />
            <div class="min-w-0 flex-1">
              <p class="font-medium text-white truncate">{{ user.full_name }}</p>
              <p class="text-xs text-slate-500 truncate">{{ user.username }}</p>
            </div>
          </div>
          <span v-if="user.roles && user.roles.length"
            class="badge badge-info text-[10px] shrink-0 whitespace-nowrap">{{
              ROLE_LABELS[user.roles[0].name] || user.roles[0].name }}</span>
        </div>

        <div class="grid grid-cols-2 gap-3 text-sm border-t border-slate-700/30 pt-3">
          <div class="min-w-0">
            <p class="text-slate-500 text-xs mb-1">Cabang</p>
            <p class="text-slate-300 truncate text-xs">{{ user.branch ? user.branch.name : '-' }}</p>
            <span class="text-[10px] font-mono text-slate-500 bg-slate-800 px-1.5 py-0.5 rounded mt-1 inline-block">{{
              user.timezone || "WIB" }}</span>
          </div>
          <div class="text-right min-w-0">
            <p class="text-slate-500 text-xs mb-1">Terakhir Dilihat</p>
            <p class="text-slate-300 font-mono text-xs truncate">
              {{ formatLastSeen(user.last_seen, user.timezone) }}
            </p>
          </div>
        </div>

        <div class="flex items-center justify-between border-t border-slate-700/50 pt-3">
          <!-- Status Toggle -->
          <div v-if="activeTab !== 'deleted'" class="flex items-center gap-2">
            <button @click="toggleStatus(user)"
              class="w-10 h-5 rounded-full transition-colors relative focus:outline-none focus:ring-2 focus:ring-blue-500/50 shrink-0"
              :class="user.is_active ? 'bg-emerald-600' : 'bg-slate-700'
                ">
              <span class="absolute top-1 left-1 bg-white w-3 h-3 rounded-full transition-transform" :class="user.is_active ? 'translate-x-5' : 'translate-x-0'
                "></span>
            </button>
            <span class="text-xs font-medium" :class="user.is_active ? 'text-emerald-400' : 'text-slate-400'
              ">
              {{ user.is_active ? "Aktif" : "Nonaktif" }}
            </span>
          </div>

          <!-- Actions -->
          <div class="flex items-center gap-1">
            <button @click="openEditModal(user)" class="p-2 hover:bg-slate-700 rounded-lg transition-colors">
              <Edit :size="16" class="text-blue-400" />
            </button>
            <button @click="permanentDeleteUser(user.id)" class="p-2 hover:bg-red-500/20 rounded-lg transition-colors">
              <Trash2 :size="16" class="text-red-400" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Add/Edit Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="closeModal"></div>

        <div class="relative bg-slate-900 rounded-2xl border border-slate-700 w-full max-w-lg p-6 shadow-2xl">
          <button @click="closeModal"
            class="absolute top-4 right-4 p-2 text-slate-400 hover:text-white transition-colors">
            <X :size="20" />
          </button>

          <h3 class="text-xl font-bold text-white mb-6">
            {{ editingUser ? "Edit User" : "Tambah User Baru" }}
          </h3>

          <form @submit.prevent="saveUser" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-slate-400 mb-2">Nama Lengkap</label>
              <input v-model="form.full_name" type="text" class="input" placeholder="John Doe" required />
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-slate-400 mb-2">Kode ID (Optional)</label>
                <input v-model="form.code_id" type="text" class="input" placeholder="EMP-001" />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-400 mb-2">Username</label>
                <input v-model="form.username" type="text" class="input" placeholder="johndoe" required />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-400 mb-2">Email (Optional)</label>
                <input v-model="form.email" type="email" class="input" placeholder="john@example.com" />
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-400 mb-2">Password
                {{ editingUser ? "(Kosongkan jika tidak diubah)" : "" }}</label>
              <div class="relative">
                <input v-model="form.password" :type="showPassword ? 'text' : 'password'" class="input pr-12"
                  placeholder="••••••••" :required="!editingUser" />
                <button type="button" @click="showPassword = !showPassword"
                  class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-500">
                  <Eye v-if="!showPassword" :size="18" />
                  <EyeOff v-else :size="18" />
                </button>
              </div>
            </div>

        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-400 mb-2">Role</label>
          <select v-model="form.role" class="input" required>
            <option value="">Pilih Role</option>
            <option v-for="role in rolesList" :key="role.value" :value="role.value">
              {{ role.label }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-400 mb-2">Zona Waktu</label>
          <select v-model="form.timezone" class="input" required>
            <option v-for="tz in timezones" :key="tz.value" :value="tz.value">
              {{ tz.label }}
            </option>
          </select>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-slate-400 mb-2">Cabang / Toko</label>
        <select v-model="form.branch_id" class="input">
          <option value="">Pilih Cabang (Optional)</option>
          <option v-for="branch in availableBranches" :key="branch.id" :value="branch.id">
            {{ branch.name }} {{ branch.type === 'online' ? '(Online)' : '' }}
          </option>
        </select>
      </div>

      <!-- Address -->
      <div>
        <label class="block text-sm font-medium text-slate-400 mb-2">Alamat (Optional)</label>
        <textarea v-model="form.address" class="input min-h-[80px]" placeholder="Alamat lengkap user..."></textarea>
      </div>

      <div class="flex justify-end gap-3 mt-6">
        <button type="button" @click="closeModal" class="px-4 py-2 text-slate-400 hover:text-white transition-colors"
          :disabled="isSaving">
          Batal
        </button>
        <button type="submit" class="btn btn-primary flex items-center gap-2" :disabled="isSaving">
          <Loader2 v-if="isSaving" class="animate-spin" :size="18" />
          <span>{{ isSaving ? 'Menyimpan...' : 'Simpan User' }}</span>
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

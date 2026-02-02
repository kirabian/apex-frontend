<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { ROLE_LABELS, ROLES } from "../../utils/permissions";
import { formatDate } from "../../utils/formatters";
import { users as usersApi, branches as branchesApi, warehouses as warehousesApi, onlineShops as onlineShopsApi, distributors as distributorsApi } from "../../api/axios";
import { useToast } from "../../composables/useToast";
import {
  Search,
  Plus,
  Filter,
  Users,
  Shield,
  Trash2,
  Edit,
  UserPlus,
  Check,
  Eye,
  EyeOff,
  Loader2,
  MapPin, // Icon for placement
  Building // Icon for warehouse
} from "lucide-vue-next";

// Toast
const toast = useToast();

// State
const users = ref([]);
const branches = ref([]);
const warehouses = ref([]); // New
const onlineShops = ref([]); // New
const distributors = ref([]); // New
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
const activeTab = ref("active");
const searchQuery = ref("");
const selectedRole = ref("");
const selectedBranch = ref("");
const showModal = ref(false);
const editingUser = ref(null);
const showPassword = ref(false);

// Form
const form = ref({
  full_name: "",
  username: "",
  code_id: "",
  email: "",
  role: "",
  branch_id: "", // For Physical Branches
  warehouse_id: "", // For Warehouse
  online_shop_id: "", // For Online Shop
  distributor_id: "", // For Distributor
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
    code_id: "",
    email: "",
    role: "",
    branch_id: "",
    warehouse_id: "",
    online_shop_id: "",
    distributor_id: "",
    timezone: "WIB",
    address: "",
    password: "",
    is_active: true,
  };
  showPassword.value = false;
}

// Fetch Data
async function fetchData() {
  isLoading.value = true;
  try {
    const [usersRes, branchesRes, warehousesRes, onlineShopsRes, distributorsRes] = await Promise.all([
      usersApi.list(),
      branchesApi.list(),
      warehousesApi.list(),
      onlineShopsApi.list(),
      distributorsApi.list()
    ]);

    users.value = usersRes.data.data || [];
    // Clean up branches list (exclude online/warehouse if backend returns them)
    // Assuming backend older data might still have type field
    const allBranches = branchesRes.data.data || [];
    branches.value = allBranches.filter(b =>
      !['online', 'warehouse'].includes(b.type)
    );

    warehouses.value = warehousesRes.data.data || [];
    onlineShops.value = onlineShopsRes.data.data || [];
    distributors.value = distributorsRes.data.data || [];

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
    day: "numeric", month: "short", hour: "2-digit", minute: "2-digit", hour12: false,
  };
  const tzMap = { 'Asia/Jakarta': 'WIB', 'Asia/Makassar': 'WITA', 'Asia/Jayapura': 'WIT' }
  let tzLabel = tzMap[timezone] || timezone || "WIB";
  return `${date.toLocaleDateString("id-ID", options)} ${tzLabel}`;
}

// Determine Placement Type
const placementType = computed(() => {
  if (!form.value.role) return 'branch'; // Default
  const role = form.value.role;

  if (role === 'gudang') return 'warehouse';
  if (role === 'distribution') return 'distributor';
  if (['toko_online', 'leader_shopee'].includes(role)) return 'online_shop';

  return 'branch'; // Default
});

// Label for Placement
const placementLabel = computed(() => {
  const map = {
    'warehouse': 'Pilih Gudang',
    'distributor': 'Pilih Distributor',
    'online_shop': 'Pilih Toko Online',
    'branch': 'Pilih Cabang Fisik'
  };
  return map[placementType.value];
});

watch(() => form.value.role, (newRole) => {
  // Clear all placements when role changes
  form.value.branch_id = "";
  form.value.warehouse_id = "";
  form.value.online_shop_id = "";
  form.value.distributor_id = "";
});

// Filtered users
const filteredUsers = computed(() => {
  let result = users.value;

  if (activeTab.value === 'deleted') return [];

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    result = result.filter(u =>
      (u.full_name?.toLowerCase() || '').includes(query) ||
      (u.username?.toLowerCase() || '').includes(query)
    );
  }

  if (selectedRole.value) {
    result = result.filter(u => u.roles?.some(r => r.name === selectedRole.value));
  }

  if (selectedBranch.value) {
    result = result.filter(u => u.branch_id === selectedBranch.value || u.branch?.name === selectedBranch.value);
  }

  return result;
});

// Stats
const stats = computed(() => [
  { label: "Total User", value: users.value.length, icon: Users, color: "blue" },
  { label: "User Aktif", value: users.value.filter(u => u.is_active).length, icon: Check, color: "emerald" },
  { label: "User Nonaktif", value: users.value.filter(u => !u.is_active).length, icon: Shield, color: "amber" },
]);

// Actions
function openAddModal() {
  editingUser.value = null;
  resetForm();
  showModal.value = true;
}

function openEditModal(user) {
  editingUser.value = user;
  form.value = {
    full_name: user.full_name,
    username: user.username,
    code_id: user.code_id || "",
    email: user.email,
    role: user.roles && user.roles.length > 0 ? user.roles[0].name : '',
    branch_id: user.branch_id,
    warehouse_id: user.warehouse_id,
    online_shop_id: user.online_shop_id,
    distributor_id: user.distributor_id,
    timezone: user.timezone || "WIB",
    address: user.address,
    is_active: !!user.is_active,
    password: "",
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
    // Ensure only relevant placement ID is sent (though backend handles overrides, cleaner here)
    const payload = { ...form.value };
    if (placementType.value !== 'branch') payload.branch_id = null;
    if (placementType.value !== 'warehouse') payload.warehouse_id = null;
    if (placementType.value !== 'online_shop') payload.online_shop_id = null;
    if (placementType.value !== 'distributor') payload.distributor_id = null;

    // Explicitly set null if empty string to avoid DB errors constraint
    if (!payload.branch_id) payload.branch_id = null;
    if (!payload.warehouse_id) payload.warehouse_id = null;
    if (!payload.online_shop_id) payload.online_shop_id = null;
    if (!payload.distributor_id) payload.distributor_id = null;

    if (editingUser.value) {
      if (!payload.password) delete payload.password;
      const res = await usersApi.update(editingUser.value.id, payload);
      const index = users.value.findIndex(u => u.id === editingUser.value.id);
      if (index !== -1) users.value[index] = res.data.data;
      toast.success("User berhasil diperbarui!");
    } else {
      const res = await usersApi.create(payload);
      users.value.unshift(res.data.data);
      toast.success("User baru berhasil ditambahkan!");
    }
    closeModal();
  } catch (error) {
    console.error("Save error", error);
    toast.error(error.response?.data?.error_message || "Gagal menyimpan user.");
  } finally {
    isSaving.value = false;
  }
}

async function toggleStatus(user) {
  try {
    const newStatus = !user.is_active;
    user.is_active = newStatus;
    await usersApi.update(user.id, { is_active: newStatus });
    toast.info(newStatus ? "User diaktifkan." : "User dinonaktifkan.");
  } catch (error) {
    user.is_active = !user.is_active;
    toast.error("Gagal mengubah status user.");
  }
}

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

// Helper to get placement name for table
function getPlacementName(user) {
  if (user.branch) return user.branch.name;
  if (user.warehouse) return `Gudang: ${user.warehouse.name}`; // Prefix for clarity
  if (user.online_shop) return `Online: ${user.online_shop.name}`;
  if (user.distributor) return `Dist: ${user.distributor.name}`;
  return '-';
}
</script>

<template>
  <div class="space-y-6 animate-in">
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4">
      <div>
        <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-2">
          <Users :size="28" class="text-blue-500" /> Staff & Role
        </h1>
        <p class="text-slate-500 mt-1">Kelola pengguna dan hak akses</p>
      </div>
      <button @click="openAddModal" class="btn btn-primary w-full md:w-auto flex justify-center">
        <UserPlus :size="18" />
        <span>Tambah User</span>
      </button>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="(stat, index) in stats" :key="index" class="card flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center"
          :class="{ 'bg-blue-600/20 text-blue-500': stat.color === 'blue', 'bg-emerald-600/20 text-emerald-500': stat.color === 'emerald', 'bg-amber-600/20 text-amber-500': stat.color === 'amber' }">
          <component :is="stat.icon" :size="24" />
        </div>
        <div>
          <p class="text-slate-500 text-sm">{{ stat.label }}</p>
          <p class="text-xl font-bold text-white">{{ stat.value }}</p>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="card space-y-4">
      <div class="flex flex-col md:flex-row gap-4">
        <div class="relative w-full md:flex-1">
          <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500" :size="18" />
          <input v-model="searchQuery" type="text" placeholder="Cari nama atau email..." class="input pl-10 w-full" />
        </div>
        <div class="flex flex-col sm:flex-row gap-4 w-full md:w-auto">
          <select v-model="selectedRole" class="input w-full sm:w-48">
            <option value="">Semua Role</option>
            <option v-for="role in rolesList" :key="role.value" :value="role.value">{{ role.label }}</option>
          </select>
          <select v-model="selectedBranch" class="input w-full sm:w-48">
            <option value="">Semua Cabang</option>
            <option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Table (Desktop) -->
    <div class="card p-0 hidden md:block overflow-hidden">
      <div v-if="isLoading" class="p-12 flex justify-center items-center">
        <Loader2 class="animate-spin text-blue-500" :size="32" />
        <span class="ml-3 text-slate-400">Memuat data user...</span>
      </div>
      <div v-else class="overflow-x-auto">
        <table class="table w-full">
          <thead>
            <tr>
              <th class="text-left py-4 px-6 text-slate-400 font-medium text-sm uppercase tracking-wider">User</th>
              <th class="text-left py-4 px-6 text-slate-400 font-medium text-sm uppercase tracking-wider">Role</th>
              <th class="text-left py-4 px-6 text-slate-400 font-medium text-sm uppercase tracking-wider">Penempatan
              </th>
              <th class="text-left py-4 px-6 text-slate-400 font-medium text-sm uppercase tracking-wider">Aktifitas</th>
              <th class="text-left py-4 px-6 text-slate-400 font-medium text-sm uppercase tracking-wider">Status</th>
              <th class="text-right py-4 px-6 text-slate-400 font-medium text-sm uppercase tracking-wider">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-700/50">
            <tr v-if="filteredUsers.length === 0">
              <td colspan="6" class="text-center py-12 text-slate-500">
                Tidak ada user ditemukan
              </td>
            </tr>
            <tr v-for="user in filteredUsers" :key="user.id" class="hover:bg-slate-800/50 transition-colors">
              <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                  <img
                    :src="`https://ui-avatars.com/api/?name=${encodeURIComponent(user.full_name)}&background=3b82f6&color=fff`"
                    class="w-10 h-10 rounded-xl object-cover" :alt="user.full_name" />
                  <div>
                    <p class="font-medium text-white">{{ user.full_name }}</p>
                    <p class="text-xs text-slate-500 font-mono">{{ user.username }}</p>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4">
                <span v-if="user.roles && user.roles.length"
                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-500/10 text-blue-400 border border-blue-500/20">
                  {{ ROLE_LABELS[user.roles[0].name] || user.roles[0].name }}
                </span>
                <span v-else class="text-xs text-slate-500 italic">No Role</span>
              </td>
              <td class="px-6 py-4">
                <div class="flex items-center gap-2">
                  <MapPin :size="14" class="text-slate-500" />
                  <span class="text-sm text-slate-300">{{ getPlacementName(user) }}</span>
                </div>
                <div class="text-[10px] text-slate-500 mt-1 pl-5 font-mono">{{ user.timezone || 'WIB' }}</div>
              </td>
              <td class="px-6 py-4 text-sm text-slate-400">
                {{ formatLastSeen(user.last_seen, user.timezone) }}
              </td>
              <td class="px-6 py-4">
                <div class="flex items-center gap-2">
                  <div :class="`w-2 h-2 rounded-full ${user.is_active ? 'bg-emerald-500' : 'bg-red-500'}`"></div>
                  <span class="text-sm" :class="user.is_active ? 'text-emerald-400' : 'text-red-400'">
                    {{ user.is_active ? 'Aktif' : 'Nonaktif' }}
                  </span>
                </div>
              </td>
              <td class="px-6 py-4">
                <div class="flex justify-end gap-2">
                  <button @click="openEditModal(user)"
                    class="p-2 hover:bg-slate-700 rounded-lg text-blue-400 transition-colors" title="Edit">
                    <Edit :size="16" />
                  </button>
                  <button @click="permanentDeleteUser(user.id)"
                    class="p-2 hover:bg-slate-700 rounded-lg text-red-400 transition-colors" title="Hapus">
                    <Trash2 :size="16" />
                  </button>
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
        <div class="flex justify-between items-start gap-3">
          <div class="flex items-center gap-3">
            <img
              :src="`https://ui-avatars.com/api/?name=${encodeURIComponent(user.full_name)}&background=3b82f6&color=fff`"
              class="w-10 h-10 rounded-xl" />
            <div>
              <p class="font-medium text-white">{{ user.full_name }}</p>
              <p class="text-xs text-slate-500">{{ user.username }}</p>
            </div>
          </div>
          <span v-if="user.roles && user.roles.length"
            class="text-xs px-2 py-1 bg-blue-500/10 text-blue-400 rounded-lg border border-blue-500/20">
            {{ ROLE_LABELS[user.roles[0].name] || user.roles[0].name }}
          </span>
        </div>

        <div class="grid grid-cols-2 gap-3 text-sm border-t border-slate-700/50 pt-3">
          <div>
            <p class="text-slate-500 text-xs mb-1">Penempatan</p>
            <p class="text-slate-300">{{ getPlacementName(user) }}</p>
          </div>
          <div class="text-right">
            <p class="text-slate-500 text-xs mb-1">Status</p>
            <div class="inline-flex items-center gap-1.5">
              <div :class="`w-1.5 h-1.5 rounded-full ${user.is_active ? 'bg-emerald-500' : 'bg-red-500'}`"></div>
              <span :class="user.is_active ? 'text-emerald-400' : 'text-red-400'">{{ user.is_active ? 'Aktif' :
                'Nonaktif' }}</span>
            </div>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-700/50">
          <button @click="openEditModal(user)"
            class="btn-sm btn-outline text-blue-400 border-slate-700 hover:bg-slate-800">
            <Edit :size="14" class="mr-1" /> Edit
          </button>
          <button @click="permanentDeleteUser(user.id)"
            class="btn-sm btn-outline text-red-400 border-slate-700 hover:bg-slate-800">
            <Trash2 :size="14" class="mr-1" /> Hapus
          </button>
        </div>
      </div>
    </div>

    <!-- Add/Edit Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="closeModal"></div>

        <div
          class="relative bg-surface-800 rounded-2xl border border-surface-700 w-full max-w-lg p-6 shadow-2xl animate-in zoom-in duration-200 max-h-[90vh] overflow-y-auto">
          <button @click="closeModal"
            class="absolute top-4 right-4 p-2 text-slate-400 hover:text-white transition-colors">
            <X :size="20" />
          </button>

          <h3 class="text-xl font-bold text-white mb-6 flex items-center gap-2">
            <UserPlus v-if="!editingUser" class="text-primary-500" :size="24" />
            <Edit v-else class="text-blue-500" :size="24" />
            {{ editingUser ? "Edit User" : "Tambah User Baru" }}
          </h3>

          <form @submit.prevent="saveUser" class="space-y-4">
            <div>
              <label class="label">Nama Lengkap</label>
              <input v-model="form.full_name" type="text" class="input" placeholder="Nama Lengkap..." required />
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="label">Kode ID (Opsional)</label>
                <input v-model="form.code_id" class="input font-mono" placeholder="EMP-001" />
              </div>
              <div>
                <label class="label">Username</label>
                <input v-model="form.username" class="input" placeholder="username" required />
              </div>
            </div>

            <!-- Password -->
            <div>
              <label class="label">Password {{ editingUser ? '(Optional)' : '' }}</label>
              <div class="relative">
                <input v-model="form.password" :type="showPassword ? 'text' : 'password'" class="input pr-10"
                  placeholder="••••••••" :required="!editingUser" />
                <button type="button" @click="showPassword = !showPassword"
                  class="absolute right-3 top-2.5 text-slate-500 hover:text-white">
                  <Eye v-if="!showPassword" :size="18" />
                  <EyeOff v-else :size="18" />
                </button>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="label">Role</label>
                <select v-model="form.role" class="input" required>
                  <option value="">Pilih Role</option>
                  <option v-for="role in rolesList" :key="role.value" :value="role.value">{{ role.label }}</option>
                </select>
              </div>
              <div>
                <label class="label">Zona Waktu</label>
                <select v-model="form.timezone" class="input">
                  <option v-for="tz in timezones" :key="tz.value" :value="tz.value">{{ tz.label }}</option>
                </select>
              </div>
            </div>

            <!-- Dynamic Placement Selection -->
            <div v-if="form.role" class="animate-in fade-in slide-in-from-top-2">
              <label class="label">{{ placementLabel }}</label>

              <!-- Warehouse Select -->
              <select v-if="placementType === 'warehouse'" v-model="form.warehouse_id" class="input">
                <option value="">Pilih Gudang...</option>
                <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }} ({{ w.code }})</option>
              </select>

              <!-- Distributor Select -->
              <select v-else-if="placementType === 'distributor'" v-model="form.distributor_id" class="input">
                <option value="">Pilih Distributor...</option>
                <option v-for="d in distributors" :key="d.id" :value="d.id">{{ d.name }}</option>
              </select>

              <!-- Online Shop Select -->
              <select v-else-if="placementType === 'online_shop'" v-model="form.online_shop_id" class="input">
                <option value="">Pilih Toko Online...</option>
                <option v-for="s in onlineShops" :key="s.id" :value="s.id">{{ s.name }} ({{ s.platform }})</option>
              </select>

              <!-- Branch Select (Default) -->
              <select v-else v-model="form.branch_id" class="input">
                <option value="">Pilih Cabang Fisik...</option>
                <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
              </select>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-slate-700/50">
              <button type="button" @click="closeModal" class="btn text-slate-400 hover:text-white">Batal</button>
              <button type="submit" class="btn btn-primary" :disabled="isSaving">
                <Loader2 v-if="isSaving" class="animate-spin mr-2" :size="18" />
                {{ isSaving ? 'Menyimpan...' : 'Simpan User' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
@reference "../../style.css";

.label {
  @apply block text-xs font-medium text-slate-400 mb-1.5 uppercase tracking-wide;
}

.input {
  @apply w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2.5 text-white focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition-all placeholder:text-slate-600;
}

.animate-in {
  animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(5px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>

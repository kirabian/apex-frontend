<script setup>
import { ref, computed } from "vue";
import {
  Truck,
  MapPin,
  Phone,
  Mail,
  Plus,
  Search,
  Edit,
  Trash2,
  ExternalLink,
  Save,
  X,
} from "lucide-vue-next";

// Mock Data
const distributors = ref([
  {
    id: 1,
    name: "PT. Distribusi Teknologi Indonesia",
    pic: "Budi Santoso",
    phone: "081234567890",
    email: "budi@distrotech.id",
    address: "Kawasan Industri MM2100, Bekasi",
    status: "active",
    products_count: 154,
  },
  {
    id: 2,
    name: "CV. Maju Jaya Gadget",
    pic: "Siti Aminah",
    phone: "081298765432",
    email: "sales@majujaya.com",
    address: "Jl. Mangga Dua Raya No. 12, Jakarta",
    status: "active",
    products_count: 89,
  },
  {
    id: 3,
    name: "Global Electronics Import",
    pic: "Michael Ong",
    phone: "021-55566677",
    email: "import@global-elec.com",
    address: "Ruko Cordoba, PIK, Jakarta Utara",
    status: "inactive",
    products_count: 0,
  },
]);

// Local State
const searchQuery = ref("");
const showModal = ref(false);
const editingItem = ref(null);

const form = ref({
  name: "",
  pic: "",
  phone: "",
  email: "",
  address: "",
  status: "active",
  notes: "",
});

// Filtered Data
const filteredDistributors = computed(() => {
  if (!searchQuery.value) return distributors.value;
  const query = searchQuery.value.toLowerCase();
  return distributors.value.filter(
    (d) =>
      d.name.toLowerCase().includes(query) ||
      d.pic.toLowerCase().includes(query) ||
      d.email.toLowerCase().includes(query)
  );
});

// Actions
function openAddModal() {
  editingItem.value = null;
  form.value = {
    name: "",
    pic: "",
    phone: "",
    email: "",
    address: "",
    status: "active",
    notes: "",
  };
  showModal.value = true;
}

function openEditModal(item) {
  editingItem.value = item;
  form.value = { ...item };
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
  editingItem.value = null;
}

function saveDistributor() {
  if (editingItem.value) {
    const index = distributors.value.findIndex(
      (d) => d.id === editingItem.value.id
    );
    if (index !== -1) {
      distributors.value[index] = {
        ...form.value,
        id: editingItem.value.id,
        products_count: distributors.value[index].products_count,
      };
    }
  } else {
    distributors.value.push({
      ...form.value,
      id: distributors.value.length + 1,
      products_count: 0,
    });
  }
  closeModal();
}

function deleteDistributor(id) {
  if (confirm("Apakah Anda yakin ingin menghapus distributor ini?")) {
    distributors.value = distributors.value.filter((d) => d.id !== id);
  }
}
</script>

<template>
  <div class="space-y-6 animate-in">
    <!-- Header -->
    <div class="flex justify-between items-end">
      <div>
        <h1 class="text-2xl font-bold text-white tracking-tight">
          Distributor
        </h1>
        <p class="text-slate-500 mt-1">
          Kelola data supplier dan partner distribusi
        </p>
      </div>
      <button @click="openAddModal" class="btn btn-primary">
        <Plus :size="16" />
        Tambah Distributor
      </button>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="card flex items-center gap-4">
        <div
          class="w-12 h-12 rounded-xl bg-blue-600 flex items-center justify-center"
        >
          <Truck :size="20" class="text-white" />
        </div>
        <div>
          <p class="text-slate-500 text-sm">Total Distributor</p>
          <p class="text-xl font-bold text-white">{{ distributors.length }}</p>
        </div>
      </div>
      <div class="card flex items-center gap-4">
        <div
          class="w-12 h-12 rounded-xl bg-emerald-600 flex items-center justify-center"
        >
          <Truck :size="20" class="text-white" />
        </div>
        <div>
          <p class="text-slate-500 text-sm">Distributor Aktif</p>
          <p class="text-xl font-bold text-white">
            {{ distributors.filter((d) => d.status === "active").length }}
          </p>
        </div>
      </div>
    </div>

    <!-- Content -->
    <div class="card p-0">
      <div class="p-4 border-b border-slate-700/50">
        <div class="relative max-w-md">
          <Search
            class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500"
            :size="18"
          />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Cari nama distributor, PIC, atau email..."
            class="input pl-10"
          />
        </div>
      </div>

      <div class="table-container">
        <table class="table">
          <thead>
            <tr>
              <th>Distributor</th>
              <th>Kontak Person (PIC)</th>
              <th>Kontak</th>
              <th>Alamat</th>
              <th>Total Produk</th>
              <th>Status</th>
              <th class="text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="items in filteredDistributors" :key="items.id">
              <td>
                <div class="flex items-center gap-3">
                  <div
                    class="w-10 h-10 rounded-lg bg-slate-700 flex items-center justify-center text-slate-400 font-bold text-lg"
                  >
                    {{ items.name.charAt(0) }}
                  </div>
                  <div>
                    <p class="font-medium text-white">{{ items.name }}</p>
                    <p class="text-xs text-slate-500 font-mono">
                      ID: DST-{{ items.id.toString().padStart(3, "0") }}
                    </p>
                  </div>
                </div>
              </td>
              <td class="text-slate-300 font-medium">{{ items.pic }}</td>
              <td>
                <div class="flex flex-col gap-1 text-sm text-slate-400">
                  <span class="flex items-center gap-2"
                    ><Phone :size="12" /> {{ items.phone }}</span
                  >
                  <span class="flex items-center gap-2"
                    ><Mail :size="12" /> {{ items.email }}</span
                  >
                </div>
              </td>
              <td class="text-slate-400 max-w-[200px] truncate">
                {{ items.address }}
              </td>
              <td class="text-center font-mono text-blue-400">
                {{ items.products_count }}
              </td>
              <td>
                <span
                  class="badge"
                  :class="
                    items.status === 'active' ? 'badge-success' : 'badge-danger'
                  "
                >
                  {{ items.status === "active" ? "Aktif" : "Nonaktif" }}
                </span>
              </td>
              <td>
                <div class="flex items-center justify-center gap-2">
                  <button
                    @click="openEditModal(items)"
                    class="p-2 hover:bg-slate-700 rounded-lg transition-colors text-blue-400"
                  >
                    <Edit :size="16" />
                  </button>
                  <button
                    @click="deleteDistributor(items.id)"
                    class="p-2 hover:bg-slate-700 rounded-lg transition-colors text-red-400"
                  >
                    <Trash2 :size="16" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal -->
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
          class="relative bg-slate-900 rounded-2xl border border-slate-700 w-full max-w-lg p-6 shadow-2xl animate-in"
        >
          <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-bold text-white">
              {{ editingItem ? "Edit Distributor" : "Tambah Distributor Baru" }}
            </h3>
            <button @click="closeModal" class="text-slate-400 hover:text-white">
              <X :size="20" />
            </button>
          </div>

          <form @submit.prevent="saveDistributor" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-slate-400 mb-1"
                >Nama Perusahaan</label
              >
              <input
                v-model="form.name"
                type="text"
                class="input"
                placeholder="PT. Makmur Jaya"
                required
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-400 mb-1"
                >PIC (Person In Charge)</label
              >
              <input
                v-model="form.pic"
                type="text"
                class="input"
                placeholder="Nama Contact Person"
                required
              />
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-slate-400 mb-1"
                  >Email</label
                >
                <input
                  v-model="form.email"
                  type="email"
                  class="input"
                  placeholder="email@example.com"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-400 mb-1"
                  >No. Telepon</label
                >
                <input
                  v-model="form.phone"
                  type="tel"
                  class="input"
                  placeholder="021-xxxxxx"
                  required
                />
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-400 mb-1"
                >Alamat Lengkap</label
              >
              <textarea
                v-model="form.address"
                class="input h-20 resize-none"
                placeholder="Alamat kantor distributor..."
              ></textarea>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-400 mb-1"
                >Catatan</label
              >
              <textarea
                v-model="form.notes"
                class="input h-16 resize-none"
                placeholder="Catatan tambahan..."
              ></textarea>
            </div>

            <div class="flex items-center gap-2">
              <input
                v-model="form.status"
                type="checkbox"
                id="status_active"
                :true-value="'active'"
                :false-value="'inactive'"
                class="w-4 h-4 rounded border-slate-600 bg-slate-700 text-blue-600 focus:ring-blue-500"
              />
              <label for="status_active" class="text-sm text-slate-300"
                >Status Aktif</label
              >
            </div>

            <div class="pt-4 flex gap-3">
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

<script setup>
import { ref, computed } from "vue";
import {
  Tags,
  Filter,
  ArrowRightLeft,
  Plus,
  Search,
  Edit,
  Trash2,
  Save,
  X,
  Layers,
} from "lucide-vue-next";

// Mock Data
const itemTypes = ref([
  {
    id: 1,
    name: "Smartphone",
    code: "SMP",
    description: "Handphone, Smartphone Android & iOS",
  },
  {
    id: 2,
    name: "Laptop",
    code: "LPT",
    description: "Laptop, Notebook, Macbook",
  },
  {
    id: 3,
    name: "Aksesoris",
    code: "ACC",
    description: "Casing, Charger, Kabel",
  },
  {
    id: 4,
    name: "Sparepart",
    code: "PRT",
    description: "LCD, Baterai, Kamera",
  },
]);

const transactionCategories = ref([
  {
    id: 1,
    name: "Penjualan Retail",
    type: "in",
    description: "Penjualan langsung ke customer",
  },
  {
    id: 2,
    name: "Penjualan Grosir",
    type: "in",
    description: "Penjualan ke reseller/distributor",
  },
  {
    id: 3,
    name: "Service",
    type: "in",
    description: "Jasa perbaikan perangkat",
  },
  {
    id: 4,
    name: "Retur Pembelian",
    type: "out",
    description: "Pengembalian barang ke supplier",
  },
  {
    id: 5,
    name: "Biaya Operasional",
    type: "out",
    description: "Pengeluaran toko sehari-hari",
  },
]);

// Local State
const activeTab = ref("items"); // items, transactions
const searchQuery = ref("");
const showModal = ref(false);
const editingItem = ref(null);

// Form
const form = ref({});

// Initial Form State Factory
const getInitialForm = (type) => {
  if (type === "items") return { name: "", code: "", description: "" };
  return { name: "", type: "in", description: "" };
};

// Filtered Data
const filteredData = computed(() => {
  const data =
    activeTab.value === "items" ? itemTypes.value : transactionCategories.value;
  if (!searchQuery.value) return data;
  const query = searchQuery.value.toLowerCase();
  return data.filter(
    (item) =>
      item.name.toLowerCase().includes(query) ||
      (item.code && item.code.toLowerCase().includes(query))
  );
});

// Actions
function openAddModal() {
  editingItem.value = null;
  form.value = getInitialForm(activeTab.value);
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

function saveData() {
  const targetArray =
    activeTab.value === "items" ? itemTypes : transactionCategories;

  if (editingItem.value) {
    const index = targetArray.value.findIndex(
      (i) => i.id === editingItem.value.id
    );
    if (index !== -1) {
      targetArray.value[index] = { ...form.value, id: editingItem.value.id };
    }
  } else {
    targetArray.value.push({
      ...form.value,
      id: targetArray.value.length + 1,
    });
  }
  closeModal();
}

function deleteData(id) {
  const targetArray =
    activeTab.value === "items" ? itemTypes : transactionCategories;
  if (confirm("Apakah Anda yakin ingin menghapus data ini?")) {
    targetArray.value = targetArray.value.filter((i) => i.id !== id);
  }
}
</script>

<template>
  <div class="space-y-6 animate-in">
    <!-- Header -->
    <div class="flex justify-between items-end">
      <div>
        <h1 class="text-2xl font-bold text-white tracking-tight">
          Kategori & Jenis
        </h1>
        <p class="text-slate-500 mt-1">
          Kelola jenis barang dan kategori transaksi
        </p>
      </div>
      <button @click="openAddModal" class="btn btn-primary">
        <Plus :size="16" />
        Tambah
        {{ activeTab === "items" ? "Jenis Barang" : "Kategori Transaksi" }}
      </button>
    </div>

    <!-- Tabs -->
    <div class="flex gap-2 bg-slate-800/50 p-1 rounded-xl w-fit">
      <button
        @click="activeTab = 'items'"
        class="px-4 py-2 text-sm font-medium rounded-lg transition-colors flex items-center gap-2"
        :class="
          activeTab === 'items'
            ? 'bg-blue-600 text-white'
            : 'text-slate-400 hover:text-white'
        "
      >
        <Tags :size="16" />
        Jenis Barang
      </button>
      <button
        @click="activeTab = 'transactions'"
        class="px-4 py-2 text-sm font-medium rounded-lg transition-colors flex items-center gap-2"
        :class="
          activeTab === 'transactions'
            ? 'bg-blue-600 text-white'
            : 'text-slate-400 hover:text-white'
        "
      >
        <ArrowRightLeft :size="16" />
        Kategori Transaksi
      </button>
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
            placeholder="Cari kategori..."
            class="input pl-10"
          />
        </div>
      </div>

      <div class="table-container">
        <!-- Item Types Table -->
        <table v-if="activeTab === 'items'" class="table">
          <thead>
            <tr>
              <th>Kode</th>
              <th>Nama Jenis Barang</th>
              <th>Deskripsi</th>
              <th class="text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in filteredData" :key="item.id">
              <td class="font-mono text-blue-400 font-medium">
                {{ item.code }}
              </td>
              <td class="font-medium text-white">{{ item.name }}</td>
              <td class="text-slate-400">{{ item.description }}</td>
              <td>
                <div class="flex items-center justify-center gap-2">
                  <button
                    @click="openEditModal(item)"
                    class="p-2 hover:bg-slate-700 rounded-lg transition-colors text-blue-400"
                  >
                    <Edit :size="16" />
                  </button>
                  <button
                    @click="deleteData(item.id)"
                    class="p-2 hover:bg-slate-700 rounded-lg transition-colors text-red-400"
                  >
                    <Trash2 :size="16" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Transaction Categories Table -->
        <table v-else class="table">
          <thead>
            <tr>
              <th>Nama Kategori</th>
              <th>Tipe Arus</th>
              <th>Deskripsi</th>
              <th class="text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in filteredData" :key="item.id">
              <td class="font-medium text-white">{{ item.name }}</td>
              <td>
                <span
                  class="badge"
                  :class="item.type === 'in' ? 'badge-success' : 'badge-danger'"
                >
                  {{ item.type === "in" ? "Pemasukan" : "Pengeluaran" }}
                </span>
              </td>
              <td class="text-slate-400">{{ item.description }}</td>
              <td>
                <div class="flex items-center justify-center gap-2">
                  <button
                    @click="openEditModal(item)"
                    class="p-2 hover:bg-slate-700 rounded-lg transition-colors text-blue-400"
                  >
                    <Edit :size="16" />
                  </button>
                  <button
                    @click="deleteData(item.id)"
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
              {{ editingItem ? "Edit Data" : "Tambah Data Baru" }}
            </h3>
            <button @click="closeModal" class="text-slate-400 hover:text-white">
              <X :size="20" />
            </button>
          </div>

          <form @submit.prevent="saveData" class="space-y-4">
            <!-- Form for Items -->
            <template v-if="activeTab === 'items'">
              <div class="grid grid-cols-3 gap-4">
                <div class="col-span-1">
                  <label class="block text-sm font-medium text-slate-400 mb-1"
                    >Kode</label
                  >
                  <input
                    v-model="form.code"
                    type="text"
                    class="input uppercase font-mono"
                    placeholder="ABC"
                    required
                    maxlength="5"
                  />
                </div>
                <div class="col-span-2">
                  <label class="block text-sm font-medium text-slate-400 mb-1"
                    >Nama Jenis</label
                  >
                  <input
                    v-model="form.name"
                    type="text"
                    class="input"
                    placeholder="Contoh: Smartphone"
                    required
                  />
                </div>
              </div>
            </template>

            <!-- Form for Transactions -->
            <template v-else>
              <div>
                <label class="block text-sm font-medium text-slate-400 mb-1"
                  >Nama Kategori</label
                >
                <input
                  v-model="form.name"
                  type="text"
                  class="input"
                  placeholder="Contoh: Biaya Listrik"
                  required
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-400 mb-1"
                  >Tipe Arus</label
                >
                <select v-model="form.type" class="input" required>
                  <option value="in">Pemasukan (Income)</option>
                  <option value="out">Pengeluaran (Expense)</option>
                </select>
              </div>
            </template>

            <div>
              <label class="block text-sm font-medium text-slate-400 mb-1"
                >Deskripsi</label
              >
              <textarea
                v-model="form.description"
                class="input h-24 resize-none"
                placeholder="Keterangan tambahan..."
              ></textarea>
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

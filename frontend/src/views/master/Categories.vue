<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import {
  Tags,
  Search,
  Plus,
  Edit,
  Trash2,
  ShoppingBag,
  ArrowRightLeft
} from 'lucide-vue-next';
import { categories as api } from '../../api/axios';
import { useToast } from '../../composables/useToast';
import CategoryModal from './CategoryModal.vue';

const toast = useToast();
const activeTab = ref('product'); // 'product' or 'transaction'
const items = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const showModal = ref(false);
const selectedItem = ref(null);

const tabs = [
  { id: 'product', label: 'Jenis Barang', icon: ShoppingBag },
  { id: 'transaction', label: 'Kategori Transaksi', icon: ArrowRightLeft },
];

const fetchData = async () => {
  loading.value = true;
  try {
    const response = await api.list({ type: activeTab.value });
    items.value = response.data.data;
  } catch (error) {
    toast.error("Gagal memuat data kategori");
  } finally {
    loading.value = false;
  }
};

watch(activeTab, () => {
  fetchData();
  searchQuery.value = '';
});

onMounted(() => {
  fetchData();
});

const filteredItems = computed(() => {
  if (!searchQuery.value) return items.value;
  const lower = searchQuery.value.toLowerCase();
  return items.value.filter(i =>
    i.name.toLowerCase().includes(lower) ||
    (i.description && i.description.toLowerCase().includes(lower))
  );
});

const openCreateModal = () => {
  selectedItem.value = null;
  showModal.value = true;
};

const openEditModal = (item) => {
  selectedItem.value = item;
  showModal.value = true;
};

const handleDelete = async (id) => {
  if (!confirm('Hapus kategori ini?')) return;
  try {
    await api.delete(id);
    toast.success('Kategori berhasil dihapus');
    items.value = items.value.filter(i => i.id !== id);
  } catch (error) {
    toast.error('Gagal menghapus kategori');
  }
};

const handleSaved = () => {
  showModal.value = false;
  fetchData();
};
</script>

<template>
  <div class="space-y-6 animate-in fade-in duration-500">
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-3">
          <Tags class="text-primary-500" :size="28" />
          Kategori & Jenis
        </h1>
        <p class="text-text-secondary mt-1">Kelola jenis barang dan kategori transaksi</p>
      </div>
      <button @click="openCreateModal" class="btn btn-primary shadow-lg shadow-primary-500/20">
        <Plus :size="20" />
        <span>Tambah {{ activeTab === 'product' ? 'Jenis Barang' : 'Kategori Transaksi' }}</span>
      </button>
    </div>

    <!-- Tabs -->
    <div class="flex border-b border-surface-700 space-x-6">
      <button v-for="tab in tabs" :key="tab.id" @click="activeTab = tab.id"
        class="pb-3 text-sm font-medium transition-all flex items-center gap-2 relative"
        :class="activeTab === tab.id ? 'text-primary-500' : 'text-text-secondary hover:text-white'">
        <component :is="tab.icon" :size="18" />
        {{ tab.label }}
        <!-- Active Indicator -->
        <span v-if="activeTab === tab.id"
          class="absolute bottom-0 left-0 w-full h-0.5 bg-primary-500 rounded-t-full"></span>
      </button>
    </div>

    <div class="bg-surface-800 rounded-2xl border border-surface-700 p-4 shadow-xl">
      <div class="relative">
        <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" :size="20" />
        <input v-model="searchQuery" type="text" placeholder="Cari nama kategori..." class="input pl-10 w-full" />
      </div>
    </div>

    <div v-if="loading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500"></div>
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div v-if="filteredItems.length === 0" class="col-span-full text-center py-10 text-text-secondary">
        Belum ada data untuk kategori ini.
      </div>

      <div v-for="item in filteredItems" :key="item.id"
        class="group bg-surface-800 rounded-2xl border border-surface-700 p-5 hover:border-primary-500/50 transition-all duration-300 relative overflow-hidden flex flex-col justify-between">
        <div>
          <div class="flex justify-between items-start mb-2">
            <h3 class="font-bold text-white text-lg">{{ item.name }}</h3>
            <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
              <button @click="openEditModal(item)"
                class="p-2 hover:bg-surface-700 rounded-lg text-blue-400 transition-colors">
                <Edit :size="16" />
              </button>
              <button @click="handleDelete(item.id)"
                class="p-2 hover:bg-surface-700 rounded-lg text-red-400 transition-colors">
                <Trash2 :size="16" />
              </button>
            </div>
          </div>
          <p class="text-sm text-text-secondary line-clamp-2">{{ item.description || '-' }}</p>
        </div>

        <div class="mt-4 pt-3 border-t border-surface-700 flex justify-between items-center text-xs">
          <span class="px-2 py-1 rounded bg-surface-700 text-text-secondary uppercase tracking-wider font-bold">
            {{ item.type === 'product' ? 'Jenis Barang' : 'Transaksi' }}
          </span>
          <span class="text-surface-500">{{ new Date(item.created_at).toLocaleDateString() }}</span>
        </div>
      </div>
    </div>

    <CategoryModal :show="showModal" :item="selectedItem" :type="activeTab" @close="showModal = false"
      @saved="handleSaved" />
  </div>
</template>

<style scoped>
@reference "../../style.css";

.input {
  @apply bg-surface-900 border border-surface-700 rounded-xl px-4 py-2 text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-all placeholder:text-surface-600;
}
</style>

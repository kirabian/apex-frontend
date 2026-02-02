<script setup>
import { ref, onMounted, computed } from 'vue';
import {
  Truck,
  Search,
  Plus,
  Edit,
  Trash2,
  Phone,
  Mail,
  MapPin,
  User
} from 'lucide-vue-next';
import { distributors as api } from '../../api/axios';
import { useToast } from '../../composables/useToast';
import DistributorModal from './DistributorModal.vue';

const toast = useToast();
const items = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const showModal = ref(false);
const selectedItem = ref(null);

const fetchData = async () => {
  loading.value = true;
  try {
    const response = await api.list();
    items.value = response.data.data;
  } catch (error) {
    toast.error("Gagal memuat data distributor");
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchData();
});

const filteredItems = computed(() => {
  if (!searchQuery.value) return items.value;
  const lower = searchQuery.value.toLowerCase();
  return items.value.filter(i =>
    i.name.toLowerCase().includes(lower) ||
    (i.code && i.code.toLowerCase().includes(lower)) ||
    (i.contact_person && i.contact_person.toLowerCase().includes(lower))
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
  if (!confirm('Hapus distributor ini?')) return;
  try {
    await api.delete(id);
    toast.success('Distributor berhasil dihapus');
    items.value = items.value.filter(i => i.id !== id);
  } catch (error) {
    toast.error('Gagal menghapus distributor');
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
          <Truck class="text-primary-500" :size="28" />
          Data Distributor
        </h1>
        <p class="text-text-secondary mt-1">Kelola daftar supplier dan distributor barang</p>
      </div>
      <button @click="openCreateModal" class="btn btn-primary shadow-lg shadow-primary-500/20">
        <Plus :size="20" />
        <span>Tambah Distributor</span>
      </button>
    </div>

    <div class="bg-surface-800 rounded-2xl border border-surface-700 p-4 sticky top-4 z-10 shadow-xl">
      <div class="relative">
        <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" :size="20" />
        <input v-model="searchQuery" type="text" placeholder="Cari distributor..." class="input pl-10 w-full" />
      </div>
    </div>

    <div v-if="loading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500"></div>
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div v-for="item in filteredItems" :key="item.id"
        class="group bg-surface-800 rounded-2xl border border-surface-700 p-5 hover:border-primary-500/50 transition-all duration-300 relative overflow-hidden">
        <div class="flex justify-between items-start mb-4">
          <div>
            <h3 class="font-bold text-white text-lg">{{ item.name }}</h3>
            <span v-if="item.code" class="text-xs font-mono text-primary-400 bg-primary-500/10 px-1.5 py-0.5 rounded">{{
              item.code }}</span>
          </div>
          <div class="flex gap-1">
            <button @click="openEditModal(item)"
              class="p-2 hover:bg-surface-700 rounded-lg text-blue-400 transition-colors">
              <Edit :size="18" />
            </button>
            <button @click="handleDelete(item.id)"
              class="p-2 hover:bg-surface-700 rounded-lg text-red-400 transition-colors">
              <Trash2 :size="18" />
            </button>
          </div>
        </div>

        <div class="space-y-2 text-sm text-text-secondary">
          <div v-if="item.contact_person" class="flex items-center gap-2">
            <User :size="14" /> {{ item.contact_person }}
          </div>
          <div v-if="item.phone" class="flex items-center gap-2">
            <Phone :size="14" /> {{ item.phone }}
          </div>
          <div v-if="item.address" class="flex items-start gap-2">
            <MapPin :size="14" class="mt-0.5 shrink-0" /> <span class="line-clamp-2">{{ item.address }}</span>
          </div>
        </div>
      </div>
    </div>

    <DistributorModal :show="showModal" :item="selectedItem" @close="showModal = false" @saved="handleSaved" />
  </div>
</template>

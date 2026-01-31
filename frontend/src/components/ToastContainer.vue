<script setup>
import { useToast } from '../composables/useToast'
import { X, CheckCircle, AlertCircle, Info, AlertTriangle } from 'lucide-vue-next'

const { toasts, remove } = useToast()

const getIcon = (type) => {
  switch (type) {
    case 'success': return CheckCircle
    case 'error': return AlertCircle
    case 'warning': return AlertTriangle
    default: return Info
  }
}

const getClasses = (type) => {
  switch (type) {
    case 'success': return 'bg-emerald-600 text-white border-emerald-500'
    case 'error': return 'bg-red-600 text-white border-red-500'
    case 'warning': return 'bg-amber-600 text-white border-amber-500'
    default: return 'bg-blue-600 text-white border-blue-500'
  }
}
</script>

<template>
  <div class="fixed top-4 right-4 z-[9999] flex flex-col gap-2 w-full max-w-sm pointer-events-none">
    <TransitionGroup 
      enter-active-class="transform ease-out duration-300 transition"
      enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
      enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
      leave-active-class="transition ease-in duration-100"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div 
        v-for="toast in toasts" 
        :key="toast.id"
        class="pointer-events-auto rounded-lg shadow-lg border p-4 flex items-start gap-3 w-full backdrop-blur-sm bg-opacity-95"
        :class="getClasses(toast.type)"
      >
        <component :is="getIcon(toast.type)" class="w-5 h-5 shrink-0 mt-0.5" />
        <div class="flex-1 text-sm font-medium">{{ toast.message }}</div>
        <button @click="remove(toast.id)" class="text-white/70 hover:text-white transition-colors">
          <X class="w-4 h-4" />
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>

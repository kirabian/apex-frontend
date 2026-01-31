import { ref } from 'vue'

const toasts = ref([])
let idCounter = 0

export function useToast() {
    const add = (message, type = 'info', duration = 3000) => {
        const id = idCounter++
        const toast = { id, message, type }
        toasts.value.push(toast)

        if (duration) {
            setTimeout(() => {
                remove(id)
            }, duration)
        }
    }

    const remove = (id) => {
        const index = toasts.value.findIndex((t) => t.id === id)
        if (index !== -1) {
            toasts.value.splice(index, 1)
        }
    }

    const success = (message, duration = 3000) => add(message, 'success', duration)
    const error = (message, duration = 3000) => add(message, 'error', duration)
    const info = (message, duration = 3000) => add(message, 'info', duration)
    const warning = (message, duration = 3000) => add(message, 'warning', duration)

    return {
        toasts,
        add,
        remove,
        success,
        error,
        info,
        warning
    }
}

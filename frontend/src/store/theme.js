import { defineStore } from 'pinia'
import { ref, watch } from 'vue'

export const useThemeStore = defineStore('theme', () => {
    // State
    const isDark = ref(localStorage.getItem('theme_is_dark') === 'true')
    const themeName = ref(localStorage.getItem('theme_name') || 'default')

    // Available Themes
    const availableThemes = [
        { id: 'default', name: 'Default (Blue)', color: '#3b82f6' },
        { id: 'emerald', name: 'Emerald', color: '#10b981' },
        { id: 'rose', name: 'Rose', color: '#f43f5e' },
        { id: 'amber', name: 'Amber', color: '#f59e0b' },
        { id: 'violet', name: 'Violet', color: '#8b5cf6' },
        { id: 'cyan', name: 'Cyan', color: '#06b6d4' },
        { id: 'fuchsia', name: 'Fuchsia', color: '#d946ef' },
        { id: 'lime', name: 'Lime', color: '#84cc16' },
        { id: 'teal', name: 'Teal', color: '#14b8a6' },
        { id: 'sky', name: 'Sky', color: '#0ea5e9' },
        { id: 'indigo', name: 'Indigo', color: '#6366f1' },
        { id: 'orange', name: 'Orange', color: '#f97316' },
        { id: 'pink', name: 'Pink', color: '#ec4899' },
        { id: 'red', name: 'Red', color: '#ef4444' },
        { id: 'stone', name: 'Stone', color: '#78716c' },
        { id: 'neutral', name: 'Neutral', color: '#737373' },
        { id: 'slate', name: 'Slate', color: '#64748b' },
        { id: 'zinc', name: 'Zinc', color: '#71717a' },
        { id: 'dracula', name: 'Dracula', color: '#ff79c6' },
        { id: 'midnight', name: 'Midnight', color: '#1e3a8a' },
        // Aesthetic Collection
        { id: 'white', name: 'Clean White 🤍', color: '#ffffff' }, // Minimalist
        { id: 'coquette', name: 'Coquette 🎀', color: '#f472b6' }, // Soft Pink
        { id: 'bear', name: 'Cute Bear 🐻', color: '#d97706' }, // Warm Amber/Brown
        { id: 'milk', name: 'Fresh Milk 🥛', color: '#7dd3fc' }, // Soft Sky Blue
    ]

    // Actions
    function toggleDarkMode() {
        isDark.value = !isDark.value
        applyTheme()
    }

    function setTheme(name) {
        if (availableThemes.find(t => t.id === name)) {
            themeName.value = name
            applyTheme()
        }
    }

    function applyTheme() {
        const html = document.documentElement

        // Handle Dark Mode
        if (isDark.value) {
            html.classList.add('dark')
        } else {
            html.classList.remove('dark')
        }

        // Handle Color Theme
        html.setAttribute('data-theme', themeName.value)

        // Persist
        localStorage.setItem('theme_is_dark', isDark.value)
        localStorage.setItem('theme_name', themeName.value)
    }

    // Initialize
    applyTheme()

    return {
        isDark,
        themeName,
        availableThemes,
        toggleDarkMode,
        setTheme,
        applyTheme
    }
})

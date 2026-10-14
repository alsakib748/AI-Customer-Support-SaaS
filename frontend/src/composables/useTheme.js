import { ref, computed } from 'vue'

export const THEME_STORAGE_KEY = 'supportai-theme'

/**
 * swatch is only used by the theme picker. The real colours live in
 * src/styles/app.css under [data-theme="<id>"].
 */
export const THEMES = [
  { id: 'system', label: 'System', swatch: 'linear-gradient(135deg, #ffffff 50%, #0b1220 50%)' },
  { id: 'light', label: 'Light', swatch: '#ffffff' },
  { id: 'dark', label: 'Dark', swatch: '#0b1220' },
  { id: 'black', label: 'Black', swatch: '#000000' },
  { id: 'deep-gray', label: 'Deep gray', swatch: '#18181b' },
  { id: 'gray', label: 'Gray', swatch: '#3a3c42' },
  { id: 'slate', label: 'Slate', swatch: '#64748b' },
]

const VALID = new Set(THEMES.map((t) => t.id))

function readPreference() {
  try {
    const saved = localStorage.getItem(THEME_STORAGE_KEY)
    return saved && VALID.has(saved) ? saved : 'system'
  } catch {
    return 'system'
  }
}

const preference = ref(readPreference())
let media = null
let listening = false

function resolve(pref) {
  if (pref !== 'system') return pref
  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
}

function apply() {
  document.documentElement.setAttribute('data-theme', resolve(preference.value))
}

function listenForSystemChanges() {
  if (listening) return
  media = window.matchMedia('(prefers-color-scheme: dark)')
  media.addEventListener('change', () => {
    if (preference.value === 'system') apply()
  })
  listening = true
}

export function initTheme() {
  apply()
  listenForSystemChanges()
}

export function useTheme() {
  const resolved = computed(() => (preference.value === 'system' ? resolve('system') : preference.value))

  function setTheme(id) {
    if (!VALID.has(id)) return
    preference.value = id
    try {
      localStorage.setItem(THEME_STORAGE_KEY, id)
    } catch {
      /* storage can be blocked; the choice still applies for this visit */
    }
    apply()
  }

  return { preference, resolved, setTheme, themes: THEMES }
}

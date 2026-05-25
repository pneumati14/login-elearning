import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import { setLocale, type AppLocale } from '@/i18n'

export interface AuthUser {
  id: number
  email: string
  firstName: string
  lastName: string
  fullName: string
  roles: string[]
  isAdmin: boolean
  avatarUrl: string | null
  locale: string
  createdAt: string
}

/** Outcome of a write request: ok, or ok=false with a message to show. */
export interface MutationResult {
  ok: boolean
  error?: string
}

const API_URL = import.meta.env.VITE_API_URL || '/api'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser | null>(null)
  // True once the initial "who am I?" check against the API has finished.
  const ready = ref(false)
  const loading = ref(false)
  const error = ref<string | null>(null)

  const isAuthenticated = computed(() => user.value !== null)
  const isAdmin = computed(() => user.value?.isAdmin ?? false)

  /** Restore the session on app load by asking the API who is logged in. */
  async function fetchMe(): Promise<void> {
    try {
      const response = await fetch(`${API_URL}/me`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
      user.value = response.ok ? ((await response.json()) as AuthUser) : null
      applyUserLocale()
    } catch {
      user.value = null
    } finally {
      ready.value = true
    }
  }

  /** Applies the signed-in user's saved language to the UI. */
  function applyUserLocale(): void {
    const loc = user.value?.locale
    if (
      'hu' === loc ||
      'en' === loc ||
      'az' === loc ||
      'de' === loc ||
      'pt' === loc ||
      'tr' === loc ||
      'pl' === loc ||
      'es' === loc
    ) {
      setLocale(loc)
    }
  }

  /**
   * Switches the UI language and, for a signed-in user, saves the choice
   * to their account so it follows them across devices.
   */
  function setLanguage(locale: AppLocale): void {
    setLocale(locale)
    if (user.value) {
      user.value.locale = locale
      void fetch(`${API_URL}/me/locale`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ locale }),
      }).catch(() => {
        // The change is already applied locally; a failed save is non-fatal.
      })
    }
  }

  async function login(email: string, password: string): Promise<boolean> {
    loading.value = true
    error.value = null

    try {
      const response = await fetch(`${API_URL}/login`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ email, password }),
      })
      const data = (await response.json().catch(() => null)) as AuthUser | { error: string } | null

      if (!response.ok) {
        error.value = (data && 'error' in data && data.error) || 'A bejelentkezés nem sikerült.'
        return false
      }

      user.value = data as AuthUser
      applyUserLocale()
      ready.value = true
      return true
    } catch {
      error.value = 'Nem sikerült elérni a szervert.'
      return false
    } finally {
      loading.value = false
    }
  }

  /** Change the signed-in user's own password. */
  async function changePassword(
    currentPassword: string,
    newPassword: string,
  ): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/me/password`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ currentPassword, newPassword }),
      })

      if (!response.ok) {
        const data = (await response.json().catch(() => null)) as { error: string } | null
        return { ok: false, error: data?.error || 'A jelszó módosítása nem sikerült.' }
      }

      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  /** Upload a new profile picture; the response carries the fresh user. */
  async function uploadAvatar(file: File): Promise<MutationResult> {
    const formData = new FormData()
    formData.append('file', file)

    try {
      const response = await fetch(`${API_URL}/me/avatar`, {
        method: 'POST',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        body: formData,
      })
      const data = (await response.json().catch(() => null)) as AuthUser | { error?: string } | null

      if (!response.ok) {
        const message = data && 'error' in data && data.error ? data.error : 'A feltöltés nem sikerült.'
        return { ok: false, error: message }
      }

      if (data && 'id' in data) {
        user.value = data
      }
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  async function deleteAvatar(): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/me/avatar`, {
        method: 'DELETE',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
      const data = (await response.json().catch(() => null)) as AuthUser | { error?: string } | null

      if (!response.ok) {
        return { ok: false, error: 'A törlés nem sikerült.' }
      }

      if (data && 'id' in data) {
        user.value = data
      }
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  async function logout(): Promise<void> {
    try {
      await fetch(`${API_URL}/logout`, {
        method: 'POST',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
    } catch {
      // A failed network call should not trap the user in a logged-in state.
    }
    user.value = null
  }

  return {
    user,
    ready,
    loading,
    error,
    isAuthenticated,
    isAdmin,
    fetchMe,
    login,
    logout,
    setLanguage,
    changePassword,
    uploadAvatar,
    deleteAvatar,
  }
})

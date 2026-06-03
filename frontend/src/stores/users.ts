import { ref } from 'vue'
import { defineStore } from 'pinia'
import type { AuthUser, MutationResult } from './auth'

const API_URL = import.meta.env.VITE_API_URL || '/api'

export type UserRole = 'user' | 'sales' | 'sales_manager' | 'admin'

export interface NewUser {
  email: string
  firstName: string
  lastName: string
  password: string
  role: UserRole
}

/** Admin-only store backing the user management screen. */
export const useUsersStore = defineStore('users', () => {
  const users = ref<AuthUser[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetchUsers(): Promise<void> {
    loading.value = true
    error.value = null

    try {
      const response = await fetch(`${API_URL}/admin/users`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })

      if (!response.ok) {
        throw new Error(`A szerver ${response.status} hibakóddal válaszolt.`)
      }

      users.value = (await response.json()) as AuthUser[]
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Ismeretlen hiba történt.'
    } finally {
      loading.value = false
    }
  }

  async function createUser(payload: NewUser): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/users`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
      })
      const data = (await response.json().catch(() => null)) as
        | AuthUser
        | { error: string }
        | null

      if (!response.ok) {
        return {
          ok: false,
          error: (data && 'error' in data && data.error) || 'A felhasználó létrehozása nem sikerült.',
        }
      }

      users.value = [data as AuthUser, ...users.value]
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  async function deleteUser(id: number): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/users/${id}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })

      if (!response.ok) {
        const data = (await response.json().catch(() => null)) as { error: string } | null
        return { ok: false, error: data?.error || 'A felhasználó törlése nem sikerült.' }
      }

      users.value = users.value.filter((u) => u.id !== id)
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  /** Change a user's role. The response carries the refreshed user. */
  async function updateRole(id: number, role: UserRole): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/users/${id}/role`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ role }),
      })
      const data = (await response.json().catch(() => null)) as AuthUser | { error: string } | null

      if (!response.ok) {
        return {
          ok: false,
          error: (data && 'error' in data && data.error) || 'A szerepkör módosítása nem sikerült.',
        }
      }

      users.value = users.value.map((u) => (u.id === id ? (data as AuthUser) : u))
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  /** Admin override: set a new password for any user, no current one needed. */
  async function setPassword(id: number, newPassword: string): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/users/${id}/password`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ newPassword }),
      })

      if (!response.ok) {
        const data = (await response.json().catch(() => null)) as { error: string } | null
        return { ok: false, error: data?.error || 'A jelszó beállítása nem sikerült.' }
      }

      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  return { users, loading, error, fetchUsers, createUser, deleteUser, updateRole, setPassword }
})

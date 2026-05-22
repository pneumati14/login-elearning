import { ref } from 'vue'
import { defineStore } from 'pinia'
import type { MutationResult } from './auth'
import type { LocalizedText } from '@/composables/localized'

const API_URL = import.meta.env.VITE_API_URL || '/api'

export interface Position {
  id: number
  title: LocalizedText
  location: LocalizedText
  type: LocalizedText
  summary: LocalizedText
}

export interface PositionFields {
  title: LocalizedText
  location: LocalizedText
  type: LocalizedText
  summary: LocalizedText
}

export const usePositionsStore = defineStore('positions', () => {
  const positions = ref<Position[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetchPositions(): Promise<void> {
    loading.value = true
    error.value = null

    try {
      const response = await fetch(`${API_URL}/positions`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })

      if (!response.ok) {
        throw new Error(`A szerver ${response.status} hibakóddal válaszolt.`)
      }

      positions.value = (await response.json()) as Position[]
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Ismeretlen hiba történt.'
    } finally {
      loading.value = false
    }
  }

  async function createPosition(fields: PositionFields): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/positions`, {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(fields),
      })

      const data = (await response.json().catch(() => null)) as
        | Position
        | { error?: string }
        | null

      if (!response.ok) {
        const message = data && 'error' in data && data.error ? data.error : 'A létrehozás nem sikerült.'
        return { ok: false, error: message }
      }

      if (data && 'id' in data) {
        positions.value = [...positions.value, data]
      }
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  async function updatePosition(id: number, fields: PositionFields): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/positions/${id}`, {
        method: 'PUT',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(fields),
      })

      const data = (await response.json().catch(() => null)) as
        | Position
        | { error?: string }
        | null

      if (!response.ok) {
        const message = data && 'error' in data && data.error ? data.error : 'A mentés nem sikerült.'
        return { ok: false, error: message }
      }

      if (data && 'id' in data) {
        positions.value = positions.value.map((p) => (p.id === id ? data : p))
      }
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  async function deletePosition(id: number): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/positions/${id}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })

      if (!response.ok) {
        return { ok: false, error: 'A törlés nem sikerült.' }
      }

      positions.value = positions.value.filter((p) => p.id !== id)
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  return { positions, loading, error, fetchPositions, createPosition, updatePosition, deletePosition }
})

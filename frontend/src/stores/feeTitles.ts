import { ref } from 'vue'
import { defineStore } from 'pinia'
import type { MutationResult } from './auth'

const API_URL = import.meta.env.VITE_API_URL || '/api'

export interface FeeTitle {
  id: number
  name: string
  createdAt: string
  updatedAt: string
}

async function readError(response: Response, fallback: string): Promise<string> {
  const data = (await response.json().catch(() => null)) as { error?: string } | null
  return data && data.error ? data.error : fallback
}

export const useFeeTitlesStore = defineStore('feeTitles', () => {
  const feeTitles = ref<FeeTitle[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  function upsert(title: FeeTitle): void {
    const idx = feeTitles.value.findIndex((f) => f.id === title.id)
    if (-1 === idx) {
      feeTitles.value = [...feeTitles.value, title].sort((a, b) => a.name.localeCompare(b.name, 'hu'))
    } else {
      feeTitles.value = feeTitles.value.map((f) => (f.id === title.id ? title : f))
    }
  }

  async function fetchFeeTitles(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const response = await fetch(`${API_URL}/admin/fee-titles`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
      if (!response.ok) {
        throw new Error(`A szerver ${response.status} hibakóddal válaszolt.`)
      }
      feeTitles.value = (await response.json()) as FeeTitle[]
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Ismeretlen hiba történt.'
    } finally {
      loading.value = false
    }
  }

  async function createFeeTitle(name: string): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/fee-titles`, {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ name }),
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, 'A jogcím létrehozása nem sikerült.') }
      }
      upsert((await response.json()) as FeeTitle)
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  async function updateFeeTitle(id: number, name: string): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/fee-titles/${id}`, {
        method: 'PUT',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ name }),
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, 'A mentés nem sikerült.') }
      }
      upsert((await response.json()) as FeeTitle)
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  async function deleteFeeTitle(id: number): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/fee-titles/${id}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, 'A törlés nem sikerült.') }
      }
      feeTitles.value = feeTitles.value.filter((f) => f.id !== id)
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  return { feeTitles, loading, error, fetchFeeTitles, createFeeTitle, updateFeeTitle, deleteFeeTitle }
})

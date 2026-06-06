import { ref } from 'vue'
import { defineStore } from 'pinia'
import type { MutationResult } from './auth'

const API_URL = import.meta.env.VITE_API_URL || '/api'

export const INTEGRATION_CATEGORIES = ['payroll', 'erp', 'access_control', 'other'] as const
export type IntegrationCategory = (typeof INTEGRATION_CATEGORIES)[number]

export interface Integration {
  id: number
  name: string
  category: IntegrationCategory
  isActive: boolean
  createdAt: string
  updatedAt: string
}

/** Fields accepted when creating or editing an integration. */
export interface IntegrationFields {
  name: string
  category: IntegrationCategory
  isActive: boolean
}

async function readError(response: Response, fallback: string): Promise<string> {
  const data = (await response.json().catch(() => null)) as { error?: string } | null
  return data && data.error ? data.error : fallback
}

export const useIntegrationsStore = defineStore('integrations', () => {
  const integrations = ref<Integration[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  function upsert(integration: Integration): void {
    const idx = integrations.value.findIndex((i) => i.id === integration.id)
    if (-1 === idx) {
      integrations.value = [...integrations.value, integration].sort(
        (a, b) => a.category.localeCompare(b.category) || a.name.localeCompare(b.name, 'hu'),
      )
    } else {
      integrations.value = integrations.value.map((i) => (i.id === integration.id ? integration : i))
    }
  }

  async function fetchIntegrations(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const response = await fetch(`${API_URL}/admin/integrations`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
      if (!response.ok) {
        throw new Error(`A szerver ${response.status} hibakóddal válaszolt.`)
      }
      integrations.value = (await response.json()) as Integration[]
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Ismeretlen hiba történt.'
    } finally {
      loading.value = false
    }
  }

  async function createIntegration(fields: IntegrationFields): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/integrations`, {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(fields),
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, 'Az integráció létrehozása nem sikerült.') }
      }
      upsert((await response.json()) as Integration)
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  async function updateIntegration(id: number, fields: IntegrationFields): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/integrations/${id}`, {
        method: 'PUT',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(fields),
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, 'A mentés nem sikerült.') }
      }
      upsert((await response.json()) as Integration)
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  async function deleteIntegration(id: number): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/integrations/${id}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, 'A törlés nem sikerült.') }
      }
      integrations.value = integrations.value.filter((i) => i.id !== id)
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  return { integrations, loading, error, fetchIntegrations, createIntegration, updateIntegration, deleteIntegration }
})

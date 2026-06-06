import { ref } from 'vue'
import { defineStore } from 'pinia'
import type { MutationResult } from './auth'

const API_URL = import.meta.env.VITE_API_URL || '/api'

export const ARCHITECTURE_FILE_KINDS = ['diagram', 'plan', 'sdd', 'other'] as const
export type ArchitectureFileKind = (typeof ARCHITECTURE_FILE_KINDS)[number]

export const DEPLOYMENT_MODELS = ['onprem', 'saas'] as const
export type DeploymentModel = (typeof DEPLOYMENT_MODELS)[number]

export interface ArchitectureFile {
  id: number
  kind: ArchitectureFileKind
  originalName: string
  mimeType: string
  createdAt: string
  url: string
}

/** The customer's architecture sheet as the API returns it. */
export interface CustomerArchitecture {
  deploymentModel: DeploymentModel | null
  saasServer: string | null
  vpnInfo: string | null
  usersInfo: string | null
  notes: string | null
  integrationIds: number[]
  updatedAt: string | null
  files: ArchitectureFile[]
}

/** Editable fields sent on save (files are uploaded separately). */
export interface CustomerArchitectureFields {
  deploymentModel: DeploymentModel | null
  saasServer: string | null
  vpnInfo: string | null
  usersInfo: string | null
  notes: string | null
  integrationIds: number[]
}

async function readError(response: Response, fallback: string): Promise<string> {
  const data = (await response.json().catch(() => null)) as { error?: string } | null
  return data && data.error ? data.error : fallback
}

/**
 * Architecture sheets are a sub-resource of a customer, so the store
 * keys them by customer id (like the opportunities store).
 */
export const useCustomerArchitectureStore = defineStore('customerArchitecture', () => {
  const byCustomer = ref<Record<number, CustomerArchitecture>>({})
  const loading = ref(false)
  const error = ref<string | null>(null)

  function get(customerId: number): CustomerArchitecture | null {
    return byCustomer.value[customerId] ?? null
  }

  function set(customerId: number, architecture: CustomerArchitecture): void {
    byCustomer.value = { ...byCustomer.value, [customerId]: architecture }
  }

  async function fetchArchitecture(customerId: number): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const response = await fetch(`${API_URL}/admin/customers/${customerId}/architecture`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
      if (!response.ok) {
        throw new Error(`A szerver ${response.status} hibakóddal válaszolt.`)
      }
      set(customerId, (await response.json()) as CustomerArchitecture)
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Ismeretlen hiba történt.'
    } finally {
      loading.value = false
    }
  }

  async function saveArchitecture(customerId: number, fields: CustomerArchitectureFields): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/customers/${customerId}/architecture`, {
        method: 'PUT',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(fields),
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, 'A mentés nem sikerült.') }
      }
      set(customerId, (await response.json()) as CustomerArchitecture)
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  async function uploadFile(customerId: number, kind: ArchitectureFileKind, file: File): Promise<MutationResult> {
    try {
      const body = new FormData()
      body.append('file', file)
      body.append('kind', kind)
      const response = await fetch(`${API_URL}/admin/customers/${customerId}/architecture/files`, {
        method: 'POST',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        body,
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, 'A fájl feltöltése nem sikerült.') }
      }
      set(customerId, (await response.json()) as CustomerArchitecture)
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  async function deleteFile(customerId: number, fileId: number): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/customers/${customerId}/architecture/files/${fileId}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, 'A fájl törlése nem sikerült.') }
      }
      set(customerId, (await response.json()) as CustomerArchitecture)
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  return { byCustomer, loading, error, get, fetchArchitecture, saveArchitecture, uploadFile, deleteFile }
})

import { ref } from 'vue'
import { defineStore } from 'pinia'
import type { MutationResult } from './auth'

const API_URL = import.meta.env.VITE_API_URL || '/api'

export interface Supplier {
  id: number
  name: string
  contactName: string | null
  email: string | null
  phone: string | null
  notes: string | null
  isActive: boolean
  createdAt: string
  updatedAt: string
}

export interface SupplierFields {
  name: string
  contactName: string | null
  email: string | null
  phone: string | null
  notes: string | null
  isActive: boolean
}

export function emptySupplierFields(): SupplierFields {
  return {
    name: '',
    contactName: null,
    email: null,
    phone: null,
    notes: null,
    isActive: true,
  }
}

async function readError(response: Response, fallback: string): Promise<string> {
  const data = (await response.json().catch(() => null)) as { error?: string } | null
  return data && data.error ? data.error : fallback
}

export const useSuppliersStore = defineStore('suppliers', () => {
  const suppliers = ref<Supplier[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  function upsert(supplier: Supplier): void {
    const idx = suppliers.value.findIndex((s) => s.id === supplier.id)
    if (-1 === idx) {
      suppliers.value = [...suppliers.value, supplier].sort((a, b) => a.name.localeCompare(b.name, 'hu'))
    } else {
      suppliers.value = suppliers.value.map((s) => (s.id === supplier.id ? supplier : s))
    }
  }

  async function fetchSuppliers(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const response = await fetch(`${API_URL}/admin/suppliers`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
      if (!response.ok) {
        throw new Error(`A szerver ${response.status} hibakóddal válaszolt.`)
      }
      suppliers.value = (await response.json()) as Supplier[]
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Ismeretlen hiba történt.'
    } finally {
      loading.value = false
    }
  }

  async function createSupplier(fields: SupplierFields): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/suppliers`, {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(fields),
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, 'A beszállító létrehozása nem sikerült.') }
      }
      upsert((await response.json()) as Supplier)
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  async function updateSupplier(id: number, fields: SupplierFields): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/suppliers/${id}`, {
        method: 'PUT',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(fields),
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, 'A mentés nem sikerült.') }
      }
      upsert((await response.json()) as Supplier)
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  async function deleteSupplier(id: number): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/suppliers/${id}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, 'A törlés nem sikerült.') }
      }
      suppliers.value = suppliers.value.filter((s) => s.id !== id)
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  return { suppliers, loading, error, fetchSuppliers, createSupplier, updateSupplier, deleteSupplier }
})

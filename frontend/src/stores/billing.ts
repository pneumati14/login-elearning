import { ref } from 'vue'
import { defineStore } from 'pinia'
import type { MutationResult } from './auth'

const API_URL = import.meta.env.VITE_API_URL || '/api'

export type BillingStatus = 'pending' | 'invoiced'

/** One itemised row to invoice, snapshotted from a won deal or added by hand. */
export interface BillingItem {
  id: number
  customerId: number
  customerName: string
  opportunityId: number | null
  opportunityTitle: string | null
  quoteNumber: string | null
  cardName: string | null
  name: string
  quantity: string
  unitPrice: string
  lineTotal: string
  currency: string
  status: BillingStatus
  wonAt: string | null
  invoicedAt: string | null
  /** Per-offer invoicing aggregate from the opportunity's quote lines. */
  offerTotalValue: string
  offerInvoicedValue: string
  offerLineCount: number
  offerInvoicedCount: number
}

export interface BillingItemFields {
  name: string
  quantity: string
  unitPrice: string
  currency: string
}

async function readError(response: Response, fallback: string): Promise<string> {
  const data = (await response.json().catch(() => null)) as { error?: string } | null
  return data && data.error ? data.error : fallback
}

export const useBillingStore = defineStore('billing', () => {
  const items = ref<BillingItem[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetchItems(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const response = await fetch(`${API_URL}/admin/billing`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
      if (!response.ok) {
        throw new Error(`A szerver ${response.status} hibakóddal válaszolt.`)
      }
      items.value = (await response.json()) as BillingItem[]
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Ismeretlen hiba történt.'
    } finally {
      loading.value = false
    }
  }

  function upsertItem(item: BillingItem): void {
    const idx = items.value.findIndex((i) => i.id === item.id)
    if (-1 === idx) {
      items.value = [item, ...items.value]
    } else {
      items.value = items.value.map((i) => (i.id === item.id ? item : i))
    }
  }

  async function mutate(
    path: string,
    method: 'POST' | 'PUT' | 'DELETE',
    body: unknown,
    fallback: string,
    removeId?: number,
  ): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/billing${path}`, {
        method,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: undefined === body ? undefined : JSON.stringify(body),
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, fallback) }
      }
      if (undefined !== removeId) {
        items.value = items.value.filter((i) => i.id !== removeId)
      } else {
        upsertItem((await response.json()) as BillingItem)
      }
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  function createItem(customerId: number, fields: BillingItemFields): Promise<MutationResult> {
    return mutate('', 'POST', { customerId, ...fields }, 'A tétel létrehozása nem sikerült.')
  }

  function updateItem(id: number, fields: BillingItemFields): Promise<MutationResult> {
    return mutate(`/${id}`, 'PUT', fields, 'A mentés nem sikerült.')
  }

  function setStatus(id: number, status: BillingStatus): Promise<MutationResult> {
    return mutate(`/${id}/status`, 'PUT', { status }, 'A státusz módosítása nem sikerült.')
  }

  function deleteItem(id: number): Promise<MutationResult> {
    return mutate(`/${id}`, 'DELETE', undefined, 'A törlés nem sikerült.', id)
  }

  return {
    items,
    loading,
    error,
    fetchItems,
    createItem,
    updateItem,
    setStatus,
    deleteItem,
  }
})

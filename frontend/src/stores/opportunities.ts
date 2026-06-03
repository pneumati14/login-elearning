import { ref } from 'vue'
import { defineStore } from 'pinia'
import type { MutationResult } from './auth'
import type { StageOutcome } from './opportunityTypes'

const API_URL = import.meta.env.VITE_API_URL || '/api'

export const CURRENCIES = ['HUF', 'EUR', 'USD'] as const
export type Currency = (typeof CURRENCIES)[number]

export interface StageChange {
  id: number
  fromStageName: string | null
  toStageName: string
  changedByName: string | null
  changedAt: string
}

export interface LineItem {
  id: number
  productId: number | null
  productName: string
  quantity: string
  unitPrice: string
  lineTotal: string
}

/** A line item as edited in the form (no id/total — computed on save). */
export interface LineItemFields {
  productId: number | null
  productName: string
  quantity: string
  unitPrice: string
}

export interface OpportunityDocument {
  id: number
  originalName: string
  size: number | null
  uploadedAt: string
  uploadedByName: string | null
  url: string
}

export interface Opportunity {
  id: number
  title: string
  quoteNumber: string | null
  value: string | null
  currency: Currency
  expectedCloseDate: string | null
  closedAt: string | null
  notes: string | null
  typeId: number
  typeName: string
  stageId: number
  stageName: string
  stageOutcome: StageOutcome
  contactId: number | null
  contactName: string | null
  ownerId: number | null
  ownerName: string | null
  hasLineItems: boolean
  lineItemsTotal: string
  lineItems: LineItem[]
  documents: OpportunityDocument[]
  stageChanges: StageChange[]
  createdAt: string
  updatedAt: string
}

/** Fields accepted when creating or editing an opportunity. */
export interface OpportunityFields {
  title: string
  quoteNumber: string | null
  typeId: number | null
  stageId: number | null
  value: string | null
  currency: Currency
  expectedCloseDate: string | null
  contactId: number | null
  notes: string | null
  lineItems: LineItemFields[]
}

export function emptyOpportunityFields(): OpportunityFields {
  return {
    title: '',
    quoteNumber: null,
    typeId: null,
    stageId: null,
    value: null,
    currency: 'HUF',
    expectedCloseDate: null,
    contactId: null,
    notes: null,
    lineItems: [],
  }
}

async function readError(response: Response, fallback: string): Promise<string> {
  const data = (await response.json().catch(() => null)) as { error?: string } | null
  return data && data.error ? data.error : fallback
}

/**
 * Opportunities are a sub-resource of a customer, so the store keys them
 * by customer id. A detail page works with one customer at a time, but
 * keying by id keeps the cache correct if the user navigates between
 * customers without a full reload.
 */
export const useOpportunitiesStore = defineStore('opportunities', () => {
  const byCustomer = ref<Record<number, Opportunity[]>>({})
  const loading = ref(false)
  const error = ref<string | null>(null)

  function list(customerId: number): Opportunity[] {
    return byCustomer.value[customerId] ?? []
  }

  function setList(customerId: number, items: Opportunity[]): void {
    byCustomer.value = { ...byCustomer.value, [customerId]: items }
  }

  function upsert(customerId: number, opp: Opportunity): void {
    const current = list(customerId)
    const idx = current.findIndex((o) => o.id === opp.id)
    const next = -1 === idx ? [opp, ...current] : current.map((o) => (o.id === opp.id ? opp : o))
    setList(customerId, next)
  }

  async function fetchOpportunities(customerId: number): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const response = await fetch(`${API_URL}/admin/customers/${customerId}/opportunities`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
      if (!response.ok) {
        throw new Error(`A szerver ${response.status} hibakóddal válaszolt.`)
      }
      setList(customerId, (await response.json()) as Opportunity[])
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Ismeretlen hiba történt.'
    } finally {
      loading.value = false
    }
  }

  async function createOpportunity(customerId: number, fields: OpportunityFields): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/customers/${customerId}/opportunities`, {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(fields),
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, 'A lehetőség mentése nem sikerült.') }
      }
      upsert(customerId, (await response.json()) as Opportunity)
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  async function updateOpportunity(
    customerId: number,
    id: number,
    fields: OpportunityFields,
  ): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/customers/${customerId}/opportunities/${id}`, {
        method: 'PUT',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(fields),
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, 'A mentés nem sikerült.') }
      }
      upsert(customerId, (await response.json()) as Opportunity)
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  /** Move an opportunity to another stage of its pipeline (kanban drag). */
  async function moveStage(customerId: number, id: number, stageId: number): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/customers/${customerId}/opportunities/${id}/stage`, {
        method: 'PUT',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ stageId }),
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, 'A fázis módosítása nem sikerült.') }
      }
      upsert(customerId, (await response.json()) as Opportunity)
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  async function deleteOpportunity(customerId: number, id: number): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/customers/${customerId}/opportunities/${id}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, 'A törlés nem sikerült.') }
      }
      setList(
        customerId,
        list(customerId).filter((o) => o.id !== id),
      )
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  /** Upload an offer PDF to an existing opportunity. */
  async function uploadDocument(customerId: number, id: number, file: File): Promise<MutationResult> {
    try {
      const body = new FormData()
      body.append('file', file)
      const response = await fetch(`${API_URL}/admin/customers/${customerId}/opportunities/${id}/documents`, {
        method: 'POST',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        body,
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, 'A dokumentum feltöltése nem sikerült.') }
      }
      upsert(customerId, (await response.json()) as Opportunity)
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  async function deleteDocument(customerId: number, id: number, docId: number): Promise<MutationResult> {
    try {
      const response = await fetch(
        `${API_URL}/admin/customers/${customerId}/opportunities/${id}/documents/${docId}`,
        { method: 'DELETE', headers: { Accept: 'application/json' }, credentials: 'same-origin' },
      )
      if (!response.ok) {
        return { ok: false, error: await readError(response, 'A dokumentum törlése nem sikerült.') }
      }
      upsert(customerId, (await response.json()) as Opportunity)
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  return {
    byCustomer,
    loading,
    error,
    list,
    fetchOpportunities,
    createOpportunity,
    updateOpportunity,
    moveStage,
    deleteOpportunity,
    uploadDocument,
    deleteDocument,
  }
})

/** Human-readable file size, e.g. "1.2 MB". */
export function formatFileSize(bytes: number | null): string {
  if (null === bytes || bytes <= 0) return ''
  const units = ['B', 'KB', 'MB', 'GB']
  let n = bytes
  let i = 0
  while (n >= 1024 && i < units.length - 1) {
    n /= 1024
    i++
  }
  return `${n.toFixed(i === 0 ? 0 : 1)} ${units[i]}`
}

/** Format a value+currency pair for display, or '—' when no value is set. */
export function formatMoney(value: string | null, currency: Currency): string {
  if (null === value || '' === value) return '—'
  const n = Number(value)
  if (Number.isNaN(n)) return `${value} ${currency}`
  return `${n.toLocaleString('hu-HU')} ${currency}`
}

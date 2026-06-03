import { ref } from 'vue'
import { defineStore } from 'pinia'
import type { MutationResult } from './auth'

const API_URL = import.meta.env.VITE_API_URL || '/api'

export type StageOutcome = 'open' | 'won' | 'lost'

export interface OpportunityStage {
  id: number
  name: string
  position: number
  outcome: StageOutcome
}

export interface OpportunityType {
  id: number
  name: string
  position: number
  isActive: boolean
  validFrom: string | null
  validUntil: string | null
  stages: OpportunityStage[]
  createdAt: string
  updatedAt: string
}

/** Fields accepted when creating or editing a type. */
export interface TypeFields {
  name: string
  isActive?: boolean
  validFrom?: string | null
  validUntil?: string | null
}

export type TypeStatus = 'active' | 'inactive' | 'scheduled' | 'expired'

/** Today as a local YYYY-MM-DD string — safe to compare with ISO dates. */
export function todayISO(): string {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

/**
 * Effective status: the manual isActive flag combined with the validity
 * window. `today` defaults to the current local date.
 */
export function typeStatus(tp: OpportunityType, today: string = todayISO()): TypeStatus {
  if (!tp.isActive) return 'inactive'
  if (tp.validUntil && today > tp.validUntil) return 'expired'
  if (tp.validFrom && today < tp.validFrom) return 'scheduled'
  return 'active'
}

async function readError(response: Response, fallback: string): Promise<string> {
  const data = (await response.json().catch(() => null)) as { error?: string } | null
  return data && data.error ? data.error : fallback
}

export const useOpportunityTypesStore = defineStore('opportunityTypes', () => {
  const types = ref<OpportunityType[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  function upsert(type: OpportunityType): void {
    const idx = types.value.findIndex((t) => t.id === type.id)
    if (-1 === idx) {
      types.value = [...types.value, type]
    } else {
      types.value = types.value.map((t) => (t.id === type.id ? type : t))
    }
  }

  async function fetchTypes(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const response = await fetch(`${API_URL}/admin/opportunity-types`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
      if (!response.ok) {
        throw new Error(`A szerver ${response.status} hibakóddal válaszolt.`)
      }
      types.value = (await response.json()) as OpportunityType[]
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Ismeretlen hiba történt.'
    } finally {
      loading.value = false
    }
  }

  /**
   * Ensure a single type is available and return it. The list endpoint
   * already carries every type with its stages, so we just load the list
   * once and pick the one we need — no dedicated GET-by-id endpoint.
   */
  async function fetchType(id: number): Promise<OpportunityType | null> {
    if (!types.value.some((t) => t.id === id)) {
      await fetchTypes()
    }
    return types.value.find((t) => t.id === id) ?? null
  }

  // ── Types ─────────────────────────────────────────────────────────
  async function createType(fields: TypeFields): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/opportunity-types`, {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(fields),
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, 'A típus létrehozása nem sikerült.') }
      }
      upsert((await response.json()) as OpportunityType)
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  async function updateType(id: number, fields: TypeFields): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/opportunity-types/${id}`, {
        method: 'PUT',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(fields),
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, 'A mentés nem sikerült.') }
      }
      upsert((await response.json()) as OpportunityType)
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  async function deleteType(id: number): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/opportunity-types/${id}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, 'A törlés nem sikerült.') }
      }
      types.value = types.value.filter((t) => t.id !== id)
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  async function reorderTypes(order: number[]): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/opportunity-types/reorder`, {
        method: 'PUT',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ order }),
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, 'Az átrendezés nem sikerült.') }
      }
      types.value = (await response.json()) as OpportunityType[]
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  // ── Stages (mutations return the parent type) ─────────────────────
  async function mutateStage(
    typeId: number,
    path: string,
    method: 'POST' | 'PUT' | 'DELETE',
    body?: unknown,
    fallback = 'A művelet nem sikerült.',
  ): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/opportunity-types/${typeId}/stages${path}`, {
        method,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: undefined === body ? undefined : JSON.stringify(body),
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, fallback) }
      }
      upsert((await response.json()) as OpportunityType)
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  function createStage(typeId: number, fields: { name: string; outcome: StageOutcome }): Promise<MutationResult> {
    return mutateStage(typeId, '', 'POST', fields, 'A fázis létrehozása nem sikerült.')
  }

  function updateStage(
    typeId: number,
    stageId: number,
    fields: { name: string; outcome: StageOutcome },
  ): Promise<MutationResult> {
    return mutateStage(typeId, `/${stageId}`, 'PUT', fields, 'A mentés nem sikerült.')
  }

  function deleteStage(typeId: number, stageId: number): Promise<MutationResult> {
    return mutateStage(typeId, `/${stageId}`, 'DELETE', undefined, 'A törlés nem sikerült.')
  }

  function reorderStages(typeId: number, order: number[]): Promise<MutationResult> {
    return mutateStage(typeId, '/reorder', 'PUT', { order }, 'Az átrendezés nem sikerült.')
  }

  return {
    types,
    loading,
    error,
    fetchTypes,
    fetchType,
    createType,
    updateType,
    deleteType,
    reorderTypes,
    createStage,
    updateStage,
    deleteStage,
    reorderStages,
  }
})

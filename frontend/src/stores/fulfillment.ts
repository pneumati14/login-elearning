import { ref } from 'vue'
import { defineStore } from 'pinia'
import type { MutationResult } from './auth'

const API_URL = import.meta.env.VITE_API_URL || '/api'

export interface FulfillmentStage {
  id: number
  name: string
  position: number
  /** Terminal stage: the delivery is completed here. */
  isDone: boolean
}

export interface FulfillmentType {
  id: number
  name: string
  position: number
  stages: FulfillmentStage[]
}

/** One won deal on the fulfillment board. */
export interface FulfillmentItem {
  id: number
  title: string
  customerId: number
  customerName: string
  value: string | null
  currency: string
  closedAt: string | null
  ownerName: string | null
  fulfillmentTypeId: number | null
  fulfillmentStageId: number | null
}

async function readError(response: Response, fallback: string): Promise<string> {
  const data = (await response.json().catch(() => null)) as { error?: string } | null
  return data && data.error ? data.error : fallback
}

export const useFulfillmentStore = defineStore('fulfillment', () => {
  const types = ref<FulfillmentType[]>([])
  const items = ref<FulfillmentItem[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetchTypes(): Promise<void> {
    try {
      const response = await fetch(`${API_URL}/admin/fulfillment-types`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
      if (response.ok) {
        types.value = (await response.json()) as FulfillmentType[]
      }
    } catch {
      // The board shows its own error state via fetchItems.
    }
  }

  async function fetchItems(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const response = await fetch(`${API_URL}/admin/fulfillment`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
      if (!response.ok) {
        throw new Error(`A szerver ${response.status} hibakóddal válaszolt.`)
      }
      items.value = (await response.json()) as FulfillmentItem[]
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Ismeretlen hiba történt.'
    } finally {
      loading.value = false
    }
  }

  function upsertItem(item: FulfillmentItem): void {
    items.value = items.value.map((i) => (i.id === item.id ? item : i))
  }

  async function mutateItem(path: string, body: unknown, fallback: string): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/fulfillment${path}`, {
        method: 'PUT',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(body),
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, fallback) }
      }
      upsertItem((await response.json()) as FulfillmentItem)
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  function assignType(itemId: number, typeId: number | null): Promise<MutationResult> {
    return mutateItem(`/${itemId}/assign`, { typeId }, 'A besorolás nem sikerült.')
  }

  function moveStage(itemId: number, stageId: number): Promise<MutationResult> {
    return mutateItem(`/${itemId}/stage`, { stageId }, 'Az áthelyezés nem sikerült.')
  }

  // ── Type / stage configuration (admin) ─────────────────────────────
  function upsertType(type: FulfillmentType): void {
    const idx = types.value.findIndex((t) => t.id === type.id)
    if (-1 === idx) {
      types.value = [...types.value, type]
    } else {
      types.value = types.value.map((t) => (t.id === type.id ? type : t))
    }
  }

  async function mutateConfig(
    path: string,
    method: 'POST' | 'PUT' | 'DELETE',
    body: unknown,
    fallback: string,
    removeTypeId?: number,
  ): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/fulfillment-types${path}`, {
        method,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: undefined === body ? undefined : JSON.stringify(body),
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, fallback) }
      }
      if (undefined !== removeTypeId) {
        types.value = types.value.filter((t) => t.id !== removeTypeId)
      } else {
        upsertType((await response.json()) as FulfillmentType)
      }
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  function createType(name: string): Promise<MutationResult> {
    return mutateConfig('', 'POST', { name }, 'A kategória létrehozása nem sikerült.')
  }

  function updateType(id: number, name: string): Promise<MutationResult> {
    return mutateConfig(`/${id}`, 'PUT', { name }, 'A mentés nem sikerült.')
  }

  function deleteType(id: number): Promise<MutationResult> {
    return mutateConfig(`/${id}`, 'DELETE', undefined, 'A törlés nem sikerült.', id)
  }

  function createStage(typeId: number, fields: { name: string; isDone: boolean }): Promise<MutationResult> {
    return mutateConfig(`/${typeId}/stages`, 'POST', fields, 'A stage létrehozása nem sikerült.')
  }

  function updateStage(typeId: number, stageId: number, fields: { name: string; isDone: boolean }): Promise<MutationResult> {
    return mutateConfig(`/${typeId}/stages/${stageId}`, 'PUT', fields, 'A mentés nem sikerült.')
  }

  function deleteStage(typeId: number, stageId: number): Promise<MutationResult> {
    return mutateConfig(`/${typeId}/stages/${stageId}`, 'DELETE', undefined, 'A törlés nem sikerült.')
  }

  function reorderStages(typeId: number, order: number[]): Promise<MutationResult> {
    return mutateConfig(`/${typeId}/stages/reorder`, 'PUT', { order }, 'Az átrendezés nem sikerült.')
  }

  return {
    types,
    items,
    loading,
    error,
    fetchTypes,
    fetchItems,
    assignType,
    moveStage,
    createType,
    updateType,
    deleteType,
    createStage,
    updateStage,
    deleteStage,
    reorderStages,
  }
})

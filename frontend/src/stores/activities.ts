import { ref } from 'vue'
import { defineStore } from 'pinia'
import type { MutationResult } from './auth'

const API_URL = import.meta.env.VITE_API_URL || '/api'

export const ACTIVITY_TYPES = ['call', 'meeting', 'email', 'note', 'task'] as const
export type ActivityType = (typeof ACTIVITY_TYPES)[number]

export interface Activity {
  id: number
  type: ActivityType
  subject: string
  body: string | null
  occurredAt: string
  completedAt: string | null
  isOpen: boolean
  isOpenTask: boolean
  contactId: number | null
  contactName: string | null
  opportunityId: number | null
  opportunityTitle: string | null
  assigneeId: number | null
  assigneeName: string | null
  createdByName: string | null
  createdAt: string
  updatedAt: string
}

/** Fields accepted when creating or editing an activity. */
export interface ActivityFields {
  type: ActivityType
  subject: string
  body: string | null
  occurredAt: string
  contactId: number | null
  opportunityId: number | null
  assigneeId: number | null
}

export function emptyActivityFields(): ActivityFields {
  return {
    type: 'note',
    subject: '',
    body: null,
    occurredAt: '',
    contactId: null,
    opportunityId: null,
    assigneeId: null,
  }
}

/** Fields for creating a task straight from the dashboard. */
export interface NewTask {
  subject: string
  body: string | null
  occurredAt: string
  assigneeId: number | null
  customerId: number | null
}

export function emptyNewTask(): NewTask {
  return { subject: '', body: null, occurredAt: '', assigneeId: null, customerId: null }
}

/** An activity as returned by the cross-customer dashboard feed (any type). */
export interface DashboardTask {
  id: number
  type: ActivityType
  subject: string
  body: string | null
  occurredAt: string
  completedAt: string | null
  isOpen: boolean
  isOpenTask: boolean
  customerId: number | null
  customerName: string | null
  opportunityId: number | null
  opportunityTitle: string | null
  contactName: string | null
  createdByName: string | null
  assigneeId: number | null
  assigneeName: string | null
}

export type TaskScope = 'mine' | 'all'
export type Urgency = 'overdue' | 'today' | 'week' | 'later'

/** Urgency bucket of an open task from its due date (occurredAt). */
export function taskUrgency(iso: string, now: Date = new Date()): Urgency {
  const due = new Date(iso)
  const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime()
  const endOfToday = startOfToday + 24 * 60 * 60 * 1000
  const endOfWeek = startOfToday + 7 * 24 * 60 * 60 * 1000
  const t = due.getTime()
  if (t < startOfToday) return 'overdue'
  if (t < endOfToday) return 'today'
  if (t < endOfWeek) return 'week'
  return 'later'
}

async function readError(response: Response, fallback: string): Promise<string> {
  const data = (await response.json().catch(() => null)) as { error?: string } | null
  return data && data.error ? data.error : fallback
}

/**
 * Activities are a sub-resource of a customer, keyed by customer id —
 * same shape as the opportunities store.
 */
export const useActivitiesStore = defineStore('activities', () => {
  const byCustomer = ref<Record<number, Activity[]>>({})
  const loading = ref(false)
  const error = ref<string | null>(null)

  // ── Cross-customer task dashboard ─────────────────────────────────
  const dashboardTasks = ref<DashboardTask[]>([])
  const dashboardLoading = ref(false)
  const dashboardError = ref<string | null>(null)

  async function fetchTasks(scope: TaskScope = 'mine'): Promise<void> {
    dashboardLoading.value = true
    dashboardError.value = null
    try {
      const response = await fetch(`${API_URL}/admin/tasks?scope=${scope}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
      if (!response.ok) {
        throw new Error(`A szerver ${response.status} hibakóddal válaszolt.`)
      }
      dashboardTasks.value = (await response.json()) as DashboardTask[]
    } catch (e) {
      dashboardError.value = e instanceof Error ? e.message : 'Ismeretlen hiba történt.'
    } finally {
      dashboardLoading.value = false
    }
  }

  /** Create a task from the dashboard (optional customer + assignee). */
  async function createTask(payload: NewTask): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/tasks`, {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, 'A feladat létrehozása nem sikerült.') }
      }
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  /** Toggle a dashboard item done/open via the dashboard-wide endpoint
   *  (works for customer-less tasks too). */
  async function toggleTaskDone(task: DashboardTask): Promise<MutationResult> {
    const done = null === task.completedAt
    try {
      const response = await fetch(`${API_URL}/admin/tasks/${task.id}/done`, {
        method: 'PUT',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ done }),
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, 'A művelet nem sikerült.') }
      }
      const updated = (await response.json()) as { completedAt: string | null; isOpen: boolean; isOpenTask: boolean }
      dashboardTasks.value = dashboardTasks.value.map((t) =>
        t.id === task.id
          ? { ...t, completedAt: updated.completedAt, isOpen: updated.isOpen, isOpenTask: updated.isOpenTask }
          : t,
      )
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  function list(customerId: number): Activity[] {
    return byCustomer.value[customerId] ?? []
  }

  function setList(customerId: number, items: Activity[]): void {
    byCustomer.value = { ...byCustomer.value, [customerId]: items }
  }

  /** Keep the per-customer list sorted by occurredAt DESC after a change. */
  function upsert(customerId: number, activity: Activity): void {
    const current = list(customerId)
    const idx = current.findIndex((a) => a.id === activity.id)
    const next = -1 === idx ? [...current, activity] : current.map((a) => (a.id === activity.id ? activity : a))
    next.sort((a, b) => (a.occurredAt < b.occurredAt ? 1 : a.occurredAt > b.occurredAt ? -1 : b.id - a.id))
    setList(customerId, next)
  }

  async function fetchActivities(customerId: number): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const response = await fetch(`${API_URL}/admin/customers/${customerId}/activities`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
      if (!response.ok) {
        throw new Error(`A szerver ${response.status} hibakóddal válaszolt.`)
      }
      setList(customerId, (await response.json()) as Activity[])
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Ismeretlen hiba történt.'
    } finally {
      loading.value = false
    }
  }

  async function createActivity(customerId: number, fields: ActivityFields): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/customers/${customerId}/activities`, {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(fields),
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, 'A tevékenység mentése nem sikerült.') }
      }
      upsert(customerId, (await response.json()) as Activity)
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  async function updateActivity(customerId: number, id: number, fields: ActivityFields): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/customers/${customerId}/activities/${id}`, {
        method: 'PUT',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(fields),
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, 'A mentés nem sikerült.') }
      }
      upsert(customerId, (await response.json()) as Activity)
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  async function setDone(customerId: number, id: number, done: boolean): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/customers/${customerId}/activities/${id}/done`, {
        method: 'PUT',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ done }),
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, 'A művelet nem sikerült.') }
      }
      upsert(customerId, (await response.json()) as Activity)
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  async function deleteActivity(customerId: number, id: number): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/customers/${customerId}/activities/${id}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, 'A törlés nem sikerült.') }
      }
      setList(
        customerId,
        list(customerId).filter((a) => a.id !== id),
      )
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
    fetchActivities,
    createActivity,
    updateActivity,
    setDone,
    deleteActivity,
    dashboardTasks,
    dashboardLoading,
    dashboardError,
    fetchTasks,
    createTask,
    toggleTaskDone,
  }
})

/** Local datetime as a "YYYY-MM-DDTHH:MM" string for datetime-local inputs. */
export function nowLocalInput(): string {
  const d = new Date()
  const pad = (n: number): string => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

/** Convert an ISO timestamp to the "YYYY-MM-DDTHH:MM" datetime-local value. */
export function isoToLocalInput(iso: string): string {
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return ''
  const pad = (n: number): string => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

/** Human-friendly date+time, e.g. "2026-06-03 14:30". */
export function formatDateTime(iso: string): string {
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return iso
  const pad = (n: number): string => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`
}

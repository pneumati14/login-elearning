import { ref } from 'vue'
import { defineStore } from 'pinia'
import type { MutationResult } from './auth'

const API_URL = import.meta.env.VITE_API_URL || '/api'

export interface Address {
  country: string | null
  city: string | null
  postalCode: string | null
  street: string | null
}

export interface SalesAssignment {
  id: number
  userId: number
  userName: string
  userEmail: string
  validFrom: string | null
  validUntil: string | null
  notes: string | null
  createdAt: string
}

export interface SalesAssignmentFields {
  userId: number | null
  validFrom: string | null
  validUntil: string | null
  notes: string | null
}

export interface Contact {
  id: number
  firstName: string
  lastName: string
  jobTitle: string | null
  email: string | null
  phone: string | null
  mobile: string | null
  isPrimary: boolean
  notes: string | null
  createdAt: string
  updatedAt: string
}

export interface ContactFields {
  firstName: string
  lastName: string
  jobTitle: string | null
  email: string | null
  phone: string | null
  mobile: string | null
  isPrimary: boolean
  notes: string | null
}

export type CustomerStatus = 'existing' | 'potential'

/** One recurring monthly fee item, valid for a period. */
export interface FeeItem {
  id: number
  /** Optional catalogue product reference; name/amount stay editable. */
  productId: number | null
  name: string
  /** Headcount-based: amount = unitAmount × quantity. */
  isPerHead: boolean
  unitAmount: string | null
  quantity: number | null
  amount: string
  currency: string
  validFrom: string | null
  validUntil: string | null
  notes: string | null
  createdAt: string
}

export interface FeeItemFields {
  productId: number | null
  name: string
  isPerHead: boolean
  unitAmount: string | null
  quantity: number | null
  amount: string | null
  currency: string
  validFrom: string | null
  validUntil: string | null
  notes: string | null
}

/** Payload of the price/headcount change action. */
export interface FeeRaiseFields {
  amount?: string
  unitAmount?: string
  quantity?: number
  effectiveFrom: string
}

/** Per-currency sum of the fee items active today. */
export interface FeeTotal {
  currency: string
  amount: string
}

export interface Customer {
  id: number
  name: string
  status: CustomerStatus
  monthlyFeeTotals: FeeTotal[]
  feeItems: FeeItem[]
  address: Address
  website: string | null
  billingAddress: Address
  taxNumber: string | null
  email: string | null
  phone: string | null
  notes: string | null
  validFrom: string | null
  validUntil: string | null
  salesAssignments: SalesAssignment[]
  contacts: Contact[]
  createdAt: string
  updatedAt: string
}

export interface CustomerFields {
  name: string
  status: CustomerStatus
  address: Address
  website: string | null
  billingAddress: Address
  taxNumber: string | null
  email: string | null
  phone: string | null
  notes: string | null
  validFrom: string | null
  validUntil: string | null
}

export const emptyAddress = (): Address => ({
  country: null,
  city: null,
  postalCode: null,
  street: null,
})

export const emptyCustomerFields = (): CustomerFields => ({
  name: '',
  status: 'potential',
  address: emptyAddress(),
  website: null,
  billingAddress: emptyAddress(),
  taxNumber: null,
  email: null,
  phone: null,
  notes: null,
  validFrom: null,
  validUntil: null,
})

export const emptyContactFields = (): ContactFields => ({
  firstName: '',
  lastName: '',
  jobTitle: null,
  email: null,
  phone: null,
  mobile: null,
  isPrimary: false,
  notes: null,
})

// Trim and normalize empty strings to null before sending, mirroring the
// customer payload contract.
export function toContactPayload(f: ContactFields): ContactFields {
  const norm = (v: string | null): string | null => (null === v || '' === v.trim() ? null : v.trim())
  return {
    firstName: f.firstName.trim(),
    lastName: f.lastName.trim(),
    jobTitle: norm(f.jobTitle),
    email: norm(f.email),
    phone: norm(f.phone),
    mobile: norm(f.mobile),
    isPrimary: f.isPrimary,
    notes: norm(f.notes),
  }
}

export function addressesEqual(a: Address, b: Address): boolean {
  return a.country === b.country
    && a.city === b.city
    && a.postalCode === b.postalCode
    && a.street === b.street
}

// Normalize empty strings to null before sending — the API treats null and
// empty string the same, but null on the wire is the simpler invariant. When
// `copyBilling` is set, the billing address mirrors the main address.
export function toCustomerPayload(f: CustomerFields, copyBilling: boolean): CustomerFields {
  const norm = (v: string | null): string | null => (null === v || '' === v.trim() ? null : v.trim())
  return {
    name: f.name.trim(),
    status: f.status,
    address: { ...f.address },
    website: norm(f.website),
    billingAddress: copyBilling ? { ...f.address } : { ...f.billingAddress },
    taxNumber: norm(f.taxNumber),
    email: norm(f.email),
    phone: norm(f.phone),
    notes: norm(f.notes),
    validFrom: norm(f.validFrom),
    validUntil: norm(f.validUntil),
  }
}

export const useCustomersStore = defineStore('customers', () => {
  const customers = ref<Customer[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetchCustomers(): Promise<void> {
    loading.value = true
    error.value = null

    try {
      const response = await fetch(`${API_URL}/admin/customers`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })

      if (!response.ok) {
        throw new Error(`A szerver ${response.status} hibakóddal válaszolt.`)
      }

      customers.value = (await response.json()) as Customer[]
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Ismeretlen hiba történt.'
    } finally {
      loading.value = false
    }
  }

  // Fetch a single customer by id — used by the detail view, which may be
  // deep-linked without the list ever having loaded. Refreshes the cached
  // list entry if present so list and detail stay in sync.
  async function fetchCustomer(id: number): Promise<Customer | null> {
    try {
      const response = await fetch(`${API_URL}/admin/customers/${id}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
      if (!response.ok) return null

      const data = (await response.json()) as Customer
      // Upsert into the cache so the list and the detail view share one
      // reactive source — mutations (incl. sales assignments) stay in sync.
      const idx = customers.value.findIndex((c) => c.id === id)
      if (-1 !== idx) {
        customers.value = customers.value.map((c) => (c.id === id ? data : c))
      } else {
        customers.value = [...customers.value, data]
      }
      return data
    } catch {
      return null
    }
  }

  async function createCustomer(fields: CustomerFields): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/customers`, {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(fields),
      })

      const data = (await response.json().catch(() => null)) as
        | Customer
        | { error?: string }
        | null

      if (!response.ok) {
        const message = data && 'error' in data && data.error ? data.error : 'A létrehozás nem sikerült.'
        return { ok: false, error: message }
      }

      if (data && 'id' in data) {
        customers.value = [...customers.value, data].sort((a, b) => a.name.localeCompare(b.name))
      }
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  async function updateCustomer(id: number, fields: CustomerFields): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/customers/${id}`, {
        method: 'PUT',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(fields),
      })

      const data = (await response.json().catch(() => null)) as
        | Customer
        | { error?: string }
        | null

      if (!response.ok) {
        const message = data && 'error' in data && data.error ? data.error : 'A mentés nem sikerült.'
        return { ok: false, error: message }
      }

      if (data && 'id' in data) {
        customers.value = customers.value
          .map((c) => (c.id === id ? data : c))
          .sort((a, b) => a.name.localeCompare(b.name))
      }
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  // ── Sales assignments ────────────────────────────────────────────
  function replaceAssignments(customerId: number, mapper: (list: SalesAssignment[]) => SalesAssignment[]): void {
    customers.value = customers.value.map((c) =>
      c.id === customerId ? { ...c, salesAssignments: mapper(c.salesAssignments) } : c,
    )
  }

  async function createSalesAssignment(customerId: number, fields: SalesAssignmentFields): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/customers/${customerId}/sales-assignments`, {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(fields),
      })
      const data = (await response.json().catch(() => null)) as
        | SalesAssignment
        | { error?: string }
        | null

      if (!response.ok) {
        const message = data && 'error' in data && data.error ? data.error : 'A hozzárendelés nem sikerült.'
        return { ok: false, error: message }
      }
      if (data && 'id' in data) {
        replaceAssignments(customerId, (list) => [...list, data])
      }
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  async function updateSalesAssignment(customerId: number, assignmentId: number, fields: SalesAssignmentFields): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/customers/${customerId}/sales-assignments/${assignmentId}`, {
        method: 'PUT',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(fields),
      })
      const data = (await response.json().catch(() => null)) as
        | SalesAssignment
        | { error?: string }
        | null

      if (!response.ok) {
        const message = data && 'error' in data && data.error ? data.error : 'A mentés nem sikerült.'
        return { ok: false, error: message }
      }
      if (data && 'id' in data) {
        replaceAssignments(customerId, (list) => list.map((a) => (a.id === assignmentId ? data : a)))
      }
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  async function deleteSalesAssignment(customerId: number, assignmentId: number): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/customers/${customerId}/sales-assignments/${assignmentId}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
      if (!response.ok) {
        return { ok: false, error: 'A törlés nem sikerült.' }
      }
      replaceAssignments(customerId, (list) => list.filter((a) => a.id !== assignmentId))
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  // ── Quick status flip (overview header) ──────────────────────────
  async function setStatus(id: number, status: CustomerStatus): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/customers/${id}/status`, {
        method: 'PUT',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ status }),
      })
      const data = (await response.json().catch(() => null)) as Customer | { error?: string } | null
      if (!response.ok) {
        const message = data && 'error' in data && data.error ? data.error : 'A mentés nem sikerült.'
        return { ok: false, error: message }
      }
      if (data && 'id' in data) {
        customers.value = customers.value.map((c) => (c.id === id ? data : c))
      }
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  // ── Monthly fee items ─────────────────────────────────────────────
  // Every fee mutation returns { feeItems, monthlyFeeTotals } — patch both
  // onto the cached customer so the list column updates too.
  async function mutateFee(
    customerId: number,
    path: string,
    method: 'POST' | 'PUT' | 'DELETE',
    body?: unknown,
    fallback = 'A művelet nem sikerült.',
  ): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/customers/${customerId}/fees${path}`, {
        method,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: undefined === body ? undefined : JSON.stringify(body),
      })
      const data = (await response.json().catch(() => null)) as
        | { feeItems: FeeItem[]; monthlyFeeTotals: FeeTotal[] }
        | { error?: string }
        | null
      if (!response.ok) {
        const message = data && 'error' in data && data.error ? data.error : fallback
        return { ok: false, error: message }
      }
      if (data && 'feeItems' in data) {
        customers.value = customers.value.map((c) =>
          c.id === customerId ? { ...c, feeItems: data.feeItems, monthlyFeeTotals: data.monthlyFeeTotals } : c,
        )
      }
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  function createFee(customerId: number, fields: FeeItemFields): Promise<MutationResult> {
    return mutateFee(customerId, '', 'POST', fields, 'A tétel létrehozása nem sikerült.')
  }

  function updateFee(customerId: number, feeId: number, fields: FeeItemFields): Promise<MutationResult> {
    return mutateFee(customerId, `/${feeId}`, 'PUT', fields, 'A mentés nem sikerült.')
  }

  function deleteFee(customerId: number, feeId: number): Promise<MutationResult> {
    return mutateFee(customerId, `/${feeId}`, 'DELETE', undefined, 'A törlés nem sikerült.')
  }

  function raiseFee(customerId: number, feeId: number, fields: FeeRaiseFields): Promise<MutationResult> {
    return mutateFee(customerId, `/${feeId}/raise`, 'POST', fields, 'Az áremelés rögzítése nem sikerült.')
  }

  // ── Contacts ─────────────────────────────────────────────────────
  function replaceContacts(customerId: number, mapper: (list: Contact[]) => Contact[]): void {
    customers.value = customers.value.map((c) =>
      c.id === customerId ? { ...c, contacts: mapper(c.contacts) } : c,
    )
  }

  async function createContact(customerId: number, fields: ContactFields): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/customers/${customerId}/contacts`, {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(toContactPayload(fields)),
      })
      const data = (await response.json().catch(() => null)) as Contact | { error?: string } | null

      if (!response.ok) {
        const message = data && 'error' in data && data.error ? data.error : 'A kapcsolattartó mentése nem sikerült.'
        return { ok: false, error: message }
      }
      if (data && 'id' in data) {
        replaceContacts(customerId, (list) => [...list, data])
      }
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  async function updateContact(customerId: number, contactId: number, fields: ContactFields): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/customers/${customerId}/contacts/${contactId}`, {
        method: 'PUT',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(toContactPayload(fields)),
      })
      const data = (await response.json().catch(() => null)) as Contact | { error?: string } | null

      if (!response.ok) {
        const message = data && 'error' in data && data.error ? data.error : 'A mentés nem sikerült.'
        return { ok: false, error: message }
      }
      if (data && 'id' in data) {
        replaceContacts(customerId, (list) => list.map((c) => (c.id === contactId ? data : c)))
      }
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  async function deleteContact(customerId: number, contactId: number): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/customers/${customerId}/contacts/${contactId}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
      if (!response.ok) {
        return { ok: false, error: 'A törlés nem sikerült.' }
      }
      replaceContacts(customerId, (list) => list.filter((c) => c.id !== contactId))
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  async function deleteCustomer(id: number): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/customers/${id}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })

      if (!response.ok) {
        return { ok: false, error: 'A törlés nem sikerült.' }
      }

      customers.value = customers.value.filter((c) => c.id !== id)
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  return {
    customers,
    loading,
    error,
    fetchCustomers,
    fetchCustomer,
    createCustomer,
    updateCustomer,
    deleteCustomer,
    setStatus,
    createFee,
    updateFee,
    deleteFee,
    raiseFee,
    createSalesAssignment,
    updateSalesAssignment,
    deleteSalesAssignment,
    createContact,
    updateContact,
    deleteContact,
  }
})

/** Returns assignments whose period covers `today` (defaults to right now). */
export function currentSalesAssignments(list: SalesAssignment[], today: Date = new Date()): SalesAssignment[] {
  const iso = today.toISOString().slice(0, 10) // YYYY-MM-DD
  return list.filter((a) => {
    if (null !== a.validFrom && a.validFrom > iso) return false
    if (null !== a.validUntil && a.validUntil < iso) return false
    return true
  })
}

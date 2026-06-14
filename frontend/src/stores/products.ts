import { ref } from 'vue'
import { defineStore } from 'pinia'
import type { MutationResult } from './auth'
import { todayISO } from './opportunityTypes'

const API_URL = import.meta.env.VITE_API_URL || '/api'

export const CURRENCIES = ['HUF', 'EUR', 'USD'] as const
export type Currency = (typeof CURRENCIES)[number]

export interface Product {
  id: number
  name: string
  sku: string | null
  categoryId: number | null
  categoryName: string | null
  subcategoryId: number | null
  subcategoryName: string | null
  description: string | null
  unitPrice: string | null
  materialUnitPrice: string | null
  feeUnitPrice: string | null
  currency: Currency
  isActive: boolean
  validFrom: string | null
  validUntil: string | null
  createdAt: string
  updatedAt: string
}

export interface ProductFields {
  name: string
  sku: string | null
  categoryId: number | null
  subcategoryId: number | null
  description: string | null
  unitPrice: string | null
  materialUnitPrice: string | null
  feeUnitPrice: string | null
  currency: Currency
  isActive: boolean
  validFrom: string | null
  validUntil: string | null
}

export function emptyProductFields(): ProductFields {
  return {
    name: '',
    sku: null,
    categoryId: null,
    subcategoryId: null,
    description: null,
    unitPrice: null,
    materialUnitPrice: null,
    feeUnitPrice: null,
    currency: 'HUF',
    isActive: true,
    validFrom: null,
    validUntil: null,
  }
}

export type ProductStatus = 'active' | 'inactive' | 'scheduled' | 'expired'

/** Effective status: manual isActive flag combined with the validity window. */
export function productStatus(p: Product, today: string = todayISO()): ProductStatus {
  if (!p.isActive) return 'inactive'
  if (p.validUntil && today > p.validUntil) return 'expired'
  if (p.validFrom && today < p.validFrom) return 'scheduled'
  return 'active'
}

async function readError(response: Response, fallback: string): Promise<string> {
  const data = (await response.json().catch(() => null)) as { error?: string } | null
  return data && data.error ? data.error : fallback
}

export const useProductsStore = defineStore('products', () => {
  const products = ref<Product[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  function upsert(product: Product): void {
    const idx = products.value.findIndex((p) => p.id === product.id)
    products.value =
      -1 === idx
        ? [...products.value, product].sort((a, b) => a.name.localeCompare(b.name))
        : products.value.map((p) => (p.id === product.id ? product : p))
  }

  async function fetchProducts(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const response = await fetch(`${API_URL}/admin/products`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
      if (!response.ok) {
        throw new Error(`A szerver ${response.status} hibakóddal válaszolt.`)
      }
      products.value = (await response.json()) as Product[]
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Ismeretlen hiba történt.'
    } finally {
      loading.value = false
    }
  }

  async function createProduct(fields: ProductFields): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/products`, {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(fields),
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, 'A termék mentése nem sikerült.') }
      }
      upsert((await response.json()) as Product)
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  async function updateProduct(id: number, fields: ProductFields): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/products/${id}`, {
        method: 'PUT',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(fields),
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, 'A mentés nem sikerült.') }
      }
      upsert((await response.json()) as Product)
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  async function deleteProduct(id: number): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/products/${id}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, 'A törlés nem sikerült.') }
      }
      products.value = products.value.filter((p) => p.id !== id)
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  return {
    products,
    loading,
    error,
    fetchProducts,
    createProduct,
    updateProduct,
    deleteProduct,
  }
})

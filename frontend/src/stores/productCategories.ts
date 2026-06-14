import { ref } from 'vue'
import { defineStore } from 'pinia'
import type { MutationResult } from './auth'

const API_URL = import.meta.env.VITE_API_URL || '/api'

export interface ProductSubcategory {
  id: number
  name: string
  position: number
}

export interface ProductCategory {
  id: number
  name: string
  position: number
  /** When true, products price their unit as material + fee (e.g. Hardver). */
  splitUnitPrice: boolean
  subcategories: ProductSubcategory[]
}

async function readError(response: Response, fallback: string): Promise<string> {
  const data = (await response.json().catch(() => null)) as { error?: string } | null
  return data && data.error ? data.error : fallback
}

export const useProductCategoriesStore = defineStore('productCategories', () => {
  const categories = ref<ProductCategory[]>([])
  const loaded = ref(false)
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetchCategories(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const response = await fetch(`${API_URL}/admin/product-categories`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
      if (!response.ok) {
        throw new Error(`A szerver ${response.status} hibakóddal válaszolt.`)
      }
      categories.value = (await response.json()) as ProductCategory[]
      loaded.value = true
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Ismeretlen hiba történt.'
    } finally {
      loading.value = false
    }
  }

  function upsert(category: ProductCategory): void {
    const idx = categories.value.findIndex((c) => c.id === category.id)
    categories.value =
      -1 === idx ? [...categories.value, category] : categories.value.map((c) => (c.id === category.id ? category : c))
  }

  async function mutate(
    path: string,
    method: 'POST' | 'PUT' | 'DELETE',
    body: unknown,
    fallback: string,
    removeId?: number,
  ): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/product-categories${path}`, {
        method,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: undefined === body ? undefined : JSON.stringify(body),
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, fallback) }
      }
      if (undefined !== removeId) {
        categories.value = categories.value.filter((c) => c.id !== removeId)
      } else {
        upsert((await response.json()) as ProductCategory)
      }
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  function createCategory(name: string, splitUnitPrice = false): Promise<MutationResult> {
    return mutate('', 'POST', { name, splitUnitPrice }, 'A kategória létrehozása nem sikerült.')
  }

  function updateCategory(id: number, name: string, splitUnitPrice: boolean): Promise<MutationResult> {
    return mutate(`/${id}`, 'PUT', { name, splitUnitPrice }, 'A mentés nem sikerült.')
  }

  function deleteCategory(id: number): Promise<MutationResult> {
    return mutate(`/${id}`, 'DELETE', undefined, 'A törlés nem sikerült.', id)
  }

  function createSubcategory(categoryId: number, name: string): Promise<MutationResult> {
    return mutate(`/${categoryId}/subcategories`, 'POST', { name }, 'Az alkategória létrehozása nem sikerült.')
  }

  function updateSubcategory(categoryId: number, subcategoryId: number, name: string): Promise<MutationResult> {
    return mutate(`/${categoryId}/subcategories/${subcategoryId}`, 'PUT', { name }, 'A mentés nem sikerült.')
  }

  function deleteSubcategory(categoryId: number, subcategoryId: number): Promise<MutationResult> {
    return mutate(`/${categoryId}/subcategories/${subcategoryId}`, 'DELETE', undefined, 'A törlés nem sikerült.')
  }

  function reorderSubcategories(categoryId: number, order: number[]): Promise<MutationResult> {
    return mutate(`/${categoryId}/subcategories/reorder`, 'PUT', { order }, 'Az átrendezés nem sikerült.')
  }

  return {
    categories,
    loaded,
    loading,
    error,
    fetchCategories,
    createCategory,
    updateCategory,
    deleteCategory,
    createSubcategory,
    updateSubcategory,
    deleteSubcategory,
    reorderSubcategories,
  }
})

import { ref } from 'vue'
import { defineStore } from 'pinia'
import type { MutationResult } from './auth'
import type { LocalizedText } from '@/composables/localized'

const API_URL = import.meta.env.VITE_API_URL || '/api'

export interface Publication {
  id: number
  title: LocalizedText
  description: LocalizedText
  topic: LocalizedText
  author: LocalizedText
  fileUrl: string
  createdAt: string
}

export interface NewPublication {
  title: LocalizedText
  topic: LocalizedText
  author: LocalizedText
  description: LocalizedText
}

export const usePublicationsStore = defineStore('publications', () => {
  const publications = ref<Publication[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetchPublications(): Promise<void> {
    loading.value = true
    error.value = null

    try {
      const response = await fetch(`${API_URL}/publications`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })

      if (!response.ok) {
        throw new Error(`A szerver ${response.status} hibakóddal válaszolt.`)
      }

      publications.value = (await response.json()) as Publication[]
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Ismeretlen hiba történt.'
    } finally {
      loading.value = false
    }
  }

  async function uploadPublication(
    fields: NewPublication,
    file: File,
  ): Promise<MutationResult> {
    const formData = new FormData()
    formData.append('titleEn', fields.title.en)
    formData.append('titleHu', fields.title.hu ?? '')
    formData.append('topicEn', fields.topic.en)
    formData.append('topicHu', fields.topic.hu ?? '')
    formData.append('authorEn', fields.author.en)
    formData.append('authorHu', fields.author.hu ?? '')
    formData.append('descriptionEn', fields.description.en)
    formData.append('descriptionHu', fields.description.hu ?? '')
    formData.append('file', file)

    try {
      const response = await fetch(`${API_URL}/admin/publications`, {
        method: 'POST',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        body: formData,
      })

      const data = (await response.json().catch(() => null)) as Publication | { error?: string } | null

      if (!response.ok) {
        const message = data && 'error' in data && data.error ? data.error : 'A feltöltés nem sikerült.'
        return { ok: false, error: message }
      }

      if (data && 'id' in data) {
        publications.value = [data, ...publications.value]
      }
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  async function updatePublication(
    id: number,
    fields: NewPublication,
    file: File | null,
  ): Promise<MutationResult> {
    const formData = new FormData()
    formData.append('titleEn', fields.title.en)
    formData.append('titleHu', fields.title.hu ?? '')
    formData.append('topicEn', fields.topic.en)
    formData.append('topicHu', fields.topic.hu ?? '')
    formData.append('authorEn', fields.author.en)
    formData.append('authorHu', fields.author.hu ?? '')
    formData.append('descriptionEn', fields.description.en)
    formData.append('descriptionHu', fields.description.hu ?? '')
    if (file) formData.append('file', file)

    try {
      const response = await fetch(`${API_URL}/admin/publications/${id}`, {
        method: 'POST',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        body: formData,
      })

      const data = (await response.json().catch(() => null)) as
        | Publication
        | { error?: string }
        | null

      if (!response.ok) {
        const message = data && 'error' in data && data.error ? data.error : 'A mentés nem sikerült.'
        return { ok: false, error: message }
      }

      if (data && 'id' in data) {
        publications.value = publications.value.map((p) => (p.id === id ? data : p))
      }
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  async function deletePublication(id: number): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/publications/${id}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })

      if (!response.ok) {
        return { ok: false, error: 'A törlés nem sikerült.' }
      }

      publications.value = publications.value.filter((p) => p.id !== id)
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  return {
    publications,
    loading,
    error,
    fetchPublications,
    uploadPublication,
    updatePublication,
    deletePublication,
  }
})

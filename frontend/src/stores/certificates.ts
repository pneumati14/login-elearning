import { ref } from 'vue'
import { defineStore } from 'pinia'
import type { LocalizedText } from '@/composables/localized'

const API_URL = import.meta.env.VITE_API_URL || '/api'

export interface CertificateSummary {
  id: number
  code: string
  recipientName: string
  courseTitle: LocalizedText
  courseSlug: string
  issuedAt: string
}

export const useCertificatesStore = defineStore('certificates', () => {
  const certificates = ref<CertificateSummary[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetchCertificates(): Promise<void> {
    loading.value = true
    error.value = null

    try {
      const response = await fetch(`${API_URL}/certificates`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })

      if (!response.ok) {
        throw new Error(`A szerver ${response.status} hibakóddal válaszolt.`)
      }

      certificates.value = (await response.json()) as CertificateSummary[]
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Ismeretlen hiba történt.'
    } finally {
      loading.value = false
    }
  }

  async function fetchCertificate(id: number): Promise<CertificateSummary | null> {
    try {
      const response = await fetch(`${API_URL}/certificates/${id}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
      return response.ok ? ((await response.json()) as CertificateSummary) : null
    } catch {
      return null
    }
  }

  return { certificates, loading, error, fetchCertificates, fetchCertificate }
})

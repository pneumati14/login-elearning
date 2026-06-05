import { ref } from 'vue'
import { defineStore } from 'pinia'
import { useI18n } from 'vue-i18n'
import type { MutationResult } from './auth'

const API_URL = import.meta.env.VITE_API_URL || '/api'

export interface CurrencySetting {
  currency: string
  decimals: number
}

/** Shown before the settings arrive (matches the backend defaults). */
const FALLBACK_DECIMALS: Record<string, number> = { HUF: 0, EUR: 2, USD: 2 }

async function readError(response: Response, fallback: string): Promise<string> {
  const data = (await response.json().catch(() => null)) as { error?: string } | null
  return data && data.error ? data.error : fallback
}

export const useCurrencySettingsStore = defineStore('currencySettings', () => {
  const settings = ref<CurrencySetting[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  let fetched = false

  function decimalsFor(currency: string): number {
    const row = settings.value.find((s) => s.currency === currency)
    return row ? row.decimals : (FALLBACK_DECIMALS[currency] ?? 2)
  }

  async function fetchSettings(): Promise<void> {
    if (fetched && 0 < settings.value.length) return
    fetched = true
    loading.value = true
    error.value = null
    try {
      const response = await fetch(`${API_URL}/admin/currency-settings`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
      if (!response.ok) {
        throw new Error(`A szerver ${response.status} hibakóddal válaszolt.`)
      }
      settings.value = (await response.json()) as CurrencySetting[]
    } catch (e) {
      fetched = false
      error.value = e instanceof Error ? e.message : 'Ismeretlen hiba történt.'
    } finally {
      loading.value = false
    }
  }

  async function updateSettings(next: CurrencySetting[]): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/currency-settings`, {
        method: 'PUT',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ settings: next }),
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, 'A mentés nem sikerült.') }
      }
      settings.value = (await response.json()) as CurrencySetting[]
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  return { settings, loading, error, decimalsFor, fetchSettings, updateSettings }
})

/**
 * The app-wide money formatter: rounds every amount to the per-currency
 * decimal places configured by the admins. Call from a component's setup.
 */
export function useMoneyFormat(): (amount: string | number, currency: string) => string {
  const store = useCurrencySettingsStore()
  const { locale } = useI18n()
  void store.fetchSettings()

  return (amount, currency) => {
    const decimals = store.decimalsFor(currency)
    return new Intl.NumberFormat(locale.value, {
      style: 'currency',
      currency,
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals,
    }).format(Number(amount))
  }
}

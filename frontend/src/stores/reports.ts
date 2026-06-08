import { ref } from 'vue'
import { defineStore } from 'pinia'

const API_URL = import.meta.env.VITE_API_URL || '/api'

/** One currency's running sums — no FX conversion, one row per currency. */
export interface ReportTotal {
  currency: string
  value: string
  weighted: string
}

export interface ReportStage {
  id: number
  name: string
  outcome: 'open' | 'won' | 'lost'
  probability: number
  count: number
  totals: ReportTotal[]
}

export interface ReportType {
  id: number
  name: string
  isActive: boolean
  stages: ReportStage[]
  openCount: number
  openTotals: ReportTotal[]
}

export interface ForecastMonth {
  month: string
  count: number
  totals: ReportTotal[]
}

export interface ClosedSummary {
  count: number
  totals: ReportTotal[]
}

/** Open-deal totals of one responsible salesperson (null = unassigned). */
export interface OwnerBreakdown {
  id: number | null
  name: string | null
  count: number
  totals: ReportTotal[]
}

export interface PipelineReport {
  types: ReportType[]
  forecast: {
    months: ForecastMonth[]
    noDateCount: number
  }
  owners: OwnerBreakdown[]
  closed: {
    from: string
    won: ClosedSummary
    lost: ClosedSummary
  }
}

/** One deal on the resource/revenue timeline (Gantt) report. */
export interface TimelineProject {
  id: number
  title: string
  customer: string
  nature: string
  stage: string
  outcome: 'open' | 'won' | 'lost'
  probability: number
  /** Projected win date (YYYY-MM-DD) — the bar's start. */
  startDate: string
  /** Effort in days, as decimal strings. */
  devDays: string
  pmDays: string
  integrationCount: number
  /** Deal value in its own currency (single currency, no FX here). */
  value: string
  currency: string
}

export interface TimelineReport {
  projects: TimelineProject[]
  /** Open/won deals without an expected close date — cannot be placed. */
  noDateCount: number
}

export interface ReportFilters {
  typeId?: number | null
  userId?: number | null
  stageIds?: number[]
  /** 'new' | 'upsell' — omit for all. */
  nature?: string | null
}

export const useReportsStore = defineStore('reports', () => {
  const report = ref<PipelineReport | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  const timeline = ref<TimelineReport | null>(null)
  const timelineLoading = ref(false)
  const timelineError = ref<string | null>(null)

  async function fetchPipelineReport(filters: ReportFilters = {}): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const params = new URLSearchParams()
      if (filters.typeId) params.set('typeId', String(filters.typeId))
      if (filters.userId) params.set('userId', String(filters.userId))
      if (filters.stageIds && filters.stageIds.length > 0) params.set('stageIds', filters.stageIds.join(','))
      if (filters.nature) params.set('nature', filters.nature)
      const query = params.toString()
      const response = await fetch(`${API_URL}/admin/reports/pipeline${query ? `?${query}` : ''}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
      if (!response.ok) {
        throw new Error(`A szerver ${response.status} hibakóddal válaszolt.`)
      }
      report.value = (await response.json()) as PipelineReport
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Ismeretlen hiba történt.'
    } finally {
      loading.value = false
    }
  }

  async function fetchTimelineReport(filters: ReportFilters = {}): Promise<void> {
    timelineLoading.value = true
    timelineError.value = null
    try {
      const params = new URLSearchParams()
      if (filters.typeId) params.set('typeId', String(filters.typeId))
      if (filters.userId) params.set('userId', String(filters.userId))
      if (filters.stageIds && filters.stageIds.length > 0) params.set('stageIds', filters.stageIds.join(','))
      if (filters.nature) params.set('nature', filters.nature)
      const query = params.toString()
      const response = await fetch(`${API_URL}/admin/reports/timeline${query ? `?${query}` : ''}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
      if (!response.ok) {
        throw new Error(`A szerver ${response.status} hibakóddal válaszolt.`)
      }
      timeline.value = (await response.json()) as TimelineReport
    } catch (e) {
      timelineError.value = e instanceof Error ? e.message : 'Ismeretlen hiba történt.'
    } finally {
      timelineLoading.value = false
    }
  }

  return {
    report,
    loading,
    error,
    fetchPipelineReport,
    timeline,
    timelineLoading,
    timelineError,
    fetchTimelineReport,
  }
})

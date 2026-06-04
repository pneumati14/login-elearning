<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import type { ChartConfiguration } from 'chart.js'
import ReportChart from '@/components/ReportChart.vue'
import { useReportsStore, type ReportTotal, type ReportType } from '@/stores/reports'
import { useOpportunityTypesStore } from '@/stores/opportunityTypes'
import { useUsersStore } from '@/stores/users'

const { t, locale } = useI18n()
const store = useReportsStore()
const typesStore = useOpportunityTypesStore()
const usersStore = useUsersStore()
const { report, loading, error } = storeToRefs(store)
const { types } = storeToRefs(typesStore)
const { users } = storeToRefs(usersStore)

// ── Brand palette for the charts ─────────────────────────────────────────
const COLOR_PRIMARY = '#ed2044'
const COLOR_MUTED_BLUE = '#aab6d3'
const COLOR_BLUE = '#2b59c3'
const COLOR_GREEN = '#1c7a45'
const COLOR_RED = '#b3122e'
const COLOR_TEXT = '#0c1c40'

// ── Filters ──────────────────────────────────────────────────────────────
const CURRENCIES = ['HUF', 'EUR', 'USD'] as const
const HORIZONS = [3, 6, 12] as const

const filterTypeId = ref<number | null>(null)
const filterUserId = ref<number | null>(null)
const filterCurrency = ref<string>('HUF')
const filterHorizon = ref<number>(6)
const selectedStageIds = ref<number[]>([])

// Only users who can actually own customers (sales staff).
const userOptions = computed(() =>
  users.value
    .filter((u) => 'user' !== u.role)
    .sort((a, b) => (a.lastName + a.firstName).localeCompare(b.lastName + b.firstName, 'hu')),
)

// Stage chips: only meaningful when a single pipeline is selected.
const stageOptions = computed(() => {
  if (null === filterTypeId.value) return []
  return types.value.find((tp) => tp.id === filterTypeId.value)?.stages ?? []
})

function toggleStage(id: number): void {
  selectedStageIds.value = selectedStageIds.value.includes(id)
    ? selectedStageIds.value.filter((sid) => sid !== id)
    : [...selectedStageIds.value, id]
}

function reload(): void {
  store.fetchPipelineReport({
    typeId: filterTypeId.value,
    userId: filterUserId.value,
    stageIds: selectedStageIds.value,
  })
}

onMounted(() => {
  reload()
  if (0 === types.value.length) typesStore.fetchTypes()
  if (0 === users.value.length) usersStore.fetchUsers()
})

// Changing the pipeline invalidates the stage selection.
watch(filterTypeId, () => {
  selectedStageIds.value = []
})
watch([filterTypeId, filterUserId, selectedStageIds], reload)

// ── Formatting ───────────────────────────────────────────────────────────
function fmtMoney(amount: string | number, currency: string = filterCurrency.value): string {
  return new Intl.NumberFormat(locale.value, {
    style: 'currency',
    currency,
    maximumFractionDigits: 0,
  }).format(Number(amount))
}

function fmtCompact(amount: number, currency: string = filterCurrency.value): string {
  return new Intl.NumberFormat(locale.value, {
    style: 'currency',
    currency,
    notation: 'compact',
    maximumFractionDigits: 1,
  }).format(amount)
}

function fmtMonth(month: string): string {
  return new Date(`${month}-01T00:00:00`).toLocaleDateString(locale.value, {
    year: 'numeric',
    month: 'short',
  })
}

function fmtMonthLong(month: string): string {
  return new Date(`${month}-01T00:00:00`).toLocaleDateString(locale.value, {
    year: 'numeric',
    month: 'long',
  })
}

function fmtDate(date: string): string {
  return new Date(`${date}T00:00:00`).toLocaleDateString(locale.value)
}

/** The selected currency's sums out of a per-currency totals list. */
function pick(totals: ReportTotal[]): { value: number; weighted: number } {
  const row = totals.find((tt) => tt.currency === filterCurrency.value)
  return { value: Number(row?.value ?? 0), weighted: Number(row?.weighted ?? 0) }
}

/** Per-currency lines for a table cell; em dash when there is nothing. */
function valueLines(totals: ReportTotal[]): string[] {
  const lines = totals.filter((tt) => 0 !== Number(tt.value)).map((tt) => fmtMoney(tt.value, tt.currency))
  return lines.length > 0 ? lines : ['—']
}

function weightedLines(totals: ReportTotal[]): string[] {
  const lines = totals.filter((tt) => 0 !== Number(tt.value)).map((tt) => fmtMoney(tt.weighted, tt.currency))
  return lines.length > 0 ? lines : ['—']
}

function hasDeals(tp: ReportType): boolean {
  return tp.stages.some((s) => s.count > 0)
}

// ── KPI cards (selected currency) ────────────────────────────────────────
const kpi = computed(() => {
  const r = report.value
  if (null === r) return null
  let openCount = 0
  let value = 0
  let weighted = 0
  for (const tp of r.types) {
    openCount += tp.openCount
    const sums = pick(tp.openTotals)
    value += sums.value
    weighted += sums.weighted
  }
  const closedTotal = r.closed.won.count + r.closed.lost.count
  const winRate = closedTotal > 0 ? Math.round((100 * r.closed.won.count) / closedTotal) : null
  return { openCount, value, weighted, winRate }
})

// ── Chart configurations ─────────────────────────────────────────────────
const BASE_PLUGINS = {
  legend: {
    position: 'bottom' as const,
    labels: { color: COLOR_TEXT, usePointStyle: true, pointStyle: 'circle' as const, boxHeight: 7 },
  },
}

/** Horizontal value/weighted bars for one pipeline's stages. */
function funnelConfig(tp: ReportType): ChartConfiguration {
  const counts = tp.stages.map((s) => s.count)
  return {
    type: 'bar',
    data: {
      labels: tp.stages.map((s) => `${s.name} (${s.probability}%)`),
      datasets: [
        {
          label: t('adminReports.colValue'),
          data: tp.stages.map((s) => pick(s.totals).value),
          backgroundColor: COLOR_MUTED_BLUE,
          borderRadius: 5,
        },
        {
          label: t('adminReports.colWeighted'),
          data: tp.stages.map((s) => pick(s.totals).weighted),
          backgroundColor: COLOR_PRIMARY,
          borderRadius: 5,
        },
      ],
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        ...BASE_PLUGINS,
        tooltip: {
          callbacks: {
            label: (ctx) =>
              `${ctx.dataset.label}: ${fmtMoney(ctx.parsed.x ?? 0)} · ${t('adminReports.dealsCount', { count: counts[ctx.dataIndex] ?? 0 })}`,
          },
        },
      },
      scales: {
        x: { ticks: { callback: (v) => fmtCompact(Number(v)), color: COLOR_TEXT }, grid: { color: '#eef1f6' } },
        y: { ticks: { color: COLOR_TEXT }, grid: { display: false } },
      },
    },
  }
}

/** The forecast months inside the selected horizon (from this month on). */
const horizonMonths = computed(() => {
  const r = report.value
  if (null === r) return []
  const now = new Date()
  const keys = new Set<string>()
  for (let i = 0; i < filterHorizon.value; i++) {
    const d = new Date(now.getFullYear(), now.getMonth() + i, 1)
    keys.add(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`)
  }
  return r.forecast.months.filter((m) => keys.has(m.month))
})

const forecastConfig = computed<ChartConfiguration>(() => {
  const months = horizonMonths.value
  const counts = months.map((m) => m.count)
  return {
    type: 'bar',
    data: {
      labels: months.map((m) => fmtMonth(m.month)),
      datasets: [
        {
          label: t('adminReports.colValue'),
          data: months.map((m) => pick(m.totals).value),
          backgroundColor: COLOR_MUTED_BLUE,
          borderRadius: 5,
        },
        {
          label: t('adminReports.colWeighted'),
          data: months.map((m) => pick(m.totals).weighted),
          backgroundColor: COLOR_PRIMARY,
          borderRadius: 5,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        ...BASE_PLUGINS,
        tooltip: {
          callbacks: {
            label: (ctx) =>
              `${ctx.dataset.label}: ${fmtMoney(ctx.parsed.y ?? 0)} · ${t('adminReports.dealsCount', { count: counts[ctx.dataIndex] ?? 0 })}`,
          },
        },
      },
      scales: {
        y: { ticks: { callback: (v) => fmtCompact(Number(v)), color: COLOR_TEXT }, grid: { color: '#eef1f6' } },
        x: { ticks: { color: COLOR_TEXT }, grid: { display: false } },
      },
    },
  }
})

const wonLostConfig = computed<ChartConfiguration>(() => {
  const r = report.value
  const won = r?.closed.won
  const lost = r?.closed.lost
  return {
    type: 'doughnut',
    data: {
      labels: [t('adminOpportunityTypes.outcome_won'), t('adminOpportunityTypes.outcome_lost')],
      datasets: [
        {
          data: [won?.count ?? 0, lost?.count ?? 0],
          backgroundColor: [COLOR_GREEN, COLOR_RED],
          borderWidth: 2,
          borderColor: '#fff',
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '62%',
      plugins: {
        ...BASE_PLUGINS,
        tooltip: {
          callbacks: {
            label: (ctx) => {
              const totals = 0 === ctx.dataIndex ? won?.totals : lost?.totals
              return `${t('adminReports.dealsCount', { count: ctx.parsed })} · ${fmtMoney(pick(totals ?? []).value)}`
            },
          },
        },
      },
    },
  }
})

const ownersConfig = computed<ChartConfiguration>(() => {
  const owners = [...(report.value?.owners ?? [])].sort((a, b) => pick(b.totals).weighted - pick(a.totals).weighted)
  return {
    type: 'bar',
    data: {
      labels: owners.map((o) => o.name ?? t('adminReports.noOwner')),
      datasets: [
        {
          label: t('adminReports.colWeighted'),
          data: owners.map((o) => pick(o.totals).weighted),
          backgroundColor: COLOR_BLUE,
          borderRadius: 5,
        },
      ],
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        ...BASE_PLUGINS,
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: (ctx) =>
              `${fmtMoney(ctx.parsed.x ?? 0)} · ${t('adminReports.dealsCount', { count: owners[ctx.dataIndex]?.count ?? 0 })}`,
          },
        },
      },
      scales: {
        x: { ticks: { callback: (v) => fmtCompact(Number(v)), color: COLOR_TEXT }, grid: { color: '#eef1f6' } },
        y: { ticks: { color: COLOR_TEXT }, grid: { display: false } },
      },
    },
  }
})

function funnelHeight(tp: ReportType): string {
  return `${Math.max(220, 56 * tp.stages.length + 70)}px`
}

const ownersHeight = computed(() => `${Math.max(220, 52 * (report.value?.owners.length ?? 0) + 70)}px`)
</script>

<template>
  <section class="admin">
    <div class="container-lg">
      <div class="admin-head">
        <span class="eyebrow">CRM</span>
        <h1>{{ t('adminReports.title') }}</h1>
        <p class="subtitle">{{ t('adminReports.subtitle') }}</p>
      </div>

      <!-- ── Filters ─────────────────────────────────────────────────── -->
      <div class="rep-panel rep-filterbar">
        <div class="rep-filters">
          <label class="rep-filter">
            <span>{{ t('adminReports.filterType') }}</span>
            <select v-model.number="filterTypeId">
              <option :value="null">{{ t('adminReports.filterAll') }}</option>
              <option v-for="tp in types" :key="tp.id" :value="tp.id">{{ tp.name }}</option>
            </select>
          </label>
          <label class="rep-filter">
            <span>{{ t('adminReports.filterUser') }}</span>
            <select v-model.number="filterUserId">
              <option :value="null">{{ t('adminReports.filterAll') }}</option>
              <option v-for="u in userOptions" :key="u.id" :value="u.id">
                {{ u.lastName }} {{ u.firstName }}
              </option>
            </select>
          </label>
          <label class="rep-filter">
            <span>{{ t('adminReports.filterCurrency') }}</span>
            <select v-model="filterCurrency">
              <option v-for="c in CURRENCIES" :key="c" :value="c">{{ c }}</option>
            </select>
          </label>
          <label class="rep-filter">
            <span>{{ t('adminReports.filterHorizon') }}</span>
            <select v-model.number="filterHorizon">
              <option v-for="h in HORIZONS" :key="h" :value="h">{{ t('adminReports.horizonOption', { n: h }) }}</option>
            </select>
          </label>
        </div>

        <div v-if="stageOptions.length > 0" class="stage-chips">
          <span class="stage-chips-label">{{ t('adminReports.filterStages') }}:</span>
          <button
            v-for="s in stageOptions"
            :key="s.id"
            type="button"
            class="chip"
            :class="{ 'is-active': selectedStageIds.includes(s.id) }"
            @click="toggleStage(s.id)"
          >
            {{ s.name }}
          </button>
        </div>
      </div>

      <p v-if="loading" class="state">{{ t('adminReports.loading') }}</p>

      <div v-else-if="error" class="state state--error">
        <strong>{{ t('adminReports.loadError') }}</strong>
        <button type="button" class="btn-retry" @click="reload">{{ t('common.retry') }}</button>
      </div>

      <template v-else-if="report">
        <!-- ── KPI cards ─────────────────────────────────────────────── -->
        <div v-if="kpi" class="kpi-grid">
          <div class="kpi-card">
            <span class="kpi-label">{{ t('adminReports.kpiOpenDeals') }}</span>
            <span class="kpi-value">{{ kpi.openCount }}</span>
          </div>
          <div class="kpi-card">
            <span class="kpi-label">{{ t('adminReports.kpiPipelineValue') }}</span>
            <span class="kpi-value">{{ fmtCompact(kpi.value) }}</span>
            <span class="kpi-sub">{{ fmtMoney(kpi.value) }}</span>
          </div>
          <div class="kpi-card kpi-card--accent">
            <span class="kpi-label">{{ t('adminReports.kpiWeighted') }}</span>
            <span class="kpi-value">{{ fmtCompact(kpi.weighted) }}</span>
            <span class="kpi-sub">{{ fmtMoney(kpi.weighted) }}</span>
          </div>
          <div class="kpi-card">
            <span class="kpi-label">{{ t('adminReports.kpiWinRate') }}</span>
            <span class="kpi-value">{{ null === kpi.winRate ? '—' : kpi.winRate + '%' }}</span>
            <span class="kpi-sub">
              {{ t('adminReports.dealsCount', { count: report.closed.won.count + report.closed.lost.count }) }}
            </span>
          </div>
        </div>

        <!-- ── Funnel per pipeline (chart + table) ───────────────────── -->
        <div v-for="tp in report.types" :key="tp.id" class="rep-panel">
          <div class="rep-panel-head">
            <h2>{{ tp.name }}</h2>
            <span v-if="!tp.isActive" class="badge badge--muted">{{ t('adminOpportunityTypes.status_inactive') }}</span>
          </div>

          <p v-if="!hasDeals(tp)" class="muted">{{ t('adminReports.typeEmpty') }}</p>

          <template v-else>
            <ReportChart :config="funnelConfig(tp)" :height="funnelHeight(tp)" />

            <details class="rep-details">
              <summary>{{ t('adminReports.detailsToggle') }}</summary>
              <div class="table-wrap">
                <table class="rep-table">
                  <thead>
                    <tr>
                      <th>{{ t('adminReports.colStage') }}</th>
                      <th class="num">{{ t('adminReports.colProbability') }}</th>
                      <th class="num">{{ t('adminReports.colCount') }}</th>
                      <th class="num">{{ t('adminReports.colValue') }}</th>
                      <th class="num">{{ t('adminReports.colWeighted') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="s in tp.stages" :key="s.id">
                      <td>
                        <span class="stage-name">{{ s.name }}</span>
                        <span class="badge" :class="`badge--${s.outcome}`">
                          {{ t('adminOpportunityTypes.outcome_' + s.outcome) }}
                        </span>
                      </td>
                      <td class="num">{{ s.probability }}%</td>
                      <td class="num">{{ s.count }}</td>
                      <td class="num">
                        <div v-for="(line, i) in valueLines(s.totals)" :key="i">{{ line }}</div>
                      </td>
                      <td class="num">
                        <div v-for="(line, i) in weightedLines(s.totals)" :key="i">{{ line }}</div>
                      </td>
                    </tr>
                  </tbody>
                  <tfoot>
                    <tr>
                      <td>{{ t('adminReports.openTotal') }}</td>
                      <td class="num"></td>
                      <td class="num">{{ tp.openCount }}</td>
                      <td class="num">
                        <div v-for="(line, i) in valueLines(tp.openTotals)" :key="i">{{ line }}</div>
                      </td>
                      <td class="num">
                        <div v-for="(line, i) in weightedLines(tp.openTotals)" :key="i">{{ line }}</div>
                      </td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </details>
          </template>
        </div>

        <!-- ── Forecast ──────────────────────────────────────────────── -->
        <div class="rep-panel">
          <h2>{{ t('adminReports.forecastHeader') }}</h2>
          <p class="rep-hint">{{ t('adminReports.forecastHint') }}</p>

          <p v-if="horizonMonths.length === 0" class="muted">{{ t('adminReports.forecastEmpty') }}</p>
          <ReportChart v-else :config="forecastConfig" height="320px" />

          <details v-if="report.forecast.months.length > 0" class="rep-details">
            <summary>{{ t('adminReports.detailsToggle') }}</summary>
            <div class="table-wrap">
              <table class="rep-table">
                <thead>
                  <tr>
                    <th>{{ t('adminReports.colMonth') }}</th>
                    <th class="num">{{ t('adminReports.colCount') }}</th>
                    <th class="num">{{ t('adminReports.colValue') }}</th>
                    <th class="num">{{ t('adminReports.colWeighted') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="m in report.forecast.months" :key="m.month">
                    <td>{{ fmtMonthLong(m.month) }}</td>
                    <td class="num">{{ m.count }}</td>
                    <td class="num">
                      <div v-for="(line, i) in valueLines(m.totals)" :key="i">{{ line }}</div>
                    </td>
                    <td class="num">
                      <div v-for="(line, i) in weightedLines(m.totals)" :key="i">{{ line }}</div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </details>

          <p v-if="report.forecast.noDateCount > 0" class="rep-note">
            {{ t('adminReports.noDateNote', { count: report.forecast.noDateCount }) }}
          </p>
        </div>

        <!-- ── Owners + won/lost side by side ────────────────────────── -->
        <div class="rep-grid">
          <div class="rep-panel">
            <h2>{{ t('adminReports.ownersHeader') }}</h2>
            <p v-if="report.owners.length === 0" class="muted">{{ t('adminReports.noData') }}</p>
            <ReportChart v-else :config="ownersConfig" :height="ownersHeight" />
          </div>

          <div class="rep-panel">
            <h2>{{ t('adminReports.closedHeader') }}</h2>
            <p class="rep-hint">{{ t('adminReports.closedHint', { from: fmtDate(report.closed.from) }) }}</p>
            <p v-if="report.closed.won.count + report.closed.lost.count === 0" class="muted">
              {{ t('adminReports.noData') }}
            </p>
            <template v-else>
              <ReportChart :config="wonLostConfig" height="240px" />
              <div class="closed-grid">
                <div class="closed-card closed-card--won">
                  <span class="closed-label">{{ t('adminOpportunityTypes.outcome_won') }}</span>
                  <span class="closed-count">{{ report.closed.won.count }}</span>
                  <div class="closed-totals">
                    <div v-for="(line, i) in valueLines(report.closed.won.totals)" :key="i">{{ line }}</div>
                  </div>
                </div>
                <div class="closed-card closed-card--lost">
                  <span class="closed-label">{{ t('adminOpportunityTypes.outcome_lost') }}</span>
                  <span class="closed-count">{{ report.closed.lost.count }}</span>
                  <div class="closed-totals">
                    <div v-for="(line, i) in valueLines(report.closed.lost.totals)" :key="i">{{ line }}</div>
                  </div>
                </div>
              </div>
            </template>
          </div>
        </div>
      </template>
    </div>
  </section>
</template>

<style scoped>
.admin {
  padding: 3.5rem 0 5rem;
}

.admin-head {
  margin-bottom: 2.2rem;
}

.eyebrow {
  display: inline-block;
  color: var(--login-primary, #ed2044);
  font-size: 0.9rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.admin-head h1 {
  margin: 0.35rem 0 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 2.4rem;
  font-weight: 700;
}

.subtitle {
  margin: 0;
  color: #545f71;
  font-size: 1rem;
}

/* ── Filter bar ─────────────────────────────────────────────────────── */
.rep-filterbar {
  padding: 1.2rem 1.85rem 1.3rem;
}

.rep-filters {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
}

.rep-filter {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}

.rep-filter span {
  color: var(--login-secondary, #0c1c40);
  font-size: 0.8rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.rep-filter select {
  min-width: 170px;
  padding: 0.55rem 0.7rem;
  background: #f7f8fb;
  border: 1px solid #d4dae6;
  border-radius: 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-family: inherit;
}

.rep-filter select:focus {
  outline: 2px solid var(--login-primary, #ed2044);
  outline-offset: -1px;
  background: #fff;
}

.stage-chips {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.45rem;
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid #eef1f6;
}

.stage-chips-label {
  color: var(--login-secondary, #0c1c40);
  font-size: 0.8rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.chip {
  padding: 0.35rem 0.85rem;
  background: #f7f8fb;
  border: 1px solid #d4dae6;
  border-radius: 2rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition:
    background 0.15s,
    border-color 0.15s,
    color 0.15s;
}

.chip:hover {
  border-color: var(--login-primary, #ed2044);
}

.chip.is-active {
  background: var(--login-primary, #ed2044);
  border-color: var(--login-primary, #ed2044);
  color: #fff;
}

/* ── KPI cards ──────────────────────────────────────────────────────── */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.kpi-card {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  padding: 1.3rem 1.5rem;
  background: #fff;
  border-radius: 1.1rem;
  box-shadow: 0 12px 32px rgba(12, 28, 64, 0.08);
}

.kpi-card--accent {
  background: var(--login-secondary, #0c1c40);
}

.kpi-label {
  color: #8b94a6;
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.kpi-card--accent .kpi-label {
  color: #aab6d3;
}

.kpi-value {
  color: var(--login-secondary, #0c1c40);
  font-size: 1.9rem;
  font-weight: 700;
  line-height: 1.15;
}

.kpi-card--accent .kpi-value {
  color: #fff;
}

.kpi-sub {
  color: #8b94a6;
  font-size: 0.85rem;
  font-weight: 600;
}

.kpi-card--accent .kpi-sub {
  color: #aab6d3;
}

/* ── Panels ─────────────────────────────────────────────────────────── */
.rep-panel {
  margin-bottom: 1.5rem;
  padding: 1.85rem;
  background: #fff;
  border-radius: 1.1rem;
  box-shadow: 0 12px 32px rgba(12, 28, 64, 0.08);
}

.rep-panel h2 {
  margin: 0 0 1.3rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.25rem;
  font-weight: 700;
}

.rep-panel-head {
  display: flex;
  align-items: center;
  gap: 0.7rem;
  margin-bottom: 1.3rem;
}

.rep-panel-head h2 {
  margin: 0;
}

.rep-hint {
  margin: -0.8rem 0 1.1rem;
  color: #8b94a6;
  font-size: 0.88rem;
}

.rep-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 1.5rem;
}

.rep-grid .rep-panel {
  margin-bottom: 0;
}

/* ── Collapsible detail tables ──────────────────────────────────────── */
.rep-details {
  margin-top: 1.2rem;
}

.rep-details summary {
  display: inline-block;
  color: var(--login-primary, #ed2044);
  font-size: 0.88rem;
  font-weight: 700;
  cursor: pointer;
  user-select: none;
}

.rep-details[open] summary {
  margin-bottom: 0.8rem;
}

.table-wrap {
  overflow-x: auto;
}

.rep-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.92rem;
}

.rep-table th {
  padding: 0.55rem 0.7rem;
  border-bottom: 2px solid #e3e7ef;
  color: #8b94a6;
  font-size: 0.78rem;
  font-weight: 700;
  text-align: left;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  white-space: nowrap;
}

.rep-table td {
  padding: 0.6rem 0.7rem;
  border-bottom: 1px solid #eef1f6;
  color: var(--login-secondary, #0c1c40);
  vertical-align: top;
}

.rep-table .num {
  text-align: right;
  white-space: nowrap;
}

.rep-table tfoot td {
  border-bottom: none;
  border-top: 2px solid #e3e7ef;
  font-weight: 700;
}

.stage-name {
  margin-right: 0.55rem;
  font-weight: 600;
}

.rep-note {
  margin: 1rem 0 0;
  padding: 0.7rem 0.9rem;
  background: #fdf3e6;
  border-radius: 0.55rem;
  color: #8a5a18;
  font-size: 0.88rem;
}

.muted {
  margin: 0;
  color: #8b94a6;
  font-size: 0.9rem;
}

/* ── Closed summary cards ───────────────────────────────────────────── */
.closed-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 1rem;
  margin-top: 1.2rem;
}

.closed-card {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  padding: 1.1rem 1.3rem;
  border-radius: 0.8rem;
}

.closed-card--won {
  background: #e3f6ec;
}

.closed-card--lost {
  background: #fde8ec;
}

.closed-label {
  font-size: 0.82rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.closed-card--won .closed-label {
  color: #1c7a45;
}

.closed-card--lost .closed-label {
  color: #b3122e;
}

.closed-count {
  color: var(--login-secondary, #0c1c40);
  font-size: 1.9rem;
  font-weight: 700;
}

.closed-totals {
  color: #545f71;
  font-size: 0.95rem;
  font-weight: 600;
}

.badge {
  display: inline-block;
  padding: 0.1rem 0.5rem;
  border-radius: 0.4rem;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.badge--open {
  background: #e7eefc;
  color: #2b59c3;
}

.badge--won {
  background: #e3f6ec;
  color: #1c7a45;
}

.badge--lost {
  background: #fde8ec;
  color: #b3122e;
}

.badge--muted {
  background: #eef1f6;
  color: #8b94a6;
}

.state {
  padding: 1.3rem;
  background: #f7f8fb;
  border-radius: 0.7rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 1rem;
}

.state--error {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  background: #fde8ec;
  color: #b3122e;
}

.btn-retry {
  padding: 0.45rem 1rem;
  background: #fff;
  border: 1px solid #b3122e;
  border-radius: 0.45rem;
  color: #b3122e;
  font-size: 0.85rem;
  font-weight: 700;
  cursor: pointer;
}
</style>

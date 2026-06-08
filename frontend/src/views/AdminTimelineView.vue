<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import type { ChartConfiguration } from 'chart.js'
import ReportChart from '@/components/ReportChart.vue'
import { useReportsStore, type TimelineProject } from '@/stores/reports'
import { useOpportunityTypesStore } from '@/stores/opportunityTypes'
import { useUsersStore } from '@/stores/users'
import { useCurrencySettingsStore, useMoneyFormat } from '@/stores/currencySettings'
import AppSelect from '@/components/AppSelect.vue'

const { t, locale } = useI18n()
const store = useReportsStore()
const typesStore = useOpportunityTypesStore()
const usersStore = useUsersStore()
const currencyStore = useCurrencySettingsStore()
const { timeline, timelineLoading: loading, timelineError: error } = storeToRefs(store)
const { types } = storeToRefs(typesStore)
const { users } = storeToRefs(usersStore)

// ── Brand palette ──────────────────────────────────────────────────────────
const COLOR_PRIMARY = '#ed2044'
const COLOR_MUTED_BLUE = '#aab6d3'
const COLOR_BLUE = '#2b59c3'
const COLOR_TEXT = '#0c1c40'

// One working day stretches over this many calendar days (5-day week), so
// development effort maps to a realistic delivery window on the timeline.
const CALENDAR_PER_WORKDAY = 7 / 5
// Deals with little/no effort still get a small, visible marker bar.
const MIN_BAR_DAYS = 3
const DAY_MS = 86_400_000

// ── Filters ──────────────────────────────────────────────────────────────
const CURRENCIES = ['HUF', 'EUR', 'USD'] as const

const filterTypeId = ref<number | null>(null)
const filterUserId = ref<number | null>(null)
const filterCurrency = ref<string>('HUF')
const filterNature = ref<string | null>(null)
const selectedStageIds = ref<number[]>([])

const userOptions = computed(() =>
  users.value
    .filter((u) => 'user' !== u.role)
    .sort((a, b) => (a.lastName + a.firstName).localeCompare(b.lastName + b.firstName, 'hu')),
)

const typeSelectOptions = computed<{ value: number | null; label: string }[]>(() => [
  { value: null, label: t('adminReports.filterAll') },
  ...types.value.map((tp) => ({ value: tp.id, label: tp.name })),
])
const userSelectOptions = computed<{ value: number | null; label: string }[]>(() => [
  { value: null, label: t('adminReports.filterAll') },
  ...userOptions.value.map((u) => ({ value: u.id, label: `${u.lastName} ${u.firstName}` })),
])
const currencySelectOptions: { value: string; label: string }[] = CURRENCIES.map((c) => ({ value: c, label: c }))
const natureSelectOptions = computed<{ value: string | null; label: string }[]>(() => [
  { value: null, label: t('adminReports.filterAll') },
  { value: 'new', label: t('adminCustomers.oppNature_new') },
  { value: 'upsell', label: t('adminCustomers.oppNature_upsell') },
])

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
  store.fetchTimelineReport({
    typeId: filterTypeId.value,
    userId: filterUserId.value,
    stageIds: selectedStageIds.value,
    nature: filterNature.value,
  })
}

onMounted(() => {
  reload()
  if (0 === types.value.length) typesStore.fetchTypes()
  if (0 === users.value.length) usersStore.fetchUsers()
  currencyStore.fetchSettings()
})

watch(filterTypeId, () => {
  selectedStageIds.value = []
})
watch([filterTypeId, filterUserId, selectedStageIds, filterNature], reload)

// ── Formatting ───────────────────────────────────────────────────────────
const fmtMoneyRaw = useMoneyFormat()
function fmtMoney(amount: string | number, currency: string = filterCurrency.value): string {
  return fmtMoneyRaw(amount, currency)
}
function fmtCompact(amount: number, currency: string = filterCurrency.value): string {
  return new Intl.NumberFormat(locale.value, {
    style: 'currency',
    currency,
    notation: 'compact',
    maximumFractionDigits: 1,
  }).format(amount)
}
function fmtDate(date: string): string {
  return new Date(`${date}T00:00:00`).toLocaleDateString(locale.value)
}
function fmtMonth(month: string): string {
  return new Date(`${month}-01T00:00:00`).toLocaleDateString(locale.value, { year: 'numeric', month: 'short' })
}
function fmtTickMonth(ms: number): string {
  return new Date(ms).toLocaleDateString(locale.value, { year: '2-digit', month: 'short' })
}
function fmtDays(days: string | number): string {
  return t('adminTimeline.daysUnit', { n: Number(days).toLocaleString(locale.value, { maximumFractionDigits: 2 }) })
}

// ── Currency conversion (each deal in its own currency → selected) ─────────
const reportCurrencies = computed<string[]>(() => {
  const found = new Set<string>()
  for (const p of timeline.value?.projects ?? []) if (0 !== Number(p.value)) found.add(p.currency)
  return [...found].sort()
})
const conversionNeeded = computed(() => reportCurrencies.value.some((c) => c !== filterCurrency.value))
const rateEditCurrencies = computed<string[]>(() => {
  if (!conversionNeeded.value) return []
  const needed = new Set(reportCurrencies.value)
  needed.add(filterCurrency.value)
  needed.delete('HUF')
  return [...needed].sort()
})
const missingRateCurrencies = computed<string[]>(() =>
  rateEditCurrencies.value.filter((c) => null === currencyStore.rateFor(c)),
)

const rateDraft = ref<Record<string, string>>({})
watch(
  [() => currencyStore.settings, rateEditCurrencies],
  () => {
    for (const c of rateEditCurrencies.value) {
      if (!(c in rateDraft.value)) {
        const rate = currencyStore.rateFor(c)
        rateDraft.value[c] = null === rate ? '' : String(rate)
      }
    }
  },
  { immediate: true, deep: true },
)

const rateSaved = ref(false)
const rateError = ref<string | null>(null)
async function onRateChange(): Promise<void> {
  rateError.value = null
  rateSaved.value = false
  const rates = rateEditCurrencies.value.map((c) => ({
    currency: c,
    rateHuf: '' === rateDraft.value[c]?.trim() ? null : (rateDraft.value[c] ?? null),
  }))
  const result = await currencyStore.updateRates(rates)
  if (result.ok) {
    rateSaved.value = true
    window.setTimeout(() => (rateSaved.value = false), 2500)
  } else {
    rateError.value = result.error ?? t('admin.saveFailed')
  }
}

/** Convert one amount into the selected currency, or null if a rate is missing. */
function convert(amount: number, currency: string): number | null {
  if (currency === filterCurrency.value) return amount
  const targetRate = currencyStore.rateFor(filterCurrency.value)
  const rate = currencyStore.rateFor(currency)
  if (null === rate || null === targetRate) return null
  return (amount * rate) / targetRate
}
function projectRevenue(p: TimelineProject): number | null {
  return convert(Number(p.value), p.currency)
}
function revenueCell(p: TimelineProject): string {
  const r = projectRevenue(p)
  return null === r ? '—' : fmtMoney(r)
}

// ── Derived timeline spans ─────────────────────────────────────────────────
const spanned = computed(() =>
  (timeline.value?.projects ?? []).map((p) => {
    const start = new Date(`${p.startDate}T00:00:00`).getTime()
    const calDays = Math.max(MIN_BAR_DAYS, Math.ceil(Number(p.devDays) * CALENDAR_PER_WORKDAY))
    return { p, start, end: start + calDays * DAY_MS }
  }),
)

const hasProjects = computed(() => spanned.value.length > 0)

/** Bar colour: green for already-won, brand red for open; opacity scales with probability. */
function barColor(p: TimelineProject): string {
  const alpha = (0.35 + (0.6 * p.probability) / 100).toFixed(2)
  return 'won' === p.outcome ? `rgba(28, 122, 69, ${alpha})` : `rgba(237, 32, 68, ${alpha})`
}

function monthStart(ms: number): number {
  const d = new Date(ms)
  d.setDate(1)
  d.setHours(0, 0, 0, 0)
  return d.getTime()
}
function monthTicks(min: number, max: number): { value: number }[] {
  if (0 === min || 0 === max) return []
  const ticks: { value: number }[] = []
  const d = new Date(min)
  d.setDate(1)
  d.setHours(0, 0, 0, 0)
  while (d.getTime() <= max) {
    ticks.push({ value: d.getTime() })
    d.setMonth(d.getMonth() + 1)
  }
  return ticks
}

// ── Gantt chart ────────────────────────────────────────────────────────────
const ganttConfig = computed<ChartConfiguration>(() => {
  const rows = spanned.value
  const min = rows.length ? monthStart(Math.min(...rows.map((r) => r.start))) : 0
  const max = rows.length ? Math.max(...rows.map((r) => r.end)) : 0
  return {
    type: 'bar',
    data: {
      labels: rows.map((r) => r.p.title),
      datasets: [
        {
          label: t('adminTimeline.ganttHeader'),
          data: rows.map((r) => [r.start, r.end] as [number, number]),
          backgroundColor: rows.map((r) => barColor(r.p)),
          borderRadius: 4,
          borderSkipped: false,
          barThickness: 16,
        },
      ],
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            title: (items) => rows[items[0]?.dataIndex ?? 0]?.p.title ?? '',
            label: (ctx) => {
              const r = rows[ctx.dataIndex]
              if (!r) return ''
              return [
                r.p.customer,
                `${t('adminReports.colStage')}: ${r.p.stage} (${r.p.probability}%)`,
                `${t('adminTimeline.colWinDate')}: ${fmtDate(r.p.startDate)}`,
                `${t('adminTimeline.devDaysLabel')}: ${fmtDays(r.p.devDays)} · ${t('adminTimeline.pmDaysLabel')}: ${fmtDays(r.p.pmDays)}`,
                `${t('adminTimeline.colIntegrations')}: ${r.p.integrationCount}`,
                `${t('adminTimeline.revenueLabel')}: ${revenueCell(r.p)}`,
              ]
            },
          },
        },
      },
      scales: {
        x: {
          min,
          max,
          position: 'top',
          afterBuildTicks: (axis) => {
            axis.ticks = monthTicks(min, max)
          },
          ticks: { callback: (v) => fmtTickMonth(Number(v)), color: COLOR_TEXT, autoSkip: false, maxRotation: 0 },
          grid: { color: '#eef1f6' },
        },
        y: { ticks: { color: COLOR_TEXT }, grid: { display: false } },
      },
    },
  }
})

const ganttHeight = computed(() => `${Math.max(220, 30 * spanned.value.length + 70)}px`)

// ── Monthly resource & revenue ─────────────────────────────────────────────
const resourceMonths = computed(() => {
  const map = new Map<string, { dev: number; pm: number; revenue: number; count: number }>()
  for (const p of timeline.value?.projects ?? []) {
    const key = p.startDate.slice(0, 7)
    const row = map.get(key) ?? { dev: 0, pm: 0, revenue: 0, count: 0 }
    row.dev += Number(p.devDays)
    row.pm += Number(p.pmDays)
    const rev = projectRevenue(p)
    if (null !== rev) row.revenue += rev
    row.count += 1
    map.set(key, row)
  }
  return [...map.entries()].sort((a, b) => a[0].localeCompare(b[0])).map(([month, v]) => ({ month, ...v }))
})

const resourceConfig = computed<ChartConfiguration>(() => {
  const months = resourceMonths.value
  return {
    type: 'bar',
    data: {
      labels: months.map((m) => fmtMonth(m.month)),
      datasets: [
        {
          label: t('adminTimeline.devDaysLabel'),
          data: months.map((m) => Math.round(m.dev * 100) / 100),
          backgroundColor: COLOR_BLUE,
          stack: 'effort',
          borderRadius: 4,
          yAxisID: 'y',
        },
        {
          label: t('adminTimeline.pmDaysLabel'),
          data: months.map((m) => Math.round(m.pm * 100) / 100),
          backgroundColor: COLOR_MUTED_BLUE,
          stack: 'effort',
          borderRadius: 4,
          yAxisID: 'y',
        },
        {
          type: 'line',
          label: t('adminTimeline.revenueLabel'),
          data: months.map((m) => Math.round(m.revenue)),
          borderColor: COLOR_PRIMARY,
          backgroundColor: COLOR_PRIMARY,
          tension: 0.3,
          pointRadius: 3,
          yAxisID: 'y1',
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'bottom',
          labels: { color: COLOR_TEXT, usePointStyle: true, pointStyle: 'circle', boxHeight: 7 },
        },
        tooltip: {
          callbacks: {
            label: (ctx) => {
              if ('y1' === ctx.dataset.yAxisID) return `${ctx.dataset.label}: ${fmtMoney(ctx.parsed.y ?? 0)}`
              return `${ctx.dataset.label}: ${fmtDays(ctx.parsed.y ?? 0)}`
            },
          },
        },
      },
      scales: {
        x: { stacked: true, ticks: { color: COLOR_TEXT }, grid: { display: false } },
        y: {
          stacked: true,
          position: 'left',
          title: { display: true, text: t('adminTimeline.daysAxis'), color: COLOR_TEXT },
          ticks: { color: COLOR_TEXT },
          grid: { color: '#eef1f6' },
        },
        y1: {
          position: 'right',
          ticks: { callback: (v) => fmtCompact(Number(v)), color: COLOR_TEXT },
          grid: { drawOnChartArea: false },
        },
      },
    },
  } as ChartConfiguration
})

// ── KPI cards ──────────────────────────────────────────────────────────────
const kpi = computed(() => {
  const ps = timeline.value?.projects ?? []
  let dev = 0
  let pm = 0
  let rev = 0
  let revComplete = true
  for (const p of ps) {
    dev += Number(p.devDays)
    pm += Number(p.pmDays)
    const r = projectRevenue(p)
    if (null === r) revComplete = false
    else rev += r
  }
  return { count: ps.length, dev, pm, rev, revComplete }
})
</script>

<template>
  <section class="admin">
    <div class="container-lg">
      <div class="admin-head">
        <span class="eyebrow">CRM</span>
        <h1>{{ t('adminTimeline.title') }}</h1>
        <p class="subtitle">{{ t('adminTimeline.subtitle') }}</p>
      </div>

      <!-- ── Filters ─────────────────────────────────────────────────── -->
      <div class="rep-panel rep-filterbar">
        <div class="rep-filters">
          <label class="rep-filter">
            <span>{{ t('adminReports.filterType') }}</span>
            <AppSelect v-model="filterTypeId" :options="typeSelectOptions" />
          </label>
          <label class="rep-filter">
            <span>{{ t('adminReports.filterUser') }}</span>
            <AppSelect v-model="filterUserId" :options="userSelectOptions" />
          </label>
          <label class="rep-filter">
            <span>{{ t('adminReports.filterNature') }}</span>
            <AppSelect v-model="filterNature" :options="natureSelectOptions" />
          </label>
          <label class="rep-filter">
            <span>{{ t('adminReports.filterCurrency') }}</span>
            <AppSelect v-model="filterCurrency" :options="currencySelectOptions" />
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

        <div v-if="rateEditCurrencies.length > 0" class="rate-bar">
          <span class="rate-bar-label">{{ t('adminReports.ratesLabel') }}:</span>
          <label v-for="c in rateEditCurrencies" :key="c" class="rate-field">
            <span>1 {{ c }} =</span>
            <input
              v-model="rateDraft[c]"
              type="text"
              inputmode="decimal"
              :placeholder="t('adminReports.ratePlaceholder')"
              @change="onRateChange"
            />
            <span>HUF</span>
          </label>
          <span v-if="rateSaved" class="rate-saved">✓ {{ t('adminCustomers.billingSaved') }}</span>
        </div>
        <p v-if="rateError" class="rate-msg rate-msg--error">{{ rateError }}</p>
        <p v-else-if="missingRateCurrencies.length > 0" class="rate-msg rate-msg--warn">
          {{ t('adminReports.rateMissing', { list: missingRateCurrencies.join(', ') }) }}
        </p>
      </div>

      <p v-if="loading" class="state">{{ t('adminTimeline.loading') }}</p>

      <div v-else-if="error" class="state state--error">
        <strong>{{ t('adminTimeline.loadError') }}</strong>
        <button type="button" class="btn-retry" @click="reload">{{ t('common.retry') }}</button>
      </div>

      <template v-else-if="timeline">
        <!-- ── KPI cards ─────────────────────────────────────────────── -->
        <div class="kpi-grid">
          <div class="kpi-card">
            <span class="kpi-label">{{ t('adminTimeline.kpiProjects') }}</span>
            <span class="kpi-value">{{ kpi.count }}</span>
          </div>
          <div class="kpi-card">
            <span class="kpi-label">{{ t('adminTimeline.kpiDevDays') }}</span>
            <span class="kpi-value">{{ fmtDays(kpi.dev) }}</span>
          </div>
          <div class="kpi-card">
            <span class="kpi-label">{{ t('adminTimeline.kpiPmDays') }}</span>
            <span class="kpi-value">{{ fmtDays(kpi.pm) }}</span>
          </div>
          <div class="kpi-card kpi-card--accent">
            <span class="kpi-label">{{ t('adminTimeline.kpiRevenue') }}</span>
            <span class="kpi-value">{{ fmtCompact(kpi.rev) }}</span>
            <span class="kpi-sub">{{ kpi.revComplete ? fmtMoney(kpi.rev) : t('adminReports.rateMissing', { list: missingRateCurrencies.join(', ') }) }}</span>
          </div>
        </div>

        <p v-if="!hasProjects" class="rep-panel muted">{{ t('adminTimeline.empty') }}</p>

        <template v-else>
          <!-- ── Gantt ─────────────────────────────────────────────────── -->
          <div class="rep-panel">
            <h2>{{ t('adminTimeline.ganttHeader') }}</h2>
            <p class="rep-hint">{{ t('adminTimeline.ganttHint') }}</p>
            <ReportChart :config="ganttConfig" :height="ganttHeight" />
          </div>

          <!-- ── Monthly resource & revenue ────────────────────────────── -->
          <div class="rep-panel">
            <h2>{{ t('adminTimeline.resourceHeader') }}</h2>
            <p class="rep-hint">{{ t('adminTimeline.resourceHint') }}</p>
            <ReportChart :config="resourceConfig" height="340px" />
          </div>

          <!-- ── Detail table ──────────────────────────────────────────── -->
          <div class="rep-panel">
            <h2>{{ t('adminTimeline.detailsHeader') }}</h2>
            <div class="table-wrap">
              <table class="rep-table">
                <thead>
                  <tr>
                    <th>{{ t('adminTimeline.colWinDate') }}</th>
                    <th>{{ t('adminTimeline.colProject') }}</th>
                    <th>{{ t('adminTimeline.colCustomer') }}</th>
                    <th>{{ t('adminReports.colStage') }}</th>
                    <th class="num">{{ t('adminTimeline.colDevDays') }}</th>
                    <th class="num">{{ t('adminTimeline.colPmDays') }}</th>
                    <th class="num">{{ t('adminTimeline.colIntegrations') }}</th>
                    <th class="num">{{ t('adminTimeline.colRevenue') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="p in timeline.projects" :key="p.id">
                    <td class="nowrap">{{ fmtDate(p.startDate) }}</td>
                    <td>
                      <span class="stage-name">{{ p.title }}</span>
                      <span class="badge" :class="`badge--${p.outcome}`">
                        {{ t('adminOpportunityTypes.outcome_' + p.outcome) }}
                      </span>
                    </td>
                    <td>{{ p.customer }}</td>
                    <td class="nowrap">{{ p.stage }} ({{ p.probability }}%)</td>
                    <td class="num">{{ fmtDays(p.devDays) }}</td>
                    <td class="num">{{ fmtDays(p.pmDays) }}</td>
                    <td class="num">{{ p.integrationCount }}</td>
                    <td class="num">{{ revenueCell(p) }}</td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr>
                    <td colspan="4">{{ t('adminTimeline.totalRow') }}</td>
                    <td class="num">{{ fmtDays(kpi.dev) }}</td>
                    <td class="num">{{ fmtDays(kpi.pm) }}</td>
                    <td class="num"></td>
                    <td class="num">{{ kpi.revComplete ? fmtMoney(kpi.rev) : '—' }}</td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </template>

        <p v-if="timeline.noDateCount > 0" class="rep-note">
          {{ t('adminTimeline.noDateNote', { count: timeline.noDateCount }) }}
        </p>
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

.rep-filter :deep(.app-select-toggle) {
  min-width: 170px;
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

/* ── Inline exchange rates ──────────────────────────────────────────── */
.rate-bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.6rem 1.1rem;
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid #eef1f6;
}

.rate-bar-label {
  color: var(--login-secondary, #0c1c40);
  font-size: 0.8rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.rate-field {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  color: #545f71;
  font-size: 0.9rem;
  font-weight: 600;
}

.rate-field input {
  width: 6.5rem;
  padding: 0.45rem 0.6rem;
  background: #f7f8fb;
  border: 1px solid #d4dae6;
  border-radius: 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.92rem;
  font-family: inherit;
  text-align: right;
}

.rate-field input:focus {
  outline: 2px solid var(--login-primary, #ed2044);
  outline-offset: -1px;
  background: #fff;
}

.rate-saved {
  color: #198754;
  font-size: 0.88rem;
  font-weight: 700;
}

.rate-msg {
  margin: 0.7rem 0 0;
  padding: 0.55rem 0.8rem;
  border-radius: 0.55rem;
  font-size: 0.85rem;
  font-weight: 600;
}

.rate-msg--warn {
  background: #fdf3e6;
  color: #8a5a18;
}

.rate-msg--error {
  background: #fde8ec;
  color: #b3122e;
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
  margin: 0 0 0.4rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.25rem;
  font-weight: 700;
}

.rep-hint {
  margin: 0 0 1.1rem;
  color: #8b94a6;
  font-size: 0.88rem;
}

/* ── Detail table ───────────────────────────────────────────────────── */
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

.rep-table .nowrap {
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
  margin: 0 0 1.5rem;
  padding: 0.7rem 0.9rem;
  background: #fdf3e6;
  border-radius: 0.55rem;
  color: #8a5a18;
  font-size: 0.88rem;
}

.muted {
  color: #8b94a6;
  font-size: 0.9rem;
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

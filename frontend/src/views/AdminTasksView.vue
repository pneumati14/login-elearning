<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import {
  useActivitiesStore,
  taskUrgency,
  formatDateTime,
  type ActivityType,
  type DashboardTask,
  type TaskScope,
  type Urgency,
} from '@/stores/activities'

type StatusFilter = 'all' | 'open' | 'closed'

const { t } = useI18n()
const store = useActivitiesStore()
const { dashboardTasks, dashboardLoading, dashboardError } = storeToRefs(store)

const scope = ref<TaskScope>('mine')
const statusFilter = ref<StatusFilter>('all')

const STATUS_FILTERS: StatusFilter[] = ['all', 'open', 'closed']

const ICONS: Record<ActivityType, string> = {
  call: '📞',
  meeting: '👥',
  email: '✉️',
  note: '📝',
  task: '✅',
}
function typeLabel(type: ActivityType): string {
  return t('adminCustomers.actType_' + type)
}

const URGENCIES: { key: Urgency; color: string }[] = [
  { key: 'overdue', color: '#b3122e' },
  { key: 'today', color: '#e8833a' },
  { key: 'week', color: '#2b59c3' },
  { key: 'later', color: '#9aa6bd' },
]

// Open / closed across every activity type.
const openItems = computed(() => dashboardTasks.value.filter((tk) => null === tk.completedAt))
const closedItems = computed(() => dashboardTasks.value.filter((tk) => null !== tk.completedAt))

// The donut summarises open *tasks* by due-date urgency — the actionable
// subset. Non-task events have no due date, so they stay out of it.
const openTaskItems = computed(() => openItems.value.filter((tk) => tk.type === 'task'))
const openTaskCount = computed(() => openTaskItems.value.length)

function urgencyOf(tk: DashboardTask): Urgency {
  return taskUrgency(tk.occurredAt)
}

const segments = computed(() =>
  URGENCIES.map((u) => ({
    ...u,
    count: openTaskItems.value.filter((tk) => urgencyOf(tk) === u.key).length,
  })),
)

// ── Donut geometry ────────────────────────────────────────────────────
const R = 60
const C = 2 * Math.PI * R

const donut = computed(() => {
  const total = openTaskCount.value
  let acc = 0
  return segments.value
    .filter((s) => s.count > 0)
    .map((s) => {
      const len = (s.count / total) * C
      const seg = { color: s.color, dash: `${len} ${C - len}`, offset: -acc }
      acc += len
      return seg
    })
})

// Open soonest-first (urgency); closed most-recently-closed first.
const sortedOpen = computed(() =>
  [...openItems.value].sort((a, b) => (a.occurredAt < b.occurredAt ? -1 : a.occurredAt > b.occurredAt ? 1 : a.id - b.id)),
)
const sortedClosed = computed(() =>
  [...closedItems.value].sort((a, b) => {
    const ax = a.completedAt ?? ''
    const bx = b.completedAt ?? ''
    return ax > bx ? -1 : ax < bx ? 1 : b.id - a.id
  }),
)
const showOpen = computed(() => statusFilter.value !== 'closed')
const showClosed = computed(() => statusFilter.value !== 'open')

function colorOf(tk: DashboardTask): string {
  if (tk.type !== 'task') return '#9aa6bd'
  return URGENCIES.find((u) => u.key === urgencyOf(tk))?.color ?? '#9aa6bd'
}

async function reload(): Promise<void> {
  await store.fetchTasks(scope.value)
}

function setScope(s: TaskScope): void {
  if (scope.value === s) return
  scope.value = s
  reload()
}

async function onToggleDone(tk: DashboardTask): Promise<void> {
  const result = await store.toggleTaskDone(tk)
  if (!result.ok) window.alert(result.error ?? t('admin.saveFailed'))
}

onMounted(reload)
</script>

<template>
  <section class="admin">
    <div class="container-lg">
      <div class="admin-head">
        <span class="eyebrow">{{ t('admin.eyebrow') }}</span>
        <h1>{{ t('nav.adminTasks') }}</h1>
        <p>{{ t('adminTasks.subtitle') }}</p>
      </div>

      <!-- ── Scope + status filter ─────────────────────────────────── -->
      <div class="tk-filters">
        <div class="scope-toggle" role="tablist">
          <button type="button" :class="{ 'is-active': scope === 'mine' }" @click="setScope('mine')">
            {{ t('adminTasks.scopeMine') }}
          </button>
          <button type="button" :class="{ 'is-active': scope === 'all' }" @click="setScope('all')">
            {{ t('adminTasks.scopeAll') }}
          </button>
        </div>

        <div class="scope-toggle" role="tablist">
          <button
            v-for="f in STATUS_FILTERS"
            :key="f"
            type="button"
            :class="{ 'is-active': statusFilter === f }"
            @click="statusFilter = f"
          >
            {{ t('adminTasks.filter_' + f) }}
          </button>
        </div>
      </div>

      <p v-if="dashboardLoading" class="state">{{ t('adminTasks.loading') }}</p>

      <div v-else-if="dashboardError" class="state state--error">
        <strong>{{ t('adminTasks.loadError') }}</strong>
        <button type="button" class="btn-retry" @click="reload">{{ t('common.retry') }}</button>
      </div>

      <template v-else>
        <!-- ── Chart card ─────────────────────────────────────────── -->
        <div class="tk-panel chart-card">
          <svg class="donut" viewBox="0 0 160 160" role="img" :aria-label="t('adminTasks.openTasks')">
            <circle class="donut-track" cx="80" cy="80" :r="R" fill="none" stroke="#eef1f6" stroke-width="18" />
            <g transform="rotate(-90 80 80)">
              <circle
                v-for="(seg, i) in donut"
                :key="i"
                cx="80"
                cy="80"
                :r="R"
                fill="none"
                :stroke="seg.color"
                stroke-width="18"
                :stroke-dasharray="seg.dash"
                :stroke-dashoffset="seg.offset"
              />
            </g>
            <text x="80" y="74" text-anchor="middle" class="donut-num">{{ openTaskCount }}</text>
            <text x="80" y="92" text-anchor="middle" class="donut-label">{{ t('adminTasks.openTotal') }}</text>
          </svg>

          <ul class="legend">
            <li v-for="s in segments" :key="s.key">
              <span class="legend-dot" :style="{ background: s.color }"></span>
              <span class="legend-label">{{ t('adminTasks.urgency_' + s.key) }}</span>
              <span class="legend-count">{{ s.count }}</span>
            </li>
            <li class="legend--done">
              <span class="legend-dot" style="background: #1c7a45"></span>
              <span class="legend-label">{{ t('adminTasks.completed') }}</span>
              <span class="legend-count">{{ closedItems.length }}</span>
            </li>
          </ul>
        </div>

        <!-- ── Open ───────────────────────────────────────────────── -->
        <div v-if="showOpen" class="tk-panel">
          <h2 class="tk-section-head">
            {{ t('adminTasks.openTasks') }} <span class="tk-count">{{ sortedOpen.length }}</span>
          </h2>

          <p v-if="sortedOpen.length === 0" class="state">{{ t('adminTasks.empty') }}</p>

          <ul v-else class="tk-list">
            <li v-for="tk in sortedOpen" :key="tk.id" class="tk-row">
              <label class="tk-check" :title="t('adminCustomers.actClose')">
                <input type="checkbox" :checked="false" @change="onToggleDone(tk)" />
              </label>
              <span class="tk-icon" :title="typeLabel(tk.type)">{{ ICONS[tk.type] }}</span>
              <span class="tk-due" :style="{ color: colorOf(tk) }">{{ formatDateTime(tk.occurredAt) }}</span>
              <div class="tk-main">
                <span class="tk-subject">{{ tk.subject }}</span>
                <span class="tk-meta">
                  <RouterLink :to="{ name: 'admin-customer-detail', params: { id: tk.customerId } }" class="tk-cust">
                    {{ tk.customerName }}
                  </RouterLink>
                  <span class="tk-chip">{{ typeLabel(tk.type) }}</span>
                  <span v-if="tk.opportunityTitle" class="tk-chip tk-chip--opp">{{ tk.opportunityTitle }}</span>
                  <span v-if="tk.createdByName" class="tk-by">· {{ tk.createdByName }}</span>
                </span>
              </div>
            </li>
          </ul>
        </div>

        <!-- ── Closed ─────────────────────────────────────────────── -->
        <div v-if="showClosed" class="tk-panel">
          <h2 class="tk-section-head tk-section-head--closed">
            {{ t('adminTasks.closedTitle') }} <span class="tk-count">{{ sortedClosed.length }}</span>
          </h2>

          <p v-if="sortedClosed.length === 0" class="state">{{ t('adminTasks.emptyClosed') }}</p>

          <ul v-else class="tk-list">
            <li v-for="tk in sortedClosed" :key="tk.id" class="tk-row is-done">
              <label class="tk-check" :title="t('adminCustomers.actReopen')">
                <input type="checkbox" checked @change="onToggleDone(tk)" />
              </label>
              <span class="tk-icon" :title="typeLabel(tk.type)">{{ ICONS[tk.type] }}</span>
              <span class="tk-due">{{ formatDateTime(tk.occurredAt) }}</span>
              <div class="tk-main">
                <span class="tk-subject">{{ tk.subject }}</span>
                <span class="tk-meta">
                  <RouterLink :to="{ name: 'admin-customer-detail', params: { id: tk.customerId } }" class="tk-cust">
                    {{ tk.customerName }}
                  </RouterLink>
                  <span class="tk-chip">{{ typeLabel(tk.type) }}</span>
                  <span v-if="tk.opportunityTitle" class="tk-chip tk-chip--opp">{{ tk.opportunityTitle }}</span>
                </span>
              </div>
            </li>
          </ul>
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
  margin-bottom: 1.6rem;
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

.admin-head p {
  max-width: 640px;
  margin: 0;
  color: #545f71;
  font-size: 1.05rem;
  line-height: 1.5;
}

.tk-filters {
  display: flex;
  flex-wrap: wrap;
  gap: 0.8rem;
  margin-bottom: 1.5rem;
}

.scope-toggle {
  display: inline-flex;
  border: 1px solid #d4dae6;
  border-radius: 0.55rem;
  overflow: hidden;
}

.scope-toggle button {
  padding: 0.5rem 1.2rem;
  background: #fff;
  border: none;
  color: #545f71;
  font-size: 0.9rem;
  font-weight: 700;
  cursor: pointer;
}

.scope-toggle button.is-active {
  background: var(--login-primary, #ed2044);
  color: #fff;
}

.tk-panel {
  margin-bottom: 1.5rem;
  padding: 1.85rem;
  background: #fff;
  border-radius: 1.1rem;
  box-shadow: 0 12px 32px rgba(12, 28, 64, 0.08);
}

/* ── Chart ────────────────────────────────────────────────────────── */
.chart-card {
  display: flex;
  align-items: center;
  gap: 2.5rem;
  flex-wrap: wrap;
}

.donut {
  width: 180px;
  height: 180px;
  flex-shrink: 0;
}

.donut-num {
  fill: var(--login-secondary, #0c1c40);
  font-size: 2rem;
  font-weight: 700;
}

.donut-label {
  fill: #8b94a6;
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.legend {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
  min-width: 12rem;
}

.legend li {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

.legend--done {
  margin-top: 0.4rem;
  padding-top: 0.6rem;
  border-top: 1px solid #eef1f6;
}

.legend-dot {
  width: 0.85rem;
  height: 0.85rem;
  border-radius: 0.25rem;
  flex-shrink: 0;
}

.legend-label {
  color: var(--login-secondary, #0c1c40);
  font-size: 0.92rem;
  font-weight: 600;
}

.legend-count {
  margin-left: auto;
  color: var(--login-secondary, #0c1c40);
  font-size: 1rem;
  font-weight: 700;
}

/* ── List ─────────────────────────────────────────────────────────── */
.tk-section-head {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  margin: 0 0 1.1rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.25rem;
  font-weight: 700;
}

.tk-section-head--closed {
  color: #545f71;
}

.tk-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.5rem;
  height: 1.5rem;
  padding: 0 0.45rem;
  background: #eef1f6;
  border-radius: 100vw;
  color: #545f71;
  font-size: 0.85rem;
  font-weight: 700;
}

.tk-icon {
  flex-shrink: 0;
  font-size: 1.05rem;
  line-height: 1.4;
}

.btn-ghost {
  padding: 0.45rem 1rem;
  background: #fff;
  border: 1px solid #d4dae6;
  border-radius: 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.85rem;
  font-weight: 700;
  cursor: pointer;
}

.btn-ghost:hover {
  border-color: var(--login-secondary, #0c1c40);
}

.tk-list {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.tk-row {
  display: flex;
  align-items: center;
  gap: 0.8rem;
  padding: 0.6rem 0.75rem;
  background: #fff;
  border: 1px solid #e3e7ee;
  border-radius: 0.55rem;
}

.tk-row.is-done {
  opacity: 0.65;
}

.tk-check input[type='checkbox'] {
  width: 1.05rem;
  height: 1.05rem;
  accent-color: #1c7a45;
  cursor: pointer;
}

.tk-due {
  flex-shrink: 0;
  width: 9.5rem;
  font-size: 0.84rem;
  font-weight: 700;
  white-space: nowrap;
}

.tk-main {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  min-width: 0;
}

.tk-subject {
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-weight: 700;
  word-break: break-word;
}

.is-done .tk-subject {
  text-decoration: line-through;
}

.tk-meta {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
  font-size: 0.8rem;
  color: #8b94a6;
}

.tk-cust {
  color: var(--login-primary, #ed2044);
  font-weight: 700;
  text-decoration: none;
}

.tk-cust:hover {
  text-decoration: underline;
}

.tk-chip {
  padding: 0.02rem 0.4rem;
  background: #fdeef1;
  border-radius: 0.4rem;
  color: #b3122e;
  font-weight: 600;
}

.tk-completed-head {
  margin: 1.6rem 0 0.8rem;
  color: #8b94a6;
  font-size: 0.9rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
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
  flex-direction: column;
  align-items: flex-start;
  gap: 0.6rem;
  background: #fde8ec;
  color: #b3122e;
}

.btn-retry {
  padding: 0.4rem 0.9rem;
  background: var(--login-primary, #ed2044);
  border: none;
  border-radius: 0.45rem;
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}

@media (max-width: 575.98px) {
  .tk-row {
    flex-wrap: wrap;
  }

  .tk-due {
    width: auto;
  }
}
</style>

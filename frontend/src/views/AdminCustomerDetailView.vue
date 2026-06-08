<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  useCustomersStore,
  currentSalesAssignments,
  type Customer,
  type CustomerStatus,
  type Address,
  type SalesAssignment,
} from '@/stores/customers'
import { useOpportunitiesStore } from '@/stores/opportunities'
import { useActivitiesStore, formatDateTime, taskUrgency, type Urgency } from '@/stores/activities'
import { useMoneyFormat } from '@/stores/currencySettings'
import CustomerEditor from '@/components/CustomerEditor.vue'
import CustomerFeesPanel from '@/components/CustomerFeesPanel.vue'
import CustomerBillingPanel from '@/components/CustomerBillingPanel.vue'
import CustomerCardsPanel from '@/components/CustomerCardsPanel.vue'
import CustomerInstalledDevicesPanel from '@/components/CustomerInstalledDevicesPanel.vue'
import CustomerContactsPanel from '@/components/CustomerContactsPanel.vue'
import CustomerOpportunitiesPanel from '@/components/CustomerOpportunitiesPanel.vue'
import CustomerArchitecturePanel from '@/components/CustomerArchitecturePanel.vue'
import ActivityList from '@/components/ActivityList.vue'
import IconEdit from '@/components/icons/IconEdit.vue'

const { t } = useI18n()
const fmtMoney = useMoneyFormat()
const route = useRoute()
const store = useCustomersStore()

const id = computed(() => Number(route.params.id))
// Source the customer from the store so edits and sales-assignment changes
// (which mutate the store) reflect here without an extra refetch.
const customer = computed<Customer | null>(() => store.customers.find((c) => c.id === id.value) ?? null)
const loading = ref(true)
const notFound = ref(false)
const editing = ref(false)

type TabKey = 'overview' | 'fees' | 'billing' | 'cards' | 'devices' | 'contacts' | 'opportunities' | 'architecture' | 'timeline'
const activeTab = ref<TabKey>('overview')

const tabs: { key: TabKey; label: string; ready: boolean }[] = [
  { key: 'overview', label: 'tabOverview', ready: true },
  { key: 'fees', label: 'tabFees', ready: true },
  { key: 'billing', label: 'tabBilling', ready: true },
  { key: 'cards', label: 'tabCards', ready: true },
  { key: 'devices', label: 'tabDevices', ready: true },
  { key: 'contacts', label: 'tabContacts', ready: true },
  { key: 'opportunities', label: 'tabOpportunities', ready: true },
  { key: 'architecture', label: 'tabArchitecture', ready: true },
  { key: 'timeline', label: 'tabTimeline', ready: true },
]

const opportunitiesStore = useOpportunitiesStore()
const activitiesStore = useActivitiesStore()

async function load(): Promise<void> {
  loading.value = true
  notFound.value = false
  editing.value = false
  const result = await store.fetchCustomer(id.value)
  notFound.value = null === result
  loading.value = false
  if (null !== result) {
    // Overview widgets: open deals, tasks and the latest activity.
    opportunitiesStore.fetchOpportunities(id.value)
    activitiesStore.fetchActivities(id.value)
  }
}

onMounted(load)

// Support in-app navigation between detail pages (id param changes).
watch(id, load)

const activeSalesIds = computed(() => {
  if (null === customer.value) return new Set<number>()
  return new Set(currentSalesAssignments(customer.value.salesAssignments).map((a) => a.id))
})

function formatDate(d: string | null): string {
  return null === d || '' === d ? '—' : d
}

function validityLabel(c: Customer): string {
  if (null === c.validFrom && null === c.validUntil) return t('adminCustomers.validityOpen')
  return `${formatDate(c.validFrom)} → ${formatDate(c.validUntil)}`
}

function feeLines(c: Customer): string[] {
  if (0 === c.monthlyFeeTotals.length) return ['—']
  return c.monthlyFeeTotals.map((tt) =>
    fmtMoney(tt.amount, tt.currency),
  )
}

// ── Overview widgets ─────────────────────────────────────────────────
/** Company monogram for the hero avatar (first letters of two words). */
const monogram = computed(() => {
  const words = (customer.value?.name ?? '').trim().split(/\s+/).filter(Boolean)
  if (0 === words.length) return '?'
  return words
    .slice(0, 2)
    .map((w) => w[0]!.toUpperCase())
    .join('')
})

const openOpportunities = computed(() =>
  (opportunitiesStore.byCustomer[id.value] ?? []).filter((o) => 'open' === o.stageOutcome),
)

/** Per-currency open-deal value lines (line-item totals when present). */
const openOppLines = computed(() => {
  const sums: Record<string, number> = {}
  for (const o of openOpportunities.value) {
    const value = Number(o.hasLineItems ? o.lineItemsTotal : (o.value ?? 0))
    if (0 === value) continue
    sums[o.currency] = (sums[o.currency] ?? 0) + value
  }
  return Object.entries(sums)
    .sort(([a], [b]) => a.localeCompare(b))
    .map(([currency, sum]) =>
      fmtMoney(sum, currency),
    )
})

const openTasks = computed(() =>
  (activitiesStore.byCustomer[id.value] ?? [])
    .filter((a) => a.isOpenTask)
    .sort((a, b) => (a.occurredAt < b.occurredAt ? -1 : 1))
    .slice(0, 3),
)

const lastActivity = computed(() => {
  const list = activitiesStore.byCustomer[id.value] ?? []
  if (0 === list.length) return null
  return [...list].sort((a, b) => (a.occurredAt > b.occurredAt ? -1 : 1))[0] ?? null
})

const primaryContact = computed(() => {
  const contacts = customer.value?.contacts ?? []
  return contacts.find((c) => c.isPrimary) ?? contacts[0] ?? null
})

function contactName(c: { firstName: string; lastName: string; email: string | null }): string {
  return `${c.lastName} ${c.firstName}`.trim() || c.email || '—'
}

const ACTIVITY_ICONS: Record<string, string> = {
  call: '📞',
  meeting: '👥',
  email: '✉️',
  note: '📝',
  task: '✅',
}

const URGENCY_COLORS: Record<Urgency, string> = {
  overdue: '#b3122e',
  today: '#e8833a',
  week: '#2b59c3',
  later: '#9aa6bd',
}

function urgencyColor(iso: string): string {
  return URGENCY_COLORS[taskUrgency(iso)]
}

/** Normalised external link for the website chip. */
const websiteHref = computed(() => {
  const site = customer.value?.website
  if (!site) return null
  return /^https?:\/\//i.test(site) ? site : `https://${site}`
})

// Quick status flip from the overview header — saves immediately.
const statusSaving = ref(false)

async function onSetStatus(status: CustomerStatus): Promise<void> {
  if (null === customer.value || customer.value.status === status || statusSaving.value) return
  statusSaving.value = true
  const result = await store.setStatus(customer.value.id, status)
  statusSaving.value = false
  if (!result.ok) window.alert(result.error ?? t('admin.saveFailed'))
}

function addressLine(a: Address): string {
  const parts: string[] = []
  if (a.postalCode) parts.push(a.postalCode)
  if (a.city) parts.push(a.city)
  if (a.street) parts.push(a.street)
  const tail = parts.join(', ')
  if (a.country) return tail ? `${a.country} · ${tail}` : a.country
  return tail || '—'
}

function salesPeriod(a: SalesAssignment): string {
  if (null === a.validFrom && null === a.validUntil) return t('adminCustomers.validityOpen')
  return `${formatDate(a.validFrom)} → ${formatDate(a.validUntil)}`
}
</script>

<template>
  <section class="admin">
    <div class="container-lg">
      <RouterLink :to="{ name: 'admin-customers' }" class="back-link">
        ← {{ t('adminCustomers.backToList') }}
      </RouterLink>

      <p v-if="loading" class="state">{{ t('adminCustomers.loading') }}</p>

      <p v-else-if="notFound" class="state state--error">{{ t('adminCustomers.notFound') }}</p>

      <template v-else-if="customer">
        <div class="admin-head">
          <span class="eyebrow">{{ t('nav.adminCustomers') }}</span>
          <h1>{{ customer.name }}</h1>
        </div>

        <!-- ── Tabs ─────────────────────────────────────────────────── -->
        <div class="tabs" role="tablist">
          <button
            v-for="tab in tabs"
            :key="tab.key"
            type="button"
            role="tab"
            class="tab"
            :class="{ 'is-active': activeTab === tab.key }"
            :aria-selected="activeTab === tab.key"
            @click="activeTab = tab.key"
          >
            {{ t('adminCustomers.' + tab.label) }}
          </button>
        </div>

        <!-- ── Overview ─────────────────────────────────────────────── -->
        <template v-if="activeTab === 'overview'">
        <!-- Edit mode keeps the plain editor panel -->
        <div v-if="editing" class="cust-panel">
          <CustomerEditor :customer="customer" @saved="editing = false" @cancel="editing = false" />
        </div>

        <template v-else>
        <!-- ── Hero: monogram, name, status, quick contacts ─────────── -->
        <div class="hero">
          <div class="hero-avatar" aria-hidden="true">{{ monogram }}</div>
          <div class="hero-main">
            <div class="hero-name-row">
              <h2 class="hero-name">{{ customer.name }}</h2>
              <div class="ov-status-switch" :class="{ 'is-saving': statusSaving }">
                <button
                  type="button"
                  class="ov-status-btn ov-status-btn--existing"
                  :class="{ 'is-active': customer.status === 'existing' }"
                  :disabled="statusSaving"
                  @click="onSetStatus('existing')"
                >
                  {{ t('adminCustomers.status_existing') }}
                </button>
                <button
                  type="button"
                  class="ov-status-btn ov-status-btn--potential"
                  :class="{ 'is-active': customer.status === 'potential' }"
                  :disabled="statusSaving"
                  @click="onSetStatus('potential')"
                >
                  {{ t('adminCustomers.status_potential') }}
                </button>
              </div>
            </div>
            <div class="hero-chips">
              <a v-if="customer.email" :href="`mailto:${customer.email}`" class="hero-chip">✉ {{ customer.email }}</a>
              <a v-if="customer.phone" :href="`tel:${customer.phone}`" class="hero-chip">☎ {{ customer.phone }}</a>
              <a v-if="websiteHref" :href="websiteHref" target="_blank" rel="noopener" class="hero-chip">🌐 {{ customer.website }}</a>
              <span v-if="!customer.email && !customer.phone && !websiteHref" class="hero-chip hero-chip--empty">
                {{ t('adminCustomers.ovNoContactData') }}
              </span>
            </div>
          </div>
          <button type="button" class="btn-edit hero-edit" @click="editing = true">
            <IconEdit />
            <span>{{ t('admin.edit') }}</span>
          </button>
        </div>

        <!-- ── Two columns: data left, snapshot sidebar right ────────── -->
        <div class="ov-layout">
          <div class="ov-col-main">
            <div class="ov-section">
              <h3 class="ov-section-title">🏢 {{ t('adminCustomers.overviewGeneral') }}</h3>
              <dl class="info-grid">
                <div class="info">
                  <dt>{{ t('adminCustomers.taxNumber') }}</dt>
                  <dd>{{ customer.taxNumber || '—' }}</dd>
                </div>
                <div class="info">
                  <dt>{{ t('adminCustomers.colValidity') }}</dt>
                  <dd>{{ validityLabel(customer) }}</dd>
                </div>
                <div class="info info--wide">
                  <dt>{{ t('adminCustomers.notes') }}</dt>
                  <dd class="dd-notes">{{ customer.notes || '—' }}</dd>
                </div>
              </dl>
            </div>

            <div class="ov-section">
              <h3 class="ov-section-title">📍 {{ t('adminCustomers.ovAddresses') }}</h3>
              <div class="addr-row">
                <div class="addr-box">
                  <span class="addr-box-label">{{ t('adminCustomers.address') }}</span>
                  <p class="addr-text">{{ addressLine(customer.address) }}</p>
                </div>
                <div class="addr-box">
                  <span class="addr-box-label">{{ t('adminCustomers.billingAddress') }}</span>
                  <p class="addr-text">{{ addressLine(customer.billingAddress) }}</p>
                </div>
              </div>
            </div>

            <div class="ov-section">
              <h3 class="ov-section-title">👥 {{ t('adminCustomers.salesHeader') }}</h3>
              <p v-if="customer.salesAssignments.length === 0" class="muted">
                {{ t('adminCustomers.salesEmpty') }}
              </p>
              <ul v-else class="sales-list">
                <li v-for="a in customer.salesAssignments" :key="a.id" class="sales-item">
                  <span class="sales-name">
                    {{ a.userName || a.userEmail }}
                    <span v-if="activeSalesIds.has(a.id)" class="badge">{{ t('adminCustomers.salesActive') }}</span>
                  </span>
                  <span class="sales-period">{{ salesPeriod(a) }}</span>
                  <span v-if="a.notes" class="sales-notes">{{ a.notes }}</span>
                </li>
              </ul>
            </div>
          </div>

          <div class="ov-col-side">
            <button type="button" class="side-card side-card--accent" @click="activeTab = 'fees'">
              <span class="side-label">💰 {{ t('adminCustomers.monthlyFee') }}</span>
              <span class="side-value side-value--light">
                <span v-for="(line, i) in feeLines(customer)" :key="i">{{ line }}</span>
              </span>
              <span class="side-link">{{ t('adminCustomers.feeCardHint') }} →</span>
            </button>

            <button type="button" class="side-card" @click="activeTab = 'opportunities'">
              <span class="side-label">🎯 {{ t('adminCustomers.ovOpenOpps') }}</span>
              <span class="side-value">
                {{ t('adminCustomers.ovDealsCount', { count: openOpportunities.length }) }}
              </span>
              <span v-for="(line, i) in openOppLines" :key="i" class="side-extra">{{ line }}</span>
              <span class="side-link">{{ t('adminCustomers.feeCardHint') }} →</span>
            </button>

            <button type="button" class="side-card" @click="activeTab = 'timeline'">
              <span class="side-label">✅ {{ t('adminCustomers.ovTasks') }}</span>
              <span v-if="openTasks.length === 0" class="side-extra">{{ t('adminCustomers.ovNoTasks') }}</span>
              <span v-for="tk in openTasks" :key="tk.id" class="side-task">
                <span class="side-task-dot" :style="{ background: urgencyColor(tk.occurredAt) }" />
                <span class="side-task-text">
                  <span class="side-task-subject">{{ tk.subject }}</span>
                  <span class="side-task-due">{{ formatDateTime(tk.occurredAt) }}</span>
                </span>
              </span>
              <span class="side-link">{{ t('adminCustomers.feeCardHint') }} →</span>
            </button>

            <button type="button" class="side-card" @click="activeTab = 'timeline'">
              <span class="side-label">🕒 {{ t('adminCustomers.ovLastActivity') }}</span>
              <template v-if="lastActivity">
                <span class="side-value side-value--text">
                  {{ ACTIVITY_ICONS[lastActivity.type] }} {{ lastActivity.subject }}
                </span>
                <span class="side-extra">{{ formatDateTime(lastActivity.occurredAt) }}</span>
              </template>
              <span v-else class="side-extra">{{ t('adminCustomers.ovNoActivity') }}</span>
            </button>

            <button type="button" class="side-card" @click="activeTab = 'contacts'">
              <span class="side-label">👤 {{ t('adminCustomers.tabContacts') }}</span>
              <span class="side-value">{{ customer.contacts.length }}</span>
              <template v-if="primaryContact">
                <span class="side-extra side-extra--strong">{{ contactName(primaryContact) }}</span>
                <span v-if="primaryContact.email" class="side-extra">{{ primaryContact.email }}</span>
              </template>
              <span v-else class="side-extra">{{ t('adminCustomers.ovNoContacts') }}</span>
              <span class="side-link">{{ t('adminCustomers.feeCardHint') }} →</span>
            </button>
          </div>
        </div>
        </template>
        </template>

        <!-- ── Monthly fees ─────────────────────────────────────────── -->
        <div v-else-if="activeTab === 'fees'" class="cust-panel">
          <CustomerFeesPanel :customer="customer" />
        </div>

        <!-- ── Billing (contract + invoicing data) ──────────────────── -->
        <div v-else-if="activeTab === 'billing'" class="cust-panel">
          <CustomerBillingPanel :customer="customer" />
        </div>

        <!-- ── Cards ────────────────────────────────────────────────── -->
        <div v-else-if="activeTab === 'cards'" class="cust-panel">
          <CustomerCardsPanel :customer="customer" />
        </div>

        <!-- ── Installed devices ────────────────────────────────────── -->
        <div v-else-if="activeTab === 'devices'" class="cust-panel">
          <CustomerInstalledDevicesPanel :customer="customer" />
        </div>

        <!-- ── Contacts ─────────────────────────────────────────────── -->
        <div v-else-if="activeTab === 'contacts'" class="cust-panel">
          <CustomerContactsPanel :customer="customer" />
        </div>

        <!-- ── Opportunities (kanban) ───────────────────────────────── -->
        <div v-else-if="activeTab === 'opportunities'" class="cust-panel">
          <CustomerOpportunitiesPanel :customer="customer" />
        </div>

        <!-- ── Architecture ─────────────────────────────────────────── -->
        <div v-else-if="activeTab === 'architecture'" class="cust-panel">
          <CustomerArchitecturePanel :customer="customer" />
        </div>

        <!-- ── Timeline (activities) ────────────────────────────────── -->
        <div v-else-if="activeTab === 'timeline'" class="cust-panel">
          <ActivityList :customer="customer" />
        </div>
      </template>
    </div>
  </section>
</template>

<style scoped>
.admin {
  padding: 3.5rem 0 5rem;
}

.back-link {
  display: inline-block;
  margin-bottom: 1.4rem;
  color: var(--login-primary, #ed2044);
  font-size: 0.9rem;
  font-weight: 700;
  text-decoration: none;
}

.back-link:hover {
  text-decoration: underline;
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
  margin: 0.35rem 0 0;
  color: var(--login-secondary, #0c1c40);
  font-size: 2.2rem;
  font-weight: 700;
}

.tabs {
  display: flex;
  gap: 0.3rem;
  margin-bottom: 1.4rem;
  border-bottom: 2px solid #e3e7ee;
  flex-wrap: wrap;
}

.tab {
  padding: 0.7rem 1.1rem;
  margin-bottom: -2px;
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  color: #8b94a6;
  font-size: 0.95rem;
  font-weight: 700;
  cursor: pointer;
}

.tab:hover {
  color: var(--login-secondary, #0c1c40);
}

.tab.is-active {
  color: var(--login-secondary, #0c1c40);
  border-bottom-color: var(--login-primary, #ed2044);
}

.cust-panel {
  padding: 1.85rem;
  background: #fff;
  border-radius: 1.1rem;
  box-shadow: 0 12px 32px rgba(12, 28, 64, 0.08);
}

.overview-head {
  display: flex;
  justify-content: flex-end;
  margin-bottom: 1rem;
}

.overview-head:empty {
  display: none;
}

.btn-edit {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.45rem 0.9rem;
  background: #fff;
  border: 1px solid #d4dae6;
  border-radius: 0.45rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.85rem;
  font-weight: 700;
  cursor: pointer;
}

.btn-edit:hover {
  border-color: var(--login-secondary, #0c1c40);
}

.block {
  margin: 0 0 1.3rem;
  padding: 1rem 1.1rem 1.2rem;
  border: 1px solid #e3e7ee;
  border-radius: 0.7rem;
}

.block:last-child {
  margin-bottom: 0;
}

.block legend {
  padding: 0 0.45rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.9rem;
  font-weight: 700;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.9rem 1.4rem;
  margin: 0;
}

.info--wide {
  grid-column: 1 / -1;
}

.info dt {
  color: #8b94a6;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.info dd {
  margin: 0.2rem 0 0;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.98rem;
  word-break: break-word;
}

.dd-notes {
  white-space: pre-wrap;
}

.addr-row {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1.3rem;
  margin-bottom: 1.3rem;
}

.addr-text {
  margin: 0;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.98rem;
}

.muted {
  margin: 0;
  color: #8b94a6;
  font-size: 0.92rem;
}

.sales-list {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.sales-item {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  padding: 0.6rem 0.8rem;
  background: #f7f8fb;
  border-radius: 0.55rem;
}

.sales-name {
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-weight: 700;
}

.badge {
  display: inline-block;
  margin-left: 0.4rem;
  padding: 0.05rem 0.45rem;
  background: #e3f6ec;
  border-radius: 0.4rem;
  color: #1c7a45;
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  vertical-align: middle;
}

/* ── Overview: hero ─────────────────────────────────────────────────── */
.hero {
  display: flex;
  align-items: center;
  gap: 1.3rem;
  flex-wrap: wrap;
  margin-bottom: 1.5rem;
  padding: 1.6rem 1.85rem;
  background: linear-gradient(135deg, var(--login-secondary, #0c1c40) 0%, #1b3263 100%);
  border-radius: 1.1rem;
  box-shadow: 0 12px 32px rgba(12, 28, 64, 0.18);
}

.hero-avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 4.2rem;
  height: 4.2rem;
  background: var(--login-primary, #ed2044);
  border-radius: 1rem;
  color: #fff;
  font-size: 1.6rem;
  font-weight: 800;
  letter-spacing: 0.02em;
}

.hero-main {
  flex: 1 1 320px;
  min-width: 0;
}

.hero-name-row {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex-wrap: wrap;
  margin-bottom: 0.55rem;
}

.hero-name {
  margin: 0;
  color: #fff;
  font-size: 1.55rem;
  font-weight: 700;
  word-break: break-word;
}

.hero-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
}

.hero-chip {
  display: inline-block;
  padding: 0.32rem 0.8rem;
  background: rgba(255, 255, 255, 0.12);
  border-radius: 2rem;
  color: #dfe6f3;
  font-size: 0.85rem;
  font-weight: 600;
  text-decoration: none;
  word-break: break-all;
}

a.hero-chip:hover {
  background: rgba(255, 255, 255, 0.22);
  color: #fff;
}

.hero-chip--empty {
  opacity: 0.6;
}

.hero-edit {
  flex-shrink: 0;
  background: #fff;
}

/* ── Overview: two-column layout ────────────────────────────────────── */
.ov-layout {
  display: grid;
  grid-template-columns: minmax(0, 2fr) minmax(260px, 1fr);
  gap: 1.5rem;
  align-items: start;
}

.ov-col-main {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
  min-width: 0;
}

.ov-section {
  padding: 1.6rem 1.85rem;
  background: #fff;
  border-radius: 1.1rem;
  box-shadow: 0 12px 32px rgba(12, 28, 64, 0.08);
}

.ov-section-title {
  margin: 0 0 1.1rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.05rem;
  font-weight: 700;
}

.addr-box {
  flex: 1 1 240px;
  padding: 0.9rem 1rem;
  background: #f7f8fb;
  border-radius: 0.7rem;
}

.addr-box-label {
  display: block;
  margin-bottom: 0.3rem;
  color: #8b94a6;
  font-size: 0.74rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

/* ── Overview: sidebar snapshot cards ───────────────────────────────── */
.ov-col-side {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  min-width: 0;
}

.side-card {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.35rem;
  width: 100%;
  padding: 1.1rem 1.3rem;
  background: #fff;
  border: none;
  border-radius: 1.1rem;
  box-shadow: 0 12px 32px rgba(12, 28, 64, 0.08);
  text-align: left;
  font-family: inherit;
  cursor: pointer;
  transition: transform 0.12s, box-shadow 0.12s;
}

.side-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 16px 36px rgba(12, 28, 64, 0.12);
}

.side-card--accent {
  background: var(--login-secondary, #0c1c40);
}

.side-label {
  color: #8b94a6;
  font-size: 0.76rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.side-card--accent .side-label {
  color: #aab6d3;
}

.side-value {
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.45rem;
  font-weight: 700;
  line-height: 1.2;
}

.side-value--light {
  color: #fff;
}

.side-value--text {
  font-size: 0.98rem;
  line-height: 1.35;
  word-break: break-word;
}

.side-extra {
  color: #545f71;
  font-size: 0.86rem;
  font-weight: 600;
  word-break: break-word;
}

.side-extra--strong {
  color: var(--login-secondary, #0c1c40);
  font-weight: 700;
}

.side-link {
  margin-top: 0.15rem;
  color: var(--login-primary, #ed2044);
  font-size: 0.8rem;
  font-weight: 700;
}

.side-card--accent .side-link {
  color: #ff9fb0;
}

.side-task {
  display: flex;
  align-items: flex-start;
  gap: 0.45rem;
  width: 100%;
}

.side-task-dot {
  flex-shrink: 0;
  width: 0.55rem;
  height: 0.55rem;
  margin-top: 0.32rem;
  border-radius: 50%;
}

.side-task-text {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.side-task-subject {
  color: var(--login-secondary, #0c1c40);
  font-size: 0.88rem;
  font-weight: 600;
  word-break: break-word;
}

.side-task-due {
  color: #8b94a6;
  font-size: 0.76rem;
  font-weight: 600;
}

@media (max-width: 991.98px) {
  .ov-layout {
    grid-template-columns: 1fr;
  }
}

.ov-status-switch {
  display: inline-flex;
  padding: 0.2rem;
  background: #f0f2f7;
  border-radius: 2rem;
  gap: 0.2rem;
}

.ov-status-switch.is-saving {
  opacity: 0.6;
}

.ov-status-btn {
  padding: 0.4rem 0.95rem;
  background: transparent;
  border: none;
  border-radius: 2rem;
  color: #545f71;
  font-size: 0.86rem;
  font-weight: 700;
  font-family: inherit;
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}

.ov-status-btn--existing.is-active {
  background: #1c7a45;
  color: #fff;
}

.ov-status-btn--potential.is-active {
  background: #2b59c3;
  color: #fff;
}

.ov-status-btn:disabled {
  cursor: progress;
}

.sales-period {
  color: #545f71;
  font-size: 0.82rem;
  font-weight: 600;
}

.sales-notes {
  color: #8b94a6;
  font-size: 0.82rem;
  word-break: break-word;
}

.placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 220px;
}

.coming-soon {
  margin: 0;
  color: #8b94a6;
  font-size: 1.1rem;
  font-weight: 700;
}

.state {
  padding: 1.3rem;
  background: #f7f8fb;
  border-radius: 0.7rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 1rem;
}

.state--error {
  background: #fde8ec;
  color: #b3122e;
}

@media (max-width: 767.98px) {
  .info-grid,
  .addr-row {
    grid-template-columns: 1fr;
  }
}
</style>

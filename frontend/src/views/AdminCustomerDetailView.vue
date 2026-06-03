<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  useCustomersStore,
  currentSalesAssignments,
  type Customer,
  type Address,
  type SalesAssignment,
} from '@/stores/customers'
import CustomerEditor from '@/components/CustomerEditor.vue'
import CustomerContactsPanel from '@/components/CustomerContactsPanel.vue'
import IconEdit from '@/components/icons/IconEdit.vue'

const { t } = useI18n()
const route = useRoute()
const store = useCustomersStore()

const id = computed(() => Number(route.params.id))
// Source the customer from the store so edits and sales-assignment changes
// (which mutate the store) reflect here without an extra refetch.
const customer = computed<Customer | null>(() => store.customers.find((c) => c.id === id.value) ?? null)
const loading = ref(true)
const notFound = ref(false)
const editing = ref(false)

type TabKey = 'overview' | 'contacts' | 'opportunities' | 'timeline'
const activeTab = ref<TabKey>('overview')

const tabs: { key: TabKey; label: string; ready: boolean }[] = [
  { key: 'overview', label: 'tabOverview', ready: true },
  { key: 'contacts', label: 'tabContacts', ready: true },
  { key: 'opportunities', label: 'tabOpportunities', ready: false },
  { key: 'timeline', label: 'tabTimeline', ready: false },
]

async function load(): Promise<void> {
  loading.value = true
  notFound.value = false
  editing.value = false
  const result = await store.fetchCustomer(id.value)
  notFound.value = null === result
  loading.value = false
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
        <div v-if="activeTab === 'overview'" class="cust-panel">
          <div v-if="!editing" class="overview-head">
            <button type="button" class="btn-edit" @click="editing = true">
              <IconEdit />
              <span>{{ t('admin.edit') }}</span>
            </button>
          </div>

          <CustomerEditor v-if="editing" :customer="customer" @saved="editing = false" @cancel="editing = false" />

          <template v-else>
          <fieldset class="block">
            <legend>{{ t('adminCustomers.overviewGeneral') }}</legend>
            <dl class="info-grid">
              <div class="info">
                <dt>{{ t('adminCustomers.website') }}</dt>
                <dd>{{ customer.website || '—' }}</dd>
              </div>
              <div class="info">
                <dt>{{ t('adminCustomers.taxNumber') }}</dt>
                <dd>{{ customer.taxNumber || '—' }}</dd>
              </div>
              <div class="info">
                <dt>{{ t('adminCustomers.email') }}</dt>
                <dd>{{ customer.email || '—' }}</dd>
              </div>
              <div class="info">
                <dt>{{ t('adminCustomers.phone') }}</dt>
                <dd>{{ customer.phone || '—' }}</dd>
              </div>
              <div class="info">
                <dt>{{ t('adminCustomers.validFrom') }} → {{ t('adminCustomers.validUntil') }}</dt>
                <dd>{{ validityLabel(customer) }}</dd>
              </div>
              <div class="info info--wide">
                <dt>{{ t('adminCustomers.notes') }}</dt>
                <dd class="dd-notes">{{ customer.notes || '—' }}</dd>
              </div>
            </dl>
          </fieldset>

          <div class="addr-row">
            <fieldset class="block">
              <legend>{{ t('adminCustomers.address') }}</legend>
              <p class="addr-text">{{ addressLine(customer.address) }}</p>
            </fieldset>
            <fieldset class="block">
              <legend>{{ t('adminCustomers.billingAddress') }}</legend>
              <p class="addr-text">{{ addressLine(customer.billingAddress) }}</p>
            </fieldset>
          </div>

          <fieldset class="block">
            <legend>{{ t('adminCustomers.salesHeader') }}</legend>
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
          </fieldset>
          </template>
        </div>

        <!-- ── Contacts ─────────────────────────────────────────────── -->
        <div v-else-if="activeTab === 'contacts'" class="cust-panel">
          <CustomerContactsPanel :customer="customer" />
        </div>

        <!-- ── Placeholder tabs (future CRM phases) ─────────────────── -->
        <div v-else class="cust-panel placeholder">
          <p class="coming-soon">{{ t('adminCustomers.comingSoon') }}</p>
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

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import { useBillingStore, type BillingItem, type BillingItemFields, type BillingStatus } from '@/stores/billing'
import { useCustomersStore } from '@/stores/customers'
import { useOpportunitiesStore, type Opportunity } from '@/stores/opportunities'
import { useProductsStore } from '@/stores/products'
import { useMoneyFormat } from '@/stores/currencySettings'
import AppSelect from '@/components/AppSelect.vue'
import OpportunityLineItems from '@/components/OpportunityLineItems.vue'

const { t, locale } = useI18n()
const store = useBillingStore()
const customersStore = useCustomersStore()
const opportunitiesStore = useOpportunitiesStore()
const productsStore = useProductsStore()
const { items, loading, error } = storeToRefs(store)
const { customers } = storeToRefs(customersStore)

// Placeholder ('') first, then one entry per customer (id kept as a string
// to match addFields.customerId).
const customerSelectOptions = computed<{ value: string; label: string }[]>(() => [
  { value: '', label: t('adminBilling.customerPlaceholder') },
  ...customers.value.map((c) => ({ value: String(c.id), label: c.name })),
])

const currencySelectOptions: { value: string; label: string }[] = [
  { value: 'HUF', label: 'HUF' },
  { value: 'EUR', label: 'EUR' },
  { value: 'USD', label: 'USD' },
]

onMounted(() => {
  store.fetchItems()
})

// ── Status filter ────────────────────────────────────────────────────
type Filter = 'all' | 'pending' | 'invoiced'
const filter = ref<Filter>('pending')

const pendingCount = computed(() => items.value.filter((i) => 'pending' === i.status).length)

// ── Grouping by offer ────────────────────────────────────────────────
// Billing rows snapshotted from the same won deal share an opportunityId.
// An offer whose opportunity has quote lines is "line-based": invoicing is
// tracked per quote line (summary row + an expandable, checkable breakdown),
// and the billing snapshot rows are not shown. Manual rows and lineless
// offers keep the flat snapshot rows with their own per-item invoicing.
type GroupStatus = 'pending' | 'invoiced' | 'partial'

interface BillingGroup {
  key: string
  opportunityId: number | null
  customerId: number
  customerName: string
  source: string
  currency: string
  items: BillingItem[]
  total: number
  wonAt: string | null
  status: GroupStatus
  isSingle: boolean
  // Line-based offers: invoicing lives on the opportunity's quote lines.
  isLineBased: boolean
  offerLineCount: number
  invoicedPercent: number | null
  invoicedAmount: number
}

const groups = computed<BillingGroup[]>(() => {
  const map = new Map<string, BillingItem[]>()
  for (const item of items.value) {
    const key = null !== item.opportunityId ? `opp-${item.opportunityId}` : `item-${item.id}`
    const bucket = map.get(key)
    if (bucket) bucket.push(item)
    else map.set(key, [item])
  }

  const result: BillingGroup[] = []
  for (const [key, groupItems] of map) {
    const first = groupItems[0]!
    const isLineBased = null !== first.opportunityId && first.offerLineCount > 0

    let status: GroupStatus
    let invoicedPercent: number | null = null
    let invoicedAmount = 0
    let total: number
    if (isLineBased) {
      // Offer status + share derive from the per-line invoiced flags.
      const lines = first.offerLineCount
      const invoiced = first.offerInvoicedCount
      status = 0 === invoiced ? 'pending' : invoiced === lines ? 'invoiced' : 'partial'
      total = Number(first.offerTotalValue)
      invoicedAmount = Number(first.offerInvoicedValue)
      invoicedPercent =
        total > 0 ? Math.round((invoicedAmount / total) * 100) : Math.round((invoiced / lines) * 100)
    } else {
      // Manual / lineless offers fall back to the billing-item statuses.
      const allInvoiced = groupItems.every((i) => 'invoiced' === i.status)
      const anyInvoiced = groupItems.some((i) => 'invoiced' === i.status)
      status = allInvoiced ? 'invoiced' : anyInvoiced ? 'partial' : 'pending'
      total = groupItems.reduce((sum, i) => sum + Number(i.lineTotal), 0)
    }

    result.push({
      key,
      opportunityId: first.opportunityId,
      customerId: first.customerId,
      customerName: first.customerName,
      source: first.opportunityTitle ?? first.cardName ?? '—',
      currency: first.currency,
      items: groupItems,
      total,
      wonAt: first.wonAt,
      status,
      isSingle: 1 === groupItems.length,
      isLineBased,
      offerLineCount: first.offerLineCount,
      invoicedPercent,
      invoicedAmount,
    })
  }
  return result
})

// The status filter selects whole offers. A partially-invoiced offer has both
// invoiced and uninvoiced lines, so it shows on BOTH tabs: 'pending' keeps any
// offer with an uninvoiced line (pending or partial); 'invoiced' keeps any
// offer with an invoiced line (invoiced or partial). Visible offers always
// show all their line items so the summary total matches the rows beneath it.
const visibleGroups = computed(() => {
  if ('all' === filter.value) return groups.value
  if ('pending' === filter.value) return groups.value.filter((g) => 'invoiced' !== g.status)
  return groups.value.filter((g) => 'pending' !== g.status)
})

// Per-currency totals of everything still waiting to be invoiced.
const pendingTotals = computed(() => {
  const sums = new Map<string, number>()
  for (const item of items.value) {
    if ('pending' !== item.status) continue
    sums.set(item.currency, (sums.get(item.currency) ?? 0) + Number(item.lineTotal))
  }
  return [...sums.entries()].map(([currency, total]) => ({ currency, total }))
})

// ── Offer detail expansion ────────────────────────────────────────────
// An offer tied to an opportunity can be expanded to show its full original
// quote lines (read-only, with category + material/fee), pulled from the
// opportunity — independent of the billing snapshot rows shown above it.
const expandedOffers = reactive(new Set<string>())

function toggleOffer(group: BillingGroup): void {
  if (expandedOffers.has(group.key)) {
    expandedOffers.delete(group.key)
    return
  }
  expandedOffers.add(group.key)
  if (null === group.opportunityId) return
  // Lazily load what the breakdown needs the first time an offer is opened.
  if (0 === productsStore.products.length) void productsStore.fetchProducts()
  if (0 === opportunitiesStore.list(group.customerId).length) {
    void opportunitiesStore.fetchOpportunities(group.customerId)
  }
}

/** The opportunity behind an offer group, once its customer's deals load. */
function offerOpportunity(group: BillingGroup): Opportunity | null {
  if (null === group.opportunityId) return null
  return opportunitiesStore.list(group.customerId).find((o) => o.id === group.opportunityId) ?? null
}

// ── Formatting ───────────────────────────────────────────────────────
const fmtMoney = useMoneyFormat()

function fmtNumber(value: string): string {
  return new Intl.NumberFormat(locale.value, { maximumFractionDigits: 2 }).format(Number(value))
}

function fmtDate(iso: string | null): string {
  return null === iso ? '—' : new Date(`${iso}T00:00:00`).toLocaleDateString(locale.value)
}

// ── Manual row ───────────────────────────────────────────────────────
const showAdd = ref(false)
const adding = ref(false)
const addFields = reactive({ customerId: '', name: '', quantity: '1', unitPrice: '', currency: 'HUF' })

function toggleAdd(): void {
  showAdd.value = !showAdd.value
  // The customer picker needs the list; load it on first open only.
  if (showAdd.value && 0 === customers.value.length) {
    customersStore.fetchCustomers()
  }
}

async function onAdd(): Promise<void> {
  if ('' === addFields.customerId || '' === addFields.name.trim()) return
  adding.value = true
  const result = await store.createItem(Number(addFields.customerId), {
    name: addFields.name.trim(),
    quantity: addFields.quantity,
    unitPrice: addFields.unitPrice,
    currency: addFields.currency,
  })
  adding.value = false
  if (!result.ok) {
    window.alert(result.error ?? t('admin.saveFailed'))
    return
  }
  addFields.name = ''
  addFields.quantity = '1'
  addFields.unitPrice = ''
  showAdd.value = false
}

// ── Inline edit ──────────────────────────────────────────────────────
const editId = ref<number | null>(null)
const editFields = reactive<BillingItemFields>({ name: '', quantity: '1', unitPrice: '0', currency: 'HUF' })

function startEdit(item: BillingItem): void {
  editId.value = item.id
  editFields.name = item.name
  editFields.quantity = item.quantity
  editFields.unitPrice = item.unitPrice
  editFields.currency = item.currency
}

function cancelEdit(): void {
  editId.value = null
}

async function saveEdit(): Promise<void> {
  if (null === editId.value || '' === editFields.name.trim()) return
  const result = await store.updateItem(editId.value, { ...editFields, name: editFields.name.trim() })
  if (!result.ok) {
    window.alert(result.error ?? t('admin.saveFailed'))
    return
  }
  editId.value = null
}

// ── Status flip & delete ─────────────────────────────────────────────
async function onToggleStatus(item: BillingItem): Promise<void> {
  const result = await store.setStatus(item.id, 'pending' === item.status ? 'invoiced' : 'pending')
  if (!result.ok) window.alert(result.error ?? t('admin.saveFailed'))
}

// Flip every line of an offer at once. Fully-invoiced offers reopen; any
// other state (pending or partial) is pushed to fully invoiced.
async function onMarkGroup(group: BillingGroup): Promise<void> {
  // Line-based offers flip every quote line; the summary refreshes from the
  // refetched billing aggregate.
  if (group.isLineBased && null !== group.opportunityId) {
    const invoiced = 'invoiced' !== group.status
    const result = await opportunitiesStore.setAllLinesInvoiced(group.customerId, group.opportunityId, invoiced)
    if (!result.ok) {
      window.alert(result.error ?? t('admin.saveFailed'))
      return
    }
    void store.fetchItems()
    return
  }

  const target: BillingStatus = 'invoiced' === group.status ? 'pending' : 'invoiced'
  const results = await Promise.all(
    group.items.filter((i) => i.status !== target).map((i) => store.setStatus(i.id, target)),
  )
  const failed = results.find((r) => !r.ok)
  if (failed) window.alert(failed.error ?? t('admin.saveFailed'))
}

// Toggle one quote line of a line-based offer, then refresh the aggregate.
async function onToggleLine(group: BillingGroup, lineId: number, invoiced: boolean): Promise<void> {
  if (null === group.opportunityId) return
  const result = await opportunitiesStore.setLineInvoiced(group.customerId, group.opportunityId, lineId, invoiced)
  if (!result.ok) {
    window.alert(result.error ?? t('admin.saveFailed'))
    return
  }
  void store.fetchItems()
}

async function onDelete(item: BillingItem): Promise<void> {
  if (!window.confirm(t('adminBilling.confirmDelete', { name: item.name }))) return
  const result = await store.deleteItem(item.id)
  if (!result.ok) window.alert(result.error ?? t('admin.deleteFailed'))
}
</script>

<template>
  <section class="admin">
    <div class="container-lg">
      <div class="admin-head">
        <span class="eyebrow">CRM</span>
        <h1>{{ t('adminBilling.title') }}</h1>
        <p class="subtitle">{{ t('adminBilling.subtitle') }}</p>
      </div>

      <p v-if="loading" class="state">{{ t('adminBilling.loading') }}</p>

      <div v-else-if="error" class="state state--error">
        <strong>{{ t('adminBilling.loadError') }}</strong>
        <button type="button" class="btn-retry" @click="store.fetchItems()">{{ t('common.retry') }}</button>
      </div>

      <template v-else>
        <!-- ── Filter tabs + pending totals + add ──────────────────── -->
        <div class="bill-toolbar">
          <div class="tabs" role="tablist">
            <button
              v-for="f in (['pending', 'invoiced', 'all'] as const)"
              :key="f"
              type="button"
              role="tab"
              class="tab"
              :class="{ 'is-active': filter === f }"
              :aria-selected="filter === f"
              @click="filter = f"
            >
              {{ t(`adminBilling.filter_${f}`) }}
              <span v-if="f === 'pending' && pendingCount > 0" class="tab-badge">{{ pendingCount }}</span>
            </button>
          </div>
          <div class="bill-toolbar-right">
            <span v-for="pt in pendingTotals" :key="pt.currency" class="bill-total">
              {{ t('adminBilling.pendingTotal') }}: <strong>{{ fmtMoney(pt.total, pt.currency) }}</strong>
            </span>
            <button type="button" class="btn-add" @click="toggleAdd">
              {{ showAdd ? t('adminBilling.closeAdd') : t('adminBilling.newItem') }}
            </button>
          </div>
        </div>

        <!-- ── Manual row form ──────────────────────────────────────── -->
        <form v-if="showAdd" class="bill-add" @submit.prevent="onAdd">
          <label class="bill-add-field">
            <span>{{ t('adminBilling.colCustomer') }}</span>
            <AppSelect
              v-model="addFields.customerId"
              :options="customerSelectOptions"
              :placeholder="t('adminBilling.customerPlaceholder')"
            />
          </label>
          <label class="bill-add-field bill-add-field--grow">
            <span>{{ t('adminBilling.colItem') }}</span>
            <input v-model="addFields.name" type="text" :placeholder="t('adminBilling.namePlaceholder')" required />
          </label>
          <label class="bill-add-field bill-add-field--narrow">
            <span>{{ t('adminBilling.colQuantity') }}</span>
            <input v-model="addFields.quantity" type="number" min="0" step="0.01" required />
          </label>
          <label class="bill-add-field bill-add-field--narrow">
            <span>{{ t('adminBilling.colUnitPrice') }}</span>
            <input v-model="addFields.unitPrice" type="number" min="0" step="0.01" required />
          </label>
          <label class="bill-add-field bill-add-field--narrow">
            <span>{{ t('adminBilling.currency') }}</span>
            <AppSelect v-model="addFields.currency" :options="currencySelectOptions" />
          </label>
          <button type="submit" class="btn-add" :disabled="adding">
            {{ adding ? t('admin.creating') : t('admin.save') }}
          </button>
        </form>

        <!-- ── Itemised table, grouped per offer ────────────────────── -->
        <div class="bill-panel">
          <p v-if="visibleGroups.length === 0" class="muted">{{ t('adminBilling.empty') }}</p>
          <div v-else class="bill-table-wrap">
            <table class="bill-table">
              <thead>
                <tr>
                  <th>{{ t('adminBilling.colCustomer') }}</th>
                  <th>{{ t('adminBilling.colSource') }}</th>
                  <th>{{ t('adminBilling.colItem') }}</th>
                  <th class="num">{{ t('adminBilling.colQuantity') }}</th>
                  <th class="num">{{ t('adminBilling.colUnitPrice') }}</th>
                  <th class="num">{{ t('adminBilling.colTotal') }}</th>
                  <th>{{ t('adminBilling.colWonAt') }}</th>
                  <th>{{ t('adminBilling.colStatus') }}</th>
                  <th class="actions">{{ t('adminBilling.colActions') }}</th>
                </tr>
              </thead>
              <tbody v-for="group in visibleGroups" :key="group.key" class="bill-group">
                <!-- ══ Line-based offer: summary row + expandable, checkable breakdown ══ -->
                <template v-if="group.isLineBased">
                  <tr class="bill-offer-row">
                    <td>
                      <button
                        type="button"
                        class="offer-toggle"
                        :class="{ 'is-open': expandedOffers.has(group.key) }"
                        :aria-expanded="expandedOffers.has(group.key)"
                        :aria-label="t('adminBilling.colItem')"
                        :title="t('adminBilling.colItem')"
                        @click="toggleOffer(group)"
                      >▸</button>
                      <RouterLink
                        :to="{ name: 'admin-customer-detail', params: { id: group.customerId } }"
                        class="bill-customer"
                      >
                        {{ group.customerName }}
                      </RouterLink>
                    </td>
                    <td class="bill-deal">{{ group.source }}</td>
                    <td class="bill-name">{{ t('adminBilling.offerItemCount', { count: group.offerLineCount }) }}</td>
                    <td class="num"></td>
                    <td class="num"></td>
                    <td class="num bill-line-total">{{ fmtMoney(group.total, group.currency) }}</td>
                    <td>{{ fmtDate(group.wonAt) }}</td>
                    <td>
                      <span class="bill-status" :class="`bill-status--${group.status}`">
                        {{ t(`adminBilling.status_${group.status}`) }}
                      </span>
                      <span v-if="group.invoicedPercent !== null" class="bill-invoiced-share">
                        {{ group.invoicedPercent }}% · {{ fmtMoney(group.invoicedAmount, group.currency) }}
                      </span>
                    </td>
                    <td class="actions">
                      <button type="button" class="row-btn row-btn--primary" @click="onMarkGroup(group)">
                        {{ group.status === 'invoiced' ? t('adminBilling.markAllPending') : t('adminBilling.markAllInvoiced') }}
                      </button>
                    </td>
                  </tr>
                  <tr v-if="expandedOffers.has(group.key)" class="bill-offer-detail">
                    <td :colspan="9">
                      <OpportunityLineItems
                        v-if="offerOpportunity(group)"
                        editable
                        :line-items="offerOpportunity(group)!.lineItems"
                        :currency="offerOpportunity(group)!.currency"
                        :total="offerOpportunity(group)!.lineItemsTotal"
                        @toggle="(lineId, invoiced) => onToggleLine(group, lineId, invoiced)"
                      />
                      <p v-else class="muted bill-offer-detail-msg">{{ t('adminBilling.offerItemsLoading') }}</p>
                    </td>
                  </tr>
                </template>

                <!-- ══ Manual / lineless offer: flat snapshot rows, per-item invoicing ══ -->
                <template v-else>
                  <tr v-if="!group.isSingle" class="bill-offer-row">
                    <td>
                      <RouterLink
                        :to="{ name: 'admin-customer-detail', params: { id: group.customerId } }"
                        class="bill-customer"
                      >
                        {{ group.customerName }}
                      </RouterLink>
                    </td>
                    <td class="bill-deal">{{ group.source }}</td>
                    <td class="bill-name">{{ t('adminBilling.offerItemCount', { count: group.items.length }) }}</td>
                    <td class="num"></td>
                    <td class="num"></td>
                    <td class="num bill-line-total">{{ fmtMoney(group.total, group.currency) }}</td>
                    <td>{{ fmtDate(group.wonAt) }}</td>
                    <td>
                      <span class="bill-status" :class="`bill-status--${group.status}`">
                        {{ t(`adminBilling.status_${group.status}`) }}
                      </span>
                    </td>
                    <td class="actions">
                      <button type="button" class="row-btn row-btn--primary" @click="onMarkGroup(group)">
                        {{ group.status === 'invoiced' ? t('adminBilling.markAllPending') : t('adminBilling.markAllInvoiced') }}
                      </button>
                    </td>
                  </tr>

                  <tr
                    v-for="item in group.items"
                    :key="item.id"
                    :class="{ 'is-invoiced': item.status === 'invoiced', 'bill-subrow': !group.isSingle }"
                  >
                    <td>
                      <RouterLink
                        v-if="group.isSingle"
                        :to="{ name: 'admin-customer-detail', params: { id: item.customerId } }"
                        class="bill-customer"
                      >
                        {{ item.customerName }}
                      </RouterLink>
                    </td>
                    <td class="bill-deal">
                      <template v-if="group.isSingle">{{ item.opportunityTitle ?? item.cardName ?? '—' }}</template>
                    </td>

                    <template v-if="editId === item.id">
                      <td><input v-model="editFields.name" type="text" class="bill-edit-input" /></td>
                      <td class="num"><input v-model="editFields.quantity" type="number" min="0" step="0.01" class="bill-edit-input bill-edit-input--num" /></td>
                      <td class="num"><input v-model="editFields.unitPrice" type="number" min="0" step="0.01" class="bill-edit-input bill-edit-input--num" /></td>
                      <td class="num">{{ fmtMoney(Number(editFields.quantity || 0) * Number(editFields.unitPrice || 0), editFields.currency) }}</td>
                    </template>
                    <template v-else>
                      <td class="bill-name" :class="{ 'bill-name--sub': !group.isSingle }">{{ item.name }}</td>
                      <td class="num">{{ fmtNumber(item.quantity) }}</td>
                      <td class="num">{{ fmtMoney(item.unitPrice, item.currency) }}</td>
                      <td class="num bill-line-total">{{ fmtMoney(item.lineTotal, item.currency) }}</td>
                    </template>

                    <td>{{ group.isSingle ? fmtDate(item.wonAt) : '' }}</td>
                    <td>
                      <span class="bill-status" :class="`bill-status--${item.status}`">
                        {{ t(`adminBilling.status_${item.status}`) }}
                      </span>
                      <span v-if="item.invoicedAt" class="bill-invoiced-at">{{ fmtDate(item.invoicedAt) }}</span>
                    </td>
                    <td class="actions">
                      <template v-if="editId === item.id">
                        <button type="button" class="row-btn row-btn--primary" @click="saveEdit">{{ t('admin.save') }}</button>
                        <button type="button" class="row-btn" @click="cancelEdit">{{ t('adminBilling.cancel') }}</button>
                      </template>
                      <template v-else>
                        <button type="button" class="row-btn row-btn--primary" @click="onToggleStatus(item)">
                          {{ item.status === 'pending' ? t('adminBilling.markInvoiced') : t('adminBilling.markPending') }}
                        </button>
                        <button type="button" class="row-btn" @click="startEdit(item)">{{ t('admin.edit') }}</button>
                        <button type="button" class="row-btn row-btn--danger" @click="onDelete(item)">{{ t('admin.delete') }}</button>
                      </template>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
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

/* ── Toolbar ────────────────────────────────────────────────────────── */
.bill-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
  margin-bottom: 1.2rem;
}

.bill-toolbar-right {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex-wrap: wrap;
}

.bill-total {
  color: #545f71;
  font-size: 0.9rem;
  font-weight: 600;
}

.bill-total strong {
  color: var(--login-primary, #ed2044);
}

.tabs {
  display: flex;
  gap: 0.4rem;
  flex-wrap: wrap;
}

.tab {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.55rem 1.1rem;
  background: #fff;
  border: 1px solid #d4dae6;
  border-radius: 2rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.92rem;
  font-weight: 700;
  font-family: inherit;
  cursor: pointer;
  transition: background 0.15s, border-color 0.15s, color 0.15s;
}

.tab:hover {
  border-color: var(--login-primary, #ed2044);
}

.tab.is-active {
  background: var(--login-secondary, #0c1c40);
  border-color: var(--login-secondary, #0c1c40);
  color: #fff;
}

.tab-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.35rem;
  height: 1.35rem;
  padding: 0 0.3rem;
  background: var(--login-primary, #ed2044);
  border-radius: 0.7rem;
  color: #fff;
  font-size: 0.72rem;
}

.btn-add {
  padding: 0.55rem 1.2rem;
  background: var(--login-primary, #ed2044);
  border: none;
  border-radius: 2rem;
  color: #fff;
  font-size: 0.92rem;
  font-weight: 700;
  font-family: inherit;
  cursor: pointer;
}

.btn-add:disabled {
  opacity: 0.6;
  cursor: default;
}

/* ── Manual row form ────────────────────────────────────────────────── */
.bill-add {
  display: flex;
  align-items: flex-end;
  gap: 0.8rem;
  flex-wrap: wrap;
  margin-bottom: 1.2rem;
  padding: 1.1rem 1.3rem;
  background: #fff;
  border-radius: 1.1rem;
  box-shadow: 0 12px 32px rgba(12, 28, 64, 0.08);
}

.bill-add-field {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}

.bill-add-field--grow {
  flex: 1 1 220px;
}

.bill-add-field--narrow {
  width: 110px;
}

.bill-add-field span {
  color: var(--login-secondary, #0c1c40);
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.bill-add-field input,
.bill-add-field select {
  padding: 0.5rem 0.7rem;
  background: #fff;
  border: 1px solid #d4dae6;
  border-radius: 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.92rem;
  font-family: inherit;
}

/* ── Table ──────────────────────────────────────────────────────────── */
.bill-panel {
  padding: 1.85rem;
  background: #fff;
  border-radius: 1.1rem;
  box-shadow: 0 12px 32px rgba(12, 28, 64, 0.08);
}

.muted {
  margin: 0;
  color: #8b94a6;
  font-size: 0.9rem;
}

.bill-table-wrap {
  overflow-x: auto;
}

.bill-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}

.bill-table th {
  padding: 0.55rem 0.7rem;
  border-bottom: 2px solid #e3e7ef;
  color: #8b94a6;
  font-size: 0.74rem;
  font-weight: 700;
  text-align: left;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  white-space: nowrap;
}

.bill-table td {
  padding: 0.65rem 0.7rem;
  border-bottom: 1px solid #eef1f6;
  color: var(--login-secondary, #0c1c40);
  vertical-align: middle;
}

.bill-table tr.is-invoiced td {
  color: #8b94a6;
}

/* ── Offer grouping ─────────────────────────────────────────────────── */
/* Each offer is its own tbody; a rule separates one offer from the next. */
.bill-group + .bill-group {
  border-top: 8px solid #f2f4f8;
}

.bill-offer-row td {
  background: #f7f8fb;
  border-bottom: 1px solid #e3e7ef;
  font-weight: 700;
}

/* Caret that expands an offer's full original quote lines. */
.offer-toggle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.2rem;
  height: 1.2rem;
  margin-right: 0.3rem;
  padding: 0;
  background: none;
  border: none;
  color: #8b94a6;
  font-size: 0.8rem;
  line-height: 1;
  cursor: pointer;
  vertical-align: middle;
  transition: transform 0.15s, color 0.15s;
}

.offer-toggle:hover {
  color: var(--login-primary, #ed2044);
}

.offer-toggle.is-open {
  transform: rotate(90deg);
  color: var(--login-secondary, #0c1c40);
}

.bill-offer-detail > td {
  padding: 0;
  background: #fbfcfe;
  border-bottom: 1px solid #e3e7ef;
}

.bill-offer-detail-msg {
  padding: 0.8rem 1rem;
}

/* Per-offer invoiced share shown under the status badge. */
.bill-invoiced-share {
  display: block;
  margin-top: 0.2rem;
  color: #545f71;
  font-size: 0.76rem;
  font-weight: 600;
  white-space: nowrap;
}

.bill-invoiced-share strong {
  color: #198754;
}

/* Line items nested under a summary row: lighter, indented name. */
.bill-subrow td {
  background: #fff;
}

.bill-name--sub {
  padding-left: 1.6rem;
  font-weight: 500;
}

.bill-table .num {
  text-align: right;
  white-space: nowrap;
}

.bill-table .actions {
  text-align: right;
  white-space: nowrap;
}

.bill-customer {
  color: var(--login-secondary, #0c1c40);
  font-weight: 700;
  text-decoration: none;
}

.bill-customer:hover {
  color: var(--login-primary, #ed2044);
  text-decoration: underline;
}

.bill-deal {
  color: #545f71;
  font-size: 0.85rem;
}

.bill-name {
  font-weight: 600;
}

.bill-line-total {
  font-weight: 700;
}

.bill-status {
  display: inline-block;
  padding: 0.18rem 0.6rem;
  border-radius: 0.7rem;
  font-size: 0.76rem;
  font-weight: 700;
}

.bill-status--pending {
  background: #fdeede;
  color: #b3611a;
}

.bill-status--invoiced {
  background: #e3f6ec;
  color: #1c7a45;
}

.bill-status--partial {
  background: #e7effc;
  color: #2563ad;
}

.bill-invoiced-at {
  display: block;
  margin-top: 0.15rem;
  color: #8b94a6;
  font-size: 0.74rem;
  font-weight: 600;
}

.bill-edit-input {
  width: 100%;
  min-width: 130px;
  padding: 0.4rem 0.55rem;
  border: 1px solid #d4dae6;
  border-radius: 0.45rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.88rem;
  font-family: inherit;
}

.bill-edit-input--num {
  min-width: 80px;
  text-align: right;
}

.row-btn {
  margin-left: 0.35rem;
  padding: 0.35rem 0.75rem;
  background: #fff;
  border: 1px solid #d4dae6;
  border-radius: 0.45rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.8rem;
  font-weight: 700;
  font-family: inherit;
  cursor: pointer;
}

.row-btn:hover {
  border-color: var(--login-secondary, #0c1c40);
}

.row-btn--primary {
  border-color: var(--login-primary, #ed2044);
  color: var(--login-primary, #ed2044);
}

.row-btn--primary:hover {
  background: var(--login-primary, #ed2044);
  border-color: var(--login-primary, #ed2044);
  color: #fff;
}

.row-btn--danger {
  border-color: #d4dae6;
  color: #b3122e;
}

.row-btn--danger:hover {
  border-color: #b3122e;
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

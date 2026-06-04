<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import { useBillingStore, type BillingItem, type BillingItemFields } from '@/stores/billing'
import { useCustomersStore } from '@/stores/customers'

const { t, locale } = useI18n()
const store = useBillingStore()
const customersStore = useCustomersStore()
const { items, loading, error } = storeToRefs(store)
const { customers } = storeToRefs(customersStore)

onMounted(() => {
  store.fetchItems()
})

// ── Status filter ────────────────────────────────────────────────────
type Filter = 'all' | 'pending' | 'invoiced'
const filter = ref<Filter>('pending')

const pendingCount = computed(() => items.value.filter((i) => 'pending' === i.status).length)

const visibleItems = computed(() =>
  'all' === filter.value ? items.value : items.value.filter((i) => i.status === filter.value),
)

// Per-currency totals of everything still waiting to be invoiced.
const pendingTotals = computed(() => {
  const sums = new Map<string, number>()
  for (const item of items.value) {
    if ('pending' !== item.status) continue
    sums.set(item.currency, (sums.get(item.currency) ?? 0) + Number(item.lineTotal))
  }
  return [...sums.entries()].map(([currency, total]) => ({ currency, total }))
})

// ── Formatting ───────────────────────────────────────────────────────
function fmtMoney(value: string | number, currency: string): string {
  return new Intl.NumberFormat(locale.value, {
    style: 'currency',
    currency,
    maximumFractionDigits: 0,
  }).format(Number(value))
}

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
            <select v-model="addFields.customerId" required>
              <option value="" disabled>{{ t('adminBilling.customerPlaceholder') }}</option>
              <option v-for="c in customers" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
            </select>
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
            <select v-model="addFields.currency">
              <option value="HUF">HUF</option>
              <option value="EUR">EUR</option>
              <option value="USD">USD</option>
            </select>
          </label>
          <button type="submit" class="btn-add" :disabled="adding">
            {{ adding ? t('admin.creating') : t('admin.save') }}
          </button>
        </form>

        <!-- ── Itemised table ───────────────────────────────────────── -->
        <div class="bill-panel">
          <p v-if="visibleItems.length === 0" class="muted">{{ t('adminBilling.empty') }}</p>
          <div v-else class="bill-table-wrap">
            <table class="bill-table">
              <thead>
                <tr>
                  <th>{{ t('adminBilling.colCustomer') }}</th>
                  <th>{{ t('adminBilling.colDeal') }}</th>
                  <th>{{ t('adminBilling.colItem') }}</th>
                  <th class="num">{{ t('adminBilling.colQuantity') }}</th>
                  <th class="num">{{ t('adminBilling.colUnitPrice') }}</th>
                  <th class="num">{{ t('adminBilling.colTotal') }}</th>
                  <th>{{ t('adminBilling.colWonAt') }}</th>
                  <th>{{ t('adminBilling.colStatus') }}</th>
                  <th class="actions">{{ t('adminBilling.colActions') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in visibleItems" :key="item.id" :class="{ 'is-invoiced': item.status === 'invoiced' }">
                  <td>
                    <RouterLink
                      :to="{ name: 'admin-customer-detail', params: { id: item.customerId } }"
                      class="bill-customer"
                    >
                      {{ item.customerName }}
                    </RouterLink>
                  </td>
                  <td class="bill-deal">{{ item.opportunityTitle ?? '—' }}</td>

                  <template v-if="editId === item.id">
                    <td><input v-model="editFields.name" type="text" class="bill-edit-input" /></td>
                    <td class="num"><input v-model="editFields.quantity" type="number" min="0" step="0.01" class="bill-edit-input bill-edit-input--num" /></td>
                    <td class="num"><input v-model="editFields.unitPrice" type="number" min="0" step="0.01" class="bill-edit-input bill-edit-input--num" /></td>
                    <td class="num">{{ fmtMoney(Number(editFields.quantity || 0) * Number(editFields.unitPrice || 0), editFields.currency) }}</td>
                  </template>
                  <template v-else>
                    <td class="bill-name">{{ item.name }}</td>
                    <td class="num">{{ fmtNumber(item.quantity) }}</td>
                    <td class="num">{{ fmtMoney(item.unitPrice, item.currency) }}</td>
                    <td class="num bill-line-total">{{ fmtMoney(item.lineTotal, item.currency) }}</td>
                  </template>

                  <td>{{ fmtDate(item.wonAt) }}</td>
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

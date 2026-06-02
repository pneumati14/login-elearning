<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import {
  useCustomersStore,
  emptyCustomerFields,
  toCustomerPayload,
  currentSalesAssignments,
  type Customer,
  type CustomerFields,
} from '@/stores/customers'
import AddressFieldset from '@/components/AddressFieldset.vue'
import IconView from '@/components/icons/IconView.vue'
import IconDelete from '@/components/icons/IconDelete.vue'

const { t } = useI18n()
const store = useCustomersStore()
const { customers, loading, error } = storeToRefs(store)

// ── New customer form ────────────────────────────────────────────────
// Collapsed by default — the form only appears once the user clicks
// "New customer", so the page opens straight on the customer list.
const showNew = ref(false)
const form = reactive<CustomerFields>(emptyCustomerFields())
const billingSame = ref(true)
const creating = ref(false)
const createError = ref<string | null>(null)
const createSuccess = ref<string | null>(null)

function toggleNew() {
  showNew.value = !showNew.value
  if (showNew.value) {
    resetForm()
    createError.value = null
    createSuccess.value = null
  }
}

// While "same as address" is on, keep billing in sync with the address so
// it's already populated if the user later unchecks it.
watch(
  () => ({ ...form.address }),
  (addr) => {
    if (billingSame.value) form.billingAddress = { ...addr }
  },
  { deep: true },
)
watch(billingSame, (same) => {
  if (same) form.billingAddress = { ...form.address }
})

// ── List filter ──────────────────────────────────────────────────────
const search = ref('')

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  if ('' === q) return customers.value
  return customers.value.filter((c) =>
    [c.name, c.email, c.taxNumber, c.address.city, c.address.postalCode, c.address.street]
      .filter((v): v is string => null !== v && '' !== v)
      .some((v) => v.toLowerCase().includes(q)),
  )
})

onMounted(() => {
  store.fetchCustomers()
})

function resetForm() {
  Object.assign(form, emptyCustomerFields())
  billingSame.value = true
}

async function onCreate() {
  createError.value = null
  createSuccess.value = null
  creating.value = true

  const result = await store.createCustomer(toCustomerPayload(form, billingSame.value))
  creating.value = false

  if (result.ok) {
    createSuccess.value = t('adminCustomers.created')
    resetForm()
    showNew.value = false
  } else {
    createError.value = result.error ?? t('adminCustomers.createFailed')
  }
}

async function onDelete(c: Customer) {
  if (!window.confirm(t('adminCustomers.confirmDelete', { name: c.name }))) return

  const result = await store.deleteCustomer(c.id)
  if (!result.ok) {
    window.alert(result.error ?? t('admin.deleteFailed'))
  }
}

function currentSalesLabel(c: Customer): string {
  const active = currentSalesAssignments(c.salesAssignments)
  if (0 === active.length) return t('adminCustomers.salesUnassigned')
  return active.map((a) => a.userName).join(', ')
}

function formatDate(d: string | null): string {
  return null === d ? '—' : d
}

function validityLabel(c: Customer): string {
  if (null === c.validFrom && null === c.validUntil) return t('adminCustomers.validityOpen')
  return `${formatDate(c.validFrom)} → ${formatDate(c.validUntil)}`
}

</script>

<template>
  <section class="admin">
    <div class="container-lg">
      <div class="admin-head">
        <span class="eyebrow">{{ t('admin.eyebrow') }}</span>
        <h1>{{ t('nav.adminCustomers') }}</h1>
        <p>{{ t('adminCustomers.subtitle') }}</p>
      </div>

      <!-- ── New customer (shown only after clicking "New customer") ─ -->
      <form v-if="showNew" class="cust-panel" @submit.prevent="onCreate">
        <div class="cust-panel-head">
          <h2>{{ t('adminCustomers.newCustomer') }}</h2>
          <button type="button" class="btn-ghost" @click="toggleNew">
            {{ t('adminUsers.cancel') }}
          </button>
        </div>

        <div class="grid">
          <label class="field field--wide">
            <span>{{ t('adminCustomers.name') }} *</span>
            <input v-model="form.name" type="text" required maxlength="255" />
          </label>
          <label class="field">
            <span>{{ t('adminCustomers.website') }}</span>
            <input v-model="form.website" type="text" maxlength="255" placeholder="https://…" />
          </label>
          <label class="field">
            <span>{{ t('adminCustomers.taxNumber') }}</span>
            <input v-model="form.taxNumber" type="text" maxlength="64" />
          </label>
          <label class="field">
            <span>{{ t('adminCustomers.email') }}</span>
            <input v-model="form.email" type="email" maxlength="180" />
          </label>
          <label class="field">
            <span>{{ t('adminCustomers.phone') }}</span>
            <input v-model="form.phone" type="text" maxlength="64" />
          </label>
          <label class="field">
            <span>{{ t('adminCustomers.validFrom') }}</span>
            <input v-model="form.validFrom" type="date" />
          </label>
          <label class="field">
            <span>{{ t('adminCustomers.validUntil') }}</span>
            <input v-model="form.validUntil" type="date" />
          </label>
          <label class="field field--wide">
            <span>{{ t('adminCustomers.notes') }}</span>
            <textarea v-model="form.notes" rows="3" />
          </label>
        </div>

        <fieldset class="addr-block">
          <legend>{{ t('adminCustomers.address') }}</legend>
          <AddressFieldset v-model="form.address" id-stem="new" />
        </fieldset>

        <label class="addr-same">
          <input v-model="billingSame" type="checkbox" />
          <span>{{ t('adminCustomers.billingSameAsAddress') }}</span>
        </label>

        <fieldset v-if="!billingSame" class="addr-block">
          <legend>{{ t('adminCustomers.billingAddress') }}</legend>
          <AddressFieldset v-model="form.billingAddress" id-stem="new-bill" />
        </fieldset>

        <p v-if="createError" class="msg msg--error">{{ createError }}</p>
        <p v-if="createSuccess" class="msg msg--success">{{ createSuccess }}</p>

        <button type="submit" class="btn-submit" :disabled="creating">
          {{ creating ? t('admin.creating') : t('adminCustomers.create') }}
        </button>
      </form>

      <!-- ── Existing customers — list ───────────────────────────── -->
      <div class="cust-panel">
        <div class="cust-list-head">
          <h2>{{ t('adminCustomers.existing') }}</h2>
          <div class="cust-list-tools">
            <input
              v-model="search"
              type="search"
              :placeholder="t('adminCustomers.searchPlaceholder')"
              class="search"
            />
            <button type="button" class="btn-submit btn-new" @click="toggleNew">
              {{ showNew ? t('adminUsers.cancel') : '+ ' + t('adminCustomers.newCustomer') }}
            </button>
          </div>
        </div>

        <p v-if="loading" class="state">{{ t('adminCustomers.loading') }}</p>

        <div v-else-if="error" class="state state--error">
          <strong>{{ t('adminCustomers.loadError') }}</strong>
          <button type="button" class="btn-retry" @click="store.fetchCustomers()">
            {{ t('common.retry') }}
          </button>
        </div>

        <p v-else-if="customers.length === 0" class="state">{{ t('adminCustomers.empty') }}</p>

        <p v-else-if="filtered.length === 0" class="state">{{ t('adminCustomers.noMatches') }}</p>

        <div v-else class="cust-table-wrap">
          <table class="cust-table">
            <thead>
              <tr>
                <th>{{ t('adminCustomers.colName') }}</th>
                <th>{{ t('adminCustomers.colCountry') }}</th>
                <th>{{ t('adminCustomers.colCity') }}</th>
                <th>{{ t('adminCustomers.colSales') }}</th>
                <th>{{ t('adminCustomers.colValidity') }}</th>
                <th class="col-actions"><span class="sr-only">{{ t('adminCustomers.colActions') }}</span></th>
              </tr>
            </thead>
            <tbody>
              <template v-for="c in filtered" :key="c.id">
                <tr class="cust-tr">
                  <td class="cell-name">
                    <RouterLink
                      :to="{ name: 'admin-customer-detail', params: { id: c.id } }"
                      class="cell-name-title cell-name-link"
                    >
                      {{ c.name }}
                    </RouterLink>
                    <span v-if="c.email || c.phone || c.website" class="cell-name-sub">
                      <template v-if="c.email">{{ c.email }}</template>
                      <template v-if="c.email && c.phone"> · </template>
                      <template v-if="c.phone">{{ c.phone }}</template>
                      <template v-if="(c.email || c.phone) && c.website"> · </template>
                      <template v-if="c.website">{{ c.website }}</template>
                    </span>
                  </td>
                  <td>{{ c.address.country || '—' }}</td>
                  <td>{{ c.address.city || '—' }}</td>
                  <td>{{ currentSalesLabel(c) }}</td>
                  <td class="cell-validity">{{ validityLabel(c) }}</td>
                  <td class="col-actions">
                    <div class="cust-row-actions">
                      <RouterLink
                        :to="{ name: 'admin-customer-detail', params: { id: c.id } }"
                        class="btn-icon"
                        :title="t('adminCustomers.view')"
                        :aria-label="t('adminCustomers.view')"
                      >
                        <IconView />
                      </RouterLink>
                      <button
                        type="button"
                        class="btn-icon btn-icon--danger"
                        :title="t('admin.delete')"
                        :aria-label="t('admin.delete')"
                        @click="onDelete(c)"
                      >
                        <IconDelete />
                      </button>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>
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

.admin-head p {
  max-width: 640px;
  margin: 0;
  color: #545f71;
  font-size: 1.05rem;
  line-height: 1.5;
}

.cust-panel {
  margin-bottom: 1.5rem;
  padding: 1.85rem;
  background: #fff;
  border-radius: 1.1rem;
  box-shadow: 0 12px 32px rgba(12, 28, 64, 0.08);
}

.cust-panel h2 {
  margin: 0 0 1.3rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.25rem;
  font-weight: 700;
}

.cust-list-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
}

.cust-list-head h2 {
  margin: 0;
}

.cust-list-tools {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  flex-wrap: wrap;
}

.btn-new {
  white-space: nowrap;
  padding: 0.5rem 1.1rem;
  font-size: 0.9rem;
}

.cust-panel-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.3rem;
}

.cust-panel-head h2 {
  margin: 0;
}

.search {
  flex: 0 1 280px;
  padding: 0.5rem 0.75rem;
  background: #f7f8fb;
  border: 1px solid #d4dae6;
  border-radius: 0.55rem;
  font-size: 0.9rem;
}

.grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.9rem 1rem;
  margin-bottom: 1.1rem;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  min-width: 0;
}

.field--wide {
  grid-column: 1 / -1;
}

.field span {
  color: var(--login-secondary, #0c1c40);
  font-size: 0.85rem;
  font-weight: 700;
}

.field input,
.field textarea {
  padding: 0.55rem 0.7rem;
  background: #f7f8fb;
  border: 1px solid #d4dae6;
  border-radius: 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-family: inherit;
}

.field input:focus,
.field textarea:focus {
  outline: 2px solid var(--login-primary, #ed2044);
  outline-offset: -1px;
  background: #fff;
}

.msg {
  margin: 0 0 0.9rem;
  padding: 0.65rem 0.85rem;
  border-radius: 0.55rem;
  font-size: 0.9rem;
}

.msg--error {
  background: #fde8ec;
  color: #b3122e;
}

.msg--success {
  background: #e3f6ec;
  color: #1c7a45;
}

.btn-submit {
  padding: 0.7rem 1.5rem;
  background: var(--login-primary, #ed2044);
  border: none;
  border-radius: 0.55rem;
  color: #fff;
  font-size: 1rem;
  font-weight: 700;
  cursor: pointer;
}

.btn-submit:disabled {
  opacity: 0.65;
  cursor: progress;
}

.cust-table-wrap {
  margin-top: 1.1rem;
  overflow-x: auto;
}

.cust-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}

.cust-table thead th {
  padding: 0.6rem 0.85rem;
  text-align: left;
  color: #8b94a6;
  font-size: 0.74rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  white-space: nowrap;
  border-bottom: 2px solid #e3e7ee;
}

.cust-tr > td {
  padding: 0.7rem 0.85rem;
  color: #545f71;
  vertical-align: middle;
  border-bottom: 1px solid #eef1f6;
}

.cust-tr:hover > td {
  background: #f7f8fb;
}

.cell-name-title {
  display: block;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-weight: 700;
}

.cell-name-link {
  text-decoration: none;
}

.cell-name-link:hover {
  color: var(--login-primary, #ed2044);
  text-decoration: underline;
}

.cell-name-sub {
  display: block;
  margin-top: 0.1rem;
  color: #8b94a6;
  font-size: 0.78rem;
  font-weight: 600;
  word-break: break-word;
}

.cell-validity {
  white-space: nowrap;
}

.col-actions {
  text-align: right;
  white-space: nowrap;
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

.addr-block {
  margin: 0 0 1rem;
  padding: 1rem 1.1rem 1.1rem;
  border: 1px solid #e3e7ee;
  border-radius: 0.7rem;
}

.addr-block legend {
  padding: 0 0.45rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.85rem;
  font-weight: 700;
}

.addr-same {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0 0 1rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-weight: 600;
  cursor: pointer;
}

.addr-same input[type='checkbox'] {
  width: 1rem;
  height: 1rem;
  accent-color: var(--login-primary, #ed2044);
}

.cust-row-actions {
  display: flex;
  gap: 0.5rem;
  flex-shrink: 0;
}

.btn-ghost {
  padding: 0.4rem 0.9rem;
  background: #fff;
  border: 1px solid #d4dae6;
  border-radius: 0.45rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.85rem;
  font-weight: 700;
  cursor: pointer;
}

.btn-ghost:hover {
  border-color: var(--login-secondary, #0c1c40);
}

.btn-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  padding: 0;
  background: #fff;
  border: 1px solid #d4dae6;
  border-radius: 0.45rem;
  color: #545f71;
  cursor: pointer;
  transition:
    color 0.15s,
    border-color 0.15s,
    background 0.15s;
}

.btn-icon:hover {
  border-color: var(--login-secondary, #0c1c40);
  color: var(--login-secondary, #0c1c40);
}

.btn-icon--danger:hover {
  background: var(--login-primary, #ed2044);
  border-color: var(--login-primary, #ed2044);
  color: #fff;
}

.btn-icon:focus-visible {
  outline: 2px solid var(--login-primary, #ed2044);
  outline-offset: 1px;
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

@media (max-width: 767.98px) {
  .grid {
    grid-template-columns: 1fr;
  }
}
</style>

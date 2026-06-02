<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import {
  useCustomersStore,
  emptyCustomerFields,
  addressesEqual,
  currentSalesAssignments,
  type Customer,
  type CustomerFields,
  type Address,
  type SalesAssignment,
  type SalesAssignmentFields,
} from '@/stores/customers'
import { useUsersStore } from '@/stores/users'
import AddressFieldset from '@/components/AddressFieldset.vue'
import IconEdit from '@/components/icons/IconEdit.vue'
import IconDelete from '@/components/icons/IconDelete.vue'

const { t } = useI18n()
const store = useCustomersStore()
const usersStore = useUsersStore()
const { customers, loading, error } = storeToRefs(store)
const { users } = storeToRefs(usersStore)

// Sorted user options for the salesperson dropdown.
const userOptions = computed(() =>
  [...users.value].sort((a, b) => (a.lastName + a.firstName).localeCompare(b.lastName + b.firstName, 'hu')),
)

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

// ── Inline editor — one row open at a time ───────────────────────────
const editingId = ref<number | null>(null)
const editForm = reactive<CustomerFields>(emptyCustomerFields())
const editBillingSame = ref(false)
const editSaving = ref(false)
const editError = ref<string | null>(null)

// While the "same as address" checkbox is on, keep billing in sync with
// address so it's already populated if the user later unchecks it.
watch(
  () => ({ ...form.address }),
  (addr) => {
    if (billingSame.value) form.billingAddress = { ...addr }
  },
  { deep: true },
)
watch(
  () => ({ ...editForm.address }),
  (addr) => {
    if (editBillingSame.value) editForm.billingAddress = { ...addr }
  },
  { deep: true },
)
watch(billingSame, (same) => {
  if (same) form.billingAddress = { ...form.address }
})
watch(editBillingSame, (same) => {
  if (same) editForm.billingAddress = { ...editForm.address }
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
  // The salesperson dropdown needs the system users. Re-uses the shared
  // admin-users store; fetch only on first visit if the list is empty.
  if (0 === users.value.length) {
    usersStore.fetchUsers()
  }
})

// ── Sales assignment editor state (only one at a time) ───────────────
const newAssignment = reactive<SalesAssignmentFields>({
  userId: null,
  validFrom: null,
  validUntil: null,
  notes: null,
})
const assigning = ref(false)
const assignError = ref<string | null>(null)

const editingAssignmentId = ref<number | null>(null)
const editAssignment = reactive<SalesAssignmentFields>({
  userId: null,
  validFrom: null,
  validUntil: null,
  notes: null,
})
const editAssignmentSaving = ref(false)
const editAssignmentError = ref<string | null>(null)

function resetNewAssignment(): void {
  newAssignment.userId = null
  newAssignment.validFrom = null
  newAssignment.validUntil = null
  newAssignment.notes = null
  assignError.value = null
}

async function onAddAssignment(customerId: number): Promise<void> {
  if (null === newAssignment.userId) {
    assignError.value = t('adminCustomers.salesPickPerson')
    return
  }
  assigning.value = true
  const result = await store.createSalesAssignment(customerId, { ...newAssignment })
  assigning.value = false
  if (result.ok) {
    resetNewAssignment()
  } else {
    assignError.value = result.error ?? t('admin.saveFailed')
  }
}

function openEditAssignment(a: SalesAssignment): void {
  editingAssignmentId.value = a.id
  editAssignment.userId = a.userId
  editAssignment.validFrom = a.validFrom
  editAssignment.validUntil = a.validUntil
  editAssignment.notes = a.notes
  editAssignmentError.value = null
}

function closeEditAssignment(): void {
  editingAssignmentId.value = null
}

async function onSaveAssignment(customerId: number): Promise<void> {
  if (null === editingAssignmentId.value) return
  if (null === editAssignment.userId) {
    editAssignmentError.value = t('adminCustomers.salesPickPerson')
    return
  }
  editAssignmentSaving.value = true
  const result = await store.updateSalesAssignment(customerId, editingAssignmentId.value, { ...editAssignment })
  editAssignmentSaving.value = false
  if (result.ok) {
    editingAssignmentId.value = null
  } else {
    editAssignmentError.value = result.error ?? t('admin.saveFailed')
  }
}

async function onDeleteAssignment(customerId: number, a: SalesAssignment): Promise<void> {
  if (!window.confirm(t('adminCustomers.salesConfirmDelete', { name: a.userName }))) return
  const result = await store.deleteSalesAssignment(customerId, a.id)
  if (!result.ok) {
    window.alert(result.error ?? t('admin.deleteFailed'))
  } else if (editingAssignmentId.value === a.id) {
    editingAssignmentId.value = null
  }
}

function currentSalesLabel(c: Customer): string {
  const active = currentSalesAssignments(c.salesAssignments)
  if (0 === active.length) return t('adminCustomers.salesUnassigned')
  return active.map((a) => a.userName).join(', ')
}

function formatAssignmentPeriod(a: SalesAssignment): string {
  if (null === a.validFrom && null === a.validUntil) return t('adminCustomers.validityOpen')
  return `${a.validFrom ?? '—'} → ${a.validUntil ?? '—'}`
}

function resetForm() {
  Object.assign(form, emptyCustomerFields())
  billingSame.value = true
}

function copyInto(target: CustomerFields, source: Customer) {
  target.name = source.name
  target.address = { ...source.address }
  target.website = source.website
  target.billingAddress = { ...source.billingAddress }
  target.taxNumber = source.taxNumber
  target.email = source.email
  target.phone = source.phone
  target.notes = source.notes
  target.validFrom = source.validFrom
  target.validUntil = source.validUntil
}

function isEmptyAddress(a: Address): boolean {
  return null === a.country && null === a.city && null === a.postalCode && null === a.street
}

async function onCreate() {
  createError.value = null
  createSuccess.value = null
  creating.value = true

  const result = await store.createCustomer(toPayload(form, billingSame.value))
  creating.value = false

  if (result.ok) {
    createSuccess.value = t('adminCustomers.created')
    resetForm()
    showNew.value = false
  } else {
    createError.value = result.error ?? t('adminCustomers.createFailed')
  }
}

function openEdit(c: Customer) {
  editingId.value = c.id
  copyInto(editForm, c)
  // If billing matches address or is empty, pre-check the "same as" box
  // so the editor opens compact; otherwise let the two diverge as stored.
  editBillingSame.value = isEmptyAddress(c.billingAddress) || addressesEqual(c.address, c.billingAddress)
  editError.value = null
  // Reset assignment-editor state when switching customers.
  editingAssignmentId.value = null
  resetNewAssignment()
}

function closeEdit() {
  editingId.value = null
}

async function onSave() {
  if (null === editingId.value) return
  editError.value = null
  editSaving.value = true

  const result = await store.updateCustomer(editingId.value, toPayload(editForm, editBillingSame.value))
  editSaving.value = false

  if (result.ok) {
    editingId.value = null
  } else {
    editError.value = result.error ?? t('admin.saveFailed')
  }
}

async function onDelete(c: Customer) {
  if (!window.confirm(t('adminCustomers.confirmDelete', { name: c.name }))) return

  const result = await store.deleteCustomer(c.id)
  if (!result.ok) {
    window.alert(result.error ?? t('admin.deleteFailed'))
  } else if (editingId.value === c.id) {
    editingId.value = null
  }
}

// Normalize empty strings to null before sending — the API treats null
// and empty string the same, but null on the wire is the simpler invariant.
function toPayload(f: CustomerFields, copyBilling: boolean): CustomerFields {
  const norm = (v: string | null): string | null => (null === v || '' === v.trim() ? null : v.trim())
  return {
    name: f.name.trim(),
    address: { ...f.address },
    website: norm(f.website),
    billingAddress: copyBilling ? { ...f.address } : { ...f.billingAddress },
    taxNumber: norm(f.taxNumber),
    email: norm(f.email),
    phone: norm(f.phone),
    notes: norm(f.notes),
    validFrom: norm(f.validFrom),
    validUntil: norm(f.validUntil),
  }
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
                <tr class="cust-tr" :class="{ 'is-open': editingId === c.id }">
                  <td class="cell-name">
                    <span class="cell-name-title">{{ c.name }}</span>
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
                      <button
                        type="button"
                        class="btn-icon"
                        :class="{ 'is-active': editingId === c.id }"
                        :title="t('admin.edit')"
                        :aria-label="t('admin.edit')"
                        @click="editingId === c.id ? closeEdit() : openEdit(c)"
                      >
                        <IconEdit />
                      </button>
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

                <!-- Inline editor for the selected row, full width. -->
                <tr v-if="editingId === c.id" class="cust-edit-row">
                  <td :colspan="6">
                    <div class="cust-edit">
              <div class="grid">
                <label class="field field--wide">
                  <span>{{ t('adminCustomers.name') }} *</span>
                  <input v-model="editForm.name" type="text" required maxlength="255" />
                </label>
                <label class="field">
                  <span>{{ t('adminCustomers.website') }}</span>
                  <input v-model="editForm.website" type="text" maxlength="255" />
                </label>
                <label class="field">
                  <span>{{ t('adminCustomers.taxNumber') }}</span>
                  <input v-model="editForm.taxNumber" type="text" maxlength="64" />
                </label>
                <label class="field">
                  <span>{{ t('adminCustomers.email') }}</span>
                  <input v-model="editForm.email" type="email" maxlength="180" />
                </label>
                <label class="field">
                  <span>{{ t('adminCustomers.phone') }}</span>
                  <input v-model="editForm.phone" type="text" maxlength="64" />
                </label>
                <label class="field">
                  <span>{{ t('adminCustomers.validFrom') }}</span>
                  <input v-model="editForm.validFrom" type="date" />
                </label>
                <label class="field">
                  <span>{{ t('adminCustomers.validUntil') }}</span>
                  <input v-model="editForm.validUntil" type="date" />
                </label>
                <label class="field field--wide">
                  <span>{{ t('adminCustomers.notes') }}</span>
                  <textarea v-model="editForm.notes" rows="3" />
                </label>
              </div>

              <fieldset class="addr-block">
                <legend>{{ t('adminCustomers.address') }}</legend>
                <AddressFieldset v-model="editForm.address" :id-stem="`edit-${c.id}`" />
              </fieldset>

              <label class="addr-same">
                <input v-model="editBillingSame" type="checkbox" />
                <span>{{ t('adminCustomers.billingSameAsAddress') }}</span>
              </label>

              <fieldset v-if="!editBillingSame" class="addr-block">
                <legend>{{ t('adminCustomers.billingAddress') }}</legend>
                <AddressFieldset v-model="editForm.billingAddress" :id-stem="`edit-${c.id}-bill`" />
              </fieldset>

              <p v-if="editError" class="msg msg--error">{{ editError }}</p>

              <div class="cust-edit-actions">
                <button type="button" class="btn-submit" :disabled="editSaving" @click="onSave">
                  {{ editSaving ? t('admin.saving') : t('admin.save') }}
                </button>
                <button type="button" class="btn-ghost" @click="closeEdit">
                  {{ t('adminUsers.cancel') }}
                </button>
              </div>

              <!-- ── Felelős értékesítők ─────────────────────────── -->
              <fieldset class="sales-block">
                <legend>{{ t('adminCustomers.salesHeader') }}</legend>

                <p v-if="c.salesAssignments.length === 0" class="sales-empty">
                  {{ t('adminCustomers.salesEmpty') }}
                </p>

                <ul v-else class="sales-rows">
                  <li v-for="a in c.salesAssignments" :key="a.id" class="sales-row-wrap">
                    <div v-if="editingAssignmentId !== a.id" class="sales-row">
                      <div class="sales-row-main">
                        <span class="sales-row-name">{{ a.userName || a.userEmail }}</span>
                        <span class="sales-row-period">{{ formatAssignmentPeriod(a) }}</span>
                        <span v-if="a.notes" class="sales-row-notes">{{ a.notes }}</span>
                      </div>
                      <div class="sales-row-actions">
                        <button
                          type="button"
                          class="btn-icon"
                          :title="t('admin.edit')"
                          :aria-label="t('admin.edit')"
                          @click="openEditAssignment(a)"
                        >
                          <IconEdit />
                        </button>
                        <button
                          type="button"
                          class="btn-icon btn-icon--danger"
                          :title="t('admin.delete')"
                          :aria-label="t('admin.delete')"
                          @click="onDeleteAssignment(c.id, a)"
                        >
                          <IconDelete />
                        </button>
                      </div>
                    </div>

                    <div v-else class="sales-edit">
                      <div class="sales-form-grid">
                        <label class="field">
                          <span>{{ t('adminCustomers.salesPerson') }}</span>
                          <select v-model.number="editAssignment.userId">
                            <option :value="null">{{ t('adminCustomers.salesPickPerson') }}</option>
                            <option v-for="u in userOptions" :key="u.id" :value="u.id">
                              {{ u.lastName }} {{ u.firstName }} ({{ u.email }})
                            </option>
                          </select>
                        </label>
                        <label class="field">
                          <span>{{ t('adminCustomers.validFrom') }}</span>
                          <input v-model="editAssignment.validFrom" type="date" />
                        </label>
                        <label class="field">
                          <span>{{ t('adminCustomers.validUntil') }}</span>
                          <input v-model="editAssignment.validUntil" type="date" />
                        </label>
                        <label class="field field--wide">
                          <span>{{ t('adminCustomers.notes') }}</span>
                          <textarea v-model="editAssignment.notes" rows="2" />
                        </label>
                      </div>

                      <p v-if="editAssignmentError" class="msg msg--error">{{ editAssignmentError }}</p>

                      <div class="cust-edit-actions">
                        <button
                          type="button"
                          class="btn-submit"
                          :disabled="editAssignmentSaving"
                          @click="onSaveAssignment(c.id)"
                        >
                          {{ editAssignmentSaving ? t('admin.saving') : t('admin.save') }}
                        </button>
                        <button type="button" class="btn-ghost" @click="closeEditAssignment">
                          {{ t('adminUsers.cancel') }}
                        </button>
                      </div>
                    </div>
                  </li>
                </ul>

                <!-- ── Új hozzárendelés ────────────────────────── -->
                <div class="sales-new">
                  <h4>{{ t('adminCustomers.salesAdd') }}</h4>
                  <div class="sales-form-grid">
                    <label class="field">
                      <span>{{ t('adminCustomers.salesPerson') }}</span>
                      <select v-model.number="newAssignment.userId">
                        <option :value="null">{{ t('adminCustomers.salesPickPerson') }}</option>
                        <option v-for="u in userOptions" :key="u.id" :value="u.id">
                          {{ u.lastName }} {{ u.firstName }} ({{ u.email }})
                        </option>
                      </select>
                    </label>
                    <label class="field">
                      <span>{{ t('adminCustomers.validFrom') }}</span>
                      <input v-model="newAssignment.validFrom" type="date" />
                    </label>
                    <label class="field">
                      <span>{{ t('adminCustomers.validUntil') }}</span>
                      <input v-model="newAssignment.validUntil" type="date" />
                    </label>
                    <label class="field field--wide">
                      <span>{{ t('adminCustomers.notes') }}</span>
                      <textarea v-model="newAssignment.notes" rows="2" />
                    </label>
                  </div>

                  <p v-if="assignError" class="msg msg--error">{{ assignError }}</p>

                  <button
                    type="button"
                    class="btn-submit"
                    :disabled="assigning"
                    @click="onAddAssignment(c.id)"
                  >
                    {{ assigning ? t('admin.saving') : t('adminCustomers.salesAddButton') }}
                  </button>
                </div>
              </fieldset>
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

.cust-tr.is-open > td {
  background: #f7f8fb;
  border-bottom-color: transparent;
}

.cell-name-title {
  display: block;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-weight: 700;
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

.cust-edit-row > td {
  padding: 0;
  background: #f7f8fb;
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

.cust-row-sales {
  color: var(--login-secondary, #0c1c40);
  font-size: 0.82rem;
}

.sales-block {
  margin: 1.2rem 0 0;
  padding: 1rem 1.1rem 1.2rem;
  border: 1px solid #e3e7ee;
  border-radius: 0.7rem;
  background: #fff;
}

.sales-block legend {
  padding: 0 0.45rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-weight: 700;
}

.sales-empty {
  margin: 0 0 0.8rem;
  color: #8b94a6;
  font-size: 0.9rem;
}

.sales-rows {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin: 0 0 1rem;
  padding: 0;
  list-style: none;
}

.sales-row-wrap {
  background: #f7f8fb;
  border-radius: 0.6rem;
}

.sales-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.7rem 0.9rem;
}

.sales-row-main {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  min-width: 0;
}

.sales-row-name {
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-weight: 700;
}

.sales-row-period {
  color: #545f71;
  font-size: 0.82rem;
  font-weight: 600;
}

.sales-row-notes {
  color: #8b94a6;
  font-size: 0.82rem;
  word-break: break-word;
}

.sales-row-actions {
  display: flex;
  gap: 0.5rem;
  flex-shrink: 0;
}

.sales-edit {
  padding: 0.9rem 1rem 1rem;
}

.sales-new {
  padding-top: 0.5rem;
  border-top: 1px dashed #d4dae6;
}

.sales-new h4 {
  margin: 0.6rem 0 0.7rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-weight: 700;
}

.sales-form-grid {
  display: grid;
  grid-template-columns: 1.6fr 1fr 1fr;
  gap: 0.7rem 0.9rem;
  margin-bottom: 0.8rem;
}

.sales-form-grid .field--wide {
  grid-column: 1 / -1;
}

@media (max-width: 767.98px) {
  .sales-form-grid {
    grid-template-columns: 1fr;
  }

  .sales-row {
    flex-direction: column;
    align-items: flex-start;
  }
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

.btn-delete {
  padding: 0.4rem 0.9rem;
  background: #fff;
  border: 1px solid #e0a9b3;
  border-radius: 0.45rem;
  color: #b3122e;
  font-size: 0.85rem;
  font-weight: 700;
  cursor: pointer;
}

.btn-delete:hover {
  background: var(--login-primary, #ed2044);
  border-color: var(--login-primary, #ed2044);
  color: #fff;
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

.btn-icon.is-active {
  border-color: var(--login-secondary, #0c1c40);
  color: var(--login-secondary, #0c1c40);
  background: #eef1f6;
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

.cust-edit {
  padding: 1.2rem 1.1rem 1.4rem;
}

.cust-edit-actions {
  display: flex;
  gap: 0.6rem;
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

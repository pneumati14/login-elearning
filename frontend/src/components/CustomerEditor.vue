<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import {
  useCustomersStore,
  emptyCustomerFields,
  addressesEqual,
  toCustomerPayload,
  type Customer,
  type CustomerFields,
  type Address,
  type SalesAssignment,
  type SalesAssignmentFields,
} from '@/stores/customers'
import { useUsersStore } from '@/stores/users'
import { useAuthStore } from '@/stores/auth'
import AddressFieldset from '@/components/AddressFieldset.vue'
import AppSelect from '@/components/AppSelect.vue'
import IconEdit from '@/components/icons/IconEdit.vue'
import IconDelete from '@/components/icons/IconDelete.vue'

const props = defineProps<{ customer: Customer }>()
const emit = defineEmits<{ saved: []; cancel: [] }>()

const { t } = useI18n()
const store = useCustomersStore()
const usersStore = useUsersStore()
const auth = useAuthStore()
const { users } = storeToRefs(usersStore)

const userOptions = computed(() =>
  [...users.value].sort((a, b) => (a.lastName + a.firstName).localeCompare(b.lastName + b.firstName, 'hu')),
)

// ── AppSelect option lists ───────────────────────────────────────────
const statusSelectOptions = computed<{ value: string; label: string }[]>(() => [
  { value: 'potential', label: t('adminCustomers.status_potential') },
  { value: 'existing', label: t('adminCustomers.status_existing') },
])
const salesPersonSelectOptions = computed<{ value: number | null; label: string }[]>(() => [
  { value: null, label: t('adminCustomers.salesPickPerson') },
  ...userOptions.value.map((u) => ({ value: u.id, label: `${u.lastName} ${u.firstName} (${u.email})` })),
])

// ── Customer fields ──────────────────────────────────────────────────
const editForm = reactive<CustomerFields>(emptyCustomerFields())
const billingSame = ref(false)
const saving = ref(false)
const error = ref<string | null>(null)

function isEmptyAddress(a: Address): boolean {
  return null === a.country && null === a.city && null === a.postalCode && null === a.street
}

function copyInto(target: CustomerFields, source: Customer) {
  target.name = source.name
  target.status = source.status
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

function init() {
  copyInto(editForm, props.customer)
  // Open compact when billing matches the address or is empty.
  billingSame.value =
    isEmptyAddress(props.customer.billingAddress) || addressesEqual(props.customer.address, props.customer.billingAddress)
  error.value = null
  editingAssignmentId.value = null
  resetNewAssignment()
}

// While "same as address" is on, keep billing in sync so it's already
// populated if the user later unchecks it.
watch(
  () => ({ ...editForm.address }),
  (addr) => {
    if (billingSame.value) editForm.billingAddress = { ...addr }
  },
  { deep: true },
)
watch(billingSame, (same) => {
  if (same) editForm.billingAddress = { ...editForm.address }
})

// Re-initialise when the editor is pointed at a different customer.
watch(() => props.customer.id, init)

onMounted(() => {
  init()
  // Only managers/admins edit assignments, and only they may read the
  // user list (GET /admin/users is gated to sales managers and above).
  if (auth.canManageAssignments && 0 === users.value.length) usersStore.fetchUsers()
})

async function onSave() {
  error.value = null
  saving.value = true
  const result = await store.updateCustomer(props.customer.id, toCustomerPayload(editForm, billingSame.value))
  saving.value = false
  if (result.ok) {
    emit('saved')
  } else {
    error.value = result.error ?? t('admin.saveFailed')
  }
}

// ── Sales assignments ────────────────────────────────────────────────
const newAssignment = reactive<SalesAssignmentFields>({ userId: null, validFrom: null, validUntil: null, notes: null })
const assigning = ref(false)
const assignError = ref<string | null>(null)

const editingAssignmentId = ref<number | null>(null)
const editAssignment = reactive<SalesAssignmentFields>({ userId: null, validFrom: null, validUntil: null, notes: null })
const editAssignmentSaving = ref(false)
const editAssignmentError = ref<string | null>(null)

function resetNewAssignment(): void {
  newAssignment.userId = null
  newAssignment.validFrom = null
  newAssignment.validUntil = null
  newAssignment.notes = null
  assignError.value = null
}

async function onAddAssignment(): Promise<void> {
  if (null === newAssignment.userId) {
    assignError.value = t('adminCustomers.salesPickPerson')
    return
  }
  assigning.value = true
  const result = await store.createSalesAssignment(props.customer.id, { ...newAssignment })
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

async function onSaveAssignment(): Promise<void> {
  if (null === editingAssignmentId.value) return
  if (null === editAssignment.userId) {
    editAssignmentError.value = t('adminCustomers.salesPickPerson')
    return
  }
  editAssignmentSaving.value = true
  const result = await store.updateSalesAssignment(props.customer.id, editingAssignmentId.value, { ...editAssignment })
  editAssignmentSaving.value = false
  if (result.ok) {
    editingAssignmentId.value = null
  } else {
    editAssignmentError.value = result.error ?? t('admin.saveFailed')
  }
}

async function onDeleteAssignment(a: SalesAssignment): Promise<void> {
  if (!window.confirm(t('adminCustomers.salesConfirmDelete', { name: a.userName }))) return
  const result = await store.deleteSalesAssignment(props.customer.id, a.id)
  if (!result.ok) {
    window.alert(result.error ?? t('admin.deleteFailed'))
  } else if (editingAssignmentId.value === a.id) {
    editingAssignmentId.value = null
  }
}

function formatAssignmentPeriod(a: SalesAssignment): string {
  if (null === a.validFrom && null === a.validUntil) return t('adminCustomers.validityOpen')
  return `${a.validFrom ?? '—'} → ${a.validUntil ?? '—'}`
}
</script>

<template>
  <div class="customer-editor">
    <div class="grid">
      <label class="field field--wide">
        <span>{{ t('adminCustomers.name') }} *</span>
        <input v-model="editForm.name" type="text" required maxlength="255" />
      </label>
      <label class="field">
        <span>{{ t('adminCustomers.status') }}</span>
        <AppSelect v-model="editForm.status" :options="statusSelectOptions" />
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
      <AddressFieldset v-model="editForm.address" :id-stem="`edit-${customer.id}`" />
    </fieldset>

    <label class="addr-same">
      <input v-model="billingSame" type="checkbox" />
      <span>{{ t('adminCustomers.billingSameAsAddress') }}</span>
    </label>

    <fieldset v-if="!billingSame" class="addr-block">
      <legend>{{ t('adminCustomers.billingAddress') }}</legend>
      <AddressFieldset v-model="editForm.billingAddress" :id-stem="`edit-${customer.id}-bill`" />
    </fieldset>

    <p v-if="error" class="msg msg--error">{{ error }}</p>

    <div class="editor-actions">
      <button type="button" class="btn-submit" :disabled="saving" @click="onSave">
        {{ saving ? t('admin.saving') : t('admin.save') }}
      </button>
      <button type="button" class="btn-ghost" @click="emit('cancel')">
        {{ t('adminUsers.cancel') }}
      </button>
    </div>

    <!-- ── Felelős értékesítők ──────────────────────────────────────── -->
    <fieldset class="sales-block">
      <legend>{{ t('adminCustomers.salesHeader') }}</legend>

      <p v-if="customer.salesAssignments.length === 0" class="sales-empty">
        {{ t('adminCustomers.salesEmpty') }}
      </p>

      <ul v-else class="sales-rows">
        <li v-for="a in customer.salesAssignments" :key="a.id" class="sales-row-wrap">
          <div v-if="editingAssignmentId !== a.id" class="sales-row">
            <div class="sales-row-main">
              <span class="sales-row-name">{{ a.userName || a.userEmail }}</span>
              <span class="sales-row-period">{{ formatAssignmentPeriod(a) }}</span>
              <span v-if="a.notes" class="sales-row-notes">{{ a.notes }}</span>
            </div>
            <div v-if="auth.canManageAssignments" class="sales-row-actions">
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
                @click="onDeleteAssignment(a)"
              >
                <IconDelete />
              </button>
            </div>
          </div>

          <div v-else class="sales-edit">
            <div class="sales-form-grid">
              <label class="field">
                <span>{{ t('adminCustomers.salesPerson') }}</span>
                <AppSelect v-model="editAssignment.userId" :options="salesPersonSelectOptions" />
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

            <div class="editor-actions">
              <button type="button" class="btn-submit" :disabled="editAssignmentSaving" @click="onSaveAssignment">
                {{ editAssignmentSaving ? t('admin.saving') : t('admin.save') }}
              </button>
              <button type="button" class="btn-ghost" @click="closeEditAssignment">
                {{ t('adminUsers.cancel') }}
              </button>
            </div>
          </div>
        </li>
      </ul>

      <!-- ── Új hozzárendelés (csak sales manager / admin) ─────────── -->
      <div v-if="auth.canManageAssignments" class="sales-new">
        <h4>{{ t('adminCustomers.salesAdd') }}</h4>
        <div class="sales-form-grid">
          <label class="field">
            <span>{{ t('adminCustomers.salesPerson') }}</span>
            <AppSelect v-model="newAssignment.userId" :options="salesPersonSelectOptions" />
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

        <button type="button" class="btn-submit" :disabled="assigning" @click="onAddAssignment">
          {{ assigning ? t('admin.saving') : t('adminCustomers.salesAddButton') }}
        </button>
      </div>
    </fieldset>
  </div>
</template>

<style scoped>
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
.field textarea,
.field select {
  padding: 0.55rem 0.7rem;
  background: #f7f8fb;
  border: 1px solid #d4dae6;
  border-radius: 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-family: inherit;
}

.field input:focus,
.field textarea:focus,
.field select:focus {
  outline: 2px solid var(--login-primary, #ed2044);
  outline-offset: -1px;
  background: #fff;
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

.editor-actions {
  display: flex;
  gap: 0.6rem;
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
  .grid,
  .sales-form-grid {
    grid-template-columns: 1fr;
  }

  .sales-row {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>

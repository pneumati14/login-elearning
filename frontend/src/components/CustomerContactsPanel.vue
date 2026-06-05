<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  useCustomersStore,
  emptyContactFields,
  type Customer,
  type Contact,
  type ContactFields,
} from '@/stores/customers'
import IconEdit from '@/components/icons/IconEdit.vue'
import IconDelete from '@/components/icons/IconDelete.vue'

const props = defineProps<{ customer: Customer }>()

const { t } = useI18n()
const store = useCustomersStore()

// A single collapsible form serves both creating and editing, mirroring
// the customer list's "new" form. editingId === null means we are
// creating; a number means we are editing that contact.
const showForm = ref(false)
const editingId = ref<number | null>(null)
const form = reactive<ContactFields>(emptyContactFields())
const saving = ref(false)
const formError = ref<string | null>(null)

function openNew(): void {
  editingId.value = null
  Object.assign(form, emptyContactFields())
  formError.value = null
  showForm.value = true
}

function openEdit(c: Contact): void {
  editingId.value = c.id
  form.firstName = c.firstName
  form.lastName = c.lastName
  form.jobTitle = c.jobTitle
  form.email = c.email
  form.phone = c.phone
  form.mobile = c.mobile
  form.isPrimary = c.isPrimary
  form.notes = c.notes
  formError.value = null
  showForm.value = true
}

function closeForm(): void {
  showForm.value = false
  editingId.value = null
}

async function onSubmit(): Promise<void> {
  if ('' === form.firstName.trim() && '' === form.lastName.trim()) {
    formError.value = t('adminCustomers.contactNameRequired')
    return
  }
  saving.value = true
  const result =
    null === editingId.value
      ? await store.createContact(props.customer.id, { ...form })
      : await store.updateContact(props.customer.id, editingId.value, { ...form })
  saving.value = false

  if (result.ok) {
    closeForm()
  } else {
    formError.value = result.error ?? t('admin.saveFailed')
  }
}

async function onDelete(c: Contact): Promise<void> {
  if (!window.confirm(t('adminCustomers.contactConfirmDelete', { name: contactName(c) }))) return
  const result = await store.deleteContact(props.customer.id, c.id)
  if (!result.ok) {
    window.alert(result.error ?? t('admin.deleteFailed'))
  } else if (editingId.value === c.id) {
    closeForm()
  }
}

function contactName(c: Contact): string {
  const name = `${c.lastName} ${c.firstName}`.trim()
  return '' === name ? (c.email ?? '—') : name
}

function reachLabel(c: Contact): string {
  return [c.email, c.phone, c.mobile].filter((v): v is string => null !== v && '' !== v).join(' · ')
}
</script>

<template>
  <div class="contacts-panel">
    <div v-if="!showForm" class="contacts-head">
      <button type="button" class="btn-new" @click="openNew()">
        {{ '+ ' + t('adminCustomers.contactAdd') }}
      </button>
    </div>

    <!-- ── Shared create / edit form ────────────────────────────────── -->
    <form v-if="showForm" class="contact-form" @submit.prevent="onSubmit">
      <h4>{{ null === editingId ? t('adminCustomers.contactAdd') : t('adminCustomers.contactEdit') }}</h4>
      <div class="contact-form-grid">
        <label class="field">
          <span>{{ t('adminCustomers.contactLastName') }}</span>
          <input v-model="form.lastName" type="text" maxlength="255" />
        </label>
        <label class="field">
          <span>{{ t('adminCustomers.contactFirstName') }}</span>
          <input v-model="form.firstName" type="text" maxlength="255" />
        </label>
        <label class="field">
          <span>{{ t('adminCustomers.contactJobTitle') }}</span>
          <input v-model="form.jobTitle" type="text" maxlength="255" />
        </label>
        <label class="field">
          <span>{{ t('adminCustomers.email') }}</span>
          <input v-model="form.email" type="email" maxlength="180" />
        </label>
        <label class="field">
          <span>{{ t('adminCustomers.contactPhone') }}</span>
          <input v-model="form.phone" type="text" maxlength="64" />
        </label>
        <label class="field">
          <span>{{ t('adminCustomers.contactMobile') }}</span>
          <input v-model="form.mobile" type="text" maxlength="64" />
        </label>
        <label class="field field--wide">
          <span>{{ t('adminCustomers.notes') }}</span>
          <textarea v-model="form.notes" rows="2" />
        </label>
        <label class="check-field field--wide">
          <input v-model="form.isPrimary" type="checkbox" />
          <span>{{ t('adminCustomers.contactPrimaryLabel') }}</span>
        </label>
      </div>

      <p v-if="formError" class="msg msg--error">{{ formError }}</p>

      <div class="form-actions">
        <button type="submit" class="btn-submit" :disabled="saving">
          {{ saving ? t('admin.saving') : null === editingId ? t('adminCustomers.contactAddButton') : t('admin.save') }}
        </button>
        <button type="button" class="btn-ghost" @click="closeForm">
          {{ t('adminUsers.cancel') }}
        </button>
      </div>
    </form>

    <!-- ── Contacts table (hidden while the form is open) ───────────── -->
    <p v-if="!showForm && customer.contacts.length === 0" class="state">{{ t('adminCustomers.contactsEmpty') }}</p>

    <div v-else-if="!showForm" class="contact-table-wrap">
      <table class="contact-table">
        <thead>
          <tr>
            <th>{{ t('adminCustomers.name') }}</th>
            <th>{{ t('adminCustomers.contactJobTitle') }}</th>
            <th>{{ t('adminCustomers.contactColReach') }}</th>
            <th class="col-actions"><span class="sr-only">{{ t('adminCustomers.colActions') }}</span></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="c in customer.contacts" :key="c.id" class="contact-tr" :class="{ 'is-editing': editingId === c.id }">
            <td class="cell-name">
              <span class="cell-name-title">
                {{ contactName(c) }}
                <span v-if="c.isPrimary" class="badge">{{ t('adminCustomers.contactPrimary') }}</span>
              </span>
              <span v-if="c.notes" class="cell-name-sub">{{ c.notes }}</span>
            </td>
            <td>{{ c.jobTitle || '—' }}</td>
            <td class="cell-reach">{{ reachLabel(c) || '—' }}</td>
            <td class="col-actions">
              <div class="contact-row-actions">
                <button
                  type="button"
                  class="btn-icon"
                  :title="t('admin.edit')"
                  :aria-label="t('admin.edit')"
                  @click="openEdit(c)"
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
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.contacts-head {
  display: flex;
  justify-content: flex-end;
  margin-bottom: 1.1rem;
}

.btn-new {
  white-space: nowrap;
  padding: 0.5rem 1.1rem;
  background: var(--login-primary, #ed2044);
  border: none;
  border-radius: 0.55rem;
  color: #fff;
  font-size: 0.9rem;
  font-weight: 700;
  cursor: pointer;
}

/* ── Shared form ──────────────────────────────────────────────────── */
.contact-form {
  margin-bottom: 1.6rem;
  padding: 1.1rem 1.2rem 1.3rem;
  background: #fff;
  border: 1px dashed #d4dae6;
  border-radius: 0.7rem;
}

.contact-form h4 {
  margin: 0 0 0.9rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-weight: 700;
}

.contact-form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.8rem 1rem;
  margin-bottom: 1rem;
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

.check-field {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.92rem;
  font-weight: 600;
  cursor: pointer;
}

.check-field input[type='checkbox'] {
  width: 1rem;
  height: 1rem;
  accent-color: var(--login-primary, #ed2044);
}

.form-actions {
  display: flex;
  gap: 0.6rem;
}

.btn-submit {
  padding: 0.6rem 1.3rem;
  background: var(--login-primary, #ed2044);
  border: none;
  border-radius: 0.5rem;
  color: #fff;
  font-size: 0.9rem;
  font-weight: 700;
  cursor: pointer;
}

.btn-submit:disabled {
  opacity: 0.6;
  cursor: default;
}

.btn-ghost {
  padding: 0.6rem 1.3rem;
  background: #fff;
  border: 1px solid #d4dae6;
  border-radius: 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.9rem;
  font-weight: 700;
  cursor: pointer;
}

.btn-ghost:hover {
  border-color: var(--login-secondary, #0c1c40);
}

.msg {
  margin: 0 0 0.8rem;
  font-size: 0.88rem;
  font-weight: 600;
}

.msg--error {
  color: #b3122e;
}

/* ── Table ────────────────────────────────────────────────────────── */
.contact-table-wrap {
  overflow-x: auto;
}

.contact-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}

.contact-table thead th {
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

.contact-tr > td {
  padding: 0.7rem 0.85rem;
  color: #545f71;
  vertical-align: middle;
  border-bottom: 1px solid #eef1f6;
}

.contact-tr:hover > td {
  background: #f7f8fb;
}

.contact-tr.is-editing > td {
  background: #fef6f7;
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

.cell-reach {
  word-break: break-word;
}

.col-actions {
  text-align: right;
  white-space: nowrap;
}

.contact-row-actions {
  display: flex;
  gap: 0.5rem;
  justify-content: flex-end;
  flex-shrink: 0;
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

.state {
  margin: 0;
  padding: 1.3rem;
  background: #f7f8fb;
  border-radius: 0.7rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 1rem;
}

@media (max-width: 767.98px) {
  .contact-form-grid {
    grid-template-columns: 1fr;
  }
}
</style>

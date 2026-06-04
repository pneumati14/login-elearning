<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import {
  useSuppliersStore,
  emptySupplierFields,
  type Supplier,
  type SupplierFields,
} from '@/stores/suppliers'
import IconEdit from '@/components/icons/IconEdit.vue'
import IconDelete from '@/components/icons/IconDelete.vue'

const { t } = useI18n()
const store = useSuppliersStore()
const { suppliers, loading, error } = storeToRefs(store)

const search = ref('')

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  if ('' === q) return suppliers.value
  return suppliers.value.filter((s) =>
    [s.name, s.contactName, s.email, s.phone]
      .filter((v): v is string => null !== v && '' !== v)
      .some((v) => v.toLowerCase().includes(q)),
  )
})

onMounted(() => {
  store.fetchSuppliers()
})

// ── Shared create/edit form ──────────────────────────────────────────
const showForm = ref(false)
const editingId = ref<number | null>(null)
const form = reactive<SupplierFields>(emptySupplierFields())
const saving = ref(false)
const formError = ref<string | null>(null)

function openCreate(): void {
  Object.assign(form, emptySupplierFields())
  editingId.value = null
  formError.value = null
  showForm.value = true
}

function openEdit(s: Supplier): void {
  Object.assign(form, {
    name: s.name,
    contactName: s.contactName,
    email: s.email,
    phone: s.phone,
    notes: s.notes,
    isActive: s.isActive,
  })
  editingId.value = s.id
  formError.value = null
  showForm.value = true
}

function closeForm(): void {
  showForm.value = false
  editingId.value = null
}

async function onSubmit(): Promise<void> {
  formError.value = null
  saving.value = true
  const result =
    null === editingId.value
      ? await store.createSupplier({ ...form })
      : await store.updateSupplier(editingId.value, { ...form })
  saving.value = false
  if (result.ok) {
    closeForm()
  } else {
    formError.value = result.error ?? t('admin.saveFailed')
  }
}

async function onDelete(s: Supplier): Promise<void> {
  if (!window.confirm(t('adminSuppliers.confirmDelete', { name: s.name }))) return
  const result = await store.deleteSupplier(s.id)
  if (!result.ok) window.alert(result.error ?? t('admin.deleteFailed'))
}
</script>

<template>
  <section class="admin">
    <div class="container-lg">
      <div class="admin-head">
        <span class="eyebrow">{{ t('admin.eyebrow') }}</span>
        <h1>{{ t('nav.adminSuppliers') }}</h1>
        <p>{{ t('adminSuppliers.subtitle') }}</p>
      </div>

      <div class="sup-panel">
        <div class="sup-list-head">
          <h2>{{ t('adminSuppliers.existing') }}</h2>
          <div class="sup-list-tools">
            <input
              v-model="search"
              type="search"
              :placeholder="t('adminSuppliers.searchPlaceholder')"
              class="search"
            />
            <button type="button" class="btn-submit btn-new" @click="showForm ? closeForm() : openCreate()">
              {{ showForm ? t('adminUsers.cancel') : '+ ' + t('adminSuppliers.newSupplier') }}
            </button>
          </div>
        </div>

        <!-- ── Create / edit form ────────────────────────────────────── -->
        <form v-if="showForm" class="sup-form" @submit.prevent="onSubmit">
          <h3>{{ null === editingId ? t('adminSuppliers.newSupplier') : t('admin.edit') }}</h3>
          <div class="grid">
            <label class="field field--wide">
              <span>{{ t('adminSuppliers.name') }} *</span>
              <input v-model="form.name" type="text" required maxlength="255" />
            </label>
            <label class="field">
              <span>{{ t('adminSuppliers.contactName') }}</span>
              <input v-model="form.contactName" type="text" maxlength="255" />
            </label>
            <label class="field">
              <span>{{ t('adminCustomers.email') }}</span>
              <input v-model="form.email" type="email" maxlength="180" />
            </label>
            <label class="field">
              <span>{{ t('adminCustomers.phone') }}</span>
              <input v-model="form.phone" type="text" maxlength="64" />
            </label>
            <label class="active-toggle">
              <input v-model="form.isActive" type="checkbox" />
              <span>{{ t('adminSuppliers.active') }}</span>
            </label>
            <label class="field field--wide">
              <span>{{ t('adminCustomers.notes') }}</span>
              <textarea v-model="form.notes" rows="2" />
            </label>
          </div>
          <p v-if="formError" class="msg msg--error">{{ formError }}</p>
          <div class="form-actions">
            <button type="submit" class="btn-submit" :disabled="saving">
              {{ saving ? t('admin.saving') : t('admin.save') }}
            </button>
            <button type="button" class="btn-ghost" @click="closeForm">{{ t('adminUsers.cancel') }}</button>
          </div>
        </form>

        <p v-if="loading" class="state">{{ t('adminSuppliers.loading') }}</p>

        <div v-else-if="error" class="state state--error">
          <strong>{{ t('adminSuppliers.loadError') }}</strong>
          <button type="button" class="btn-retry" @click="store.fetchSuppliers()">{{ t('common.retry') }}</button>
        </div>

        <p v-else-if="suppliers.length === 0" class="state">{{ t('adminSuppliers.empty') }}</p>

        <p v-else-if="filtered.length === 0" class="state">{{ t('adminSuppliers.noMatches') }}</p>

        <div v-else class="sup-table-wrap">
          <table class="sup-table">
            <thead>
              <tr>
                <th>{{ t('adminSuppliers.name') }}</th>
                <th>{{ t('adminSuppliers.contactName') }}</th>
                <th>{{ t('adminCustomers.email') }}</th>
                <th>{{ t('adminCustomers.phone') }}</th>
                <th>{{ t('adminSuppliers.colStatus') }}</th>
                <th class="col-actions"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="s in filtered" :key="s.id" :class="{ 'is-inactive': !s.isActive }">
                <td>
                  <span class="sup-name">{{ s.name }}</span>
                  <span v-if="s.notes" class="sup-notes">{{ s.notes }}</span>
                </td>
                <td>{{ s.contactName || '—' }}</td>
                <td>{{ s.email || '—' }}</td>
                <td>{{ s.phone || '—' }}</td>
                <td>
                  <span class="badge" :class="s.isActive ? 'badge--active' : 'badge--inactive'">
                    {{ s.isActive ? t('adminSuppliers.active') : t('adminSuppliers.inactive') }}
                  </span>
                </td>
                <td class="col-actions">
                  <div class="row-actions">
                    <button type="button" class="btn-icon" :title="t('admin.edit')" :aria-label="t('admin.edit')" @click="openEdit(s)">
                      <IconEdit />
                    </button>
                    <button
                      type="button"
                      class="btn-icon btn-icon--danger"
                      :title="t('admin.delete')"
                      :aria-label="t('admin.delete')"
                      @click="onDelete(s)"
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

.sup-panel {
  padding: 1.85rem;
  background: #fff;
  border-radius: 1.1rem;
  box-shadow: 0 12px 32px rgba(12, 28, 64, 0.08);
}

.sup-panel h2 {
  margin: 0;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.25rem;
  font-weight: 700;
}

.sup-list-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
  margin-bottom: 1.1rem;
}

.sup-list-tools {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  flex-wrap: wrap;
}

.search {
  flex: 0 1 280px;
  padding: 0.5rem 0.75rem;
  background: #f7f8fb;
  border: 1px solid #d4dae6;
  border-radius: 0.55rem;
  font-size: 0.9rem;
}

.btn-new {
  white-space: nowrap;
  padding: 0.5rem 1.1rem;
  font-size: 0.9rem;
}

.sup-form {
  margin-bottom: 1.3rem;
  padding: 1.2rem 1.3rem;
  background: #f7f8fb;
  border-radius: 0.8rem;
}

.sup-form h3 {
  margin: 0 0 1rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.05rem;
  font-weight: 700;
}

.grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.9rem 1rem;
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
  background: #fff;
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
}

.active-toggle {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  align-self: end;
  padding-bottom: 0.4rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-weight: 600;
  cursor: pointer;
}

.active-toggle input {
  width: 1rem;
  height: 1rem;
  accent-color: var(--login-primary, #ed2044);
}

.form-actions {
  display: flex;
  gap: 0.6rem;
}

.sup-table-wrap {
  overflow-x: auto;
}

.sup-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.92rem;
}

.sup-table th {
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

.sup-table td {
  padding: 0.65rem 0.7rem;
  border-bottom: 1px solid #eef1f6;
  color: #545f71;
  vertical-align: middle;
}

.sup-table tr.is-inactive td {
  color: #9aa6bd;
}

.sup-name {
  display: block;
  color: var(--login-secondary, #0c1c40);
  font-weight: 700;
}

.sup-notes {
  display: block;
  margin-top: 0.1rem;
  color: #8b94a6;
  font-size: 0.8rem;
}

.col-actions {
  text-align: right;
  white-space: nowrap;
}

.row-actions {
  display: inline-flex;
  gap: 0.4rem;
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

.badge--active {
  background: #e3f6ec;
  color: #1c7a45;
}

.badge--inactive {
  background: #eef1f6;
  color: #8b94a6;
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

.btn-submit {
  padding: 0.6rem 1.3rem;
  background: var(--login-primary, #ed2044);
  border: none;
  border-radius: 0.55rem;
  color: #fff;
  font-size: 0.95rem;
  font-weight: 700;
  cursor: pointer;
}

.btn-submit:disabled {
  opacity: 0.65;
  cursor: progress;
}

.btn-ghost {
  padding: 0.5rem 1.1rem;
  background: #fff;
  border: 1px solid #d4dae6;
  border-radius: 0.45rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.9rem;
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
  width: 1.9rem;
  height: 1.9rem;
  padding: 0;
  background: #fff;
  border: 1px solid #d4dae6;
  border-radius: 0.45rem;
  color: #545f71;
  cursor: pointer;
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

@media (max-width: 767.98px) {
  .grid {
    grid-template-columns: 1fr;
  }
}
</style>

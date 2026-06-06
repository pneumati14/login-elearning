<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import {
  useIntegrationsStore,
  INTEGRATION_CATEGORIES,
  type Integration,
  type IntegrationCategory,
} from '@/stores/integrations'
import AppSelect from '@/components/AppSelect.vue'
import IconEdit from '@/components/icons/IconEdit.vue'
import IconDelete from '@/components/icons/IconDelete.vue'

const { t } = useI18n()
const store = useIntegrationsStore()
const { integrations, loading, error } = storeToRefs(store)

const search = ref('')

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  if ('' === q) return integrations.value
  return integrations.value.filter((i) => i.name.toLowerCase().includes(q))
})

onMounted(() => {
  store.fetchIntegrations()
})

const categorySelectOptions = computed<{ value: IntegrationCategory; label: string }[]>(() =>
  INTEGRATION_CATEGORIES.map((c) => ({ value: c, label: t('adminIntegrations.cat_' + c) })),
)

// ── Shared create/edit form (replaces the list while open) ────────────
const showForm = ref(false)
const editingId = ref<number | null>(null)
const formName = ref('')
const formCategory = ref<IntegrationCategory>('other')
const formActive = ref(true)
const saving = ref(false)
const formError = ref<string | null>(null)

function openCreate(): void {
  formName.value = ''
  formCategory.value = 'other'
  formActive.value = true
  editingId.value = null
  formError.value = null
  showForm.value = true
}

function openEdit(i: Integration): void {
  formName.value = i.name
  formCategory.value = i.category
  formActive.value = i.isActive
  editingId.value = i.id
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
  const fields = { name: formName.value.trim(), category: formCategory.value, isActive: formActive.value }
  const result =
    null === editingId.value
      ? await store.createIntegration(fields)
      : await store.updateIntegration(editingId.value, fields)
  saving.value = false
  if (result.ok) {
    closeForm()
  } else {
    formError.value = result.error ?? t('admin.saveFailed')
  }
}

async function onDelete(i: Integration): Promise<void> {
  if (!window.confirm(t('adminIntegrations.confirmDelete', { name: i.name }))) return
  const result = await store.deleteIntegration(i.id)
  if (!result.ok) window.alert(result.error ?? t('admin.deleteFailed'))
}
</script>

<template>
  <section class="admin">
    <div class="container-lg">
      <div class="admin-head">
        <span class="eyebrow">{{ t('admin.eyebrow') }}</span>
        <h1>{{ t('nav.adminIntegrations') }}</h1>
        <p>{{ t('adminIntegrations.subtitle') }}</p>
      </div>

      <div class="int-panel">
        <!-- ── Create / edit form (replaces the list while open) ─────── -->
        <form v-if="showForm" class="int-form" @submit.prevent="onSubmit">
          <h3>{{ null === editingId ? t('adminIntegrations.newItem') : t('admin.edit') }}</h3>
          <label class="field">
            <span>{{ t('adminIntegrations.name') }} *</span>
            <input v-model="formName" type="text" required maxlength="255" />
          </label>
          <label class="field">
            <span>{{ t('adminIntegrations.category') }}</span>
            <AppSelect v-model="formCategory" :options="categorySelectOptions" />
          </label>
          <label class="field field--check">
            <input v-model="formActive" type="checkbox" />
            <span>{{ t('adminIntegrations.active') }}</span>
          </label>
          <p v-if="formError" class="msg msg--error">{{ formError }}</p>
          <div class="form-actions">
            <button type="submit" class="btn-submit" :disabled="saving">
              {{ saving ? t('admin.saving') : t('admin.save') }}
            </button>
            <button type="button" class="btn-ghost" @click="closeForm">{{ t('adminUsers.cancel') }}</button>
          </div>
        </form>

        <!-- ── List (hidden while the form is open) ──────────────────── -->
        <template v-else>
          <div class="int-list-head">
            <h2>{{ t('adminIntegrations.existing') }}</h2>
            <div class="int-list-tools">
              <input
                v-model="search"
                type="search"
                :placeholder="t('adminIntegrations.searchPlaceholder')"
                class="search"
              />
              <button type="button" class="btn-submit btn-new" @click="openCreate()">
                {{ '+ ' + t('adminIntegrations.newItem') }}
              </button>
            </div>
          </div>

          <p v-if="loading" class="state">{{ t('adminIntegrations.loading') }}</p>

          <div v-else-if="error" class="state state--error">
            <strong>{{ t('adminIntegrations.loadError') }}</strong>
            <button type="button" class="btn-retry" @click="store.fetchIntegrations()">{{ t('common.retry') }}</button>
          </div>

          <p v-else-if="integrations.length === 0" class="state">{{ t('adminIntegrations.empty') }}</p>

          <p v-else-if="filtered.length === 0" class="state">{{ t('adminIntegrations.noMatches') }}</p>

          <div v-else class="int-table-wrap">
            <table class="int-table">
              <thead>
                <tr>
                  <th>{{ t('adminIntegrations.name') }}</th>
                  <th>{{ t('adminIntegrations.category') }}</th>
                  <th>{{ t('adminIntegrations.status') }}</th>
                  <th class="col-actions"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="i in filtered" :key="i.id">
                  <td>
                    <span class="int-name">{{ i.name }}</span>
                  </td>
                  <td>
                    <span class="cat-badge" :class="`cat-badge--${i.category}`">
                      {{ t('adminIntegrations.cat_' + i.category) }}
                    </span>
                  </td>
                  <td>
                    <span class="status-badge" :class="i.isActive ? 'status-badge--active' : 'status-badge--inactive'">
                      {{ i.isActive ? t('adminIntegrations.statusActive') : t('adminIntegrations.statusInactive') }}
                    </span>
                  </td>
                  <td class="col-actions">
                    <div class="row-actions">
                      <button type="button" class="btn-icon" :title="t('admin.edit')" :aria-label="t('admin.edit')" @click="openEdit(i)">
                        <IconEdit />
                      </button>
                      <button
                        type="button"
                        class="btn-icon btn-icon--danger"
                        :title="t('admin.delete')"
                        :aria-label="t('admin.delete')"
                        @click="onDelete(i)"
                      >
                        <IconDelete />
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>
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
  margin: 0.2rem 0 0.5rem;
  color: var(--login-secondary, #0c1c40);
}

.admin-head p {
  margin: 0;
  color: #545f71;
}

.int-panel {
  padding: 1.6rem 1.7rem;
  background: #fff;
  border-radius: 1rem;
  box-shadow: 0 10px 34px rgba(12, 28, 64, 0.08);
}

.int-list-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
  margin-bottom: 1.1rem;
}

.int-list-head h2 {
  margin: 0;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.2rem;
}

.int-list-tools {
  display: flex;
  align-items: center;
  gap: 0.7rem;
  flex-wrap: wrap;
}

.search {
  padding: 0.55rem 0.8rem;
  background: #f7f8fb;
  border: 1px solid #d4dae6;
  border-radius: 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.92rem;
  font-family: inherit;
  min-width: 220px;
}

.search:focus {
  outline: 2px solid var(--login-primary, #ed2044);
  outline-offset: -1px;
}

.int-form {
  margin-bottom: 1.3rem;
  padding: 1.2rem 1.3rem;
  background: #f7f8fb;
  border-radius: 0.8rem;
}

.int-form h3 {
  margin: 0 0 0.9rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.05rem;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  max-width: 420px;
  margin-bottom: 1rem;
}

.field span {
  color: var(--login-secondary, #0c1c40);
  font-size: 0.85rem;
  font-weight: 700;
}

.field input[type='text'] {
  padding: 0.55rem 0.7rem;
  background: #fff;
  border: 1px solid #d4dae6;
  border-radius: 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-family: inherit;
}

.field input[type='text']:focus {
  outline: 2px solid var(--login-primary, #ed2044);
  outline-offset: -1px;
}

.field--check {
  flex-direction: row;
  align-items: center;
  gap: 0.5rem;
}

.form-actions {
  display: flex;
  gap: 0.6rem;
}

.state {
  margin: 0;
  padding: 1.4rem 0;
  color: #8b94a6;
  font-size: 0.95rem;
}

.state--error {
  display: flex;
  align-items: center;
  gap: 1rem;
  color: #b3122e;
}

.btn-retry {
  padding: 0.4rem 0.9rem;
  background: #fff;
  border: 1px solid #d4dae6;
  border-radius: 0.45rem;
  font-size: 0.85rem;
  font-weight: 700;
  cursor: pointer;
}

.int-table-wrap {
  overflow-x: auto;
}

.int-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.92rem;
}

.int-table th {
  padding: 0.55rem 0.7rem;
  border-bottom: 2px solid #e3e7ef;
  color: #8b94a6;
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-align: left;
  text-transform: uppercase;
}

.int-table td {
  padding: 0.65rem 0.7rem;
  border-bottom: 1px solid #eef1f6;
  color: var(--login-secondary, #0c1c40);
  vertical-align: middle;
}

.int-name {
  font-weight: 700;
}

.cat-badge {
  display: inline-block;
  padding: 0.1rem 0.5rem;
  border-radius: 0.4rem;
  font-size: 0.74rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  white-space: nowrap;
}

.cat-badge--payroll {
  background: #e3f6ec;
  color: #1c7a45;
}

.cat-badge--erp {
  background: #e7eefc;
  color: #2b59c3;
}

.cat-badge--access_control {
  background: #fdf3e6;
  color: #8a5a18;
}

.cat-badge--other {
  background: #eef1f6;
  color: #545f71;
}

.status-badge {
  display: inline-block;
  padding: 0.1rem 0.5rem;
  border-radius: 0.4rem;
  font-size: 0.74rem;
  font-weight: 700;
}

.status-badge--active {
  background: #e3f6ec;
  color: #1c7a45;
}

.status-badge--inactive {
  background: #eef1f6;
  color: #8b94a6;
}

.col-actions {
  width: 90px;
  text-align: right;
}

.row-actions {
  display: flex;
  gap: 0.35rem;
  justify-content: flex-end;
}

.btn-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  padding: 0;
  background: #fff;
  border: 1px solid #d4dae6;
  border-radius: 0.45rem;
  color: var(--login-secondary, #0c1c40);
  cursor: pointer;
}

.btn-icon:hover {
  border-color: var(--login-secondary, #0c1c40);
}

.btn-icon--danger {
  color: #b3122e;
}

.btn-icon--danger:hover {
  border-color: #b3122e;
  background: #fde8ec;
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
</style>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import { useFeeTitlesStore, type FeeTitle } from '@/stores/feeTitles'
import IconEdit from '@/components/icons/IconEdit.vue'
import IconDelete from '@/components/icons/IconDelete.vue'

const { t } = useI18n()
const store = useFeeTitlesStore()
const { feeTitles, loading, error } = storeToRefs(store)

const search = ref('')

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  if ('' === q) return feeTitles.value
  return feeTitles.value.filter((f) => f.name.toLowerCase().includes(q))
})

onMounted(() => {
  store.fetchFeeTitles()
})

// ── Shared create/edit form ──────────────────────────────────────────
const showForm = ref(false)
const editingId = ref<number | null>(null)
const formName = ref('')
const saving = ref(false)
const formError = ref<string | null>(null)

function openCreate(): void {
  formName.value = ''
  editingId.value = null
  formError.value = null
  showForm.value = true
}

function openEdit(f: FeeTitle): void {
  formName.value = f.name
  editingId.value = f.id
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
      ? await store.createFeeTitle(formName.value.trim())
      : await store.updateFeeTitle(editingId.value, formName.value.trim())
  saving.value = false
  if (result.ok) {
    closeForm()
  } else {
    formError.value = result.error ?? t('admin.saveFailed')
  }
}

async function onDelete(f: FeeTitle): Promise<void> {
  if (!window.confirm(t('adminFeeTitles.confirmDelete', { name: f.name }))) return
  const result = await store.deleteFeeTitle(f.id)
  if (!result.ok) window.alert(result.error ?? t('admin.deleteFailed'))
}
</script>

<template>
  <section class="admin">
    <div class="container-lg">
      <div class="admin-head">
        <span class="eyebrow">{{ t('admin.eyebrow') }}</span>
        <h1>{{ t('nav.adminFeeTitles') }}</h1>
        <p>{{ t('adminFeeTitles.subtitle') }}</p>
      </div>

      <div class="ft-panel">
        <div class="ft-list-head">
          <h2>{{ t('adminFeeTitles.existing') }}</h2>
          <div class="ft-list-tools">
            <input
              v-model="search"
              type="search"
              :placeholder="t('adminFeeTitles.searchPlaceholder')"
              class="search"
            />
            <button type="button" class="btn-submit btn-new" @click="showForm ? closeForm() : openCreate()">
              {{ showForm ? t('adminUsers.cancel') : '+ ' + t('adminFeeTitles.newTitle') }}
            </button>
          </div>
        </div>

        <!-- ── Create / edit form ────────────────────────────────────── -->
        <form v-if="showForm" class="ft-form" @submit.prevent="onSubmit">
          <h3>{{ null === editingId ? t('adminFeeTitles.newTitle') : t('admin.edit') }}</h3>
          <label class="field">
            <span>{{ t('adminFeeTitles.name') }} *</span>
            <input v-model="formName" type="text" required maxlength="255" />
          </label>
          <p v-if="formError" class="msg msg--error">{{ formError }}</p>
          <div class="form-actions">
            <button type="submit" class="btn-submit" :disabled="saving">
              {{ saving ? t('admin.saving') : t('admin.save') }}
            </button>
            <button type="button" class="btn-ghost" @click="closeForm">{{ t('adminUsers.cancel') }}</button>
          </div>
        </form>

        <p v-if="loading" class="state">{{ t('adminFeeTitles.loading') }}</p>

        <div v-else-if="error" class="state state--error">
          <strong>{{ t('adminFeeTitles.loadError') }}</strong>
          <button type="button" class="btn-retry" @click="store.fetchFeeTitles()">{{ t('common.retry') }}</button>
        </div>

        <p v-else-if="feeTitles.length === 0" class="state">{{ t('adminFeeTitles.empty') }}</p>

        <p v-else-if="filtered.length === 0" class="state">{{ t('adminFeeTitles.noMatches') }}</p>

        <div v-else class="ft-table-wrap">
          <table class="ft-table">
            <thead>
              <tr>
                <th>{{ t('adminFeeTitles.name') }}</th>
                <th class="col-actions"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="f in filtered" :key="f.id">
                <td>
                  <span class="ft-name">{{ f.name }}</span>
                </td>
                <td class="col-actions">
                  <div class="row-actions">
                    <button type="button" class="btn-icon" :title="t('admin.edit')" :aria-label="t('admin.edit')" @click="openEdit(f)">
                      <IconEdit />
                    </button>
                    <button
                      type="button"
                      class="btn-icon btn-icon--danger"
                      :title="t('admin.delete')"
                      :aria-label="t('admin.delete')"
                      @click="onDelete(f)"
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
  margin: 0.2rem 0 0.5rem;
  color: var(--login-secondary, #0c1c40);
}

.admin-head p {
  margin: 0;
  color: #545f71;
}

.ft-panel {
  padding: 1.6rem 1.7rem;
  background: #fff;
  border-radius: 1rem;
  box-shadow: 0 10px 34px rgba(12, 28, 64, 0.08);
}

.ft-list-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
  margin-bottom: 1.1rem;
}

.ft-list-head h2 {
  margin: 0;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.2rem;
}

.ft-list-tools {
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

.ft-form {
  margin-bottom: 1.3rem;
  padding: 1.2rem 1.3rem;
  background: #f7f8fb;
  border-radius: 0.8rem;
}

.ft-form h3 {
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

.field input {
  padding: 0.55rem 0.7rem;
  background: #fff;
  border: 1px solid #d4dae6;
  border-radius: 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-family: inherit;
}

.field input:focus {
  outline: 2px solid var(--login-primary, #ed2044);
  outline-offset: -1px;
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

.ft-table-wrap {
  overflow-x: auto;
}

.ft-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.92rem;
}

.ft-table th {
  padding: 0.55rem 0.7rem;
  border-bottom: 2px solid #e3e7ef;
  color: #8b94a6;
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-align: left;
  text-transform: uppercase;
}

.ft-table td {
  padding: 0.65rem 0.7rem;
  border-bottom: 1px solid #eef1f6;
  color: var(--login-secondary, #0c1c40);
  vertical-align: middle;
}

.ft-name {
  font-weight: 700;
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

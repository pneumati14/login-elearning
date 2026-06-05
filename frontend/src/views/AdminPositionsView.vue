<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import { usePositionsStore, type Position } from '@/stores/positions'
import { emptyLocalized, toLocalizedDraft, useLocalized } from '@/composables/localized'
import LocalizedInput from '@/components/LocalizedInput.vue'

const { t } = useI18n()
const { l } = useLocalized()
const store = usePositionsStore()
const { positions, loading, error } = storeToRefs(store)

// ── New position form (replaces the list while open) ─────────────────
const form = reactive({
  title: emptyLocalized(),
  location: emptyLocalized(),
  type: emptyLocalized(),
  summary: emptyLocalized(),
})
const showCreate = ref(false)
const creating = ref(false)
const createError = ref<string | null>(null)

// ── Editor — replaces the list while open ────────────────────────────
const editingId = ref<number | null>(null)
const editForm = reactive({
  title: emptyLocalized(),
  location: emptyLocalized(),
  type: emptyLocalized(),
  summary: emptyLocalized(),
})
const editSaving = ref(false)
const editError = ref<string | null>(null)

onMounted(() => store.fetchPositions())

function resetForm() {
  form.title = emptyLocalized()
  form.location = emptyLocalized()
  form.type = emptyLocalized()
  form.summary = emptyLocalized()
}

function openCreate() {
  resetForm()
  createError.value = null
  showCreate.value = true
}

function closeCreate() {
  showCreate.value = false
}

async function onCreate() {
  createError.value = null
  creating.value = true

  const result = await store.createPosition({
    title: form.title,
    location: form.location,
    type: form.type,
    summary: form.summary,
  })
  creating.value = false

  if (result.ok) {
    resetForm()
    showCreate.value = false
  } else {
    createError.value = result.error ?? t('adminPositions.createFailed')
  }
}

function openEdit(pos: Position) {
  editingId.value = pos.id
  editForm.title = toLocalizedDraft(pos.title)
  editForm.location = toLocalizedDraft(pos.location)
  editForm.type = toLocalizedDraft(pos.type)
  editForm.summary = toLocalizedDraft(pos.summary)
  editError.value = null
}

function closeEdit() {
  editingId.value = null
}

async function onSave() {
  if (null === editingId.value) return
  editError.value = null
  editSaving.value = true

  const result = await store.updatePosition(editingId.value, {
    title: editForm.title,
    location: editForm.location,
    type: editForm.type,
    summary: editForm.summary,
  })
  editSaving.value = false

  if (result.ok) {
    editingId.value = null
  } else {
    editError.value = result.error ?? t('admin.saveFailed')
  }
}

async function onDelete(pos: Position) {
  if (!window.confirm(t('adminPositions.confirmDelete', { title: pos.title.en }))) return

  const result = await store.deletePosition(pos.id)
  if (!result.ok) {
    window.alert(result.error ?? t('admin.deleteFailed'))
  } else if (editingId.value === pos.id) {
    editingId.value = null
  }
}
</script>

<template>
  <section class="admin">
    <div class="container-lg">
      <div class="admin-head">
        <span class="eyebrow">{{ t('admin.eyebrow') }}</span>
        <h1>{{ t('nav.adminPositions') }}</h1>
        <p>{{ t('adminPositions.subtitle') }}</p>
      </div>

      <!-- ── New position (replaces the list while open) ───────────── -->
      <form v-if="showCreate" class="pos-panel" @submit.prevent="onCreate">
        <h2>{{ t('adminPositions.newPosition') }}</h2>

        <LocalizedInput v-model="form.title" :label="t('adminPositions.titleLabel')" required />
        <LocalizedInput
          v-model="form.location"
          :label="t('adminPositions.location')"
          :placeholder="t('adminPositions.locationPlaceholder')"
        />
        <LocalizedInput
          v-model="form.type"
          :label="t('adminPositions.type')"
          :placeholder="t('adminPositions.typePlaceholder')"
        />
        <LocalizedInput v-model="form.summary" :label="t('adminPositions.summary')" multiline />

        <p v-if="createError" class="msg msg--error">{{ createError }}</p>

        <div class="pos-edit-actions">
          <button type="submit" class="btn-submit" :disabled="creating">
            {{ creating ? t('admin.creating') : t('adminPositions.create') }}
          </button>
          <button type="button" class="btn-ghost" @click="closeCreate">{{ t('adminUsers.cancel') }}</button>
        </div>
      </form>

      <!-- ── Editor (replaces the list while open) ─────────────────── -->
      <div v-else-if="editingId !== null" class="pos-panel">
        <h2>{{ t('admin.edit') }}</h2>

        <LocalizedInput v-model="editForm.title" :label="t('adminPositions.titleLabel')" required />
        <LocalizedInput v-model="editForm.location" :label="t('adminPositions.location')" />
        <LocalizedInput v-model="editForm.type" :label="t('adminPositions.type')" />
        <LocalizedInput v-model="editForm.summary" :label="t('adminPositions.summary')" multiline />

        <p v-if="editError" class="msg msg--error">{{ editError }}</p>

        <div class="pos-edit-actions">
          <button type="button" class="btn-submit" :disabled="editSaving" @click="onSave">
            {{ editSaving ? t('admin.saving') : t('admin.save') }}
          </button>
          <button type="button" class="btn-ghost" @click="closeEdit">
            {{ t('adminUsers.cancel') }}
          </button>
        </div>
      </div>

      <!-- ── Existing positions — list view ────────────────────────── -->
      <div v-else class="pos-panel">
        <div class="pos-list-head">
          <h2>{{ t('adminPositions.existing') }}</h2>
          <button type="button" class="btn-submit" @click="openCreate">
            {{ '+ ' + t('adminPositions.newPosition') }}
          </button>
        </div>

        <p v-if="loading" class="state">{{ t('adminPositions.loading') }}</p>

        <div v-else-if="error" class="state state--error">
          <strong>{{ t('adminPositions.loadError') }}</strong>
          <button type="button" class="btn-retry" @click="store.fetchPositions()">
            {{ t('common.retry') }}
          </button>
        </div>

        <p v-else-if="positions.length === 0" class="state">{{ t('adminPositions.empty') }}</p>

        <ul v-else class="pos-rows">
          <li v-for="pos in positions" :key="pos.id" class="pos-row-wrap">
            <div class="pos-row">
              <div class="pos-row-main">
                <span class="pos-row-title">{{ l(pos.title) }}</span>
                <span class="pos-row-meta">
                  <template v-if="l(pos.location)">{{ l(pos.location) }}</template>
                  <template v-if="l(pos.location) && l(pos.type)"> · </template>
                  <template v-if="l(pos.type)">{{ l(pos.type) }}</template>
                </span>
              </div>
              <div class="pos-row-actions">
                <button type="button" class="btn-ghost" @click="openEdit(pos)">
                  {{ t('admin.edit') }}
                </button>
                <button type="button" class="btn-delete" @click="onDelete(pos)">
                  {{ t('admin.delete') }}
                </button>
              </div>
            </div>
          </li>
        </ul>
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

.pos-panel {
  margin-bottom: 1.5rem;
  padding: 1.85rem;
  background: #fff;
  border-radius: 1.1rem;
  box-shadow: 0 12px 32px rgba(12, 28, 64, 0.08);
}

.pos-panel h2 {
  margin: 0 0 1.3rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.25rem;
  font-weight: 700;
}

.pos-list-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
}

.pos-list-head h2 {
  margin-bottom: 1.3rem;
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

/* ── Position list ───────────────────────────────────────────────── */
.pos-rows {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.pos-row-wrap {
  background: #f7f8fb;
  border-radius: 0.7rem;
}

.pos-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.9rem 1.1rem;
}

.pos-row-main {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  min-width: 0;
}

.pos-row-title {
  color: var(--login-secondary, #0c1c40);
  font-size: 1.02rem;
  font-weight: 700;
}

.pos-row-meta {
  color: #8b94a6;
  font-size: 0.82rem;
  font-weight: 700;
}

.pos-row-actions {
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

/* ── Inline editor ───────────────────────────────────────────────── */
.pos-edit {
  margin: 0 1.1rem;
  padding: 1.2rem 0 1.3rem;
  border-top: 1px solid #e3e7ee;
}

.pos-edit-actions {
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

@media (max-width: 575.98px) {
  .pos-row {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>

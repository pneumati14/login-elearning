<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import { usePublicationsStore, type Publication } from '@/stores/publications'
import { emptyLocalized, toLocalizedDraft, useLocalized } from '@/composables/localized'
import LocalizedInput from '@/components/LocalizedInput.vue'

const { t } = useI18n()
const { l } = useLocalized()
const store = usePublicationsStore()
const { publications, loading, error } = storeToRefs(store)

// ── New publication form (replaces the list while open) ──────────────
const form = reactive({
  title: emptyLocalized(),
  topic: emptyLocalized(),
  author: emptyLocalized(),
  description: emptyLocalized(),
})
const showCreate = ref(false)
const selectedFile = ref<File | null>(null)
const fileInputEl = ref<HTMLInputElement | null>(null)
const uploading = ref(false)
const formError = ref<string | null>(null)

// ── Editor — replaces the list while open ────────────────────────────
const editingId = ref<number | null>(null)
const editForm = reactive({
  title: emptyLocalized(),
  topic: emptyLocalized(),
  author: emptyLocalized(),
  description: emptyLocalized(),
})
const editReplaceFile = ref<File | null>(null)
const editFileKey = ref(0)
const editSaving = ref(false)
const editError = ref<string | null>(null)

onMounted(() => store.fetchPublications())

function onFileChange(event: Event) {
  selectedFile.value = (event.target as HTMLInputElement).files?.[0] ?? null
}

function openCreate() {
  form.title = emptyLocalized()
  form.topic = emptyLocalized()
  form.author = emptyLocalized()
  form.description = emptyLocalized()
  selectedFile.value = null
  formError.value = null
  showCreate.value = true
}

function closeCreate() {
  showCreate.value = false
}

async function onUpload() {
  formError.value = null

  const file = selectedFile.value
  if (!form.title.en.trim()) {
    formError.value = t('adminPublications.errNoTitle')
    return
  }
  if (!file) {
    formError.value = t('adminPublications.errNoFile')
    return
  }

  uploading.value = true
  const result = await store.uploadPublication(
    {
      title: form.title,
      topic: form.topic,
      author: form.author,
      description: form.description,
    },
    file,
  )
  uploading.value = false

  if (result.ok) {
    form.title = emptyLocalized()
    form.topic = emptyLocalized()
    form.author = emptyLocalized()
    form.description = emptyLocalized()
    selectedFile.value = null
    if (fileInputEl.value) fileInputEl.value.value = ''
    showCreate.value = false
  } else {
    formError.value = result.error ?? t('adminPublications.uploadFailed')
  }
}

function openEdit(pub: Publication) {
  editingId.value = pub.id
  editForm.title = toLocalizedDraft(pub.title)
  editForm.topic = toLocalizedDraft(pub.topic)
  editForm.author = toLocalizedDraft(pub.author)
  editForm.description = toLocalizedDraft(pub.description)
  editReplaceFile.value = null
  editFileKey.value++
  editError.value = null
}

function closeEdit() {
  editingId.value = null
}

function onReplaceFileChange(event: Event) {
  editReplaceFile.value = (event.target as HTMLInputElement).files?.[0] ?? null
}

async function onSave() {
  if (null === editingId.value) return
  editError.value = null
  editSaving.value = true
  const result = await store.updatePublication(
    editingId.value,
    {
      title: editForm.title,
      topic: editForm.topic,
      author: editForm.author,
      description: editForm.description,
    },
    editReplaceFile.value,
  )
  editSaving.value = false

  if (result.ok) {
    editingId.value = null
  } else {
    editError.value = result.error ?? t('admin.saveFailed')
  }
}

async function onDelete(pub: Publication) {
  if (!window.confirm(t('adminPublications.confirmDelete', { title: pub.title.en }))) return

  const result = await store.deletePublication(pub.id)
  if (!result.ok) {
    window.alert(result.error ?? t('admin.deleteFailed'))
  } else if (editingId.value === pub.id) {
    editingId.value = null
  }
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('hu-HU', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  })
}
</script>

<template>
  <section class="admin">
    <div class="container-lg">
      <div class="admin-head">
        <span class="eyebrow">{{ t('adminPublications.eyebrow') }}</span>
        <h1>{{ t('adminPublications.title') }}</h1>
        <p>{{ t('adminPublications.subtitle') }}</p>
      </div>

      <!-- ── New publication (replaces the list while open) ────────── -->
      <form v-if="showCreate" class="ap-panel" @submit.prevent="onUpload">
        <h2>{{ t('adminPublications.newPublication') }}</h2>
        <LocalizedInput v-model="form.title" :label="t('admin.title')" required />
        <label class="field">
          <span class="field-label">{{ t('adminPublications.pdfFile') }}</span>
          <input ref="fileInputEl" type="file" accept="application/pdf" @change="onFileChange" />
        </label>
        <LocalizedInput
          v-model="form.topic"
          :label="t('adminPublications.topic')"
          :placeholder="t('adminPublications.topicPlaceholder')"
        />
        <LocalizedInput
          v-model="form.author"
          :label="t('adminPublications.author')"
          :placeholder="t('adminPublications.authorPlaceholder')"
        />
        <LocalizedInput
          v-model="form.description"
          :label="t('adminPublications.descriptionOptional')"
          multiline
        />

        <p v-if="formError" class="msg msg--error">{{ formError }}</p>

        <div class="pub-edit-actions">
          <button type="submit" class="btn-submit" :disabled="uploading">
            {{ uploading ? t('account.uploading') : t('adminPublications.upload') }}
          </button>
          <button type="button" class="btn-ghost" @click="closeCreate">{{ t('adminUsers.cancel') }}</button>
        </div>
      </form>

      <!-- ── Editor (replaces the list while open) ─────────────────── -->
      <div v-else-if="editingId !== null" class="ap-panel">
        <h2>{{ t('admin.edit') }}</h2>

        <LocalizedInput v-model="editForm.title" :label="t('admin.title')" required />
        <LocalizedInput v-model="editForm.topic" :label="t('adminPublications.topic')" />
        <LocalizedInput v-model="editForm.author" :label="t('adminPublications.author')" />
        <LocalizedInput
          v-model="editForm.description"
          :label="t('adminPublications.descriptionOptional')"
          multiline
        />
        <label class="field">
          <span class="field-label">{{ t('adminPublications.replaceFile') }}</span>
          <input :key="editFileKey" type="file" accept="application/pdf" @change="onReplaceFileChange" />
        </label>

        <p v-if="editError" class="msg msg--error">{{ editError }}</p>

        <div class="pub-edit-actions">
          <button type="button" class="btn-submit" :disabled="editSaving" @click="onSave">
            {{ editSaving ? t('admin.saving') : t('admin.save') }}
          </button>
          <button type="button" class="btn-ghost" @click="closeEdit">
            {{ t('adminUsers.cancel') }}
          </button>
        </div>
      </div>

      <!-- ── Existing publications — list view ─────────────────────── -->
      <div v-else class="ap-panel">
        <div class="ap-list-head">
          <h2>{{ t('adminPublications.existing') }}</h2>
          <button type="button" class="btn-submit" @click="openCreate">
            {{ '+ ' + t('adminPublications.newPublication') }}
          </button>
        </div>

        <p v-if="loading" class="state">{{ t('common.loading') }}</p>

        <div v-else-if="error" class="state state--error">
          <strong>{{ t('adminPublications.loadError') }}</strong>
          <button type="button" class="btn-retry" @click="store.fetchPublications()">
            {{ t('common.retry') }}
          </button>
        </div>

        <p v-else-if="publications.length === 0" class="state">{{ t('adminPublications.empty') }}</p>

        <ul v-else class="pub-rows">
          <li v-for="pub in publications" :key="pub.id" class="pub-row-wrap">
            <div class="pub-row">
              <div class="pub-row-main">
                <span class="pub-row-title">{{ l(pub.title) }}</span>
                <span class="pub-row-meta">
                  <template v-if="l(pub.topic)">{{ l(pub.topic) }}</template>
                  <template v-if="l(pub.topic) && l(pub.author)"> · </template>
                  <template v-if="l(pub.author)">{{ l(pub.author) }}</template>
                  <template v-if="l(pub.topic) || l(pub.author)"> · </template>
                  {{ formatDate(pub.createdAt) }}
                </span>
              </div>
              <div class="pub-row-actions">
                <a :href="pub.fileUrl" target="_blank" rel="noopener" class="btn-ghost">
                  {{ t('admin.open') }}
                </a>
                <button type="button" class="btn-ghost" @click="openEdit(pub)">
                  {{ t('admin.edit') }}
                </button>
                <button type="button" class="btn-delete" @click="onDelete(pub)">
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

.ap-panel {
  margin-bottom: 1.5rem;
  padding: 1.85rem;
  background: #fff;
  border-radius: 1.1rem;
  box-shadow: 0 12px 32px rgba(12, 28, 64, 0.08);
}

.ap-panel h2 {
  margin: 0 0 1.3rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.25rem;
  font-weight: 700;
}

.ap-list-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
}

.field {
  display: block;
  margin-bottom: 1rem;
}

.field-label {
  display: block;
  margin-bottom: 0.35rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.88rem;
  font-weight: 700;
}

.field input {
  width: 100%;
  padding: 0.65rem 0.8rem;
  border: 1px solid #d4dae6;
  border-radius: 0.55rem;
  font-size: 0.98rem;
  font-family: inherit;
  color: var(--login-secondary, #0c1c40);
  background: #fff;
}

.field input:focus {
  outline: none;
  border-color: var(--login-primary, #ed2044);
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

/* ── Publication list ────────────────────────────────────────────── */
.pub-rows {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.pub-row-wrap {
  background: #f7f8fb;
  border-radius: 0.7rem;
}

.pub-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.9rem 1.1rem;
}

.pub-row-main {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  min-width: 0;
}

.pub-row-title {
  color: var(--login-secondary, #0c1c40);
  font-size: 1.02rem;
  font-weight: 700;
}

.pub-row-meta {
  color: #8b94a6;
  font-size: 0.82rem;
  font-weight: 700;
}

.pub-row-actions {
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
.pub-edit {
  margin: 0 1.1rem;
  padding: 1.2rem 0 1.3rem;
  border-top: 1px solid #e3e7ee;
}

.pub-edit-actions {
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
  .pub-row {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>

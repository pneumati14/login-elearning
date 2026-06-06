<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { Customer } from '@/stores/customers'
import {
  useCustomerArchitectureStore,
  ARCHITECTURE_FILE_KINDS,
  type ArchitectureFileKind,
  type CustomerArchitectureFields,
  type DeploymentModel,
} from '@/stores/customerArchitecture'
import { useIntegrationsStore, INTEGRATION_CATEGORIES, type Integration } from '@/stores/integrations'
import AppSelect from '@/components/AppSelect.vue'
import IconDelete from '@/components/icons/IconDelete.vue'

const props = defineProps<{ customer: Customer }>()

const { t, locale } = useI18n()
const store = useCustomerArchitectureStore()
const integrationsStore = useIntegrationsStore()

const architecture = computed(() => store.get(props.customer.id))

// ── Editable form state, refilled whenever the sheet (re)loads ───────
const form = reactive<CustomerArchitectureFields>({
  deploymentModel: null,
  saasServer: null,
  vpnInfo: null,
  usersInfo: null,
  notes: null,
  integrationIds: [],
})

watch(
  architecture,
  (next) => {
    if (null === next) return
    form.deploymentModel = next.deploymentModel
    form.saasServer = next.saasServer
    form.vpnInfo = next.vpnInfo
    form.usersInfo = next.usersInfo
    form.notes = next.notes
    form.integrationIds = [...next.integrationIds]
  },
  { immediate: true },
)

async function load(): Promise<void> {
  await Promise.all([integrationsStore.fetchIntegrations(), store.fetchArchitecture(props.customer.id)])
}

onMounted(load)
watch(() => props.customer.id, load)

const modelSelectOptions = computed<{ value: DeploymentModel | null; label: string }[]>(() => [
  { value: null, label: t('adminCustomers.archModelNone') },
  { value: 'onprem', label: t('adminCustomers.archModel_onprem') },
  { value: 'saas', label: t('adminCustomers.archModel_saas') },
])

// ── Integrations grouped by category (active + already-picked ones) ──
const integrationGroups = computed(() =>
  INTEGRATION_CATEGORIES.map((category) => ({
    category,
    items: integrationsStore.integrations.filter(
      (i) => i.category === category && (i.isActive || form.integrationIds.includes(i.id)),
    ),
  })).filter((g) => g.items.length > 0),
)

const hasCatalog = computed(() => integrationsStore.integrations.length > 0)

function toggleIntegration(i: Integration): void {
  form.integrationIds = form.integrationIds.includes(i.id)
    ? form.integrationIds.filter((id) => id !== i.id)
    : [...form.integrationIds, i.id]
}

// ── Save ──────────────────────────────────────────────────────────────
const saving = ref(false)
const saved = ref(false)
const saveError = ref<string | null>(null)

async function onSave(): Promise<void> {
  saving.value = true
  saveError.value = null
  saved.value = false
  const result = await store.saveArchitecture(props.customer.id, { ...form, integrationIds: [...form.integrationIds] })
  saving.value = false
  if (result.ok) {
    saved.value = true
    window.setTimeout(() => (saved.value = false), 2500)
  } else {
    saveError.value = result.error ?? t('admin.saveFailed')
  }
}

// ── Attachments (typed: diagram / plan / sdd / other) ────────────────
const uploadKind = ref<ArchitectureFileKind>('diagram')
const uploading = ref(false)
const fileError = ref<string | null>(null)

const kindSelectOptions = computed<{ value: ArchitectureFileKind; label: string }[]>(() =>
  ARCHITECTURE_FILE_KINDS.map((k) => ({ value: k, label: t('adminCustomers.archKind_' + k) })),
)

async function onUpload(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  fileError.value = null
  uploading.value = true
  const result = await store.uploadFile(props.customer.id, uploadKind.value, file)
  uploading.value = false
  input.value = ''
  if (!result.ok) fileError.value = result.error ?? t('admin.saveFailed')
}

async function onDeleteFile(fileId: number): Promise<void> {
  if (!window.confirm(t('adminCustomers.archConfirmDeleteFile'))) return
  const result = await store.deleteFile(props.customer.id, fileId)
  if (!result.ok) window.alert(result.error ?? t('admin.deleteFailed'))
}

function fileIcon(mimeType: string): string {
  if ('application/pdf' === mimeType) return '📄'
  if (mimeType.startsWith('image/')) return '🖼️'
  return '📝'
}

function fmtDate(iso: string): string {
  return new Date(iso).toLocaleDateString(locale.value)
}
</script>

<template>
  <div class="arch-panel">
    <p v-if="store.loading && null === architecture" class="state">{{ t('common.loading') }}</p>

    <div v-else-if="store.error && null === architecture" class="state state--error">
      <strong>{{ store.error }}</strong>
      <button type="button" class="btn-retry" @click="load">{{ t('common.retry') }}</button>
    </div>

    <form v-else class="arch-form" @submit.prevent="onSave">
      <!-- ── Deployment ──────────────────────────────────────────────── -->
      <div class="arch-grid">
        <label class="field">
          <span>{{ t('adminCustomers.archModel') }}</span>
          <AppSelect v-model="form.deploymentModel" :options="modelSelectOptions" />
        </label>
        <label v-if="'saas' === form.deploymentModel" class="field">
          <span>{{ t('adminCustomers.archSaasServer') }}</span>
          <input v-model="form.saasServer" type="text" maxlength="255" :placeholder="t('adminCustomers.archSaasServerPlaceholder')" />
        </label>
      </div>

      <template v-if="'onprem' === form.deploymentModel">
        <label class="field field--wide">
          <span>{{ t('adminCustomers.archVpn') }}</span>
          <textarea v-model="form.vpnInfo" rows="3" />
          <em class="field-hint">{{ t('adminCustomers.archVpnHint') }}</em>
        </label>
        <label class="field field--wide">
          <span>{{ t('adminCustomers.archUsers') }}</span>
          <textarea v-model="form.usersInfo" rows="3" />
        </label>
      </template>

      <!-- ── Integrations ────────────────────────────────────────────── -->
      <div class="arch-section">
        <span class="arch-section-title">{{ t('adminCustomers.archIntegrations') }}</span>
        <p v-if="!hasCatalog" class="arch-hint">{{ t('adminCustomers.archNoCatalog') }}</p>
        <div v-for="group in integrationGroups" :key="group.category" class="int-group">
          <span class="int-group-label">{{ t('adminIntegrations.cat_' + group.category) }}:</span>
          <button
            v-for="i in group.items"
            :key="i.id"
            type="button"
            class="chip"
            :class="{ 'is-active': form.integrationIds.includes(i.id) }"
            @click="toggleIntegration(i)"
          >
            {{ i.name }}
          </button>
        </div>
      </div>

      <!-- ── Notes ───────────────────────────────────────────────────── -->
      <label class="field field--wide">
        <span>{{ t('adminCustomers.notes') }}</span>
        <textarea v-model="form.notes" rows="3" />
      </label>

      <p v-if="saveError" class="msg msg--error">{{ saveError }}</p>
      <div class="form-actions">
        <button type="submit" class="btn-submit" :disabled="saving">
          {{ saving ? t('admin.saving') : t('admin.save') }}
        </button>
        <span v-if="saved" class="saved-tick">✓ {{ t('adminCustomers.billingSaved') }}</span>
      </div>

      <!-- ── Attachments ─────────────────────────────────────────────── -->
      <div class="arch-section arch-files">
        <span class="arch-section-title">{{ t('adminCustomers.archFiles') }}</span>

        <ul v-if="architecture && architecture.files.length" class="file-list">
          <li v-for="f in architecture.files" :key="f.id" class="file-row">
            <span class="kind-badge" :class="`kind-badge--${f.kind}`">{{ t('adminCustomers.archKind_' + f.kind) }}</span>
            <a :href="f.url" target="_blank" rel="noopener" class="file-link">{{ fileIcon(f.mimeType) }} {{ f.originalName }}</a>
            <span class="file-date">{{ fmtDate(f.createdAt) }}</span>
            <button type="button" class="btn-icon btn-icon--danger" :title="t('admin.delete')" :aria-label="t('admin.delete')" @click="onDeleteFile(f.id)">
              <IconDelete />
            </button>
          </li>
        </ul>
        <p v-else class="arch-hint">{{ t('adminCustomers.archFilesEmpty') }}</p>

        <div class="upload-row">
          <AppSelect v-model="uploadKind" :options="kindSelectOptions" compact />
          <label class="upload-btn">
            <input type="file" accept="application/pdf,.doc,.docx,image/*" :disabled="uploading" @change="onUpload" />
            <span>{{ uploading ? t('adminCustomers.archUploading') : t('adminCustomers.archUpload') }}</span>
          </label>
        </div>
        <p class="arch-hint">{{ t('adminCustomers.archFilesHint') }}</p>
        <p v-if="fileError" class="msg msg--error">{{ fileError }}</p>
      </div>
    </form>
  </div>
</template>

<style scoped>
.arch-grid {
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
  margin-bottom: 1rem;
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

.field-hint {
  color: #8a5a18;
  font-size: 0.78rem;
  font-style: normal;
}

/* ── Sections ───────────────────────────────────────────────────────── */
.arch-section {
  margin-bottom: 1rem;
  padding-top: 0.8rem;
  border-top: 1px solid #eef1f6;
}

.arch-section-title {
  display: block;
  margin-bottom: 0.6rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.88rem;
  font-weight: 700;
}

.arch-hint {
  margin: 0.3rem 0;
  color: #8b94a6;
  font-size: 0.82rem;
}

/* ── Integration chips ──────────────────────────────────────────────── */
.int-group {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.45rem;
  margin-bottom: 0.55rem;
}

.int-group-label {
  color: #8b94a6;
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  min-width: 6.5rem;
}

.chip {
  padding: 0.32rem 0.85rem;
  background: #f7f8fb;
  border: 1px solid #d4dae6;
  border-radius: 2rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition:
    background 0.15s,
    border-color 0.15s,
    color 0.15s;
}

.chip:hover {
  border-color: var(--login-primary, #ed2044);
}

.chip.is-active {
  background: var(--login-primary, #ed2044);
  border-color: var(--login-primary, #ed2044);
  color: #fff;
}

/* ── Files ──────────────────────────────────────────────────────────── */
.file-list {
  margin: 0 0 0.8rem;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.file-row {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

.kind-badge {
  flex-shrink: 0;
  padding: 0.1rem 0.5rem;
  border-radius: 0.4rem;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  white-space: nowrap;
}

.kind-badge--diagram {
  background: #e7eefc;
  color: #2b59c3;
}

.kind-badge--plan {
  background: #e3f6ec;
  color: #1c7a45;
}

.kind-badge--sdd {
  background: #f3e8fb;
  color: #7a3aa8;
}

.kind-badge--other {
  background: #eef1f6;
  color: #545f71;
}

.file-link {
  color: var(--login-primary, #ed2044);
  font-size: 0.9rem;
  font-weight: 700;
  text-decoration: none;
  word-break: break-all;
}

.file-link:hover {
  text-decoration: underline;
}

.file-date {
  margin-left: auto;
  color: #8b94a6;
  font-size: 0.78rem;
  white-space: nowrap;
}

.upload-row {
  display: flex;
  align-items: center;
  gap: 0.7rem;
  flex-wrap: wrap;
}

.upload-btn {
  display: inline-flex;
  flex-direction: column;
  gap: 0.3rem;
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--login-secondary, #0c1c40);
}

.upload-btn input[type='file'] {
  font-size: 0.82rem;
}

/* ── Actions / messages ─────────────────────────────────────────────── */
.form-actions {
  display: flex;
  align-items: center;
  gap: 0.8rem;
  margin-bottom: 1rem;
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

.saved-tick {
  color: #198754;
  font-size: 0.92rem;
  font-weight: 700;
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

.btn-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.8rem;
  height: 1.8rem;
  padding: 0;
  background: #fff;
  border: 1px solid #d4dae6;
  border-radius: 0.4rem;
  color: #b3122e;
  cursor: pointer;
}

.btn-icon--danger:hover {
  border-color: #b3122e;
  background: #fde8ec;
}

.state {
  margin: 0;
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
  .arch-grid {
    grid-template-columns: 1fr;
  }
}
</style>

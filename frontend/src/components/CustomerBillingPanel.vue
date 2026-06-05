<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  useCustomersStore,
  BILLING_PERIODS,
  type BillingPeriod,
  type Customer,
  type CustomerBillingFields,
} from '@/stores/customers'
import { useFeeTitlesStore } from '@/stores/feeTitles'
import { useMoneyFormat } from '@/stores/currencySettings'
import AppSelect from '@/components/AppSelect.vue'

const props = defineProps<{ customer: Customer }>()

const { t, locale } = useI18n()
const store = useCustomersStore()
const feeTitlesStore = useFeeTitlesStore()

onMounted(() => {
  if (0 === feeTitlesStore.feeTitles.length) void feeTitlesStore.fetchFeeTitles()
})

// ── Billing fields form ──────────────────────────────────────────────
const form = reactive<Omit<CustomerBillingFields, 'feeDiscountPercent'>>({
  contractNumber: props.customer.billing.contractNumber,
  firstInvoiceDate: props.customer.billing.firstInvoiceDate,
  billingPeriod: props.customer.billing.billingPeriod,
  feeTitleId: props.customer.billing.feeTitleId,
})

// Refresh the form when another customer's panel reuses this instance.
watch(
  () => props.customer.id,
  () => {
    form.contractNumber = props.customer.billing.contractNumber
    form.firstInvoiceDate = props.customer.billing.firstInvoiceDate
    form.billingPeriod = props.customer.billing.billingPeriod
    form.feeTitleId = props.customer.billing.feeTitleId
  },
)

const periodOptions = computed<{ value: BillingPeriod | null; label: string }[]>(() => [
  { value: null, label: '—' },
  ...BILLING_PERIODS.map((p) => ({ value: p, label: t('adminCustomers.billingPeriod_' + p) })),
])

const feeTitleOptions = computed<{ value: number | null; label: string }[]>(() => [
  { value: null, label: '—' },
  ...feeTitlesStore.feeTitles.map((f) => ({ value: f.id, label: f.name })),
])

const saving = ref(false)
const saveError = ref<string | null>(null)
const saved = ref(false)

async function onSave(): Promise<void> {
  saving.value = true
  saveError.value = null
  saved.value = false
  const result = await store.updateBilling(props.customer.id, {
    contractNumber: form.contractNumber,
    firstInvoiceDate: form.firstInvoiceDate || null,
    billingPeriod: form.billingPeriod,
    feeTitleId: form.feeTitleId,
  })
  saving.value = false
  if (result.ok) {
    saved.value = true
    window.setTimeout(() => (saved.value = false), 2500)
  } else {
    saveError.value = result.error ?? t('admin.saveFailed')
  }
}

// ── Monthly fee (read-only, live sum of the fee tab) ─────────────────
const fmtMoney = useMoneyFormat()

const discountPercent = computed(() => Number(props.customer.billing.feeDiscountPercent ?? 0))

/** Trimmed percent for display: 10 instead of 10.00, 12.5 instead of 12.50. */
const discountLabel = computed(() => String(Number(discountPercent.value)))

// ── Contract attachments ─────────────────────────────────────────────
const fileInput = ref<HTMLInputElement | null>(null)
const uploadBusy = ref(false)
const fileError = ref<string | null>(null)

async function onFileChange(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file) return

  fileError.value = null
  uploadBusy.value = true
  const result = await store.uploadContractFile(props.customer.id, file)
  uploadBusy.value = false
  if (!result.ok) fileError.value = result.error ?? t('adminCustomers.billingUploadFailed')
}

async function onDeleteFile(fileId: number): Promise<void> {
  if (!window.confirm(t('adminCustomers.billingFileDeleteConfirm'))) return
  fileError.value = null
  const result = await store.deleteContractFile(props.customer.id, fileId)
  if (!result.ok) fileError.value = result.error ?? t('adminCustomers.billingDeleteFailed')
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
  <div class="cbill-panel">
    <!-- ── Monthly fee summary (live from the fee tab) ─────────────── -->
    <div class="cbill-summary">
      <div class="cbill-sum-card">
        <span class="cbill-sum-label">{{ t('adminCustomers.billingMonthlyFee') }}</span>
        <span v-if="customer.monthlyFeeTotals.length === 0" class="cbill-sum-value">—</span>
        <span v-else class="cbill-sum-value">
          <span v-for="tt in customer.monthlyFeeTotals" :key="tt.currency" class="cbill-sum-line">
            {{ fmtMoney(tt.amount, tt.currency) }}
          </span>
        </span>
        <span v-if="discountPercent > 0 && customer.monthlyFeeGrossTotals.length > 0" class="cbill-sum-gross">
          <span class="cbill-gross-strike">
            <span v-for="tt in customer.monthlyFeeGrossTotals" :key="tt.currency" class="cbill-gross-line">
              {{ fmtMoney(tt.amount, tt.currency) }}
            </span>
          </span>
          <span class="cbill-gross-badge">−{{ discountLabel }}%</span>
        </span>
        <span class="cbill-sum-sub">{{ t('adminCustomers.billingMonthlyFeeHint') }}</span>
      </div>
    </div>

    <!-- ── Billing data ────────────────────────────────────────────── -->
    <form class="cbill-form" @submit.prevent="onSave">
      <h3>{{ t('adminCustomers.billingDataTitle') }}</h3>
      <div class="cbill-form-grid">
        <label class="field">
          <span>{{ t('adminCustomers.billingContractNumber') }}</span>
          <input v-model="form.contractNumber" type="text" maxlength="255" />
        </label>
        <label class="field">
          <span>{{ t('adminCustomers.billingFirstInvoiceDate') }}</span>
          <input v-model="form.firstInvoiceDate" type="date" />
        </label>
        <label class="field">
          <span>{{ t('adminCustomers.billingPeriod') }}</span>
          <AppSelect v-model="form.billingPeriod" :options="periodOptions" />
        </label>
        <label class="field">
          <span>{{ t('adminCustomers.billingFeeTitle') }}</span>
          <AppSelect v-model="form.feeTitleId" :options="feeTitleOptions" />
        </label>
      </div>

      <p v-if="saveError" class="msg msg--error">{{ saveError }}</p>
      <div class="cbill-form-actions">
        <button class="btn-submit" type="submit" :disabled="saving">
          {{ saving ? t('admin.saving') : t('admin.save') }}
        </button>
        <span v-if="saved" class="cbill-saved">✓ {{ t('adminCustomers.billingSaved') }}</span>
      </div>
    </form>

    <!-- ── Contract attachments ────────────────────────────────────── -->
    <div class="cbill-contracts">
      <div class="cbill-contracts-head">
        <h3>{{ t('adminCustomers.billingContracts') }}</h3>
        <button type="button" class="btn-submit" :disabled="uploadBusy" @click="fileInput?.click()">
          {{ uploadBusy ? t('adminCustomers.billingUploading') : '+ ' + t('adminCustomers.billingUpload') }}
        </button>
        <input
          ref="fileInput"
          type="file"
          class="cbill-file-input"
          accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,image/*"
          @change="onFileChange"
        />
      </div>
      <p class="cbill-contracts-hint">{{ t('adminCustomers.billingUploadHint') }}</p>

      <p v-if="fileError" class="msg msg--error">{{ fileError }}</p>

      <p v-if="customer.billing.contractFiles.length === 0" class="cbill-empty">
        {{ t('adminCustomers.billingNoFiles') }}
      </p>
      <ul v-else class="cbill-file-list">
        <li v-for="file in customer.billing.contractFiles" :key="file.id" class="cbill-file">
          <span class="cbill-file-icon" aria-hidden="true">{{ fileIcon(file.mimeType) }}</span>
          <a
            class="cbill-file-name"
            :href="store.contractFileUrl(customer.id, file.id)"
            target="_blank"
            rel="noopener"
          >
            {{ file.name }}
          </a>
          <span class="cbill-file-date">{{ fmtDate(file.createdAt) }}</span>
          <button type="button" class="btn-ghost btn-danger" @click="onDeleteFile(file.id)">
            {{ t('admin.delete') }}
          </button>
        </li>
      </ul>
    </div>
  </div>
</template>

<style scoped>
.cbill-summary {
  margin-bottom: 1.3rem;
}

.cbill-sum-card {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  padding: 1.1rem 1.4rem;
  background: var(--login-secondary, #0c1c40);
  border-radius: 0.8rem;
  min-width: 220px;
  max-width: max-content;
}

.cbill-sum-label {
  color: #aab6d3;
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.cbill-sum-value {
  display: flex;
  flex-wrap: wrap;
  gap: 0.2rem 1rem;
  color: #fff;
  font-size: 1.7rem;
  font-weight: 700;
  line-height: 1.2;
}

.cbill-sum-sub {
  color: #aab6d3;
  font-size: 0.82rem;
  font-weight: 600;
}

/* List price + discount badge above the hint when a discount applies. */
.cbill-sum-gross {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  flex-wrap: wrap;
}

.cbill-gross-strike {
  display: flex;
  gap: 0.2rem 0.8rem;
  flex-wrap: wrap;
  color: #8fa0c5;
  font-size: 0.98rem;
  font-weight: 600;
  text-decoration: line-through;
}

.cbill-gross-badge {
  padding: 0.1rem 0.5rem;
  background: var(--login-primary, #ed2044);
  border-radius: 100vw;
  color: #fff;
  font-size: 0.78rem;
  font-weight: 700;
}

.cbill-form,
.cbill-contracts {
  margin-bottom: 1.3rem;
  padding: 1.2rem 1.3rem;
  background: #f7f8fb;
  border-radius: 0.8rem;
}

.cbill-form h3,
.cbill-contracts h3 {
  margin: 0 0 0.9rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.05rem;
}

.cbill-form-grid {
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

.cbill-form-actions {
  display: flex;
  align-items: center;
  gap: 0.8rem;
}

.cbill-saved {
  color: #198754;
  font-size: 0.92rem;
  font-weight: 700;
}

.cbill-contracts-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
}

.cbill-contracts-head h3 {
  margin: 0;
}

.cbill-contracts-hint {
  margin: 0.4rem 0 0.9rem;
  color: #8b94a6;
  font-size: 0.85rem;
}

.cbill-file-input {
  display: none;
}

.cbill-empty {
  margin: 0;
  color: #8b94a6;
  font-size: 0.92rem;
}

.cbill-file-list {
  margin: 0;
  padding: 0;
  list-style: none;
}

.cbill-file {
  display: flex;
  align-items: center;
  gap: 0.7rem;
  padding: 0.55rem 0.2rem;
  border-bottom: 1px solid #e9edf4;
}

.cbill-file:last-child {
  border-bottom: none;
}

.cbill-file-icon {
  flex-shrink: 0;
  font-size: 1.1rem;
}

.cbill-file-name {
  flex: 1 1 auto;
  min-width: 0;
  overflow: hidden;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.cbill-file-name:hover {
  color: var(--login-primary, #ed2044);
  text-decoration: underline;
}

.cbill-file-date {
  flex-shrink: 0;
  color: #8b94a6;
  font-size: 0.85rem;
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
  padding: 0.45rem 0.9rem;
  background: #fff;
  border: 1px solid #d4dae6;
  border-radius: 0.45rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.85rem;
  font-weight: 700;
  cursor: pointer;
}

.btn-danger {
  border-color: #f3c2cc;
  color: #b3122e;
}

.btn-danger:hover {
  background: #fde8ec;
}

@media (max-width: 575.98px) {
  .cbill-form-grid {
    grid-template-columns: 1fr;
  }
}
</style>

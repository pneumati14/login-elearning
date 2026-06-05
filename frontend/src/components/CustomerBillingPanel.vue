<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  useCustomersStore,
  BILLING_PERIODS,
  BILLING_MODES,
  type BillingPeriod,
  type BillingMode,
  type Customer,
  type CustomerBillingFields,
  type PoNumber,
  type PoNumberFields,
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
  billingMode: props.customer.billing.billingMode,
  paymentDueDays: props.customer.billing.paymentDueDays,
  feeTitleId: props.customer.billing.feeTitleId,
  hasPo: props.customer.billing.hasPo,
})

// Refresh the form when another customer's panel reuses this instance.
watch(
  () => props.customer.id,
  () => {
    form.contractNumber = props.customer.billing.contractNumber
    form.firstInvoiceDate = props.customer.billing.firstInvoiceDate
    form.billingPeriod = props.customer.billing.billingPeriod
    form.billingMode = props.customer.billing.billingMode
    form.paymentDueDays = props.customer.billing.paymentDueDays
    form.feeTitleId = props.customer.billing.feeTitleId
    form.hasPo = props.customer.billing.hasPo
  },
)

const periodOptions = computed<{ value: BillingPeriod | null; label: string }[]>(() => [
  { value: null, label: '—' },
  ...BILLING_PERIODS.map((p) => ({ value: p, label: t('adminCustomers.billingPeriod_' + p) })),
])

const modeOptions = computed<{ value: BillingMode | null; label: string }[]>(() => [
  { value: null, label: '—' },
  ...BILLING_MODES.map((m) => ({ value: m, label: t('adminCustomers.billingMode_' + m) })),
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
    billingMode: form.billingMode,
    // The empty number input yields '' — treat it as "not set".
    paymentDueDays: 'number' === typeof form.paymentDueDays ? form.paymentDueDays : null,
    feeTitleId: form.feeTitleId,
    hasPo: form.hasPo,
  })
  saving.value = false
  if (result.ok) {
    saved.value = true
    window.setTimeout(() => (saved.value = false), 2500)
  } else {
    saveError.value = result.error ?? t('admin.saveFailed')
  }
}

// The PO section follows the checkbox instantly, so persist the flag
// right away — otherwise a PO recorded before "Save" could be hidden
// behind an unsaved checkbox on the next visit.
async function onHasPoChange(): Promise<void> {
  const result = await store.updateBilling(props.customer.id, { hasPo: form.hasPo })
  if (!result.ok) {
    form.hasPo = !form.hasPo
    saveError.value = result.error ?? t('admin.saveFailed')
  }
}

// ── Monthly fee (read-only, live sum of the fee tab) ─────────────────
const fmtMoney = useMoneyFormat()

const discountPercent = computed(() => Number(props.customer.billing.feeDiscountPercent ?? 0))

/** Trimmed percent for display: 10 instead of 10.00, 12.5 instead of 12.50. */
const discountLabel = computed(() => String(Number(discountPercent.value)))

// ── PO numbers (history with validity, gated by the hasPo flag) ──────
function todayISO(): string {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

type PoState = 'active' | 'scheduled' | 'expired'

function poState(po: PoNumber, today: string = todayISO()): PoState {
  if (null !== po.validFrom && po.validFrom > today) return 'scheduled'
  if (null !== po.validUntil && po.validUntil < today) return 'expired'
  return 'active'
}

function poValidity(po: PoNumber): string {
  if (null === po.validFrom && null === po.validUntil) return t('adminCustomers.validityOpen')
  return `${po.validFrom ?? '—'} → ${po.validUntil ?? '—'}`
}

// Active first, then scheduled, then expired — each group oldest first.
const PO_ORDER: Record<PoState, number> = { active: 0, scheduled: 1, expired: 2 }
const sortedPoNumbers = computed(() => {
  const today = todayISO()
  return [...props.customer.billing.poNumbers].sort((a, b) => {
    const diff = PO_ORDER[poState(a, today)] - PO_ORDER[poState(b, today)]
    if (0 !== diff) return diff
    return (a.validFrom ?? '') < (b.validFrom ?? '') ? -1 : 1
  })
})

const emptyPoFields = (): PoNumberFields => ({
  poNumber: '',
  validFrom: null,
  validUntil: null,
  notes: null,
})

const showPoForm = ref(false)
const editingPoId = ref<number | null>(null)
const poForm = reactive<PoNumberFields>(emptyPoFields())
const poSaving = ref(false)
const poError = ref<string | null>(null)

function openPoCreate(): void {
  Object.assign(poForm, emptyPoFields(), { validFrom: todayISO() })
  editingPoId.value = null
  poError.value = null
  showPoForm.value = true
}

function openPoEdit(po: PoNumber): void {
  Object.assign(poForm, {
    poNumber: po.poNumber,
    validFrom: po.validFrom,
    validUntil: po.validUntil,
    notes: po.notes,
  })
  editingPoId.value = po.id
  poError.value = null
  showPoForm.value = true
}

async function onPoSubmit(): Promise<void> {
  poError.value = null
  poSaving.value = true
  const fields: PoNumberFields = {
    poNumber: poForm.poNumber.trim(),
    validFrom: poForm.validFrom || null,
    validUntil: poForm.validUntil || null,
    notes: poForm.notes && '' !== poForm.notes.trim() ? poForm.notes.trim() : null,
  }
  const result =
    null === editingPoId.value
      ? await store.createPoNumber(props.customer.id, fields)
      : await store.updatePoNumber(props.customer.id, editingPoId.value, fields)
  poSaving.value = false
  if (result.ok) {
    showPoForm.value = false
    editingPoId.value = null
  } else {
    poError.value = result.error ?? t('admin.saveFailed')
  }
}

async function onPoDelete(po: PoNumber): Promise<void> {
  if (!window.confirm(t('adminCustomers.poDeleteConfirm', { number: po.poNumber }))) return
  const result = await store.deletePoNumber(props.customer.id, po.id)
  if (!result.ok) window.alert(result.error ?? t('admin.deleteFailed'))
}

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
          <span>{{ t('adminCustomers.billingMode') }}</span>
          <AppSelect v-model="form.billingMode" :options="modeOptions" />
        </label>
        <label class="field">
          <span>{{ t('adminCustomers.billingPaymentDueDays') }}</span>
          <div class="cbill-days">
            <input v-model.number="form.paymentDueDays" type="number" min="0" max="365" step="1" />
            <span class="cbill-days-unit">{{ t('adminCustomers.billingDaysUnit') }}</span>
          </div>
        </label>
        <label class="field">
          <span>{{ t('adminCustomers.billingFeeTitle') }}</span>
          <AppSelect v-model="form.feeTitleId" :options="feeTitleOptions" />
        </label>
        <label class="cbill-po-toggle">
          <input v-model="form.hasPo" type="checkbox" @change="onHasPoChange" />
          <span>{{ t('adminCustomers.billingHasPo') }}</span>
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

    <!-- ── PO numbers (shown as soon as the checkbox is ticked) ────── -->
    <div v-if="form.hasPo" class="cbill-po">
      <div class="cbill-po-head">
        <h3>{{ t('adminCustomers.poTitle') }}</h3>
        <button v-if="!showPoForm" type="button" class="btn-submit" @click="openPoCreate()">
          {{ '+ ' + t('adminCustomers.poAdd') }}
        </button>
      </div>

      <form v-if="showPoForm" class="cbill-po-form" @submit.prevent="onPoSubmit">
        <div class="cbill-po-grid">
          <label class="field">
            <span>{{ t('adminCustomers.poNumber') }} *</span>
            <input v-model="poForm.poNumber" type="text" required maxlength="255" />
          </label>
          <label class="field">
            <span>{{ t('adminCustomers.notes') }}</span>
            <input v-model="poForm.notes" type="text" maxlength="500" />
          </label>
          <label class="field">
            <span>{{ t('adminCustomers.poValidFrom') }}</span>
            <input v-model="poForm.validFrom" type="date" />
          </label>
          <label class="field">
            <span>{{ t('adminCustomers.poValidUntil') }}</span>
            <input v-model="poForm.validUntil" type="date" />
          </label>
        </div>
        <p v-if="poError" class="msg msg--error">{{ poError }}</p>
        <div class="cbill-form-actions">
          <button type="submit" class="btn-submit" :disabled="poSaving">
            {{ poSaving ? t('admin.saving') : t('admin.save') }}
          </button>
          <button type="button" class="btn-ghost" @click="showPoForm = false">{{ t('adminUsers.cancel') }}</button>
        </div>
      </form>

      <p v-if="!showPoForm && customer.billing.poNumbers.length === 0" class="cbill-empty">
        {{ t('adminCustomers.poEmpty') }}
      </p>
      <div v-else-if="!showPoForm" class="cbill-po-table-wrap">
        <table class="cbill-po-table">
          <thead>
            <tr>
              <th>{{ t('adminCustomers.poNumber') }}</th>
              <th>{{ t('adminCustomers.poValidity') }}</th>
              <th>{{ t('adminCustomers.poStatus') }}</th>
              <th class="po-col-actions"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="po in sortedPoNumbers" :key="po.id" :class="{ 'is-expired': poState(po) === 'expired' }">
              <td>
                <span class="cbill-po-number">{{ po.poNumber }}</span>
                <span v-if="po.notes" class="cbill-po-notes">{{ po.notes }}</span>
              </td>
              <td class="cbill-po-validity">{{ poValidity(po) }}</td>
              <td>
                <span class="badge" :class="`badge--po-${poState(po)}`">
                  {{ t('adminCustomers.feeState_' + poState(po)) }}
                </span>
              </td>
              <td class="po-col-actions">
                <div class="cbill-po-actions">
                  <button type="button" class="btn-ghost" @click="openPoEdit(po)">{{ t('admin.edit') }}</button>
                  <button type="button" class="btn-ghost btn-danger" @click="onPoDelete(po)">{{ t('admin.delete') }}</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

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

/* Payment deadline: number input with a "days" unit after it. */
.cbill-days {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.cbill-days input {
  width: 6rem;
}

.cbill-days-unit {
  color: #8b94a6;
  font-size: 0.9rem;
  font-weight: 600;
}

.cbill-saved {
  color: #198754;
  font-size: 0.92rem;
  font-weight: 700;
}

.cbill-po-toggle {
  display: flex;
  align-items: center;
  align-self: end;
  gap: 0.5rem;
  padding: 0.55rem 0;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.92rem;
  font-weight: 700;
  cursor: pointer;
}

.cbill-po-toggle input {
  width: 18px;
  height: 18px;
  accent-color: var(--login-primary, #ed2044);
}

.cbill-po {
  margin-bottom: 1.3rem;
  padding: 1.2rem 1.3rem;
  background: #f7f8fb;
  border-radius: 0.8rem;
}

.cbill-po-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
  margin-bottom: 0.9rem;
}

.cbill-po-head h3 {
  margin: 0;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.05rem;
}

.cbill-po-form {
  margin-bottom: 1rem;
  padding: 1rem 1.1rem;
  background: #fff;
  border-radius: 0.7rem;
}

.cbill-po-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.9rem 1rem;
  margin-bottom: 1rem;
}

.cbill-po-table-wrap {
  overflow-x: auto;
}

.cbill-po-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.92rem;
}

.cbill-po-table th {
  padding: 0.5rem 0.6rem;
  border-bottom: 2px solid #e3e7ef;
  color: #8b94a6;
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-align: left;
  text-transform: uppercase;
}

.cbill-po-table td {
  padding: 0.6rem;
  border-bottom: 1px solid #e9edf4;
  color: var(--login-secondary, #0c1c40);
  vertical-align: middle;
}

.cbill-po-table tr:last-child td {
  border-bottom: none;
}

.cbill-po-table tr.is-expired td {
  color: #9aa6bd;
}

.cbill-po-number {
  display: block;
  font-weight: 700;
}

.cbill-po-notes {
  display: block;
  color: #8b94a6;
  font-size: 0.82rem;
}

.cbill-po-validity {
  white-space: nowrap;
}

.badge {
  display: inline-block;
  padding: 0.18rem 0.55rem;
  border-radius: 100vw;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  text-transform: uppercase;
}

.badge--po-active {
  background: #e5f6ec;
  color: #198754;
}

.badge--po-scheduled {
  background: #e7effc;
  color: #2563ad;
}

.badge--po-expired {
  background: #eef0f5;
  color: #8b94a6;
}

.po-col-actions {
  width: 160px;
  text-align: right;
}

.cbill-po-actions {
  display: flex;
  gap: 0.35rem;
  justify-content: flex-end;
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

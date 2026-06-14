<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import { useCustomersStore, type Customer, type FeeItem, type FeeItemFields } from '@/stores/customers'
import { useProductsStore, productStatus } from '@/stores/products'
import { useProductCategoriesStore } from '@/stores/productCategories'
import { useMoneyFormat } from '@/stores/currencySettings'
import AppSelect from '@/components/AppSelect.vue'
import IconEdit from '@/components/icons/IconEdit.vue'
import IconDelete from '@/components/icons/IconDelete.vue'

const props = defineProps<{ customer: Customer }>()

const { t } = useI18n()
const store = useCustomersStore()
const productsStore = useProductsStore()
const { products } = storeToRefs(productsStore)
const categoriesStore = useProductCategoriesStore()
const { categories } = storeToRefs(categoriesStore)

const CURRENCIES = ['HUF', 'EUR', 'USD']

// ── Catalogue filter for the product picker ──────────────────────────
// Narrows the picker by product category / subcategory (the taxonomy);
// purely a UI aid, nothing is persisted on the fee item.
const catFilter = ref<number | null>(null)
const subcatFilter = ref<number | null>(null)

// Picking a new category clears the subcategory; the subcategory select
// is disabled until a category is chosen.
function onCatFilterChange(): void {
  subcatFilter.value = null
}

function resetProductFilter(): void {
  catFilter.value = null
  subcatFilter.value = null
}

// Active catalogue products for the picker, narrowed by the category/
// subcategory filter. The item's already-linked product and the currently
// picked one stay selectable even if inactive or filtered out, so the
// chosen product never silently disappears from the list.
const productOptions = computed(() => {
  const linkedId = editingId.value ? props.customer.feeItems.find((i) => i.id === editingId.value)?.productId : null
  return products.value
    .filter((p) => {
      const alwaysKeep = p.id === linkedId || p.id === form.productId
      if (alwaysKeep) return true
      if ('active' !== productStatus(p)) return false
      if (null !== catFilter.value && p.categoryId !== catFilter.value) return false
      if (null !== subcatFilter.value && p.subcategoryId !== subcatFilter.value) return false
      return true
    })
    .sort((a, b) => a.name.localeCompare(b.name, 'hu'))
})

onMounted(() => {
  if (0 === products.value.length) productsStore.fetchProducts()
  if (0 === categories.value.length) categoriesStore.fetchCategories()
})

// ── Total discount (applies to every fee item) ──────────────────────
// Stored on the customer; the backend applies it to the monthly totals
// shown everywhere, while the per-item list prices stay untouched.
const discountInput = ref(props.customer.billing.feeDiscountPercent ?? '')
watch(
  () => props.customer.id,
  () => (discountInput.value = props.customer.billing.feeDiscountPercent ?? ''),
)
const discountSaving = ref(false)
const discountSaved = ref(false)
const discountError = ref<string | null>(null)

async function saveDiscount(): Promise<void> {
  discountSaving.value = true
  discountError.value = null
  discountSaved.value = false
  const result = await store.updateBilling(props.customer.id, {
    feeDiscountPercent: '' === String(discountInput.value).trim() ? null : String(discountInput.value),
  })
  discountSaving.value = false
  if (result.ok) {
    discountSaved.value = true
    window.setTimeout(() => (discountSaved.value = false), 2500)
  } else {
    discountError.value = result.error ?? t('admin.saveFailed')
  }
}

// ── AppSelect option lists ───────────────────────────────────────────
const productSelectOptions = computed<{ value: number | null; label: string }[]>(() => [
  { value: null, label: t('adminCustomers.feeProductNone') },
  ...productOptions.value.map((p) => ({
    value: p.id,
    label: p.unitPrice ? `${p.name} — ${fmtMoney(p.unitPrice, p.currency)}` : p.name,
  })),
])
const currencySelectOptions = computed<{ value: string; label: string }[]>(() =>
  CURRENCIES.map((c) => ({ value: c, label: c })),
)
const categorySelectOptions = computed<{ value: number | null; label: string }[]>(() => [
  { value: null, label: t('adminCustomers.feeCatAll') },
  ...categories.value.map((c) => ({ value: c.id, label: c.name })),
])
const subcategorySelectOptions = computed<{ value: number | null; label: string }[]>(() => {
  const cat = categories.value.find((c) => c.id === catFilter.value)
  return [
    { value: null, label: t('adminCustomers.feeSubcatAll') },
    ...(cat?.subcategories ?? []).map((s) => ({ value: s.id, label: s.name })),
  ]
})

// Picking a product prefills the editable fields; clearing it keeps them.
// For per-head items the catalogue price is the unit price.
function onProductPicked(): void {
  if (null === form.productId) return
  const product = products.value.find((p) => p.id === form.productId)
  if (!product) return
  form.name = product.name
  if (null !== product.unitPrice) {
    if (form.isPerHead) form.unitAmount = product.unitPrice
    else form.amount = product.unitPrice
  }
  form.currency = product.currency
}

// Live total preview for per-head items in the form.
const formPerHeadTotal = computed(() => {
  if (!form.isPerHead) return null
  const unit = Number(form.unitAmount ?? 0)
  const qty = Number(form.quantity ?? 0)
  if (unit <= 0 || qty <= 0) return null
  return fmtMoney(String(unit * qty), form.currency)
})

// ── Formatting ───────────────────────────────────────────────────────
const fmtMoney = useMoneyFormat()

function todayISO(): string {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

type FeeState = 'active' | 'scheduled' | 'expired'

function feeState(item: FeeItem, today: string = todayISO()): FeeState {
  if (null !== item.validFrom && item.validFrom > today) return 'scheduled'
  if (null !== item.validUntil && item.validUntil < today) return 'expired'
  return 'active'
}

function validityLabel(item: FeeItem): string {
  if (null === item.validFrom && null === item.validUntil) return t('adminCustomers.validityOpen')
  return `${item.validFrom ?? '—'} → ${item.validUntil ?? '—'}`
}

// Active items first, then scheduled, then expired — each group oldest first.
const STATE_ORDER: Record<FeeState, number> = { active: 0, scheduled: 1, expired: 2 }
const sortedItems = computed(() => {
  const today = todayISO()
  return [...props.customer.feeItems].sort((a, b) => {
    const diff = STATE_ORDER[feeState(a, today)] - STATE_ORDER[feeState(b, today)]
    if (0 !== diff) return diff
    return (a.validFrom ?? '') < (b.validFrom ?? '') ? -1 : 1
  })
})

const activeCount = computed(() => {
  const today = todayISO()
  return props.customer.feeItems.filter((item) => 'active' === feeState(item, today)).length
})

// ── Shared create/edit form ──────────────────────────────────────────
const emptyFields = (): FeeItemFields => ({
  productId: null,
  name: '',
  isPerHead: false,
  unitAmount: null,
  quantity: null,
  amount: null,
  currency: 'HUF',
  validFrom: null,
  validUntil: null,
  notes: null,
})

const showForm = ref(false)
const editingId = ref<number | null>(null)
const form = reactive<FeeItemFields>(emptyFields())
const saving = ref(false)
const formError = ref<string | null>(null)

function openCreate(): void {
  Object.assign(form, emptyFields())
  editingId.value = null
  formError.value = null
  resetProductFilter()
  showForm.value = true
}

function openEdit(item: FeeItem): void {
  Object.assign(form, {
    productId: item.productId,
    name: item.name,
    isPerHead: item.isPerHead,
    unitAmount: item.unitAmount,
    quantity: item.quantity,
    amount: item.amount,
    currency: item.currency,
    validFrom: item.validFrom,
    validUntil: item.validUntil,
    notes: item.notes,
  })
  editingId.value = item.id
  formError.value = null
  resetProductFilter()
  showForm.value = true
  showRaise.value = false
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
      ? await store.createFee(props.customer.id, { ...form })
      : await store.updateFee(props.customer.id, editingId.value, { ...form })
  saving.value = false
  if (result.ok) {
    closeForm()
  } else {
    formError.value = result.error ?? t('admin.saveFailed')
  }
}

async function onDelete(item: FeeItem): Promise<void> {
  if (!window.confirm(t('adminCustomers.confirmDeleteFee', { name: item.name }))) return
  const result = await store.deleteFee(props.customer.id, item.id)
  if (!result.ok) window.alert(result.error ?? t('admin.deleteFailed'))
}

// ── Raise: a percentage on the WHOLE monthly fee ─────────────────────
// Every item active on the effective date is closed and re-opened at
// the raised price (per-head: raised unit price); history is kept.
const showRaise = ref(false)
const raisePercent = ref('')
const raiseFrom = ref('')
const raising = ref(false)
const raiseError = ref<string | null>(null)

function openRaise(): void {
  raisePercent.value = ''
  raiseFrom.value = todayISO()
  raiseError.value = null
  showRaise.value = true
  showForm.value = false
}

// Live preview: the current REAL total raised by the entered percent.
const raisePreview = computed(() => {
  const pct = Number(raisePercent.value)
  if (!pct || pct <= -100) return null
  return props.customer.monthlyFeeTotals
    .map((tt) => fmtMoney(String(Number(tt.amount) * (1 + pct / 100)), tt.currency))
    .join(' · ')
})

// ── Footer rows ──────────────────────────────────────────────────────
// Base line: list-price sum × discount (no raises yet); then one row
// per raise with the CUMULATIVE total after it, in date order.
const discountedBaseTotals = computed(() => {
  const factor = 1 - Number(props.customer.billing.feeDiscountPercent ?? 0) / 100
  return props.customer.monthlyFeeGrossTotals.map((tt) => ({
    currency: tt.currency,
    amount: String(Number(tt.amount) * factor),
  }))
})

const raiseRows = computed(() => {
  const sorted = [...props.customer.feeRaises].sort((a, b) =>
    a.effectiveFrom === b.effectiveFrom ? a.id - b.id : a.effectiveFrom < b.effectiveFrom ? -1 : 1,
  )
  let factor = 1
  return sorted.map((raise) => {
    factor *= 1 + Number(raise.percent) / 100
    const f = factor
    return {
      raise,
      percentLabel: String(Number(raise.percent)),
      totals: discountedBaseTotals.value.map((tt) => ({
        currency: tt.currency,
        amount: String(Number(tt.amount) * f),
      })),
    }
  })
})

async function onDeleteRaise(raiseId: number): Promise<void> {
  if (!window.confirm(t('adminCustomers.raiseDeleteConfirm'))) return
  const result = await store.deleteFeeRaise(props.customer.id, raiseId)
  if (!result.ok) window.alert(result.error ?? t('admin.deleteFailed'))
}

async function onRaise(): Promise<void> {
  raiseError.value = null
  raising.value = true
  const result = await store.raiseAllFees(props.customer.id, raisePercent.value, raiseFrom.value)
  raising.value = false
  if (result.ok) {
    showRaise.value = false
  } else {
    raiseError.value = result.error ?? t('admin.saveFailed')
  }
}
</script>

<template>
  <div class="fee-panel">
    <!-- ── Active total (hidden while a form is open) ──────────────── -->
    <div v-if="!showForm && !showRaise" class="fee-summary">
      <div class="fee-sum-card">
        <span class="fee-sum-label">{{ t('adminCustomers.feeSumActive') }}</span>
        <span v-if="customer.monthlyFeeTotals.length === 0" class="fee-sum-value">—</span>
        <span v-else class="fee-sum-value">
          <span v-for="tt in customer.monthlyFeeTotals" :key="tt.currency" class="fee-sum-line">
            {{ fmtMoney(tt.amount, tt.currency) }}
          </span>
        </span>
        <span v-if="Number(customer.billing.feeDiscountPercent ?? 0) > 0 && customer.monthlyFeeGrossTotals.length > 0" class="fee-sum-gross">
          <span class="fee-gross-strike">
            <span v-for="tt in customer.monthlyFeeGrossTotals" :key="tt.currency">
              {{ fmtMoney(tt.amount, tt.currency) }}
            </span>
          </span>
          <span class="fee-gross-badge">−{{ Number(customer.billing.feeDiscountPercent) }}%</span>
        </span>
        <span class="fee-sum-sub">{{ t('adminCustomers.feeActiveCount', { count: activeCount }) }}</span>
        <span v-if="Number(customer.billing.feeDiscountPercent ?? 0) > 0" class="fee-sum-sub">
          {{ t('adminCustomers.feeDiscountNote', { percent: Number(customer.billing.feeDiscountPercent) }) }}
        </span>
      </div>

      <!-- Total discount applied to every fee item. -->
      <div class="fee-discount-box">
        <label class="fee-discount-field">
          <span>{{ t('adminCustomers.billingDiscount') }}</span>
          <input
            v-model="discountInput"
            type="number"
            min="0"
            max="100"
            step="0.01"
            inputmode="decimal"
            @keydown.enter.prevent="saveDiscount"
          />
        </label>
        <button type="button" class="btn-ghost" :disabled="discountSaving" @click="saveDiscount">
          {{ discountSaving ? t('admin.saving') : t('admin.save') }}
        </button>
        <span v-if="discountSaved" class="fee-discount-saved">✓</span>
        <p v-if="discountError" class="fee-discount-error">{{ discountError }}</p>
      </div>

      <div class="fee-head-actions">
        <button type="button" class="btn-ghost btn-raise-open" @click="openRaise()">
          {{ t('adminCustomers.raise') }}
        </button>
        <button type="button" class="btn-submit" @click="openCreate()">
          {{ '+ ' + t('adminCustomers.feeAddButton') }}
        </button>
      </div>
    </div>

    <!-- ── Raise the whole monthly fee ─────────────────────────────── -->
    <form v-if="showRaise" class="raise-form raise-form--global" @submit.prevent="onRaise">
      <h3>{{ t('adminCustomers.raiseAllTitle') }}</h3>
      <p class="raise-hint">{{ t('adminCustomers.raiseAllHint') }}</p>
      <div class="raise-grid">
        <label class="field">
          <span>{{ t('adminCustomers.raiseAllPercent') }} *</span>
          <input v-model="raisePercent" type="number" min="-99.99" max="1000" step="0.01" required />
        </label>
        <label class="field">
          <span>{{ t('adminCustomers.raiseEffectiveFrom') }} *</span>
          <input v-model="raiseFrom" type="date" required />
        </label>
        <span v-if="raisePreview" class="raise-total">→ {{ raisePreview }}</span>
      </div>
      <p v-if="raiseError" class="msg msg--error">{{ raiseError }}</p>
      <div class="raise-actions">
        <button type="submit" class="btn-submit" :disabled="raising">
          {{ raising ? t('admin.saving') : t('adminCustomers.raiseSubmit') }}
        </button>
        <button type="button" class="btn-ghost" @click="showRaise = false">{{ t('adminUsers.cancel') }}</button>
      </div>
    </form>

    <!-- ── Create / edit form ──────────────────────────────────────── -->
    <form v-if="showForm" class="fee-form" @submit.prevent="onSubmit">
      <h3>{{ null === editingId ? t('adminCustomers.feeAddButton') : t('admin.edit') }}</h3>
      <div class="fee-form-grid">
        <label class="field">
          <span>{{ t('adminCustomers.feeFilterCategory') }}</span>
          <AppSelect v-model="catFilter" :options="categorySelectOptions" @change="onCatFilterChange" />
        </label>
        <label class="field">
          <span>{{ t('adminCustomers.feeFilterSubcategory') }}</span>
          <AppSelect v-model="subcatFilter" :options="subcategorySelectOptions" :disabled="null === catFilter" />
        </label>
        <label class="field field--wide">
          <span>{{ t('adminCustomers.feeProduct') }}</span>
          <AppSelect v-model="form.productId" :options="productSelectOptions" @change="onProductPicked" />
        </label>
        <label class="field field--wide">
          <span>{{ t('adminCustomers.feeName') }} *</span>
          <input v-model="form.name" type="text" required maxlength="255" />
        </label>
        <label class="perhead-toggle field--wide">
          <input v-model="form.isPerHead" type="checkbox" @change="onProductPicked" />
          <span>{{ t('adminCustomers.feePerHead') }}</span>
        </label>
        <template v-if="form.isPerHead">
          <label class="field">
            <span>{{ t('adminCustomers.feeUnitAmount') }} *</span>
            <input v-model="form.unitAmount" type="number" min="0" step="any" required />
          </label>
          <label class="field">
            <span>{{ t('adminCustomers.feeQuantity') }} *</span>
            <input v-model.number="form.quantity" type="number" min="1" step="1" required />
          </label>
          <label class="field">
            <span>{{ t('adminCustomers.currency') }}</span>
            <AppSelect v-model="form.currency" :options="currencySelectOptions" />
          </label>
          <p v-if="formPerHeadTotal" class="perhead-total field--wide">
            {{ t('adminCustomers.feeAmount') }}: <strong>{{ formPerHeadTotal }}</strong>
          </p>
        </template>
        <template v-else>
          <label class="field">
            <span>{{ t('adminCustomers.feeAmount') }} *</span>
            <input v-model="form.amount" type="number" min="0" step="any" required />
          </label>
          <label class="field">
            <span>{{ t('adminCustomers.currency') }}</span>
            <AppSelect v-model="form.currency" :options="currencySelectOptions" />
          </label>
        </template>
        <label class="field">
          <span>{{ t('adminCustomers.validFrom') }}</span>
          <input v-model="form.validFrom" type="date" />
        </label>
        <label class="field">
          <span>{{ t('adminCustomers.validUntil') }}</span>
          <input v-model="form.validUntil" type="date" />
        </label>
        <label class="field field--wide">
          <span>{{ t('adminCustomers.notes') }}</span>
          <textarea v-model="form.notes" rows="2" />
        </label>
      </div>
      <p v-if="formError" class="msg msg--error">{{ formError }}</p>
      <div class="fee-form-actions">
        <button type="submit" class="btn-submit" :disabled="saving">
          {{ saving ? t('admin.saving') : t('admin.save') }}
        </button>
        <button type="button" class="btn-ghost" @click="closeForm">{{ t('adminUsers.cancel') }}</button>
      </div>
    </form>

    <!-- ── Items (hidden while a form is open) ─────────────────────── -->
    <p v-if="!showForm && !showRaise && customer.feeItems.length === 0" class="muted">{{ t('adminCustomers.feeEmpty') }}</p>

    <div v-else-if="!showForm && !showRaise" class="fee-table-wrap">
      <table class="fee-table">
        <thead>
          <tr>
            <th>{{ t('adminCustomers.feeName') }}</th>
            <th class="num">{{ t('adminCustomers.feeAmount') }}</th>
            <th>{{ t('adminCustomers.colValidity') }}</th>
            <th>{{ t('adminCustomers.status') }}</th>
            <th class="col-actions"></th>
          </tr>
        </thead>
        <tbody>
          <template v-for="item in sortedItems" :key="item.id">
            <tr :class="{ 'is-expired': feeState(item) === 'expired' }">
              <td>
                <span class="fee-name">
                  {{ item.name }}
                  <span v-if="item.productId" class="fee-product-tag" :title="t('adminCustomers.feeProduct')">⬡</span>
                </span>
                <span v-if="item.notes" class="fee-notes">{{ item.notes }}</span>
              </td>
              <td class="num fee-amount">
                {{ fmtMoney(item.amount, item.currency) }}
                <span v-if="item.isPerHead && item.unitAmount && item.quantity" class="fee-breakdown">
                  {{ t('adminCustomers.feeBreakdown', { count: item.quantity, unit: fmtMoney(item.unitAmount, item.currency) }) }}
                </span>
              </td>
              <td class="fee-validity">{{ validityLabel(item) }}</td>
              <td>
                <span class="badge" :class="`badge--fee-${feeState(item)}`">
                  {{ t('adminCustomers.feeState_' + feeState(item)) }}
                </span>
              </td>
              <td class="col-actions">
                <div class="fee-row-actions">
                  <button type="button" class="btn-icon" :title="t('admin.edit')" :aria-label="t('admin.edit')" @click="openEdit(item)">
                    <IconEdit />
                  </button>
                  <button
                    type="button"
                    class="btn-icon btn-icon--danger"
                    :title="t('admin.delete')"
                    :aria-label="t('admin.delete')"
                    @click="onDelete(item)"
                  >
                    <IconDelete />
                  </button>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
        <tfoot>
          <tr>
            <td>{{ t('adminCustomers.feeTotalRow') }}</td>
            <td class="num fee-total-cell">
              <div v-if="customer.monthlyFeeGrossTotals.length === 0">—</div>
              <div v-for="tt in customer.monthlyFeeGrossTotals" :key="tt.currency">
                {{ fmtMoney(tt.amount, tt.currency) }}
              </div>
            </td>
            <td colspan="3" class="fee-total-hint">{{ t('adminCustomers.feeTotalHint') }}</td>
          </tr>
          <!-- Grand total after the customer-level discount. -->
          <tr v-if="Number(customer.billing.feeDiscountPercent ?? 0) > 0" class="fee-net-row">
            <td>{{ t('adminCustomers.feeNetTotalRow') }}</td>
            <td class="num fee-total-cell fee-net-cell">
              <div v-for="tt in discountedBaseTotals" :key="tt.currency">
                {{ fmtMoney(tt.amount, tt.currency) }}
              </div>
            </td>
            <td colspan="3" class="fee-total-hint">−{{ Number(customer.billing.feeDiscountPercent) }}%</td>
          </tr>
          <!-- One row per whole-fee raise: percent, date, the total after it. -->
          <tr v-for="row in raiseRows" :key="row.raise.id" class="fee-raise-row">
            <td>{{ t('adminCustomers.raiseRowLabel', { percent: row.percentLabel, date: row.raise.effectiveFrom }) }}</td>
            <td class="num fee-total-cell">
              <div v-for="tt in row.totals" :key="tt.currency">
                {{ fmtMoney(tt.amount, tt.currency) }}
              </div>
            </td>
            <td colspan="3" class="fee-total-hint fee-raise-actions">
              <button type="button" class="btn-raise-del" :title="t('admin.delete')" @click="onDeleteRaise(row.raise.id)">
                ✕
              </button>
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</template>

<style scoped>
.fee-summary {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
  margin-bottom: 1.3rem;
}

.fee-sum-card {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  padding: 1.1rem 1.4rem;
  background: var(--login-secondary, #0c1c40);
  border-radius: 0.8rem;
  min-width: 220px;
}

.fee-sum-label {
  color: #aab6d3;
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.fee-sum-value {
  display: flex;
  flex-wrap: wrap;
  gap: 0.2rem 1rem;
  color: #fff;
  font-size: 1.7rem;
  font-weight: 700;
  line-height: 1.2;
}

.fee-sum-sub {
  color: #aab6d3;
  font-size: 0.82rem;
  font-weight: 600;
}

.fee-sum-gross {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  flex-wrap: wrap;
}

.fee-gross-strike {
  display: flex;
  gap: 0.2rem 0.8rem;
  flex-wrap: wrap;
  color: #8fa0c5;
  font-size: 0.98rem;
  font-weight: 600;
  text-decoration: line-through;
}

.fee-gross-badge {
  padding: 0.1rem 0.5rem;
  background: var(--login-primary, #ed2044);
  border-radius: 100vw;
  color: #fff;
  font-size: 0.78rem;
  font-weight: 700;
}

/* Stands apart from the list-price total: brand-red on a soft pink band.
   Specificity must beat `.fee-table tfoot td`, hence the long selector. */
.fee-table tfoot .fee-net-row td {
  background: #fdeef1;
  border-top: 2px solid var(--login-primary, #ed2044);
  color: var(--login-primary, #ed2044);
  font-weight: 700;
}

.fee-table tfoot .fee-net-row .fee-total-hint {
  color: var(--login-primary, #ed2044);
}

.fee-net-cell {
  font-size: 1.02rem;
}

/* Raise history rows under the net total: amber band, cumulative totals. */
.fee-table tfoot .fee-raise-row td {
  background: #fff7ed;
  border-top: 1px solid #fcd9a8;
  color: #9a6b1f;
  font-weight: 700;
}

.fee-raise-actions {
  text-align: right;
}

.btn-raise-del {
  padding: 0.1rem 0.5rem;
  background: none;
  border: 1px solid #e8c489;
  border-radius: 0.4rem;
  color: #9a6b1f;
  font-size: 0.8rem;
  cursor: pointer;
}

.btn-raise-del:hover {
  background: #fcecd2;
}

.fee-head-actions {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

.raise-form--global {
  margin-bottom: 1.3rem;
  padding: 1.2rem 1.3rem;
  background: #fff7ed;
  border: 1px solid #fcd9a8;
  border-radius: 0.8rem;
}

.raise-form--global h3 {
  margin: 0 0 0.3rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.05rem;
}

.raise-hint {
  margin: 0 0 0.9rem;
  color: #8a6d3b;
  font-size: 0.85rem;
}

.raise-grid {
  display: flex;
  align-items: flex-end;
  gap: 1rem;
  flex-wrap: wrap;
  margin-bottom: 1rem;
}

.raise-actions {
  display: flex;
  gap: 0.6rem;
}

.fee-discount-box {
  display: flex;
  align-items: flex-end;
  gap: 0.6rem;
  flex-wrap: wrap;
  margin-right: auto;
  padding: 0.8rem 1rem;
  background: #f7f8fb;
  border-radius: 0.8rem;
}

.fee-discount-field {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}

.fee-discount-field span {
  color: var(--login-secondary, #0c1c40);
  font-size: 0.85rem;
  font-weight: 700;
}

.fee-discount-field input {
  width: 110px;
  padding: 0.5rem 0.7rem;
  background: #fff;
  border: 1px solid #d4dae6;
  border-radius: 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-family: inherit;
}

.fee-discount-field input:focus {
  outline: 2px solid var(--login-primary, #ed2044);
  outline-offset: -1px;
}

.fee-discount-saved {
  align-self: center;
  color: #198754;
  font-size: 1rem;
  font-weight: 700;
}

.fee-discount-error {
  flex-basis: 100%;
  margin: 0;
  color: #b3122e;
  font-size: 0.85rem;
}

.fee-form {
  margin-bottom: 1.3rem;
  padding: 1.2rem 1.3rem;
  background: #f7f8fb;
  border-radius: 0.8rem;
}

.fee-form h3 {
  margin: 0 0 1rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.05rem;
  font-weight: 700;
}

.fee-form-grid {
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
.field textarea,
.field select {
  padding: 0.55rem 0.7rem;
  background: #fff;
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
}

.fee-form-actions {
  display: flex;
  gap: 0.6rem;
}

.fee-table-wrap {
  overflow-x: auto;
}

.fee-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.92rem;
}

.fee-table th {
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

.fee-table td {
  padding: 0.65rem 0.7rem;
  border-bottom: 1px solid #eef1f6;
  color: var(--login-secondary, #0c1c40);
  vertical-align: middle;
}

.fee-table .num {
  text-align: right;
  white-space: nowrap;
}

.fee-table tr.is-expired td {
  color: #9aa6bd;
}

.fee-table tfoot td {
  border-bottom: none;
  border-top: 2px solid #e3e7ef;
  font-weight: 700;
}

.fee-total-cell {
  font-size: 1.02rem;
}

.fee-table tfoot .fee-total-hint {
  color: #8b94a6;
  font-size: 0.8rem;
  font-weight: 600;
  text-align: right;
}

.fee-name {
  display: block;
  font-weight: 600;
}

.fee-notes {
  display: block;
  margin-top: 0.1rem;
  color: #8b94a6;
  font-size: 0.8rem;
}

/* Small marker on items linked to a catalogue product. */
.fee-product-tag {
  margin-left: 0.25rem;
  color: #2b59c3;
  font-size: 0.85rem;
}

.fee-breakdown {
  display: block;
  margin-top: 0.1rem;
  color: #8b94a6;
  font-size: 0.78rem;
  font-weight: 600;
}

.perhead-toggle {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-weight: 600;
  cursor: pointer;
}

.perhead-toggle input {
  width: 1rem;
  height: 1rem;
  accent-color: var(--login-primary, #ed2044);
}

.perhead-total {
  margin: 0;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
}

.raise-total {
  align-self: flex-end;
  padding-bottom: 0.6rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-weight: 700;
}

.fee-amount {
  font-weight: 700;
}

.fee-validity {
  white-space: nowrap;
}

.col-actions {
  text-align: right;
  white-space: nowrap;
}

.fee-row-actions {
  display: inline-flex;
  align-items: center;
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
  white-space: nowrap;
}

.badge--fee-active {
  background: #e3f6ec;
  color: #1c7a45;
}

.badge--fee-scheduled {
  background: #e7eefc;
  color: #2b59c3;
}

.badge--fee-expired {
  background: #eef1f6;
  color: #8b94a6;
}

.btn-mini-raise {
  padding: 0.32rem 0.75rem;
  background: #fff;
  border: 1px solid var(--login-primary, #ed2044);
  border-radius: 0.45rem;
  color: var(--login-primary, #ed2044);
  font-size: 0.8rem;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
}

.btn-mini-raise:hover {
  background: var(--login-primary, #ed2044);
  color: #fff;
}

.raise-row td {
  padding: 0;
  border-bottom: 1px solid #eef1f6;
  background: #fdf6f7;
}

.raise-form {
  display: flex;
  align-items: flex-end;
  gap: 0.9rem;
  flex-wrap: wrap;
  padding: 0.9rem 1rem;
}

.raise-form .field {
  min-width: 160px;
}

.btn-raise-submit {
  padding: 0.55rem 1.1rem;
  font-size: 0.9rem;
}

.raise-error {
  flex-basis: 100%;
  margin: 0;
}

.muted {
  margin: 0;
  color: #8b94a6;
  font-size: 0.9rem;
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

@media (max-width: 767.98px) {
  .fee-form-grid {
    grid-template-columns: 1fr;
  }
}
</style>

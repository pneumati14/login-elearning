<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { Customer } from '@/stores/customers'
import {
  useOpportunitiesStore,
  emptyOpportunityFields,
  formatMoney,
  formatFileSize,
  CURRENCIES,
  HOURS_PER_DAY,
  type Opportunity,
  type OpportunityFields,
  type LineItemFields,
  type EffortEstimateFields,
  type EffortType,
  type EffortUnit,
  type OpportunityNature,
} from '@/stores/opportunities'
import { useOpportunityTypesStore, typeStatus, type OpportunityType } from '@/stores/opportunityTypes'
import { useProductsStore } from '@/stores/products'
import ActivityList from '@/components/ActivityList.vue'
import AppSelect from '@/components/AppSelect.vue'
import IconEdit from '@/components/icons/IconEdit.vue'
import IconDelete from '@/components/icons/IconDelete.vue'

const props = defineProps<{ customer: Customer }>()

const { t } = useI18n()
const store = useOpportunitiesStore()
const typesStore = useOpportunityTypesStore()
const productsStore = useProductsStore()

const opportunities = computed<Opportunity[]>(() => store.list(props.customer.id))

// ── List ↔ Kanban view ────────────────────────────────────────────────
const view = ref<'list' | 'kanban'>('list')

// Which pipeline (type) the kanban board shows.
const selectedTypeId = ref<number | null>(null)
const selectedType = computed<OpportunityType | null>(
  () => typesStore.types.find((tp) => tp.id === selectedTypeId.value) ?? null,
)

/** Types usable for *new* opportunities: active and with at least one stage. */
const usableTypes = computed(() =>
  typesStore.types.filter((tp) => tp.stages.length > 0 && 'active' === typeStatus(tp)),
)

function typeCount(typeId: number): number {
  return opportunities.value.filter((o) => o.typeId === typeId).length
}

function stagesForType(typeId: number) {
  return typesStore.types.find((tp) => tp.id === typeId)?.stages ?? []
}

// Kanban nature filter: all deals, only new business or only upsells.
const natureFilter = ref<OpportunityNature | null>(null)

const boardOpportunities = computed(() =>
  opportunities.value.filter(
    (o) => o.typeId === selectedTypeId.value && (null === natureFilter.value || o.nature === natureFilter.value),
  ),
)

function columnOpportunities(stageId: number): Opportunity[] {
  return boardOpportunities.value.filter((o) => o.stageId === stageId)
}

async function load(): Promise<void> {
  await Promise.all([
    typesStore.fetchTypes(),
    productsStore.fetchProducts(),
    store.fetchOpportunities(props.customer.id),
  ])
  pickInitialType()
}

function pickInitialType(): void {
  if (null !== selectedTypeId.value && typesStore.types.some((tp) => tp.id === selectedTypeId.value)) return
  const withDeals = typesStore.types.find((tp) => typeCount(tp.id) > 0)
  selectedTypeId.value = withDeals?.id ?? usableTypes.value[0]?.id ?? typesStore.types[0]?.id ?? null
}

onMounted(load)
watch(() => props.customer.id, load)

// ── Create / edit form ────────────────────────────────────────────────
const showForm = ref(false)
const editingId = ref<number | null>(null)
const form = reactive<OpportunityFields>(emptyOpportunityFields())
const saving = ref(false)
const formError = ref<string | null>(null)

const formStages = computed(() => typesStore.types.find((tp) => tp.id === form.typeId)?.stages ?? [])

const lineItemsTotal = computed(() =>
  form.lineItems.reduce((sum, li) => sum + Number(li.quantity || 0) * Number(li.unitPrice || 0), 0),
)
const hasLines = computed(() => form.lineItems.length > 0)

function openNew(): void {
  editingId.value = null
  Object.assign(form, emptyOpportunityFields())
  form.typeId = selectedTypeId.value ?? usableTypes.value[0]?.id ?? null
  form.stageId = formStages.value[0]?.id ?? null
  formError.value = null
  showForm.value = true
}

function openEdit(o: Opportunity): void {
  editingId.value = o.id
  form.title = o.title
  form.quoteNumber = o.quoteNumber
  form.typeId = o.typeId
  form.stageId = o.stageId
  form.value = o.value
  form.currency = o.currency
  form.nature = o.nature
  form.expectedCloseDate = o.expectedCloseDate
  form.contactId = o.contactId
  form.notes = o.notes
  form.lineItems = o.lineItems.map((li) => ({
    productId: li.productId,
    productName: li.productName,
    quantity: li.quantity,
    unitPrice: li.unitPrice,
  }))
  form.effortEstimates = o.effortEstimates.map((ee) => ({
    name: ee.name,
    effortType: ee.effortType,
    amount: ee.amount,
    unit: ee.unit,
  }))
  formError.value = null
  showForm.value = true
}

function closeForm(): void {
  showForm.value = false
  editingId.value = null
}

watch(
  () => form.typeId,
  () => {
    if (null === editingId.value) form.stageId = formStages.value[0]?.id ?? null
  },
)

// ── Line item editing ─────────────────────────────────────────────────
function addLine(): void {
  form.lineItems.push({ productId: null, productName: '', quantity: '1', unitPrice: '0' })
}

function removeLine(index: number): void {
  form.lineItems.splice(index, 1)
}

function onPickProduct(line: LineItemFields): void {
  if (null === line.productId) return
  const product = productsStore.products.find((p) => p.id === line.productId)
  if (product) {
    line.productName = product.name
    line.unitPrice = product.unitPrice ?? '0'
  }
}

function lineTotal(line: LineItemFields): number {
  return Number(line.quantity || 0) * Number(line.unitPrice || 0)
}

// ── Preliminary effort estimate editing ───────────────────────────────
const hasEstimates = computed(() => form.effortEstimates.length > 0)

function addEstimate(): void {
  form.effortEstimates.push({ name: '', effortType: 'development', amount: '', unit: 'day' })
}

function removeEstimate(index: number): void {
  form.effortEstimates.splice(index, 1)
}

/** A row's effort in days (hour rows are converted with the 8h workday). */
function estimateDays(row: EffortEstimateFields): number {
  const amount = Number(String(row.amount).replace(',', '.')) || 0
  return 'hour' === row.unit ? amount / HOURS_PER_DAY : amount
}

function effortTotalDays(effortType: EffortEstimateFields['effortType']): number {
  return form.effortEstimates
    .filter((row) => row.effortType === effortType)
    .reduce((sum, row) => sum + estimateDays(row), 0)
}

/** Days as a human-readable number ("0,75"), without trailing zeros. */
function formatDays(days: number): string {
  return (Math.round(days * 100) / 100).toLocaleString('hu-HU')
}

async function onSubmit(): Promise<void> {
  if ('' === form.title.trim()) {
    formError.value = t('adminCustomers.oppTitleRequired')
    return
  }
  if (null === form.typeId) {
    formError.value = t('adminCustomers.oppNoTypes')
    return
  }
  // Every line needs a name (custom lines without a product included).
  for (const li of form.lineItems) {
    if ('' === li.productName.trim() && null === li.productId) {
      formError.value = t('adminCustomers.oppLineItems')
      return
    }
  }
  // Every effort estimate row needs a name too.
  for (const ee of form.effortEstimates) {
    if ('' === ee.name.trim()) {
      formError.value = t('adminCustomers.oppEffortNameRequired')
      return
    }
  }
  saving.value = true
  const payload: OpportunityFields = { ...form, value: '' === form.value ? null : form.value }
  const result =
    null === editingId.value
      ? await store.createOpportunity(props.customer.id, payload)
      : await store.updateOpportunity(props.customer.id, editingId.value, payload)
  saving.value = false

  if (result.ok) {
    if (null !== form.typeId) selectedTypeId.value = form.typeId
    closeForm()
  } else {
    formError.value = result.error ?? t('admin.saveFailed')
  }
}

async function onDelete(o: Opportunity): Promise<void> {
  if (!window.confirm(t('adminCustomers.oppConfirmDelete', { title: o.title }))) return
  const result = await store.deleteOpportunity(props.customer.id, o.id)
  if (!result.ok) {
    window.alert(result.error ?? t('admin.deleteFailed'))
  } else if (editingId.value === o.id) {
    closeForm()
  }
}

// ── Offer documents (PDF) ─────────────────────────────────────────────
// The opportunity being edited, sourced from the store so its document
// list stays reactive after an upload/delete.
const editingOpportunity = computed<Opportunity | null>(() =>
  null === editingId.value ? null : (opportunities.value.find((o) => o.id === editingId.value) ?? null),
)
const uploading = ref(false)
const docError = ref<string | null>(null)

async function onUploadDocument(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file || null === editingId.value) return
  if ('application/pdf' !== file.type) {
    docError.value = t('adminCustomers.oppDocPdfOnly')
    input.value = ''
    return
  }
  docError.value = null
  uploading.value = true
  const result = await store.uploadDocument(props.customer.id, editingId.value, file)
  uploading.value = false
  input.value = ''
  if (!result.ok) docError.value = result.error ?? t('admin.saveFailed')
}

async function onDeleteDocument(docId: number): Promise<void> {
  if (null === editingId.value) return
  if (!window.confirm(t('adminCustomers.oppDocConfirmDelete'))) return
  const result = await store.deleteDocument(props.customer.id, editingId.value, docId)
  if (!result.ok) window.alert(result.error ?? t('admin.deleteFailed'))
}

/** Change an opportunity's stage from the list-view dropdown. */
async function onStageChange(o: Opportunity, stageId: number): Promise<void> {
  if (stageId === o.stageId) return
  const result = await store.moveStage(props.customer.id, o.id, stageId)
  if (!result.ok) window.alert(result.error ?? t('admin.saveFailed'))
}

// ── Kanban drag-and-drop ──────────────────────────────────────────────
const dragId = ref<number | null>(null)
const overStageId = ref<number | null>(null)

function onDragStart(o: Opportunity, e: DragEvent): void {
  dragId.value = o.id
  if (e.dataTransfer) {
    e.dataTransfer.effectAllowed = 'move'
    e.dataTransfer.setData('text/plain', String(o.id))
  }
}

function onDragOver(stageId: number, e: DragEvent): void {
  e.preventDefault()
  if (e.dataTransfer) e.dataTransfer.dropEffect = 'move'
  overStageId.value = stageId
}

function onDragEnd(): void {
  dragId.value = null
  overStageId.value = null
}

async function onDrop(stageId: number): Promise<void> {
  const id = dragId.value
  onDragEnd()
  if (null === id) return
  const opp = opportunities.value.find((o) => o.id === id)
  if (!opp || opp.stageId === stageId) return
  const result = await store.moveStage(props.customer.id, id, stageId)
  if (!result.ok) window.alert(result.error ?? t('admin.saveFailed'))
}

// ── History expansion (kanban cards) ──────────────────────────────────
const expandedId = ref<number | null>(null)
function toggleHistory(id: number): void {
  expandedId.value = expandedId.value === id ? null : id
}

function shortDate(iso: string): string {
  return iso.slice(0, 10)
}

const hasTypes = computed(() => typesStore.types.length > 0)

// ── AppSelect option lists (downward-opening custom select) ───────────
const pipelineSelectOptions = computed<{ value: number; label: string }[]>(() =>
  typesStore.types.map((tp) => ({ value: tp.id, label: `${tp.name} (${typeCount(tp.id)})` })),
)
const typeSelectOptions = computed<{ value: number; label: string }[]>(() =>
  usableTypes.value.map((tp) => ({ value: tp.id, label: tp.name })),
)
const formStageSelectOptions = computed<{ value: number; label: string }[]>(() =>
  formStages.value.map((s) => ({ value: s.id, label: s.name })),
)
const currencySelectOptions = CURRENCIES.map((c) => ({ value: c, label: c }))
const natureSelectOptions = computed<{ value: OpportunityNature; label: string }[]>(() => [
  { value: 'new', label: t('adminCustomers.oppNature_new') },
  { value: 'upsell', label: t('adminCustomers.oppNature_upsell') },
])
const natureFilterOptions = computed<{ value: OpportunityNature | null; label: string }[]>(() => [
  { value: null, label: t('adminCustomers.oppNatureAll') },
  ...natureSelectOptions.value,
])
const contactSelectOptions = computed<{ value: number | null; label: string }[]>(() => [
  { value: null, label: t('adminCustomers.oppNoContact') },
  ...props.customer.contacts.map((ct) => ({
    value: ct.id,
    label: `${ct.lastName} ${ct.firstName}`.trim() || ct.email || '',
  })),
])
const productSelectOptions = computed<{ value: number | null; label: string }[]>(() => [
  { value: null, label: t('adminCustomers.oppCustomLine') },
  ...productsStore.products.map((p) => ({ value: p.id, label: p.name })),
])
const effortTypeSelectOptions = computed<{ value: EffortType; label: string }[]>(() => [
  { value: 'development', label: t('adminCustomers.oppEffortTypeDev') },
  { value: 'pm', label: t('adminCustomers.oppEffortTypePm') },
])
const effortUnitSelectOptions = computed<{ value: EffortUnit; label: string }[]>(() => [
  { value: 'day', label: t('adminCustomers.oppEffortUnitDay') },
  { value: 'hour', label: t('adminCustomers.oppEffortUnitHour') },
])

/** Stage options for the list-view row select of a given opportunity. */
function stageSelectOptions(typeId: number): { value: number; label: string }[] {
  return stagesForType(typeId).map((s) => ({ value: s.id, label: s.name }))
}
</script>

<template>
  <div class="opp-panel">
    <p v-if="!hasTypes" class="state">{{ t('adminCustomers.oppNoTypes') }}</p>

    <template v-else>
      <div v-if="!showForm" class="opp-head">
        <div class="view-toggle" role="tablist">
          <button type="button" :class="{ 'is-active': view === 'list' }" @click="view = 'list'">
            {{ t('adminCustomers.oppViewList') }}
          </button>
          <button type="button" :class="{ 'is-active': view === 'kanban' }" @click="view = 'kanban'">
            {{ t('adminCustomers.oppViewKanban') }}
          </button>
        </div>

        <div class="opp-head-right">
          <label v-if="view === 'kanban'" class="pipeline-select">
            <span>{{ t('adminCustomers.oppNature') }}</span>
            <AppSelect v-model="natureFilter" :options="natureFilterOptions" />
          </label>
          <label v-if="view === 'kanban'" class="pipeline-select">
            <span>{{ t('adminCustomers.oppPipeline') }}</span>
            <AppSelect v-model="selectedTypeId" :options="pipelineSelectOptions" />
          </label>
          <button type="button" class="btn-new" @click="openNew()">
            {{ '+ ' + t('adminCustomers.oppAdd') }}
          </button>
        </div>
      </div>

      <!-- ── Shared create / edit form ──────────────────────────────── -->
      <form v-if="showForm" class="opp-form" @submit.prevent="onSubmit">
        <h4>{{ null === editingId ? t('adminCustomers.oppAdd') : t('adminCustomers.oppEdit') }}</h4>
        <div class="opp-form-grid">
          <label class="field">
            <span>{{ t('adminCustomers.oppTitle') }}</span>
            <input v-model="form.title" type="text" maxlength="255" />
          </label>
          <label class="field">
            <span>{{ t('adminCustomers.oppQuoteNumber') }}</span>
            <input v-model="form.quoteNumber" type="text" maxlength="64" />
          </label>
          <label class="field">
            <span>{{ t('adminCustomers.oppType') }}</span>
            <AppSelect v-model="form.typeId" :options="typeSelectOptions" :disabled="null !== editingId" />
          </label>
          <label class="field">
            <span>{{ t('adminCustomers.oppStage') }}</span>
            <AppSelect v-model="form.stageId" :options="formStageSelectOptions" />
          </label>
          <label class="field">
            <span>{{ t('adminCustomers.oppNature') }}</span>
            <AppSelect v-model="form.nature" :options="natureSelectOptions" />
          </label>
          <label class="field">
            <span>{{ t('adminCustomers.oppCurrency') }}</span>
            <AppSelect v-model="form.currency" :options="currencySelectOptions" />
          </label>
          <label class="field">
            <span>{{ t('adminCustomers.oppValue') }}</span>
            <input v-if="!hasLines" v-model="form.value" type="text" inputmode="decimal" />
            <input v-else type="text" :value="formatMoney(String(lineItemsTotal.toFixed(2)), form.currency)" disabled />
          </label>
          <label class="field">
            <span>{{ t('adminCustomers.oppExpectedClose') }}</span>
            <input v-model="form.expectedCloseDate" type="date" />
          </label>
          <label class="field">
            <span>{{ t('adminCustomers.oppContact') }}</span>
            <AppSelect v-model="form.contactId" :options="contactSelectOptions" />
          </label>
          <label class="field field--wide">
            <span>{{ t('adminCustomers.notes') }}</span>
            <textarea v-model="form.notes" rows="2" />
          </label>
        </div>

        <!-- ── Line items ───────────────────────────────────────────── -->
        <div class="line-items">
          <div class="line-items-head">
            <span class="line-items-title">{{ t('adminCustomers.oppLineItems') }}</span>
            <button type="button" class="btn-line-add" @click="addLine">{{ t('adminCustomers.oppLineItemAdd') }}</button>
          </div>

          <div v-if="hasLines" class="line-rows">
            <div class="line-row line-row--head">
              <span>{{ t('adminCustomers.oppProduct') }}</span>
              <span>{{ t('adminCustomers.oppQuantity') }}</span>
              <span>{{ t('adminCustomers.oppUnitPrice') }}</span>
              <span class="ta-right">{{ t('adminCustomers.oppLineTotal') }}</span>
              <span></span>
            </div>
            <div v-for="(li, i) in form.lineItems" :key="i" class="line-row">
              <div class="line-product">
                <AppSelect v-model="li.productId" :options="productSelectOptions" @change="onPickProduct(li)" />
                <!-- The name input is only for custom lines; for a catalogue
                     product the select already shows the name. -->
                <input
                  v-if="null === li.productId"
                  v-model="li.productName"
                  type="text"
                  maxlength="255"
                  :placeholder="t('adminCustomers.oppProduct')"
                />
              </div>
              <input v-model="li.quantity" type="text" inputmode="decimal" class="line-num" />
              <input v-model="li.unitPrice" type="text" inputmode="decimal" class="line-num" />
              <span class="line-total ta-right">{{ formatMoney(String(lineTotal(li).toFixed(2)), form.currency) }}</span>
              <button type="button" class="btn-icon btn-icon--danger" :title="t('admin.delete')" :aria-label="t('admin.delete')" @click="removeLine(i)">
                <IconDelete />
              </button>
            </div>
            <div class="line-row line-row--total">
              <span class="line-total-label">{{ t('adminCustomers.oppLineItemsTotal') }}</span>
              <strong>{{ formatMoney(String(lineItemsTotal.toFixed(2)), form.currency) }}</strong>
            </div>
            <p class="line-note">{{ t('adminCustomers.oppValueFromLines') }}</p>
          </div>
        </div>

        <!-- ── Preliminary effort estimate ──────────────────────────── -->
        <div class="effort">
          <div class="effort-head">
            <span class="effort-title">{{ t('adminCustomers.oppEffort') }}</span>
            <button type="button" class="btn-line-add" @click="addEstimate">{{ t('adminCustomers.oppEffortAdd') }}</button>
          </div>

          <p v-if="!hasEstimates" class="effort-hint">{{ t('adminCustomers.oppEffortEmpty') }}</p>

          <div v-else class="line-rows">
            <div class="effort-row effort-row--head">
              <span>{{ t('adminCustomers.oppEffortName') }}</span>
              <span>{{ t('adminCustomers.oppEffortType') }}</span>
              <span class="ta-right">{{ t('adminCustomers.oppEffortAmount') }}</span>
              <span>{{ t('adminCustomers.oppEffortUnit') }}</span>
              <span class="ta-right">{{ t('adminCustomers.oppEffortInDays') }}</span>
              <span></span>
            </div>
            <div v-for="(ee, i) in form.effortEstimates" :key="i" class="effort-row">
              <input v-model="ee.name" type="text" maxlength="255" :placeholder="t('adminCustomers.oppEffortName')" />
              <AppSelect v-model="ee.effortType" :options="effortTypeSelectOptions" compact />
              <input v-model="ee.amount" type="text" inputmode="decimal" class="line-num" />
              <AppSelect v-model="ee.unit" :options="effortUnitSelectOptions" compact />
              <span class="effort-days ta-right">{{ formatDays(estimateDays(ee)) }}</span>
              <button type="button" class="btn-icon btn-icon--danger" :title="t('admin.delete')" :aria-label="t('admin.delete')" @click="removeEstimate(i)">
                <IconDelete />
              </button>
            </div>
            <div class="effort-row effort-row--total">
              <span class="line-total-label">{{ t('adminCustomers.oppEffortTotals') }}</span>
              <strong>{{ t('adminCustomers.oppEffortTotalLine', { dev: formatDays(effortTotalDays('development')), pm: formatDays(effortTotalDays('pm')) }) }}</strong>
            </div>
            <p class="line-note">{{ t('adminCustomers.oppEffortConversionNote', { hours: HOURS_PER_DAY }) }}</p>
          </div>
        </div>

        <!-- ── Offer documents (PDF) ────────────────────────────────── -->
        <div class="documents">
          <div class="documents-head">
            <span class="documents-title">{{ t('adminCustomers.oppDocuments') }}</span>
          </div>

          <!-- Uploads need an existing opportunity id, so only after save. -->
          <p v-if="null === editingId" class="doc-hint">{{ t('adminCustomers.oppDocSaveFirst') }}</p>

          <template v-else>
            <ul v-if="editingOpportunity && editingOpportunity.documents.length" class="doc-list">
              <li v-for="d in editingOpportunity.documents" :key="d.id" class="doc-row">
                <a :href="d.url" target="_blank" rel="noopener" class="doc-link">📄 {{ d.originalName }}</a>
                <span class="doc-size">{{ formatFileSize(d.size) }}</span>
                <button type="button" class="btn-icon btn-icon--danger" :title="t('admin.delete')" :aria-label="t('admin.delete')" @click="onDeleteDocument(d.id)">
                  <IconDelete />
                </button>
              </li>
            </ul>
            <p v-else class="doc-hint">{{ t('adminCustomers.oppDocEmpty') }}</p>

            <label class="doc-upload">
              <input type="file" accept="application/pdf" :disabled="uploading" @change="onUploadDocument" />
              <span>{{ uploading ? t('adminCustomers.oppDocUploading') : t('adminCustomers.oppDocUpload') }}</span>
            </label>
            <p v-if="docError" class="msg msg--error">{{ docError }}</p>
          </template>
        </div>

        <!-- ── Linked activities ────────────────────────────────────── -->
        <div v-if="null !== editingId" class="opp-activities">
          <span class="opp-activities-title">{{ t('adminCustomers.tabTimeline') }}</span>
          <ActivityList :customer="customer" :opportunity-id="editingId" />
        </div>

        <p v-if="formError" class="msg msg--error">{{ formError }}</p>

        <div class="form-actions">
          <button type="submit" class="btn-submit" :disabled="saving">
            {{ saving ? t('admin.saving') : null === editingId ? t('adminCustomers.oppAddButton') : t('admin.save') }}
          </button>
          <button type="button" class="btn-ghost" @click="closeForm">{{ t('adminUsers.cancel') }}</button>
        </div>
      </form>

      <!-- ── List view (hidden while the form is open) ──────────────── -->
      <template v-if="!showForm && view === 'list'">
        <p v-if="opportunities.length === 0" class="state">{{ t('adminCustomers.contactsEmpty') }}</p>
        <div v-else class="opp-table-wrap">
          <table class="opp-table">
            <thead>
              <tr>
                <th>{{ t('adminCustomers.oppTitle') }}</th>
                <th>{{ t('adminCustomers.oppQuoteNumber') }}</th>
                <th>{{ t('adminCustomers.oppNature') }}</th>
                <th>{{ t('adminCustomers.oppType') }}</th>
                <th>{{ t('adminCustomers.oppStage') }}</th>
                <th class="ta-right">{{ t('adminCustomers.oppValue') }}</th>
                <th>{{ t('adminCustomers.oppOwner') }}</th>
                <th>{{ t('adminCustomers.oppContact') }}</th>
                <th>{{ t('adminCustomers.oppExpectedClose') }}</th>
                <th>{{ t('adminCustomers.oppDocuments') }}</th>
                <th class="col-actions"><span class="sr-only">{{ t('adminCustomers.colActions') }}</span></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="o in opportunities" :key="o.id" class="opp-tr" :class="{ 'is-editing': editingId === o.id }">
                <td class="cell-title">{{ o.title }}</td>
                <td>
                  <span v-if="o.quoteNumber" class="quote-badge">{{ o.quoteNumber }}</span>
                  <span v-else>—</span>
                </td>
                <td>
                  <span class="nature-badge" :class="`nature-badge--${o.nature}`">
                    {{ t('adminCustomers.oppNature_' + o.nature) }}
                  </span>
                </td>
                <td>{{ o.typeName }}</td>
                <td>
                  <div class="stage-select" :class="`outcome-${o.stageOutcome}`">
                    <AppSelect
                      :model-value="o.stageId"
                      :options="stageSelectOptions(o.typeId)"
                      compact
                      @change="(v) => onStageChange(o, v)"
                    />
                  </div>
                </td>
                <td class="ta-right cell-value">{{ formatMoney(o.value, o.currency) }}</td>
                <td>{{ o.ownerName || '—' }}</td>
                <td>{{ o.contactName || '—' }}</td>
                <td class="cell-date">{{ o.expectedCloseDate || '—' }}</td>
                <td class="cell-docs">
                  <template v-if="o.documents.length">
                    <a
                      v-for="d in o.documents"
                      :key="d.id"
                      :href="d.url"
                      target="_blank"
                      rel="noopener"
                      class="doc-indicator"
                      :title="d.originalName"
                    >📄 {{ d.originalName }}</a>
                  </template>
                  <span v-else>—</span>
                </td>
                <td class="col-actions">
                  <div class="opp-row-actions">
                    <button type="button" class="btn-icon" :title="t('admin.edit')" :aria-label="t('admin.edit')" @click="openEdit(o)">
                      <IconEdit />
                    </button>
                    <button type="button" class="btn-icon btn-icon--danger" :title="t('admin.delete')" :aria-label="t('admin.delete')" @click="onDelete(o)">
                      <IconDelete />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>

      <!-- ── Kanban view (hidden while the form is open) ────────────── -->
      <template v-else-if="!showForm">
        <p v-if="null === selectedType" class="state">{{ t('adminCustomers.oppNoTypes') }}</p>
        <p v-else-if="selectedType.stages.length === 0" class="state">{{ t('adminCustomers.oppNoStages') }}</p>
        <template v-else>
          <p class="drag-hint">{{ t('adminCustomers.oppDragHint') }}</p>
          <div class="board">
            <div
              v-for="stage in selectedType.stages"
              :key="stage.id"
              class="column"
              :class="[`outcome-${stage.outcome}`, { 'is-over': overStageId === stage.id }]"
              @dragover="onDragOver(stage.id, $event)"
              @drop="onDrop(stage.id)"
            >
              <div class="column-head">
                <span class="column-title">{{ stage.name }}</span>
                <span class="column-count">{{ columnOpportunities(stage.id).length }}</span>
              </div>

              <div class="column-body">
                <article
                  v-for="o in columnOpportunities(stage.id)"
                  :key="o.id"
                  class="card"
                  :class="{ 'is-dragging': dragId === o.id }"
                  draggable="true"
                  @dragstart="onDragStart(o, $event)"
                  @dragend="onDragEnd"
                >
                  <div class="card-top">
                    <span class="card-title">{{ o.title }}</span>
                    <div class="card-actions">
                      <button type="button" class="btn-icon" :title="t('admin.edit')" :aria-label="t('admin.edit')" @click="openEdit(o)">
                        <IconEdit />
                      </button>
                      <button type="button" class="btn-icon btn-icon--danger" :title="t('admin.delete')" :aria-label="t('admin.delete')" @click="onDelete(o)">
                        <IconDelete />
                      </button>
                    </div>
                  </div>

                  <span class="nature-badge" :class="`nature-badge--${o.nature}`">
                    {{ t('adminCustomers.oppNature_' + o.nature) }}
                  </span>

                  <p class="card-value">{{ formatMoney(o.value, o.currency) }}</p>

                  <dl class="card-meta">
                    <div v-if="o.ownerName"><dt>{{ t('adminCustomers.oppOwner') }}</dt><dd>{{ o.ownerName }}</dd></div>
                    <div v-if="o.contactName"><dt>{{ t('adminCustomers.oppContact') }}</dt><dd>{{ o.contactName }}</dd></div>
                    <div v-if="o.lineItems.length"><dt>{{ t('adminCustomers.oppLineItems') }}</dt><dd>{{ o.lineItems.length }}</dd></div>
                    <div v-if="o.effortEstimates.length">
                      <dt>{{ t('adminCustomers.oppEffortShort') }}</dt>
                      <dd>{{ t('adminCustomers.oppEffortTotalLine', { dev: formatDays(Number(o.effortTotalDevelopmentDays)), pm: formatDays(Number(o.effortTotalPmDays)) }) }}</dd>
                    </div>
                    <div v-if="o.expectedCloseDate"><dt>{{ t('adminCustomers.oppExpectedClose') }}</dt><dd>{{ o.expectedCloseDate }}</dd></div>
                  </dl>

                  <p v-if="o.notes" class="card-notes">{{ o.notes }}</p>

                  <button type="button" class="history-toggle" @click="toggleHistory(o.id)">
                    {{ t('adminCustomers.oppHistory') }} ({{ o.stageChanges.length }})
                  </button>
                  <ul v-if="expandedId === o.id" class="history">
                    <li v-for="ch in o.stageChanges" :key="ch.id">
                      <span class="history-date">{{ shortDate(ch.changedAt) }}</span>
                      <span class="history-text">
                        <template v-if="ch.fromStageName">{{ ch.fromStageName }} → </template>
                        <strong>{{ ch.toStageName }}</strong>
                        <template v-if="ch.changedByName"> · {{ ch.changedByName }}</template>
                      </span>
                    </li>
                  </ul>
                </article>

                <p v-if="columnOpportunities(stage.id).length === 0" class="column-empty">—</p>
              </div>
            </div>
          </div>
        </template>
      </template>
    </template>
  </div>
</template>

<style scoped>
.opp-head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.1rem;
  flex-wrap: wrap;
}

.opp-head-right {
  display: flex;
  align-items: flex-end;
  gap: 0.8rem;
  flex-wrap: wrap;
}

.view-toggle {
  display: inline-flex;
  border: 1px solid #d4dae6;
  border-radius: 0.55rem;
  overflow: hidden;
}

.view-toggle button {
  padding: 0.5rem 1.1rem;
  background: #fff;
  border: none;
  color: #545f71;
  font-size: 0.88rem;
  font-weight: 700;
  cursor: pointer;
}

.view-toggle button.is-active {
  background: var(--login-primary, #ed2044);
  color: #fff;
}

.pipeline-select {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}

.pipeline-select span {
  color: var(--login-secondary, #0c1c40);
  font-size: 0.85rem;
  font-weight: 700;
}

.pipeline-select select {
  min-width: 14rem;
  padding: 0.5rem 0.7rem;
  background: #f7f8fb;
  border: 1px solid #d4dae6;
  border-radius: 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-family: inherit;
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
.opp-form {
  margin-bottom: 1.6rem;
  padding: 1.1rem 1.2rem 1.3rem;
  background: #fff;
  border: 1px dashed #d4dae6;
  border-radius: 0.7rem;
}

.opp-form h4 {
  margin: 0 0 0.9rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-weight: 700;
}

.opp-form-grid {
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
.field select,
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
.field select:focus,
.field textarea:focus {
  outline: 2px solid var(--login-primary, #ed2044);
  outline-offset: -1px;
  background: #fff;
}

.field input:disabled,
.field select:disabled {
  opacity: 0.6;
}

/* ── Line items editor ────────────────────────────────────────────── */
.line-items {
  margin-bottom: 1rem;
  padding-top: 0.6rem;
  border-top: 1px solid #eef1f6;
}

.line-items-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.6rem;
}

.line-items-title {
  color: var(--login-secondary, #0c1c40);
  font-size: 0.88rem;
  font-weight: 700;
}

.btn-line-add {
  padding: 0.35rem 0.8rem;
  background: #fff;
  border: 1px solid #d4dae6;
  border-radius: 0.45rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
}

.btn-line-add:hover {
  border-color: var(--login-secondary, #0c1c40);
}

.line-rows {
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
}

.line-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 5rem 7rem 8rem 2rem;
  gap: 0.5rem;
  align-items: center;
}

.line-row--head {
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: #8b94a6;
}

.line-product {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  min-width: 0;
}

.line-product select,
.line-product input,
.line-num {
  padding: 0.45rem 0.55rem;
  background: #f7f8fb;
  border: 1px solid #d4dae6;
  border-radius: 0.45rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.9rem;
  font-family: inherit;
}

.line-num {
  text-align: right;
}

.line-total {
  font-size: 0.9rem;
  font-weight: 700;
  color: var(--login-secondary, #0c1c40);
}

.ta-right {
  text-align: right;
}

.line-row--total {
  grid-template-columns: 1fr auto;
  padding-top: 0.5rem;
  border-top: 1px dashed #d4dae6;
}

.line-total-label {
  color: #545f71;
  font-size: 0.85rem;
  font-weight: 700;
}

.line-row--total strong {
  color: var(--login-primary, #ed2044);
  font-size: 1rem;
}

.line-note {
  margin: 0.4rem 0 0;
  color: #8b94a6;
  font-size: 0.78rem;
}

/* ── Preliminary effort estimate ──────────────────────────────────── */
.effort {
  margin-bottom: 1rem;
  padding-top: 0.6rem;
  border-top: 1px solid #eef1f6;
}

.effort-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.6rem;
}

.effort-title {
  color: var(--login-secondary, #0c1c40);
  font-size: 0.88rem;
  font-weight: 700;
}

.effort-hint {
  margin: 0;
  color: #8b94a6;
  font-size: 0.8rem;
}

.effort-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 9rem 5rem 6rem 6rem 2rem;
  gap: 0.5rem;
  align-items: center;
}

.effort-row--head {
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: #8b94a6;
}

.effort-row input {
  padding: 0.45rem 0.55rem;
  background: #f7f8fb;
  border: 1px solid #d4dae6;
  border-radius: 0.45rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.9rem;
  font-family: inherit;
}

.effort-days {
  font-size: 0.9rem;
  font-weight: 700;
  color: var(--login-secondary, #0c1c40);
}

.effort-row--total {
  grid-template-columns: auto 1fr;
  justify-content: start;
  padding-top: 0.5rem;
  border-top: 1px dashed #d4dae6;
}

.effort-row--total strong {
  color: var(--login-primary, #ed2044);
  font-size: 0.95rem;
  justify-self: end;
}

/* ── Documents ────────────────────────────────────────────────────── */
.documents {
  margin-bottom: 1rem;
  padding-top: 0.6rem;
  border-top: 1px solid #eef1f6;
}

/* ── Linked activities ────────────────────────────────────────────── */
.opp-activities {
  margin-bottom: 1rem;
  padding-top: 0.6rem;
  border-top: 1px solid #eef1f6;
}

.opp-activities-title {
  display: block;
  margin-bottom: 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.88rem;
  font-weight: 700;
}

.documents-head {
  margin-bottom: 0.5rem;
}

.documents-title {
  color: var(--login-secondary, #0c1c40);
  font-size: 0.88rem;
  font-weight: 700;
}

.doc-hint {
  margin: 0 0 0.6rem;
  color: #8b94a6;
  font-size: 0.8rem;
}

.doc-list {
  margin: 0 0 0.7rem;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.doc-row {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

.doc-link {
  color: var(--login-primary, #ed2044);
  font-size: 0.88rem;
  font-weight: 700;
  text-decoration: none;
  word-break: break-all;
}

.doc-link:hover {
  text-decoration: underline;
}

.doc-size {
  color: #8b94a6;
  font-size: 0.76rem;
  margin-left: auto;
}

.doc-upload {
  display: inline-flex;
  flex-direction: column;
  gap: 0.3rem;
  font-size: 0.82rem;
  font-weight: 700;
  color: var(--login-secondary, #0c1c40);
}

.doc-upload input[type='file'] {
  font-size: 0.82rem;
}

/* ── Quote number + document indicator in the list ────────────────── */
.cell-title-row {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.quote-badge {
  padding: 0.05rem 0.45rem;
  background: #eef1f6;
  border-radius: 0.4rem;
  color: #545f71;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.02em;
}

/* ── New / upsell nature badge ──────────────────────────────────────── */
.nature-badge {
  display: inline-block;
  padding: 0.1rem 0.5rem;
  border-radius: 0.4rem;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  white-space: nowrap;
}

.nature-badge--new {
  background: #e7eefc;
  color: #2b59c3;
}

.nature-badge--upsell {
  background: #f3e8fb;
  color: #7a3aa8;
}

.card .nature-badge {
  margin-top: 0.4rem;
}

.doc-indicator {
  color: var(--login-primary, #ed2044);
  font-size: 0.78rem;
  font-weight: 700;
  text-decoration: none;
  white-space: nowrap;
}

.doc-indicator:hover {
  text-decoration: underline;
}

.cell-docs {
  max-width: 12rem;
}

.cell-docs .doc-indicator {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  margin-bottom: 0.15rem;
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

/* ── List table ───────────────────────────────────────────────────── */
.opp-table-wrap {
  overflow-x: auto;
}

.opp-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}

.opp-table thead th {
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

.opp-tr > td {
  padding: 0.6rem 0.85rem;
  color: #545f71;
  vertical-align: middle;
  border-bottom: 1px solid #eef1f6;
}

.opp-tr:hover > td {
  background: #f7f8fb;
}

.opp-tr.is-editing > td {
  background: #fef6f7;
}

.cell-title {
  color: var(--login-secondary, #0c1c40);
  font-weight: 700;
}

.cell-value {
  white-space: nowrap;
  font-weight: 700;
  color: var(--login-secondary, #0c1c40);
}

.cell-date {
  white-space: nowrap;
}

.stage-select {
  display: inline-flex;
  max-width: 11rem;
}

.stage-select :deep(.app-select) {
  width: 100%;
}

.stage-select.outcome-won :deep(.app-select-toggle) {
  border-color: #1c7a45;
  color: #1c7a45;
}

.stage-select.outcome-lost :deep(.app-select-toggle) {
  border-color: #b3122e;
  color: #b3122e;
}

/* ── Board ────────────────────────────────────────────────────────── */
.drag-hint {
  margin: 0 0 0.7rem;
  color: #8b94a6;
  font-size: 0.82rem;
}

.board {
  display: flex;
  gap: 0.9rem;
  overflow-x: auto;
  padding-bottom: 0.6rem;
  align-items: flex-start;
}

.column {
  flex: 0 0 16rem;
  width: 16rem;
  background: #f4f6fa;
  border: 1px solid #e3e7ee;
  border-top: 3px solid #9aa6bd;
  border-radius: 0.6rem;
  padding: 0.7rem;
}

.column.outcome-won {
  border-top-color: #1c7a45;
}

.column.outcome-lost {
  border-top-color: #b3122e;
}

.column.is-over {
  background: #fef6f7;
  border-color: var(--login-primary, #ed2044);
}

.column-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.6rem;
}

.column-title {
  color: var(--login-secondary, #0c1c40);
  font-size: 0.85rem;
  font-weight: 700;
}

.column-count {
  min-width: 1.4rem;
  padding: 0.05rem 0.4rem;
  background: #e3e7ee;
  border-radius: 0.7rem;
  color: #545f71;
  font-size: 0.75rem;
  font-weight: 700;
  text-align: center;
}

.column-body {
  display: flex;
  flex-direction: column;
  gap: 0.55rem;
  min-height: 2.5rem;
}

.column-empty {
  margin: 0;
  padding: 0.5rem 0;
  color: #b6bdca;
  font-size: 0.9rem;
  text-align: center;
}

/* ── Card ─────────────────────────────────────────────────────────── */
.card {
  background: #fff;
  border: 1px solid #e3e7ee;
  border-radius: 0.55rem;
  padding: 0.65rem 0.7rem;
  cursor: grab;
  box-shadow: 0 1px 2px rgba(12, 28, 64, 0.05);
}

.card.is-dragging {
  opacity: 0.5;
}

.card-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.4rem;
}

.card-title {
  color: var(--login-secondary, #0c1c40);
  font-size: 0.92rem;
  font-weight: 700;
  word-break: break-word;
}

.card-actions {
  display: flex;
  gap: 0.3rem;
  flex-shrink: 0;
}

.card-value {
  margin: 0.35rem 0 0;
  color: var(--login-primary, #ed2044);
  font-size: 0.9rem;
  font-weight: 700;
}

.card-meta {
  margin: 0.45rem 0 0;
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}

.card-meta > div {
  display: flex;
  gap: 0.35rem;
  font-size: 0.78rem;
}

.card-meta dt {
  margin: 0;
  color: #8b94a6;
  font-weight: 700;
}

.card-meta dd {
  margin: 0;
  color: #545f71;
  word-break: break-word;
}

.card-notes {
  margin: 0.45rem 0 0;
  color: #545f71;
  font-size: 0.8rem;
  word-break: break-word;
}

.history-toggle {
  margin-top: 0.5rem;
  padding: 0;
  background: none;
  border: none;
  color: var(--login-primary, #ed2044);
  font-size: 0.76rem;
  font-weight: 700;
  cursor: pointer;
}

.history {
  margin: 0.4rem 0 0;
  padding: 0.4rem 0 0;
  list-style: none;
  border-top: 1px solid #eef1f6;
}

.history li {
  display: flex;
  gap: 0.4rem;
  font-size: 0.74rem;
  color: #545f71;
  padding: 0.12rem 0;
}

.history-date {
  color: #8b94a6;
  flex-shrink: 0;
}

.btn-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.7rem;
  height: 1.7rem;
  padding: 0;
  background: #fff;
  border: 1px solid #d4dae6;
  border-radius: 0.4rem;
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

.col-actions {
  text-align: right;
  white-space: nowrap;
}

.opp-row-actions {
  display: flex;
  gap: 0.5rem;
  justify-content: flex-end;
  flex-shrink: 0;
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
  .opp-form-grid {
    grid-template-columns: 1fr;
  }

  .line-row,
  .line-row--head {
    grid-template-columns: 1fr 4rem 6rem;
    grid-auto-rows: auto;
  }

  .line-total,
  .line-row .btn-icon--danger {
    grid-column: span 3;
    justify-self: end;
  }

  .effort-row,
  .effort-row--head {
    grid-template-columns: 1fr 7rem 4rem;
    grid-auto-rows: auto;
  }

  .effort-days,
  .effort-row .btn-icon--danger {
    grid-column: span 3;
    justify-self: end;
  }
}
</style>

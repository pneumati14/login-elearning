<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import {
  useProductsStore,
  emptyProductFields,
  productStatus,
  CURRENCIES,
  type Currency,
  type Product,
  type ProductFields,
  type ProductStatus,
} from '@/stores/products'
import { useProductCategoriesStore } from '@/stores/productCategories'
import IconEdit from '@/components/icons/IconEdit.vue'
import IconDelete from '@/components/icons/IconDelete.vue'
import IconFilter from '@/components/icons/IconFilter.vue'
import AppSelect from '@/components/AppSelect.vue'

const { t } = useI18n()
const store = useProductsStore()
const { products, loading, error } = storeToRefs(store)

const categoriesStore = useProductCategoriesStore()
const { categories } = storeToRefs(categoriesStore)

const search = ref('')
const categoryFilter = ref<number | null>(null)
const subcategoryFilter = ref<number | null>(null)
const validityDate = ref('')

// The slide-in filter drawer (top-right funnel icon).
const showFilters = ref(false)

// Options for the downward-opening AppSelect (labels unchanged).
const currencyOptions: { value: Currency; label: string }[] = CURRENCIES.map((c) => ({
  value: c,
  label: c,
}))

// ── Category selects (cascading) ─────────────────────────────────────
const categorySelectOptions = computed<{ value: number | null; label: string }[]>(() => [
  { value: null, label: t('adminProducts.categoryPlaceholder') },
  ...categories.value.map((c) => ({ value: c.id, label: c.name })),
])

const subcategorySelectOptions = computed<{ value: number | null; label: string }[]>(() => {
  const category = categories.value.find((c) => c.id === form.categoryId)
  return [
    { value: null, label: t('adminProducts.subcategoryNone') },
    ...(category?.subcategories ?? []).map((s) => ({ value: s.id, label: s.name })),
  ]
})

// Changing the category drops a now-invalid sub-category.
function onCategoryPicked(): void {
  const category = categories.value.find((c) => c.id === form.categoryId)
  if (!category || !category.subcategories.some((s) => s.id === form.subcategoryId)) {
    form.subcategoryId = null
  }
}

const categoryFilterOptions = computed<{ value: number | null; label: string }[]>(() => [
  { value: null, label: t('adminProducts.allCategories') },
  ...categories.value.map((c) => ({ value: c.id, label: c.name })),
])

// Sub-category filter cascades from the chosen category filter.
const subcategoryFilterOptions = computed<{ value: number | null; label: string }[]>(() => {
  const category = categories.value.find((c) => c.id === categoryFilter.value)
  return [
    { value: null, label: t('adminProducts.allSubcategories') },
    ...(category?.subcategories ?? []).map((s) => ({ value: s.id, label: s.name })),
  ]
})

// Picking another category invalidates the sub-category filter.
function onCategoryFilterPicked(): void {
  subcategoryFilter.value = null
}

// A product is valid on a date when the date falls inside its validity
// window (open-ended on either side).
function isValidOn(p: Product, date: string): boolean {
  if (p.validFrom && date < p.validFrom) return false
  if (p.validUntil && date > p.validUntil) return false
  return true
}

const activeFilterCount = computed(() => {
  let n = 0
  if ('' !== validityDate.value) n++
  if (null !== categoryFilter.value) n++
  if (null !== subcategoryFilter.value) n++
  if ('' !== search.value.trim()) n++
  return n
})

function clearFilters(): void {
  validityDate.value = ''
  categoryFilter.value = null
  subcategoryFilter.value = null
  search.value = ''
}

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  return products.value.filter((p) => {
    if ('' !== validityDate.value && !isValidOn(p, validityDate.value)) return false
    if (null !== categoryFilter.value && p.categoryId !== categoryFilter.value) return false
    if (null !== subcategoryFilter.value && p.subcategoryId !== subcategoryFilter.value) return false
    if ('' === q) return true
    return p.name.toLowerCase().includes(q) || (p.sku ?? '').toLowerCase().includes(q)
  })
})

onMounted(() => {
  store.fetchProducts()
  categoriesStore.fetchCategories()
})

// ── Shared create / edit form ─────────────────────────────────────────
const showForm = ref(false)
const editingId = ref<number | null>(null)
const form = reactive<ProductFields>(emptyProductFields())
const saving = ref(false)
const formError = ref<string | null>(null)

function openNew(): void {
  editingId.value = null
  Object.assign(form, emptyProductFields())
  formError.value = null
  showForm.value = true
}

function openEdit(p: Product): void {
  editingId.value = p.id
  form.name = p.name
  form.sku = p.sku
  form.categoryId = p.categoryId
  form.subcategoryId = p.subcategoryId
  form.description = p.description
  form.unitPrice = p.unitPrice
  form.currency = p.currency
  form.isActive = p.isActive
  form.validFrom = p.validFrom
  form.validUntil = p.validUntil
  formError.value = null
  showForm.value = true
}

function closeForm(): void {
  showForm.value = false
  editingId.value = null
}

async function onSubmit(): Promise<void> {
  if ('' === form.name.trim()) {
    formError.value = t('adminProducts.nameRequired')
    return
  }
  if (null === form.categoryId) {
    formError.value = t('adminProducts.categoryRequired')
    return
  }
  saving.value = true
  const payload: ProductFields = { ...form, unitPrice: '' === form.unitPrice ? null : form.unitPrice }
  const result =
    null === editingId.value
      ? await store.createProduct(payload)
      : await store.updateProduct(editingId.value, payload)
  saving.value = false

  if (result.ok) {
    closeForm()
  } else {
    formError.value = result.error ?? t('admin.saveFailed')
  }
}

/**
 * Save the unit price edited directly in the list, without opening the
 * form. Other fields are preserved. Using @change (not v-model) keeps the
 * input stable while typing and only persists on blur/Enter.
 */
async function saveInlinePrice(p: Product, event: Event): Promise<void> {
  const raw = (event.target as HTMLInputElement).value.trim()
  const newPrice = '' === raw ? null : raw
  if (newPrice === p.unitPrice) return
  const result = await store.updateProduct(p.id, {
    name: p.name,
    sku: p.sku,
    categoryId: p.categoryId,
    subcategoryId: p.subcategoryId,
    description: p.description,
    unitPrice: newPrice,
    currency: p.currency,
    isActive: p.isActive,
    validFrom: p.validFrom,
    validUntil: p.validUntil,
  })
  if (!result.ok) {
    window.alert(result.error ?? t('admin.saveFailed'))
    ;(event.target as HTMLInputElement).value = p.unitPrice ?? ''
  }
}

async function onDelete(p: Product): Promise<void> {
  if (!window.confirm(t('adminProducts.confirmDelete', { name: p.name }))) return
  const result = await store.deleteProduct(p.id)
  if (!result.ok) {
    window.alert(result.error ?? t('admin.deleteFailed'))
  } else if (editingId.value === p.id) {
    closeForm()
  }
}

function statusOf(p: Product): ProductStatus {
  return productStatus(p)
}

function validityLabel(p: Product): string {
  if (null === p.validFrom && null === p.validUntil) return t('adminProducts.validityOpen')
  return `${p.validFrom ?? '—'} → ${p.validUntil ?? '—'}`
}
</script>

<template>
  <section class="admin">
    <div class="container-lg">
      <div class="admin-head">
        <span class="eyebrow">{{ t('admin.eyebrow') }}</span>
        <h1>{{ t('nav.adminProducts') }}</h1>
        <p>{{ t('adminProducts.subtitle') }}</p>
      </div>

      <!-- ── Shared create / edit form ──────────────────────────────── -->
      <form v-if="showForm" class="pr-panel" @submit.prevent="onSubmit">
        <div class="pr-panel-head">
          <h2>{{ null === editingId ? t('adminProducts.newProduct') : t('adminProducts.editProduct') }}</h2>
          <button type="button" class="btn-ghost" @click="closeForm">{{ t('adminUsers.cancel') }}</button>
        </div>

        <div class="form-grid">
          <label class="field field--wide">
            <span>{{ t('adminProducts.name') }} *</span>
            <input v-model="form.name" type="text" required maxlength="255" />
          </label>
          <label class="field field--wide">
            <span>{{ t('adminProducts.sku') }}</span>
            <input v-model="form.sku" type="text" maxlength="64" />
          </label>
          <label class="field">
            <span>{{ t('adminProducts.category') }} *</span>
            <AppSelect
              v-model="form.categoryId"
              :options="categorySelectOptions"
              :placeholder="t('adminProducts.categoryPlaceholder')"
              @change="onCategoryPicked"
            />
          </label>
          <label class="field">
            <span>{{ t('adminProducts.subcategory') }}</span>
            <AppSelect
              v-model="form.subcategoryId"
              :options="subcategorySelectOptions"
              :placeholder="t('adminProducts.subcategoryNone')"
              :disabled="null === form.categoryId"
            />
          </label>
          <label class="field">
            <span>{{ t('adminProducts.unitPrice') }}</span>
            <input v-model="form.unitPrice" type="text" inputmode="decimal" />
          </label>
          <label class="field">
            <span>{{ t('adminProducts.currency') }}</span>
            <AppSelect v-model="form.currency" :options="currencyOptions" />
          </label>
          <label class="field">
            <span>{{ t('adminProducts.validFrom') }}</span>
            <input v-model="form.validFrom" type="date" />
          </label>
          <label class="field">
            <span>{{ t('adminProducts.validUntil') }}</span>
            <input v-model="form.validUntil" type="date" />
          </label>
          <label class="field field--wide">
            <span>{{ t('adminProducts.description') }}</span>
            <textarea v-model="form.description" rows="2" />
          </label>
          <label class="check-field field--wide">
            <input v-model="form.isActive" type="checkbox" />
            <span>{{ t('adminProducts.activeLabel') }}</span>
          </label>
        </div>

        <p v-if="formError" class="msg msg--error">{{ formError }}</p>

        <button type="submit" class="btn-submit" :disabled="saving">
          {{ saving ? t('admin.saving') : null === editingId ? t('adminProducts.create') : t('admin.save') }}
        </button>
      </form>

      <!-- ── Product list (hidden while the form is open) ───────────── -->
      <div v-if="!showForm" class="pr-panel">
        <div class="pr-list-head">
          <h2>{{ t('adminProducts.existing') }}</h2>
          <div class="pr-list-tools">
            <button
              type="button"
              class="btn-filter"
              :class="{ 'is-active': activeFilterCount > 0 }"
              :title="t('adminProducts.filters')"
              :aria-label="t('adminProducts.filters')"
              @click="showFilters = true"
            >
              <IconFilter />
              <span>{{ t('adminProducts.filters') }}</span>
              <span v-if="activeFilterCount > 0" class="filter-count">{{ activeFilterCount }}</span>
            </button>
            <button type="button" class="btn-submit btn-new" @click="openNew()">
              {{ '+ ' + t('adminProducts.newProduct') }}
            </button>
          </div>
        </div>

        <p v-if="loading" class="state">{{ t('adminProducts.loading') }}</p>

        <div v-else-if="error" class="state state--error">
          <strong>{{ t('adminProducts.loadError') }}</strong>
          <button type="button" class="btn-retry" @click="store.fetchProducts()">{{ t('common.retry') }}</button>
        </div>

        <p v-else-if="products.length === 0" class="state">{{ t('adminProducts.empty') }}</p>

        <p v-else-if="filtered.length === 0" class="state">{{ t('adminProducts.noMatches') }}</p>

        <div v-else class="pr-table-wrap">
          <table class="pr-table">
            <thead>
              <tr>
                <th>{{ t('adminProducts.name') }}</th>
                <th>{{ t('adminProducts.sku') }}</th>
                <th>{{ t('adminProducts.category') }}</th>
                <th class="col-price">{{ t('adminProducts.unitPrice') }}</th>
                <th>{{ t('adminProducts.colStatus') }}</th>
                <th>{{ t('adminProducts.colValidity') }}</th>
                <th class="col-actions"><span class="sr-only">{{ t('adminProducts.colActions') }}</span></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in filtered" :key="p.id" class="pr-tr" :class="{ 'is-inactive': statusOf(p) !== 'active', 'is-editing': editingId === p.id }">
                <td class="cell-name">
                  <span class="cell-name-title">{{ p.name }}</span>
                  <span v-if="p.description" class="cell-name-sub">{{ p.description }}</span>
                </td>
                <td>{{ p.sku || '—' }}</td>
                <td class="cell-category">
                  <template v-if="p.categoryName">
                    {{ p.categoryName }}<span v-if="p.subcategoryName" class="cell-sub"> · {{ p.subcategoryName }}</span>
                  </template>
                  <template v-else>—</template>
                </td>
                <td class="col-price">
                  <span class="price-edit">
                    <input
                      type="text"
                      inputmode="decimal"
                      class="price-input"
                      :value="p.unitPrice ?? ''"
                      :title="t('adminProducts.unitPrice')"
                      :aria-label="t('adminProducts.unitPrice')"
                      @change="saveInlinePrice(p, $event)"
                      @keydown.enter.prevent="($event.target as HTMLInputElement).blur()"
                    />
                    <span class="price-cur">{{ p.currency }}</span>
                  </span>
                </td>
                <td>
                  <span class="badge" :class="`badge--${statusOf(p)}`">{{ t('adminProducts.status_' + statusOf(p)) }}</span>
                </td>
                <td class="cell-validity">{{ validityLabel(p) }}</td>
                <td class="col-actions">
                  <div class="pr-row-actions">
                    <button type="button" class="btn-icon" :title="t('admin.edit')" :aria-label="t('admin.edit')" @click="openEdit(p)">
                      <IconEdit />
                    </button>
                    <button type="button" class="btn-icon btn-icon--danger" :title="t('admin.delete')" :aria-label="t('admin.delete')" @click="onDelete(p)">
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

    <!-- ── Slide-in filter drawer ──────────────────────────────────── -->
    <Transition name="drawer-fade">
      <div v-if="showFilters" class="drawer-overlay" @click.self="showFilters = false">
        <aside class="drawer" role="dialog" aria-modal="true">
          <div class="drawer-head">
            <h3>{{ t('adminProducts.filters') }}</h3>
            <button type="button" class="drawer-close" :aria-label="t('adminUsers.cancel')" @click="showFilters = false">×</button>
          </div>

          <div class="drawer-body">
            <label class="field">
              <span>{{ t('adminProducts.filterDate') }}</span>
              <input v-model="validityDate" type="date" />
              <small class="field-hint">{{ t('adminProducts.filterDateHint') }}</small>
            </label>

            <label class="field">
              <span>{{ t('adminProducts.category') }}</span>
              <AppSelect v-model="categoryFilter" :options="categoryFilterOptions" @change="onCategoryFilterPicked" />
            </label>

            <label class="field">
              <span>{{ t('adminProducts.subcategory') }}</span>
              <AppSelect
                v-model="subcategoryFilter"
                :options="subcategoryFilterOptions"
                :disabled="null === categoryFilter"
              />
            </label>

            <label class="field">
              <span>{{ t('adminProducts.filterProduct') }}</span>
              <input v-model="search" type="search" :placeholder="t('adminProducts.searchPlaceholder')" />
            </label>
          </div>

          <div class="drawer-foot">
            <span class="drawer-result">{{ t('adminProducts.filterResult', { count: filtered.length }) }}</span>
            <button type="button" class="btn-ghost" :disabled="activeFilterCount === 0" @click="clearFilters">
              {{ t('adminProducts.clearFilters') }}
            </button>
            <button type="button" class="btn-submit" @click="showFilters = false">
              {{ t('adminProducts.applyFilters') }}
            </button>
          </div>
        </aside>
      </div>
    </Transition>
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

.pr-panel {
  margin-bottom: 1.5rem;
  padding: 1.85rem;
  background: #fff;
  border-radius: 1.1rem;
  box-shadow: 0 12px 32px rgba(12, 28, 64, 0.08);
}

.pr-panel h2 {
  margin: 0 0 1.3rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.25rem;
  font-weight: 700;
}

.pr-list-head,
.pr-panel-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
}

.pr-list-head h2,
.pr-panel-head h2 {
  margin: 0;
}

.pr-panel-head {
  margin-bottom: 1.3rem;
}

.pr-list-tools {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  flex-wrap: wrap;
}

.btn-new {
  white-space: nowrap;
  padding: 0.5rem 1.1rem;
  font-size: 0.9rem;
}

.search {
  flex: 0 1 280px;
  padding: 0.5rem 0.75rem;
  background: #f7f8fb;
  border: 1px solid #d4dae6;
  border-radius: 0.55rem;
  font-size: 0.9rem;
}

.btn-filter {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.5rem 1rem;
  background: #fff;
  border: 1px solid #d4dae6;
  border-radius: 0.55rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.9rem;
  font-weight: 700;
  cursor: pointer;
  transition:
    border-color 0.15s,
    background 0.15s;
}

.btn-filter:hover {
  border-color: var(--login-secondary, #0c1c40);
}

.btn-filter.is-active {
  border-color: var(--login-primary, #ed2044);
  color: var(--login-primary, #ed2044);
}

.filter-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.25rem;
  height: 1.25rem;
  padding: 0 0.35rem;
  background: var(--login-primary, #ed2044);
  border-radius: 0.7rem;
  color: #fff;
  font-size: 0.72rem;
  font-weight: 700;
}

/* ── Filter drawer ─────────────────────────────────────────────────── */
.drawer-overlay {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: flex;
  justify-content: flex-end;
  background: rgba(12, 28, 64, 0.35);
}

.drawer {
  display: flex;
  flex-direction: column;
  width: min(380px, 100%);
  height: 100%;
  background: #fff;
  box-shadow: -16px 0 40px rgba(12, 28, 64, 0.18);
}

.drawer-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1.3rem 1.5rem;
  border-bottom: 1px solid #eef1f6;
}

.drawer-head h3 {
  margin: 0;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.2rem;
  font-weight: 700;
}

.drawer-close {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  padding: 0;
  background: #f7f8fb;
  border: none;
  border-radius: 0.5rem;
  color: #545f71;
  font-size: 1.4rem;
  line-height: 1;
  cursor: pointer;
}

.drawer-close:hover {
  background: #eef1f6;
  color: var(--login-secondary, #0c1c40);
}

.drawer-body {
  flex: 1 1 auto;
  display: flex;
  flex-direction: column;
  gap: 1.1rem;
  padding: 1.5rem;
  overflow-y: auto;
}

.drawer-body .field input {
  padding: 0.55rem 0.7rem;
  background: #f7f8fb;
  border: 1px solid #d4dae6;
  border-radius: 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-family: inherit;
}

.drawer-body .field input:focus {
  outline: 2px solid var(--login-primary, #ed2044);
  outline-offset: -1px;
  background: #fff;
}

.field-hint {
  color: #8b94a6;
  font-size: 0.78rem;
  font-weight: 600;
}

.drawer-foot {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  flex-wrap: wrap;
  padding: 1.1rem 1.5rem;
  border-top: 1px solid #eef1f6;
}

.drawer-result {
  flex: 1 1 100%;
  margin-bottom: 0.3rem;
  color: #8b94a6;
  font-size: 0.82rem;
  font-weight: 600;
}

.drawer-foot .btn-ghost:disabled {
  opacity: 0.5;
  cursor: default;
}

.drawer-fade-enter-active,
.drawer-fade-leave-active {
  transition: opacity 0.2s ease;
}

.drawer-fade-enter-active .drawer,
.drawer-fade-leave-active .drawer {
  transition: transform 0.22s ease;
}

.drawer-fade-enter-from,
.drawer-fade-leave-to {
  opacity: 0;
}

.drawer-fade-enter-from .drawer,
.drawer-fade-leave-to .drawer {
  transform: translateX(100%);
}

.cell-category {
  color: var(--login-secondary, #0c1c40);
  font-weight: 600;
  white-space: nowrap;
}

.cell-sub {
  color: #8b94a6;
  font-weight: 600;
}

.form-grid {
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

.check-field {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.92rem;
  font-weight: 600;
  cursor: pointer;
}

.check-field input[type='checkbox'] {
  width: 1rem;
  height: 1rem;
  accent-color: var(--login-primary, #ed2044);
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

.pr-table-wrap {
  margin-top: 1.1rem;
  overflow-x: auto;
}

.pr-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}

.pr-table thead th {
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

.pr-tr > td {
  padding: 0.7rem 0.85rem;
  color: #545f71;
  vertical-align: middle;
  border-bottom: 1px solid #eef1f6;
}

.pr-tr:hover > td {
  background: #f7f8fb;
}

.pr-tr.is-inactive > td {
  opacity: 0.72;
}

.pr-tr.is-editing > td {
  background: #fef6f7;
}

.cell-name-title {
  display: block;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-weight: 700;
}

.cell-name-sub {
  display: block;
  margin-top: 0.1rem;
  color: #8b94a6;
  font-size: 0.78rem;
  font-weight: 600;
  word-break: break-word;
}

.col-price {
  white-space: nowrap;
}

.price-edit {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
}

.price-input {
  width: 7rem;
  padding: 0.4rem 0.55rem;
  background: #f7f8fb;
  border: 1px solid #d4dae6;
  border-radius: 0.45rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.9rem;
  font-family: inherit;
  text-align: right;
}

.price-input:focus {
  outline: 2px solid var(--login-primary, #ed2044);
  outline-offset: -1px;
  background: #fff;
}

.price-cur {
  color: #8b94a6;
  font-size: 0.8rem;
  font-weight: 700;
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

.badge--scheduled {
  background: #e7eefc;
  color: #2b59c3;
}

.badge--expired {
  background: #fde8ec;
  color: #b3122e;
}

.cell-validity {
  white-space: nowrap;
}

.col-actions {
  text-align: right;
  white-space: nowrap;
}

.pr-row-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.4rem;
  flex-shrink: 0;
}

.btn-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  padding: 0;
  background: #fff;
  border: 1px solid #d4dae6;
  border-radius: 0.45rem;
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

.btn-icon:focus-visible {
  outline: 2px solid var(--login-primary, #ed2044);
  outline-offset: 1px;
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
  background: #fde8ec;
  color: #b3122e;
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

@media (max-width: 767.98px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
}
</style>

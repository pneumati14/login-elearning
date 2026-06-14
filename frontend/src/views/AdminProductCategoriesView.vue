<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import {
  useProductCategoriesStore,
  type ProductCategory,
  type ProductSubcategory,
} from '@/stores/productCategories'
import IconEdit from '@/components/icons/IconEdit.vue'
import IconDelete from '@/components/icons/IconDelete.vue'

const { t } = useI18n()
const store = useProductCategoriesStore()
const { categories } = storeToRefs(store)

const loading = ref(true)

onMounted(async () => {
  await store.fetchCategories()
  loading.value = false
})

// ── New category ─────────────────────────────────────────────────────
const newCategoryName = ref('')
const newCategorySplit = ref(false)

async function onCreateCategory(): Promise<void> {
  const name = newCategoryName.value.trim()
  if ('' === name) return
  const result = await store.createCategory(name, newCategorySplit.value)
  if (result.ok) {
    newCategoryName.value = ''
    newCategorySplit.value = false
  } else {
    window.alert(result.error ?? t('admin.saveFailed'))
  }
}

// ── Rename / delete category ─────────────────────────────────────────
const editingCategoryId = ref<number | null>(null)
const editCategoryName = ref('')
const editCategorySplit = ref(false)

function startEditCategory(c: ProductCategory): void {
  editingCategoryId.value = c.id
  editCategoryName.value = c.name
  editCategorySplit.value = c.splitUnitPrice
}

async function saveEditCategory(c: ProductCategory): Promise<void> {
  const name = editCategoryName.value.trim()
  if ('' === name) return
  const result = await store.updateCategory(c.id, name, editCategorySplit.value)
  if (result.ok) {
    editingCategoryId.value = null
  } else {
    window.alert(result.error ?? t('admin.saveFailed'))
  }
}

async function onDeleteCategory(c: ProductCategory): Promise<void> {
  if (!window.confirm(t('adminProductCategories.confirmDeleteCategory', { name: c.name }))) return
  const result = await store.deleteCategory(c.id)
  if (!result.ok) window.alert(result.error ?? t('admin.deleteFailed'))
}

// ── Sub-categories ───────────────────────────────────────────────────
const subName = ref<Record<number, string>>({})

async function onAddSubcategory(c: ProductCategory): Promise<void> {
  const name = (subName.value[c.id] ?? '').trim()
  if ('' === name) return
  const result = await store.createSubcategory(c.id, name)
  if (result.ok) {
    subName.value = { ...subName.value, [c.id]: '' }
  } else {
    window.alert(result.error ?? t('admin.saveFailed'))
  }
}

const editingSubId = ref<number | null>(null)
const editSubName = ref('')

function startEditSub(s: ProductSubcategory): void {
  editingSubId.value = s.id
  editSubName.value = s.name
}

async function saveEditSub(c: ProductCategory, s: ProductSubcategory): Promise<void> {
  const name = editSubName.value.trim()
  if ('' === name) return
  const result = await store.updateSubcategory(c.id, s.id, name)
  if (result.ok) {
    editingSubId.value = null
  } else {
    window.alert(result.error ?? t('admin.saveFailed'))
  }
}

async function onDeleteSub(c: ProductCategory, s: ProductSubcategory): Promise<void> {
  if (!window.confirm(t('adminProductCategories.confirmDeleteSubcategory', { name: s.name }))) return
  const result = await store.deleteSubcategory(c.id, s.id)
  if (!result.ok) window.alert(result.error ?? t('admin.deleteFailed'))
}

// ── Sub-category drag-and-drop reordering (within one category) ──────
const dragCategoryId = ref<number | null>(null)
const dragIndex = ref<number | null>(null)
const overIndex = ref<number | null>(null)

function onDragStart(c: ProductCategory, index: number, e: DragEvent): void {
  dragCategoryId.value = c.id
  dragIndex.value = index
  if (e.dataTransfer) {
    e.dataTransfer.effectAllowed = 'move'
    e.dataTransfer.setData('text/plain', String(index))
  }
}

function onDragOver(c: ProductCategory, index: number, e: DragEvent): void {
  if (dragCategoryId.value !== c.id) return
  e.preventDefault()
  overIndex.value = index
}

function onDragEnd(): void {
  dragCategoryId.value = null
  dragIndex.value = null
  overIndex.value = null
}

async function onDrop(c: ProductCategory, index: number): Promise<void> {
  const from = dragIndex.value
  const sameCategory = dragCategoryId.value === c.id
  onDragEnd()
  if (!sameCategory || null === from || from === index) return
  const order = c.subcategories.map((s) => s.id)
  const [moved] = order.splice(from, 1)
  order.splice(index, 0, moved!)
  const result = await store.reorderSubcategories(c.id, order)
  if (!result.ok) window.alert(result.error ?? t('admin.saveFailed'))
}
</script>

<template>
  <section class="admin">
    <div class="container-lg">
      <div class="admin-head">
        <span class="eyebrow">{{ t('admin.eyebrow') }}</span>
        <h1>{{ t('nav.adminProductCategories') }}</h1>
        <p>{{ t('adminProductCategories.subtitle') }}</p>
      </div>

      <p v-if="loading" class="state">{{ t('adminProductCategories.loading') }}</p>

      <template v-else>
        <!-- ── New category ──────────────────────────────────────────── -->
        <div class="pc-panel pc-new">
          <input
            v-model="newCategoryName"
            type="text"
            maxlength="255"
            class="pc-input"
            :placeholder="t('adminProductCategories.categoryNamePlaceholder')"
            @keyup.enter="onCreateCategory"
          />
          <label class="split-toggle" :title="t('adminProductCategories.splitUnitPriceHint')">
            <input v-model="newCategorySplit" type="checkbox" />
            <span>{{ t('adminProductCategories.splitUnitPrice') }}</span>
          </label>
          <button type="button" class="btn-submit" @click="onCreateCategory">
            + {{ t('adminProductCategories.newCategory') }}
          </button>
        </div>

        <!-- ── Categories ────────────────────────────────────────────── -->
        <div v-for="c in categories" :key="c.id" class="pc-panel">
          <div class="pc-head">
            <template v-if="editingCategoryId === c.id">
              <input v-model="editCategoryName" type="text" maxlength="255" class="pc-input" @keyup.enter="saveEditCategory(c)" />
              <label class="split-toggle" :title="t('adminProductCategories.splitUnitPriceHint')">
                <input v-model="editCategorySplit" type="checkbox" />
                <span>{{ t('adminProductCategories.splitUnitPrice') }}</span>
              </label>
              <div class="pc-head-actions">
                <button type="button" class="btn-mini" @click="saveEditCategory(c)">{{ t('admin.save') }}</button>
                <button type="button" class="btn-mini btn-mini--ghost" @click="editingCategoryId = null">{{ t('adminUsers.cancel') }}</button>
              </div>
            </template>
            <template v-else>
              <h2>
                {{ c.name }}
                <span v-if="c.splitUnitPrice" class="split-badge">{{ t('adminProductCategories.splitUnitPriceBadge') }}</span>
              </h2>
              <div class="pc-head-actions">
                <button type="button" class="btn-icon" :title="t('admin.edit')" :aria-label="t('admin.edit')" @click="startEditCategory(c)">
                  <IconEdit />
                </button>
                <button
                  type="button"
                  class="btn-icon btn-icon--danger"
                  :title="t('admin.delete')"
                  :aria-label="t('admin.delete')"
                  @click="onDeleteCategory(c)"
                >
                  <IconDelete />
                </button>
              </div>
            </template>
          </div>

          <p v-if="c.subcategories.length === 0" class="muted">{{ t('adminProductCategories.noSubcategories') }}</p>
          <p v-else class="drag-hint">{{ t('adminProductCategories.dragHint') }}</p>

          <ul class="sub-list">
            <li
              v-for="(s, si) in c.subcategories"
              :key="s.id"
              class="sub-row"
              :class="{ 'is-dragging': dragCategoryId === c.id && dragIndex === si, 'is-over': dragCategoryId === c.id && overIndex === si && dragIndex !== si }"
              :draggable="editingSubId !== s.id"
              @dragstart="onDragStart(c, si, $event)"
              @dragover="onDragOver(c, si, $event)"
              @drop="onDrop(c, si)"
              @dragend="onDragEnd"
            >
              <template v-if="editingSubId === s.id">
                <input v-model="editSubName" type="text" maxlength="255" class="pc-input sub-input" />
                <div class="sub-actions">
                  <button type="button" class="btn-mini" @click="saveEditSub(c, s)">{{ t('admin.save') }}</button>
                  <button type="button" class="btn-mini btn-mini--ghost" @click="editingSubId = null">{{ t('adminUsers.cancel') }}</button>
                </div>
              </template>
              <template v-else>
                <span class="drag-handle" aria-hidden="true">⠿</span>
                <span class="sub-order">{{ si + 1 }}.</span>
                <span class="sub-name">{{ s.name }}</span>
                <div class="sub-actions">
                  <button type="button" class="btn-icon" :title="t('admin.edit')" :aria-label="t('admin.edit')" @click="startEditSub(s)">
                    <IconEdit />
                  </button>
                  <button
                    type="button"
                    class="btn-icon btn-icon--danger"
                    :title="t('admin.delete')"
                    :aria-label="t('admin.delete')"
                    @click="onDeleteSub(c, s)"
                  >
                    <IconDelete />
                  </button>
                </div>
              </template>
            </li>
          </ul>

          <div class="sub-add">
            <input
              :value="subName[c.id] ?? ''"
              type="text"
              maxlength="255"
              class="pc-input sub-input"
              :placeholder="t('adminProductCategories.subcategoryNamePlaceholder')"
              @input="subName = { ...subName, [c.id]: ($event.target as HTMLInputElement).value }"
              @keyup.enter="onAddSubcategory(c)"
            />
            <button type="button" class="btn-mini" @click="onAddSubcategory(c)">+ {{ t('adminProductCategories.addSubcategory') }}</button>
          </div>
        </div>
      </template>
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

.pc-panel {
  margin-bottom: 1.5rem;
  padding: 1.85rem;
  background: #fff;
  border-radius: 1.1rem;
  box-shadow: 0 12px 32px rgba(12, 28, 64, 0.08);
}

.pc-new {
  display: flex;
  gap: 0.7rem;
  flex-wrap: wrap;
}

.pc-new .pc-input {
  flex: 1 1 260px;
}

.pc-input {
  padding: 0.55rem 0.7rem;
  background: #f7f8fb;
  border: 1px solid #d4dae6;
  border-radius: 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-family: inherit;
}

.pc-input:focus {
  outline: 2px solid var(--login-primary, #ed2044);
  outline-offset: -1px;
  background: #fff;
}

.pc-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.8rem;
  flex-wrap: wrap;
  margin-bottom: 1.1rem;
}

.pc-head h2 {
  margin: 0;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.25rem;
  font-weight: 700;
}

.split-toggle {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.9rem;
  font-weight: 600;
  white-space: nowrap;
  cursor: pointer;
}

.split-toggle input[type='checkbox'] {
  width: 1rem;
  height: 1rem;
  accent-color: var(--login-primary, #ed2044);
}

.split-badge {
  margin-left: 0.5rem;
  padding: 0.12rem 0.5rem;
  background: #e7eefc;
  border-radius: 0.4rem;
  color: #2b59c3;
  font-size: 0.72rem;
  font-weight: 700;
  vertical-align: middle;
}

.pc-head-actions {
  display: inline-flex;
  gap: 0.4rem;
}

.muted {
  margin: 0 0 0.8rem;
  color: #8b94a6;
  font-size: 0.9rem;
}

.drag-hint {
  margin: 0 0 0.7rem;
  color: #8b94a6;
  font-size: 0.82rem;
}

.sub-list {
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
  margin: 0 0 1rem;
  padding: 0;
  list-style: none;
}

.sub-row {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  flex-wrap: wrap;
  padding: 0.5rem 0.7rem;
  background: #f7f8fb;
  border-radius: 0.55rem;
}

.sub-row[draggable='true'] {
  cursor: grab;
}

.sub-row.is-dragging {
  opacity: 0.45;
}

.sub-row.is-over {
  outline: 2px dashed var(--login-primary, #ed2044);
  outline-offset: -2px;
}

.drag-handle {
  flex-shrink: 0;
  color: #b5bdca;
  font-size: 1.05rem;
  line-height: 1;
  user-select: none;
}

.sub-order {
  color: #8b94a6;
  font-size: 0.82rem;
  font-weight: 700;
}

.sub-name {
  flex: 1 1 auto;
  min-width: 0;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-weight: 600;
  word-break: break-word;
}

.sub-input {
  flex: 1 1 200px;
}

.sub-actions {
  display: flex;
  gap: 0.3rem;
  flex-shrink: 0;
  margin-left: auto;
}

.sub-add {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  flex-wrap: wrap;
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

.btn-mini {
  padding: 0.4rem 0.8rem;
  background: var(--login-primary, #ed2044);
  border: none;
  border-radius: 0.45rem;
  color: #fff;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
}

.btn-mini--ghost {
  background: #fff;
  border: 1px solid #d4dae6;
  color: var(--login-secondary, #0c1c40);
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

.state {
  padding: 1.3rem;
  background: #f7f8fb;
  border-radius: 0.7rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 1rem;
}
</style>

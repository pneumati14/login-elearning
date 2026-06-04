<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import { useFulfillmentStore, type FulfillmentStage, type FulfillmentType } from '@/stores/fulfillment'
import IconEdit from '@/components/icons/IconEdit.vue'
import IconDelete from '@/components/icons/IconDelete.vue'

const { t } = useI18n()
const store = useFulfillmentStore()
const { types } = storeToRefs(store)

const loading = ref(true)

onMounted(async () => {
  await store.fetchTypes()
  loading.value = false
})

// ── New category ─────────────────────────────────────────────────────
const newTypeName = ref('')

async function onCreateType(): Promise<void> {
  const name = newTypeName.value.trim()
  if ('' === name) return
  const result = await store.createType(name)
  if (result.ok) {
    newTypeName.value = ''
  } else {
    window.alert(result.error ?? t('admin.saveFailed'))
  }
}

// ── Rename / delete category ─────────────────────────────────────────
const editingTypeId = ref<number | null>(null)
const editTypeName = ref('')

function startEditType(tp: FulfillmentType): void {
  editingTypeId.value = tp.id
  editTypeName.value = tp.name
}

async function saveEditType(tp: FulfillmentType): Promise<void> {
  const name = editTypeName.value.trim()
  if ('' === name) return
  const result = await store.updateType(tp.id, name)
  if (result.ok) {
    editingTypeId.value = null
  } else {
    window.alert(result.error ?? t('admin.saveFailed'))
  }
}

async function onDeleteType(tp: FulfillmentType): Promise<void> {
  if (!window.confirm(t('adminFulfillment.confirmDeleteType', { name: tp.name }))) return
  const result = await store.deleteType(tp.id)
  if (!result.ok) window.alert(result.error ?? t('admin.deleteFailed'))
}

// ── Stages ───────────────────────────────────────────────────────────
const stageName = ref<Record<number, string>>({})
const stageDone = ref<Record<number, boolean>>({})

async function onAddStage(tp: FulfillmentType): Promise<void> {
  const name = (stageName.value[tp.id] ?? '').trim()
  if ('' === name) return
  const result = await store.createStage(tp.id, { name, isDone: stageDone.value[tp.id] ?? false })
  if (result.ok) {
    stageName.value = { ...stageName.value, [tp.id]: '' }
    stageDone.value = { ...stageDone.value, [tp.id]: false }
  } else {
    window.alert(result.error ?? t('admin.saveFailed'))
  }
}

const editingStageId = ref<number | null>(null)
const editStageName = ref('')
const editStageDone = ref(false)

function startEditStage(s: FulfillmentStage): void {
  editingStageId.value = s.id
  editStageName.value = s.name
  editStageDone.value = s.isDone
}

async function saveEditStage(tp: FulfillmentType, s: FulfillmentStage): Promise<void> {
  const name = editStageName.value.trim()
  if ('' === name) return
  const result = await store.updateStage(tp.id, s.id, { name, isDone: editStageDone.value })
  if (result.ok) {
    editingStageId.value = null
  } else {
    window.alert(result.error ?? t('admin.saveFailed'))
  }
}

async function onDeleteStage(tp: FulfillmentType, s: FulfillmentStage): Promise<void> {
  if (!window.confirm(t('adminFulfillment.confirmDeleteStage', { name: s.name }))) return
  const result = await store.deleteStage(tp.id, s.id)
  if (!result.ok) window.alert(result.error ?? t('admin.deleteFailed'))
}

// ── Stage drag-and-drop reordering (within one type) ─────────────────
const dragTypeId = ref<number | null>(null)
const dragIndex = ref<number | null>(null)
const overIndex = ref<number | null>(null)

function onDragStart(tp: FulfillmentType, index: number, e: DragEvent): void {
  dragTypeId.value = tp.id
  dragIndex.value = index
  if (e.dataTransfer) {
    e.dataTransfer.effectAllowed = 'move'
    e.dataTransfer.setData('text/plain', String(index))
  }
}

function onDragOver(tp: FulfillmentType, index: number, e: DragEvent): void {
  if (dragTypeId.value !== tp.id) return
  e.preventDefault()
  overIndex.value = index
}

function onDragEnd(): void {
  dragTypeId.value = null
  dragIndex.value = null
  overIndex.value = null
}

async function onDrop(tp: FulfillmentType, index: number): Promise<void> {
  const from = dragIndex.value
  const sameType = dragTypeId.value === tp.id
  onDragEnd()
  if (!sameType || null === from || from === index) return
  const order = tp.stages.map((s) => s.id)
  const [moved] = order.splice(from, 1)
  order.splice(index, 0, moved!)
  const result = await store.reorderStages(tp.id, order)
  if (!result.ok) window.alert(result.error ?? t('admin.saveFailed'))
}
</script>

<template>
  <section class="admin">
    <div class="container-lg">
      <div class="admin-head">
        <span class="eyebrow">{{ t('admin.eyebrow') }}</span>
        <h1>{{ t('nav.adminFulfillmentTypes') }}</h1>
        <p>{{ t('adminFulfillment.typesSubtitle') }}</p>
      </div>

      <p v-if="loading" class="state">{{ t('adminFulfillment.loading') }}</p>

      <template v-else>
        <!-- ── New category ──────────────────────────────────────────── -->
        <div class="ft-panel ft-new">
          <input
            v-model="newTypeName"
            type="text"
            maxlength="255"
            class="ft-input"
            :placeholder="t('adminFulfillment.typeNamePlaceholder')"
            @keyup.enter="onCreateType"
          />
          <button type="button" class="btn-submit" @click="onCreateType">
            + {{ t('adminFulfillment.newType') }}
          </button>
        </div>

        <!-- ── Categories ────────────────────────────────────────────── -->
        <div v-for="tp in types" :key="tp.id" class="ft-panel">
          <div class="ft-head">
            <template v-if="editingTypeId === tp.id">
              <input v-model="editTypeName" type="text" maxlength="255" class="ft-input" @keyup.enter="saveEditType(tp)" />
              <div class="ft-head-actions">
                <button type="button" class="btn-mini" @click="saveEditType(tp)">{{ t('admin.save') }}</button>
                <button type="button" class="btn-mini btn-mini--ghost" @click="editingTypeId = null">{{ t('adminUsers.cancel') }}</button>
              </div>
            </template>
            <template v-else>
              <h2>{{ tp.name }}</h2>
              <div class="ft-head-actions">
                <button type="button" class="btn-icon" :title="t('admin.edit')" :aria-label="t('admin.edit')" @click="startEditType(tp)">
                  <IconEdit />
                </button>
                <button
                  type="button"
                  class="btn-icon btn-icon--danger"
                  :title="t('admin.delete')"
                  :aria-label="t('admin.delete')"
                  @click="onDeleteType(tp)"
                >
                  <IconDelete />
                </button>
              </div>
            </template>
          </div>

          <p v-if="tp.stages.length === 0" class="muted">{{ t('adminFulfillment.noStages') }}</p>
          <p v-else class="drag-hint">{{ t('adminFulfillment.dragHint') }}</p>

          <ul class="stage-list">
            <li
              v-for="(s, si) in tp.stages"
              :key="s.id"
              class="stage-row"
              :class="{ 'is-dragging': dragTypeId === tp.id && dragIndex === si, 'is-over': dragTypeId === tp.id && overIndex === si && dragIndex !== si }"
              :draggable="editingStageId !== s.id"
              @dragstart="onDragStart(tp, si, $event)"
              @dragover="onDragOver(tp, si, $event)"
              @drop="onDrop(tp, si)"
              @dragend="onDragEnd"
            >
              <template v-if="editingStageId === s.id">
                <input v-model="editStageName" type="text" maxlength="255" class="ft-input stage-input" />
                <label class="done-toggle">
                  <input v-model="editStageDone" type="checkbox" />
                  <span>{{ t('adminFulfillment.stageDone') }}</span>
                </label>
                <div class="stage-actions">
                  <button type="button" class="btn-mini" @click="saveEditStage(tp, s)">{{ t('admin.save') }}</button>
                  <button type="button" class="btn-mini btn-mini--ghost" @click="editingStageId = null">{{ t('adminUsers.cancel') }}</button>
                </div>
              </template>
              <template v-else>
                <span class="drag-handle" aria-hidden="true">⠿</span>
                <span class="stage-order">{{ si + 1 }}.</span>
                <span class="stage-name">{{ s.name }}</span>
                <span v-if="s.isDone" class="badge badge--done">✓ {{ t('adminFulfillment.stageDone') }}</span>
                <div class="stage-actions">
                  <button type="button" class="btn-icon" :title="t('admin.edit')" :aria-label="t('admin.edit')" @click="startEditStage(s)">
                    <IconEdit />
                  </button>
                  <button
                    type="button"
                    class="btn-icon btn-icon--danger"
                    :title="t('admin.delete')"
                    :aria-label="t('admin.delete')"
                    @click="onDeleteStage(tp, s)"
                  >
                    <IconDelete />
                  </button>
                </div>
              </template>
            </li>
          </ul>

          <div class="stage-add">
            <input
              :value="stageName[tp.id] ?? ''"
              type="text"
              maxlength="255"
              class="ft-input stage-input"
              :placeholder="t('adminFulfillment.stageNamePlaceholder')"
              @input="stageName = { ...stageName, [tp.id]: ($event.target as HTMLInputElement).value }"
              @keyup.enter="onAddStage(tp)"
            />
            <label class="done-toggle">
              <input
                :checked="stageDone[tp.id] ?? false"
                type="checkbox"
                @change="stageDone = { ...stageDone, [tp.id]: ($event.target as HTMLInputElement).checked }"
              />
              <span>{{ t('adminFulfillment.stageDone') }}</span>
            </label>
            <button type="button" class="btn-mini" @click="onAddStage(tp)">+ {{ t('adminFulfillment.addStage') }}</button>
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

.ft-panel {
  margin-bottom: 1.5rem;
  padding: 1.85rem;
  background: #fff;
  border-radius: 1.1rem;
  box-shadow: 0 12px 32px rgba(12, 28, 64, 0.08);
}

.ft-new {
  display: flex;
  gap: 0.7rem;
  flex-wrap: wrap;
}

.ft-new .ft-input {
  flex: 1 1 260px;
}

.ft-input {
  padding: 0.55rem 0.7rem;
  background: #f7f8fb;
  border: 1px solid #d4dae6;
  border-radius: 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-family: inherit;
}

.ft-input:focus {
  outline: 2px solid var(--login-primary, #ed2044);
  outline-offset: -1px;
  background: #fff;
}

.ft-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.8rem;
  flex-wrap: wrap;
  margin-bottom: 1.1rem;
}

.ft-head h2 {
  margin: 0;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.25rem;
  font-weight: 700;
}

.ft-head-actions {
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

.stage-list {
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
  margin: 0 0 1rem;
  padding: 0;
  list-style: none;
}

.stage-row {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  flex-wrap: wrap;
  padding: 0.5rem 0.7rem;
  background: #f7f8fb;
  border-radius: 0.55rem;
}

.stage-row[draggable='true'] {
  cursor: grab;
}

.stage-row.is-dragging {
  opacity: 0.45;
}

.stage-row.is-over {
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

.stage-order {
  color: #8b94a6;
  font-size: 0.82rem;
  font-weight: 700;
}

.stage-name {
  flex: 1 1 auto;
  min-width: 0;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-weight: 600;
  word-break: break-word;
}

.stage-input {
  flex: 1 1 200px;
}

.stage-actions {
  display: flex;
  gap: 0.3rem;
  flex-shrink: 0;
  margin-left: auto;
}

.stage-add {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  flex-wrap: wrap;
}

.done-toggle {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.88rem;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
}

.done-toggle input {
  width: 1rem;
  height: 1rem;
  accent-color: var(--login-primary, #ed2044);
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

.badge--done {
  background: #e3f6ec;
  color: #1c7a45;
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

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  useOpportunityTypesStore,
  typeStatus,
  type OpportunityType,
  type OpportunityStage,
  type StageOutcome,
} from '@/stores/opportunityTypes'
import IconEdit from '@/components/icons/IconEdit.vue'
import IconDelete from '@/components/icons/IconDelete.vue'

const { t } = useI18n()
const route = useRoute()
const store = useOpportunityTypesStore()

const id = computed(() => Number(route.params.id))
// Source the type from the store so stage edits (which upsert the parent
// type into the store) reflect here without a refetch.
const type = computed<OpportunityType | null>(() => store.types.find((tp) => tp.id === id.value) ?? null)
const loading = ref(true)
const notFound = ref(false)

const OUTCOMES: StageOutcome[] = ['open', 'won', 'lost']

async function load(): Promise<void> {
  loading.value = true
  const result = await store.fetchType(id.value)
  notFound.value = null === result
  loading.value = false
}

onMounted(load)
watch(id, load)

function outcomeLabel(o: StageOutcome): string {
  return t(`adminOpportunityTypes.outcome_${o}`)
}

// ── Edit type (name + active + validity) ────────────────────────────────
const editing = ref(false)
const editName = ref('')
const editActive = ref(true)
const editValidFrom = ref('')
const editValidUntil = ref('')

function startEdit(tp: OpportunityType): void {
  editName.value = tp.name
  editActive.value = tp.isActive
  editValidFrom.value = tp.validFrom ?? ''
  editValidUntil.value = tp.validUntil ?? ''
  editing.value = true
}

async function saveEdit(tp: OpportunityType): Promise<void> {
  const name = editName.value.trim()
  if ('' === name) return
  const result = await store.updateType(tp.id, {
    name,
    isActive: editActive.value,
    validFrom: editValidFrom.value || null,
    validUntil: editValidUntil.value || null,
  })
  if (result.ok) {
    editing.value = false
  } else {
    window.alert(result.error ?? t('admin.saveFailed'))
  }
}

function validityLabel(tp: OpportunityType): string {
  if (null === tp.validFrom && null === tp.validUntil) return t('adminOpportunityTypes.validityOpen')
  return `${tp.validFrom ?? '—'} → ${tp.validUntil ?? '—'}`
}

// ── Add stage ───────────────────────────────────────────────────────────
const stageName = ref('')
const stageOutcome = ref<StageOutcome>('open')

async function onAddStage(tp: OpportunityType): Promise<void> {
  const name = stageName.value.trim()
  if ('' === name) return
  const result = await store.createStage(tp.id, { name, outcome: stageOutcome.value })
  if (result.ok) {
    stageName.value = ''
    stageOutcome.value = 'open'
  } else {
    window.alert(result.error ?? t('admin.saveFailed'))
  }
}

// ── Edit stage ──────────────────────────────────────────────────────────
const editingStageId = ref<number | null>(null)
const editStageName = ref('')
const editStageOutcome = ref<StageOutcome>('open')

function startEditStage(s: OpportunityStage): void {
  editingStageId.value = s.id
  editStageName.value = s.name
  editStageOutcome.value = s.outcome
}

async function saveEditStage(tp: OpportunityType, s: OpportunityStage): Promise<void> {
  const name = editStageName.value.trim()
  if ('' === name) return
  const result = await store.updateStage(tp.id, s.id, { name, outcome: editStageOutcome.value })
  if (result.ok) {
    editingStageId.value = null
  } else {
    window.alert(result.error ?? t('admin.saveFailed'))
  }
}

// ── Drag-and-drop reordering of stages ──────────────────────────────────
const dragIndex = ref<number | null>(null)
const overIndex = ref<number | null>(null)

function onDragStart(index: number, e: DragEvent): void {
  dragIndex.value = index
  if (e.dataTransfer) {
    e.dataTransfer.effectAllowed = 'move'
    // Firefox requires some data to be set for the drag to start.
    e.dataTransfer.setData('text/plain', String(index))
  }
}

function onDragOver(index: number, e: DragEvent): void {
  e.preventDefault()
  if (e.dataTransfer) e.dataTransfer.dropEffect = 'move'
  overIndex.value = index
}

function onDragEnd(): void {
  dragIndex.value = null
  overIndex.value = null
}

async function onDrop(tp: OpportunityType, index: number): Promise<void> {
  const from = dragIndex.value
  onDragEnd()
  if (null === from || from === index) return
  const order = tp.stages.map((s) => s.id)
  const [moved] = order.splice(from, 1)
  order.splice(index, 0, moved!)
  const result = await store.reorderStages(tp.id, order)
  if (!result.ok) window.alert(result.error ?? t('admin.saveFailed'))
}

async function removeStage(tp: OpportunityType, s: OpportunityStage): Promise<void> {
  if (!window.confirm(t('adminOpportunityTypes.confirmDeleteStage', { name: s.name }))) return
  const result = await store.deleteStage(tp.id, s.id)
  if (!result.ok) window.alert(result.error ?? t('admin.deleteFailed'))
}
</script>

<template>
  <section class="admin">
    <div class="container-lg">
      <RouterLink :to="{ name: 'admin-opportunity-types' }" class="back-link">
        ← {{ t('adminOpportunityTypes.backToList') }}
      </RouterLink>

      <p v-if="loading" class="state">{{ t('adminOpportunityTypes.loading') }}</p>

      <p v-else-if="notFound" class="state state--error">{{ t('adminOpportunityTypes.notFound') }}</p>

      <template v-else-if="type">
        <div class="admin-head">
          <span class="eyebrow">{{ t('nav.adminOpportunityTypes') }}</span>
          <h1>{{ type.name }}</h1>
        </div>

        <!-- ── Type (name + active) ─────────────────────────────────── -->
        <div class="ot-panel">
          <div class="ot-panel-head">
            <h2>{{ t('adminOpportunityTypes.typeName') }}</h2>
            <button v-if="!editing" type="button" class="btn-edit" @click="startEdit(type)">
              <IconEdit />
              <span>{{ t('admin.edit') }}</span>
            </button>
          </div>

          <template v-if="editing">
            <label class="field">
              <span>{{ t('adminOpportunityTypes.typeName') }} *</span>
              <input v-model="editName" type="text" required maxlength="255" @keyup.enter="saveEdit(type)" />
            </label>
            <div class="date-row">
              <label class="field">
                <span>{{ t('adminOpportunityTypes.validFrom') }}</span>
                <input v-model="editValidFrom" type="date" />
              </label>
              <label class="field">
                <span>{{ t('adminOpportunityTypes.validUntil') }}</span>
                <input v-model="editValidUntil" type="date" />
              </label>
            </div>
            <label class="active-toggle">
              <input v-model="editActive" type="checkbox" />
              <span>{{ t('adminOpportunityTypes.active') }}</span>
            </label>
            <div class="edit-actions">
              <button type="button" class="btn-submit" @click="saveEdit(type)">{{ t('admin.save') }}</button>
              <button type="button" class="btn-ghost" @click="editing = false">{{ t('adminUsers.cancel') }}</button>
            </div>
          </template>

          <template v-else>
            <p class="type-row">
              <span class="type-name">{{ type.name }}</span>
              <span class="badge" :class="`badge--${typeStatus(type)}`">
                {{ t('adminOpportunityTypes.status_' + typeStatus(type)) }}
              </span>
            </p>
            <p class="validity-line">
              <span class="validity-key">{{ t('adminOpportunityTypes.colValidity') }}:</span>
              {{ validityLabel(type) }}
            </p>
          </template>
        </div>

        <!-- ── Stages ───────────────────────────────────────────────── -->
        <div class="ot-panel">
          <h2>{{ t('adminOpportunityTypes.stagesHeader') }}</h2>

          <p v-if="type.stages.length === 0" class="muted">{{ t('adminOpportunityTypes.stagesEmpty') }}</p>

          <p v-if="type.stages.length > 1" class="drag-hint">{{ t('adminOpportunityTypes.dragHint') }}</p>

          <ul class="stage-list">
            <li
              v-for="(s, si) in type.stages"
              :key="s.id"
              class="stage-row"
              :class="{ 'is-dragging': dragIndex === si, 'is-over': overIndex === si && dragIndex !== si }"
              :draggable="editingStageId !== s.id"
              @dragstart="onDragStart(si, $event)"
              @dragover="onDragOver(si, $event)"
              @drop="onDrop(type, si)"
              @dragend="onDragEnd"
            >
              <template v-if="editingStageId === s.id">
                <input v-model="editStageName" type="text" maxlength="255" class="stage-name-input" />
                <select v-model="editStageOutcome" class="outcome-select">
                  <option v-for="o in OUTCOMES" :key="o" :value="o">{{ outcomeLabel(o) }}</option>
                </select>
                <div class="stage-row-actions">
                  <button type="button" class="btn-mini" @click="saveEditStage(type, s)">{{ t('admin.save') }}</button>
                  <button type="button" class="btn-mini btn-mini--ghost" @click="editingStageId = null">{{ t('adminUsers.cancel') }}</button>
                </div>
              </template>
              <template v-else>
                <span class="drag-handle" :title="t('adminOpportunityTypes.dragHint')" aria-hidden="true">⠿</span>
                <span class="stage-order">{{ si + 1 }}.</span>
                <span class="stage-name">{{ s.name }}</span>
                <span class="badge" :class="`badge--${s.outcome}`">{{ outcomeLabel(s.outcome) }}</span>
                <div class="stage-row-actions">
                  <button type="button" class="btn-icon" :title="t('admin.edit')" :aria-label="t('admin.edit')" @click="startEditStage(s)"><IconEdit /></button>
                  <button type="button" class="btn-icon btn-icon--danger" :title="t('admin.delete')" :aria-label="t('admin.delete')" @click="removeStage(type, s)"><IconDelete /></button>
                </div>
              </template>
            </li>
          </ul>

          <!-- Add stage -->
          <div class="stage-add">
            <input
              v-model="stageName"
              type="text"
              maxlength="255"
              class="stage-name-input"
              :placeholder="t('adminOpportunityTypes.stageNamePlaceholder')"
              @keyup.enter="onAddStage(type)"
            />
            <select v-model="stageOutcome" class="outcome-select">
              <option v-for="o in OUTCOMES" :key="o" :value="o">{{ outcomeLabel(o) }}</option>
            </select>
            <button type="button" class="btn-mini" @click="onAddStage(type)">+ {{ t('adminOpportunityTypes.addStageButton') }}</button>
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

.back-link {
  display: inline-block;
  margin-bottom: 1.5rem;
  color: var(--login-primary, #ed2044);
  font-size: 0.9rem;
  font-weight: 700;
  text-decoration: none;
}

.back-link:hover {
  text-decoration: underline;
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

.ot-panel {
  margin-bottom: 1.5rem;
  padding: 1.85rem;
  background: #fff;
  border-radius: 1.1rem;
  box-shadow: 0 12px 32px rgba(12, 28, 64, 0.08);
}

.ot-panel h2 {
  margin: 0 0 1.3rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.25rem;
  font-weight: 700;
}

.ot-panel-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.3rem;
}

.ot-panel-head h2 {
  margin: 0;
}

.btn-edit {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.4rem 0.9rem;
  background: #fff;
  border: 1px solid #d4dae6;
  border-radius: 0.45rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.85rem;
  font-weight: 700;
  cursor: pointer;
}

.btn-edit:hover {
  border-color: var(--login-secondary, #0c1c40);
}

.type-row {
  display: flex;
  align-items: center;
  gap: 0.7rem;
  margin: 0;
}

.type-name {
  color: var(--login-secondary, #0c1c40);
  font-size: 1.05rem;
  font-weight: 700;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  max-width: 420px;
  margin-bottom: 1rem;
}

.field span {
  color: var(--login-secondary, #0c1c40);
  font-size: 0.85rem;
  font-weight: 700;
}

.field input {
  padding: 0.55rem 0.7rem;
  background: #f7f8fb;
  border: 1px solid #d4dae6;
  border-radius: 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-family: inherit;
}

.field input:focus {
  outline: 2px solid var(--login-primary, #ed2044);
  outline-offset: -1px;
  background: #fff;
}

.active-toggle {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  margin-bottom: 1.1rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-weight: 600;
  cursor: pointer;
}

.active-toggle input {
  width: 1rem;
  height: 1rem;
  accent-color: var(--login-primary, #ed2044);
}

.edit-actions {
  display: flex;
  gap: 0.6rem;
}

.muted {
  margin: 0 0 1rem;
  color: #8b94a6;
  font-size: 0.9rem;
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
  cursor: grab;
  user-select: none;
}

.drag-hint {
  margin: 0 0 0.7rem;
  color: #8b94a6;
  font-size: 0.82rem;
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

.stage-row-actions {
  display: flex;
  gap: 0.3rem;
  flex-shrink: 0;
  margin-left: auto;
}

.stage-add {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
  padding-top: 0.4rem;
}

.stage-add .stage-name-input {
  flex: 1 1 200px;
}

.stage-name-input {
  padding: 0.45rem 0.65rem;
  background: #f7f8fb;
  border: 1px solid #d4dae6;
  border-radius: 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-family: inherit;
}

.stage-name-input:focus,
.outcome-select:focus {
  outline: 2px solid var(--login-primary, #ed2044);
  outline-offset: -1px;
  background: #fff;
}

.outcome-select {
  padding: 0.45rem 0.6rem;
  background: #f7f8fb;
  border: 1px solid #d4dae6;
  border-radius: 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.9rem;
  font-family: inherit;
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

.badge--open {
  background: #e7eefc;
  color: #2b59c3;
}

.badge--won {
  background: #e3f6ec;
  color: #1c7a45;
}

.badge--lost {
  background: #fde8ec;
  color: #b3122e;
}

.badge--active {
  background: #e3f6ec;
  color: #1c7a45;
}

.badge--inactive,
.badge--muted {
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

.date-row {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.9rem 1rem;
  max-width: 420px;
}

.date-row .field {
  max-width: none;
}

.validity-line {
  margin: 0.7rem 0 0;
  color: #545f71;
  font-size: 0.95rem;
}

.validity-key {
  color: #8b94a6;
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.btn-submit {
  padding: 0.6rem 1.4rem;
  background: var(--login-primary, #ed2044);
  border: none;
  border-radius: 0.55rem;
  color: #fff;
  font-size: 0.95rem;
  font-weight: 700;
  cursor: pointer;
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
  font-size: 0.95rem;
  cursor: pointer;
}

.btn-icon:hover:not(:disabled) {
  border-color: var(--login-secondary, #0c1c40);
  color: var(--login-secondary, #0c1c40);
}

.btn-icon:disabled {
  opacity: 0.4;
  cursor: default;
}

.btn-icon--danger:hover:not(:disabled) {
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

.state--error {
  background: #fde8ec;
  color: #b3122e;
}
</style>

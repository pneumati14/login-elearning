<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import { useFulfillmentStore, type FulfillmentItem, type FulfillmentStage } from '@/stores/fulfillment'
import { useMoneyFormat } from '@/stores/currencySettings'
import AppSelect from '@/components/AppSelect.vue'

const { t, locale } = useI18n()
const store = useFulfillmentStore()
const { types, items, loading, error } = storeToRefs(store)

// Placeholder (null) first, then one entry per category.
const assignSelectOptions = computed<{ value: number | null; label: string }[]>(() => [
  { value: null, label: t('adminFulfillment.assignPlaceholder') },
  ...types.value.map((tp) => ({ value: tp.id, label: tp.name })),
])

onMounted(() => {
  store.fetchTypes()
  store.fetchItems()
})

function reload(): void {
  store.fetchTypes()
  store.fetchItems()
}

// ── Tabs: one per category + the uncategorised intake ───────────────
const activeTypeId = ref<number | 'new'>('new')

const uncategorized = computed(() => items.value.filter((i) => null === i.fulfillmentTypeId))

const activeType = computed(() => types.value.find((tp) => tp.id === activeTypeId.value) ?? null)

function itemsInStage(stage: FulfillmentStage): FulfillmentItem[] {
  return items.value
    .filter((i) => i.fulfillmentStageId === stage.id)
    .sort((a, b) => ((a.closedAt ?? '') > (b.closedAt ?? '') ? -1 : 1))
}

function typeCount(typeId: number): number {
  return items.value.filter((i) => i.fulfillmentTypeId === typeId).length
}

// ── Formatting ───────────────────────────────────────────────────────
const fmtMoneyRaw = useMoneyFormat()
function fmtMoney(value: string | null, currency: string): string {
  return null === value ? '—' : fmtMoneyRaw(value, currency)
}

function fmtDate(iso: string | null): string {
  return null === iso ? '—' : new Date(`${iso}T00:00:00`).toLocaleDateString(locale.value)
}

// ── Categorise an uncategorised deal ─────────────────────────────────
async function onAssign(item: FulfillmentItem, typeId: number | null): Promise<void> {
  if (null === typeId) return
  const result = await store.assignType(item.id, typeId)
  if (!result.ok) {
    window.alert(result.error ?? t('admin.saveFailed'))
  } else {
    // Jump to the category the deal just entered.
    activeTypeId.value = typeId
  }
}

// ── Kanban drag-and-drop ─────────────────────────────────────────────
const dragItemId = ref<number | null>(null)
const dragOverStageId = ref<number | null>(null)

function onDragStart(item: FulfillmentItem, e: DragEvent): void {
  dragItemId.value = item.id
  if (e.dataTransfer) {
    e.dataTransfer.effectAllowed = 'move'
    // Firefox requires some data to be set for the drag to start.
    e.dataTransfer.setData('text/plain', String(item.id))
  }
}

function onDragOver(stage: FulfillmentStage, e: DragEvent): void {
  e.preventDefault()
  if (e.dataTransfer) e.dataTransfer.dropEffect = 'move'
  dragOverStageId.value = stage.id
}

function onDragEnd(): void {
  dragItemId.value = null
  dragOverStageId.value = null
}

async function onDrop(stage: FulfillmentStage): Promise<void> {
  const itemId = dragItemId.value
  onDragEnd()
  if (null === itemId) return
  const item = items.value.find((i) => i.id === itemId)
  if (!item || item.fulfillmentStageId === stage.id) return
  const result = await store.moveStage(itemId, stage.id)
  if (!result.ok) window.alert(result.error ?? t('admin.saveFailed'))
}
</script>

<template>
  <section class="admin">
    <div class="container-lg">
      <div class="admin-head">
        <span class="eyebrow">CRM</span>
        <h1>{{ t('adminFulfillment.title') }}</h1>
        <p class="subtitle">{{ t('adminFulfillment.subtitle') }}</p>
      </div>

      <p v-if="loading" class="state">{{ t('adminFulfillment.loading') }}</p>

      <div v-else-if="error" class="state state--error">
        <strong>{{ t('adminFulfillment.loadError') }}</strong>
        <button type="button" class="btn-retry" @click="reload">{{ t('common.retry') }}</button>
      </div>

      <template v-else>
        <!-- ── Category tabs ─────────────────────────────────────────── -->
        <div class="tabs" role="tablist">
          <button
            type="button"
            role="tab"
            class="tab"
            :class="{ 'is-active': activeTypeId === 'new' }"
            :aria-selected="activeTypeId === 'new'"
            @click="activeTypeId = 'new'"
          >
            {{ t('adminFulfillment.uncategorized') }}
            <span v-if="uncategorized.length > 0" class="tab-badge">{{ uncategorized.length }}</span>
          </button>
          <button
            v-for="tp in types"
            :key="tp.id"
            type="button"
            role="tab"
            class="tab"
            :class="{ 'is-active': activeTypeId === tp.id }"
            :aria-selected="activeTypeId === tp.id"
            @click="activeTypeId = tp.id"
          >
            {{ tp.name }}
            <span v-if="typeCount(tp.id) > 0" class="tab-badge tab-badge--muted">{{ typeCount(tp.id) }}</span>
          </button>
        </div>

        <!-- ── Uncategorised intake ──────────────────────────────────── -->
        <div v-if="activeTypeId === 'new'" class="ful-panel">
          <p v-if="uncategorized.length === 0" class="muted">{{ t('adminFulfillment.uncategorizedEmpty') }}</p>
          <ul v-else class="intake-list">
            <li v-for="item in uncategorized" :key="item.id" class="intake-item">
              <div class="intake-main">
                <RouterLink
                  :to="{ name: 'admin-customer-detail', params: { id: item.customerId } }"
                  class="intake-customer"
                >
                  {{ item.customerName }}
                </RouterLink>
                <span class="intake-title">{{ item.title }}</span>
                <span class="intake-meta">
                  {{ fmtMoney(item.value, item.currency) }}
                  <template v-if="item.ownerName"> · {{ item.ownerName }}</template>
                  · {{ t('adminFulfillment.wonAt') }}: {{ fmtDate(item.closedAt) }}
                </span>
              </div>
              <label class="intake-assign">
                <span>{{ t('adminFulfillment.assignLabel') }}</span>
                <AppSelect
                  class="assign-select"
                  :model-value="null"
                  :options="assignSelectOptions"
                  :placeholder="t('adminFulfillment.assignPlaceholder')"
                  @change="(v) => onAssign(item, v)"
                />
              </label>
            </li>
          </ul>
        </div>

        <!-- ── Kanban of the selected category ───────────────────────── -->
        <div v-else-if="activeType" class="ful-panel">
          <p v-if="activeType.stages.length === 0" class="muted">{{ t('adminFulfillment.noStages') }}</p>
          <div v-else class="kanban" :style="{ gridTemplateColumns: `repeat(${activeType.stages.length}, minmax(190px, 1fr))` }">
            <div
              v-for="stage in activeType.stages"
              :key="stage.id"
              class="kanban-col"
              :class="{ 'is-over': dragOverStageId === stage.id }"
              @dragover="onDragOver(stage, $event)"
              @drop="onDrop(stage)"
            >
              <div class="kanban-col-head" :class="{ 'kanban-col-head--done': stage.isDone }">
                <span>{{ stage.name }}<template v-if="stage.isDone"> ✓</template></span>
                <span class="kanban-count">{{ itemsInStage(stage).length }}</span>
              </div>
              <div class="kanban-col-body">
                <div
                  v-for="item in itemsInStage(stage)"
                  :key="item.id"
                  class="kanban-item"
                  :class="{ 'is-dragging': dragItemId === item.id }"
                  draggable="true"
                  @dragstart="onDragStart(item, $event)"
                  @dragend="onDragEnd"
                >
                  <span class="kanban-item-title">{{ item.title }}</span>
                  <RouterLink
                    :to="{ name: 'admin-customer-detail', params: { id: item.customerId } }"
                    class="kanban-item-customer"
                  >
                    {{ item.customerName }}
                  </RouterLink>
                  <span class="kanban-item-value">{{ fmtMoney(item.value, item.currency) }}</span>
                  <span class="kanban-item-meta">
                    <template v-if="item.ownerName">{{ item.ownerName }} · </template>{{ fmtDate(item.closedAt) }}
                  </span>
                </div>
              </div>
            </div>
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

.subtitle {
  margin: 0;
  color: #545f71;
  font-size: 1rem;
}

/* ── Tabs ───────────────────────────────────────────────────────────── */
.tabs {
  display: flex;
  gap: 0.4rem;
  flex-wrap: wrap;
  margin-bottom: 1.2rem;
}

.tab {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.55rem 1.1rem;
  background: #fff;
  border: 1px solid #d4dae6;
  border-radius: 2rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.92rem;
  font-weight: 700;
  font-family: inherit;
  cursor: pointer;
  transition: background 0.15s, border-color 0.15s, color 0.15s;
}

.tab:hover {
  border-color: var(--login-primary, #ed2044);
}

.tab.is-active {
  background: var(--login-secondary, #0c1c40);
  border-color: var(--login-secondary, #0c1c40);
  color: #fff;
}

.tab-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.35rem;
  height: 1.35rem;
  padding: 0 0.3rem;
  background: var(--login-primary, #ed2044);
  border-radius: 0.7rem;
  color: #fff;
  font-size: 0.72rem;
}

.tab-badge--muted {
  background: #e3e7ef;
  color: var(--login-secondary, #0c1c40);
}

.tab.is-active .tab-badge--muted {
  background: rgba(255, 255, 255, 0.25);
  color: #fff;
}

/* ── Panels ─────────────────────────────────────────────────────────── */
.ful-panel {
  padding: 1.85rem;
  background: #fff;
  border-radius: 1.1rem;
  box-shadow: 0 12px 32px rgba(12, 28, 64, 0.08);
}

.muted {
  margin: 0;
  color: #8b94a6;
  font-size: 0.9rem;
}

/* ── Uncategorised intake ───────────────────────────────────────────── */
.intake-list {
  display: flex;
  flex-direction: column;
  gap: 0.7rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.intake-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
  padding: 0.9rem 1.1rem;
  background: #f7f8fb;
  border-radius: 0.8rem;
}

.intake-main {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  min-width: 0;
}

.intake-customer {
  color: var(--login-secondary, #0c1c40);
  font-size: 1rem;
  font-weight: 700;
  text-decoration: none;
}

.intake-customer:hover {
  color: var(--login-primary, #ed2044);
  text-decoration: underline;
}

.intake-title {
  color: #545f71;
  font-size: 0.92rem;
  font-weight: 600;
}

.intake-meta {
  color: #8b94a6;
  font-size: 0.82rem;
  font-weight: 600;
}

.intake-assign {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}

.intake-assign span {
  color: var(--login-secondary, #0c1c40);
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.intake-assign select {
  min-width: 220px;
  padding: 0.5rem 0.7rem;
  background: #fff;
  border: 1px solid #d4dae6;
  border-radius: 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.92rem;
  font-family: inherit;
}

.assign-select :deep(.app-select-toggle) {
  min-width: 220px;
}

/* ── Kanban ─────────────────────────────────────────────────────────── */
.kanban {
  display: grid;
  gap: 0.7rem;
  overflow-x: auto;
  padding-bottom: 0.4rem;
}

.kanban-col {
  display: flex;
  flex-direction: column;
  min-width: 190px;
  background: #f7f8fb;
  border-radius: 0.8rem;
  outline: 2px dashed transparent;
  outline-offset: -2px;
  transition: outline-color 0.1s;
}

.kanban-col.is-over {
  outline-color: var(--login-primary, #ed2044);
  background: #fdf2f4;
}

.kanban-col-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.4rem;
  padding: 0.6rem 0.8rem;
  background: #e7eefc;
  border-radius: 0.8rem 0.8rem 0 0;
  color: #2b59c3;
  font-size: 0.76rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.kanban-col-head--done {
  background: #e3f6ec;
  color: #1c7a45;
}

.kanban-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.35rem;
  height: 1.35rem;
  padding: 0 0.3rem;
  background: rgba(255, 255, 255, 0.75);
  border-radius: 0.7rem;
  font-size: 0.72rem;
}

.kanban-col-body {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  flex: 1 1 auto;
  min-height: 4rem;
  padding: 0.55rem;
}

.kanban-item {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  padding: 0.7rem 0.8rem;
  background: #fff;
  border-radius: 0.6rem;
  box-shadow: 0 3px 10px rgba(12, 28, 64, 0.08);
  cursor: grab;
  transition: transform 0.1s, box-shadow 0.1s;
}

.kanban-item:hover {
  transform: translateY(-1px);
  box-shadow: 0 6px 14px rgba(12, 28, 64, 0.12);
}

.kanban-item.is-dragging {
  opacity: 0.45;
}

.kanban-item-title {
  color: var(--login-secondary, #0c1c40);
  font-size: 0.9rem;
  font-weight: 700;
  word-break: break-word;
}

.kanban-item-customer {
  color: #545f71;
  font-size: 0.8rem;
  font-weight: 600;
  text-decoration: none;
  word-break: break-word;
}

.kanban-item-customer:hover {
  color: var(--login-primary, #ed2044);
  text-decoration: underline;
}

.kanban-item-value {
  color: var(--login-primary, #ed2044);
  font-size: 0.86rem;
  font-weight: 700;
}

.kanban-item-meta {
  color: #8b94a6;
  font-size: 0.74rem;
  font-weight: 600;
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
</style>

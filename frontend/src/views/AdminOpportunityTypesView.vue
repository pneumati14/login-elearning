<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import {
  useOpportunityTypesStore,
  typeStatus,
  type OpportunityType,
  type TypeStatus,
} from '@/stores/opportunityTypes'
import IconView from '@/components/icons/IconView.vue'
import IconDelete from '@/components/icons/IconDelete.vue'

const { t } = useI18n()
const store = useOpportunityTypesStore()
const { types, loading, error } = storeToRefs(store)

const sortedTypes = computed(() => [...types.value].sort((a, b) => a.position - b.position))

// ── List filter ───────────────────────────────────────────────────────
const search = ref('')

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  if ('' === q) return sortedTypes.value
  return sortedTypes.value.filter((tp) => tp.name.toLowerCase().includes(q))
})

// Manual reordering only makes sense on the full, unfiltered list.
const canReorder = computed(() => '' === search.value.trim())

onMounted(() => {
  store.fetchTypes()
})

// ── New type (collapsed by default) ────────────────────────────────────
const showNew = ref(false)
const newTypeName = ref('')
const newValidFrom = ref('')
const newValidUntil = ref('')
const creating = ref(false)
const createError = ref<string | null>(null)
const createSuccess = ref<string | null>(null)

function toggleNew(): void {
  showNew.value = !showNew.value
  if (showNew.value) {
    newTypeName.value = ''
    newValidFrom.value = ''
    newValidUntil.value = ''
    createError.value = null
    createSuccess.value = null
  }
}

async function onCreate(): Promise<void> {
  createError.value = null
  createSuccess.value = null
  if ('' === newTypeName.value.trim()) {
    createError.value = t('adminOpportunityTypes.typeNameRequired')
    return
  }
  creating.value = true
  const result = await store.createType({
    name: newTypeName.value.trim(),
    validFrom: newValidFrom.value || null,
    validUntil: newValidUntil.value || null,
  })
  creating.value = false
  if (result.ok) {
    createSuccess.value = t('adminOpportunityTypes.created')
    showNew.value = false
  } else {
    createError.value = result.error ?? t('admin.saveFailed')
  }
}

function statusOf(tp: OpportunityType): TypeStatus {
  return typeStatus(tp)
}

function validityLabel(tp: OpportunityType): string {
  if (null === tp.validFrom && null === tp.validUntil) return t('adminOpportunityTypes.validityOpen')
  return `${tp.validFrom ?? '—'} → ${tp.validUntil ?? '—'}`
}

async function moveType(index: number, dir: 'up' | 'down'): Promise<void> {
  const arr = sortedTypes.value
  const j = 'up' === dir ? index - 1 : index + 1
  if (j < 0 || j >= arr.length) return
  const order = arr.map((tp) => tp.id)
  const tmp = order[index]!
  order[index] = order[j]!
  order[j] = tmp
  const result = await store.reorderTypes(order)
  if (!result.ok) window.alert(result.error ?? t('admin.saveFailed'))
}

async function onDelete(tp: OpportunityType): Promise<void> {
  if (!window.confirm(t('adminOpportunityTypes.confirmDeleteType', { name: tp.name }))) return
  const result = await store.deleteType(tp.id)
  if (!result.ok) window.alert(result.error ?? t('admin.deleteFailed'))
}
</script>

<template>
  <section class="admin">
    <div class="container-lg">
      <div class="admin-head">
        <span class="eyebrow">{{ t('admin.eyebrow') }}</span>
        <h1>{{ t('nav.adminOpportunityTypes') }}</h1>
        <p>{{ t('adminOpportunityTypes.subtitle') }}</p>
      </div>

      <!-- ── New type (shown only after clicking "New type") ───────── -->
      <form v-if="showNew" class="ot-panel" @submit.prevent="onCreate">
        <div class="ot-panel-head">
          <h2>{{ t('adminOpportunityTypes.newType') }}</h2>
          <button type="button" class="btn-ghost" @click="toggleNew">{{ t('adminUsers.cancel') }}</button>
        </div>

        <label class="field">
          <span>{{ t('adminOpportunityTypes.typeName') }} *</span>
          <input v-model="newTypeName" type="text" required maxlength="255" :placeholder="t('adminOpportunityTypes.typeNamePlaceholder')" />
        </label>

        <div class="date-row">
          <label class="field">
            <span>{{ t('adminOpportunityTypes.validFrom') }}</span>
            <input v-model="newValidFrom" type="date" />
          </label>
          <label class="field">
            <span>{{ t('adminOpportunityTypes.validUntil') }}</span>
            <input v-model="newValidUntil" type="date" />
          </label>
        </div>

        <p v-if="createError" class="msg msg--error">{{ createError }}</p>

        <button type="submit" class="btn-submit" :disabled="creating">
          {{ creating ? t('admin.creating') : t('adminOpportunityTypes.create') }}
        </button>
      </form>

      <!-- ── Existing types — list ─────────────────────────────────── -->
      <div class="ot-panel">
        <div class="ot-list-head">
          <h2>{{ t('adminOpportunityTypes.existing') }}</h2>
          <div class="ot-list-tools">
            <input v-model="search" type="search" :placeholder="t('adminOpportunityTypes.searchPlaceholder')" class="search" />
            <button type="button" class="btn-submit btn-new" @click="toggleNew">
              {{ showNew ? t('adminUsers.cancel') : '+ ' + t('adminOpportunityTypes.newType') }}
            </button>
          </div>
        </div>

        <p v-if="createSuccess" class="msg msg--success">{{ createSuccess }}</p>

        <p v-if="loading" class="state">{{ t('adminOpportunityTypes.loading') }}</p>

        <div v-else-if="error" class="state state--error">
          <strong>{{ t('adminOpportunityTypes.loadError') }}</strong>
          <button type="button" class="btn-retry" @click="store.fetchTypes()">{{ t('common.retry') }}</button>
        </div>

        <p v-else-if="sortedTypes.length === 0" class="state">{{ t('adminOpportunityTypes.empty') }}</p>

        <p v-else-if="filtered.length === 0" class="state">{{ t('adminOpportunityTypes.noMatches') }}</p>

        <div v-else class="ot-table-wrap">
          <table class="ot-table">
            <thead>
              <tr>
                <th>{{ t('adminOpportunityTypes.colName') }}</th>
                <th class="col-num">{{ t('adminOpportunityTypes.colStages') }}</th>
                <th>{{ t('adminOpportunityTypes.colStatus') }}</th>
                <th>{{ t('adminOpportunityTypes.colValidity') }}</th>
                <th class="col-actions"><span class="sr-only">{{ t('adminOpportunityTypes.colActions') }}</span></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(tp, ti) in filtered" :key="tp.id" class="ot-tr" :class="{ 'is-inactive': statusOf(tp) !== 'active' }">
                <td class="cell-name">
                  <RouterLink :to="{ name: 'admin-opportunity-type-detail', params: { id: tp.id } }" class="cell-name-link">
                    {{ tp.name }}
                  </RouterLink>
                </td>
                <td class="col-num">{{ tp.stages.length }}</td>
                <td>
                  <span class="badge" :class="`badge--${statusOf(tp)}`">
                    {{ t('adminOpportunityTypes.status_' + statusOf(tp)) }}
                  </span>
                </td>
                <td class="cell-validity">{{ validityLabel(tp) }}</td>
                <td class="col-actions">
                  <div class="ot-row-actions">
                    <template v-if="canReorder">
                      <button type="button" class="btn-icon" :title="t('adminOpportunityTypes.moveUp')" :aria-label="t('adminOpportunityTypes.moveUp')" :disabled="ti === 0" @click="moveType(ti, 'up')">↑</button>
                      <button type="button" class="btn-icon" :title="t('adminOpportunityTypes.moveDown')" :aria-label="t('adminOpportunityTypes.moveDown')" :disabled="ti === filtered.length - 1" @click="moveType(ti, 'down')">↓</button>
                    </template>
                    <RouterLink :to="{ name: 'admin-opportunity-type-detail', params: { id: tp.id } }" class="btn-icon" :title="t('adminOpportunityTypes.view')" :aria-label="t('adminOpportunityTypes.view')">
                      <IconView />
                    </RouterLink>
                    <button type="button" class="btn-icon btn-icon--danger" :title="t('admin.delete')" :aria-label="t('admin.delete')" @click="onDelete(tp)">
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

.ot-list-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
}

.ot-list-head h2 {
  margin: 0;
}

.ot-list-tools {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  flex-wrap: wrap;
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

.field {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  max-width: 420px;
  margin-bottom: 1.1rem;
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

.msg--success {
  background: #e3f6ec;
  color: #1c7a45;
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

.ot-table-wrap {
  margin-top: 1.1rem;
  overflow-x: auto;
}

.ot-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}

.ot-table thead th {
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

.ot-tr > td {
  padding: 0.7rem 0.85rem;
  color: #545f71;
  vertical-align: middle;
  border-bottom: 1px solid #eef1f6;
}

.ot-tr:hover > td {
  background: #f7f8fb;
}

.ot-tr.is-inactive > td {
  opacity: 0.72;
}

.col-num {
  width: 6rem;
  text-align: left;
}

.cell-name-link {
  display: inline-block;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-weight: 700;
  text-decoration: none;
}

.cell-name-link:hover {
  color: var(--login-primary, #ed2044);
  text-decoration: underline;
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

.cell-validity {
  white-space: nowrap;
}

.col-actions {
  text-align: right;
  white-space: nowrap;
}

.ot-row-actions {
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
  font-size: 0.95rem;
  cursor: pointer;
  transition:
    color 0.15s,
    border-color 0.15s,
    background 0.15s;
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
</style>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { Customer } from '@/stores/customers'
import {
  useActivitiesStore,
  emptyActivityFields,
  nowLocalInput,
  isoToLocalInput,
  formatDateTime,
  ACTIVITY_TYPES,
  type Activity,
  type ActivityFields,
  type ActivityType,
} from '@/stores/activities'
import { useOpportunitiesStore } from '@/stores/opportunities'
import IconEdit from '@/components/icons/IconEdit.vue'
import IconDelete from '@/components/icons/IconDelete.vue'

const props = defineProps<{
  customer: Customer
  // When set, the list is scoped to one opportunity and the form presets
  // (and hides) the opportunity selector.
  opportunityId?: number | null
}>()

const { t } = useI18n()
const store = useActivitiesStore()
const opportunitiesStore = useOpportunitiesStore()

const fixedOpportunityId = computed(() => props.opportunityId ?? null)

const ICONS: Record<ActivityType, string> = {
  call: '📞',
  meeting: '👥',
  email: '✉️',
  note: '📝',
  task: '✅',
}

const activities = computed<Activity[]>(() => {
  const all = store.list(props.customer.id)
  return null === fixedOpportunityId.value
    ? all
    : all.filter((a) => a.opportunityId === fixedOpportunityId.value)
})

const opportunities = computed(() => opportunitiesStore.list(props.customer.id))

async function load(): Promise<void> {
  await store.fetchActivities(props.customer.id)
  if (null === fixedOpportunityId.value && 0 === opportunities.value.length) {
    await opportunitiesStore.fetchOpportunities(props.customer.id)
  }
}

onMounted(load)
watch(() => props.customer.id, load)

// ── Create / edit form ────────────────────────────────────────────────
const showForm = ref(false)
const editingId = ref<number | null>(null)
const form = reactive<ActivityFields>(emptyActivityFields())
const saving = ref(false)
const formError = ref<string | null>(null)

function openNew(): void {
  editingId.value = null
  Object.assign(form, emptyActivityFields())
  form.occurredAt = nowLocalInput()
  form.opportunityId = fixedOpportunityId.value
  formError.value = null
  showForm.value = true
}

function openEdit(a: Activity): void {
  editingId.value = a.id
  form.type = a.type
  form.subject = a.subject
  form.body = a.body
  form.occurredAt = isoToLocalInput(a.occurredAt)
  form.contactId = a.contactId
  form.opportunityId = a.opportunityId
  // Preserve the responsible user across edits (the timeline form has no
  // picker yet, but we must not clear an assignee set from the dashboard).
  form.assigneeId = a.assigneeId
  formError.value = null
  showForm.value = true
}

function closeForm(): void {
  showForm.value = false
  editingId.value = null
}

async function onSubmit(): Promise<void> {
  if ('' === form.subject.trim()) {
    formError.value = t('adminCustomers.actSubjectRequired')
    return
  }
  saving.value = true
  const payload: ActivityFields = { ...form, opportunityId: fixedOpportunityId.value ?? form.opportunityId }
  const result =
    null === editingId.value
      ? await store.createActivity(props.customer.id, payload)
      : await store.updateActivity(props.customer.id, editingId.value, payload)
  saving.value = false

  if (result.ok) {
    closeForm()
  } else {
    formError.value = result.error ?? t('admin.saveFailed')
  }
}

async function onDelete(a: Activity): Promise<void> {
  if (!window.confirm(t('adminCustomers.actConfirmDelete'))) return
  const result = await store.deleteActivity(props.customer.id, a.id)
  if (!result.ok) {
    window.alert(result.error ?? t('admin.deleteFailed'))
  } else if (editingId.value === a.id) {
    closeForm()
  }
}

async function onToggleDone(a: Activity): Promise<void> {
  const result = await store.setDone(props.customer.id, a.id, null === a.completedAt)
  if (!result.ok) window.alert(result.error ?? t('admin.saveFailed'))
}

function typeLabel(type: ActivityType): string {
  return t('adminCustomers.actType_' + type)
}

const nowIso = new Date().toISOString()
function isOverdue(a: Activity): boolean {
  return a.isOpenTask && a.occurredAt < nowIso
}

function contactName(a: Activity): string {
  return a.contactName ?? ''
}
</script>

<template>
  <div class="activity-list">
    <div class="act-head">
      <button type="button" class="btn-new" @click="showForm && editingId === null ? closeForm() : openNew()">
        {{ showForm && editingId === null ? t('adminUsers.cancel') : '+ ' + t('adminCustomers.actAdd') }}
      </button>
    </div>

    <!-- ── Shared create / edit form ──────────────────────────────── -->
    <form v-if="showForm" class="act-form" @submit.prevent="onSubmit">
      <h4>{{ null === editingId ? t('adminCustomers.actAdd') : t('adminCustomers.actEdit') }}</h4>
      <div class="act-form-grid">
        <label class="field">
          <span>{{ t('adminCustomers.actTypeLabel') }}</span>
          <select v-model="form.type">
            <option v-for="ty in ACTIVITY_TYPES" :key="ty" :value="ty">{{ typeLabel(ty) }}</option>
          </select>
        </label>
        <label class="field">
          <span>{{ form.type === 'task' ? t('adminCustomers.actDueAt') : t('adminCustomers.actOccurredAt') }}</span>
          <input v-model="form.occurredAt" type="datetime-local" />
        </label>
        <label class="field field--wide">
          <span>{{ t('adminCustomers.actSubject') }}</span>
          <input v-model="form.subject" type="text" maxlength="255" />
        </label>
        <label class="field">
          <span>{{ t('adminCustomers.oppContact') }}</span>
          <select v-model.number="form.contactId">
            <option :value="null">{{ t('adminCustomers.oppNoContact') }}</option>
            <option v-for="ct in customer.contacts" :key="ct.id" :value="ct.id">
              {{ `${ct.lastName} ${ct.firstName}`.trim() || ct.email }}
            </option>
          </select>
        </label>
        <label v-if="null === fixedOpportunityId" class="field">
          <span>{{ t('adminCustomers.tabOpportunities') }}</span>
          <select v-model.number="form.opportunityId">
            <option :value="null">{{ t('adminCustomers.oppNoContact') }}</option>
            <option v-for="o in opportunities" :key="o.id" :value="o.id">{{ o.title }}</option>
          </select>
        </label>
        <label class="field field--wide">
          <span>{{ t('adminCustomers.notes') }}</span>
          <textarea v-model="form.body" rows="2" />
        </label>
      </div>

      <p v-if="formError" class="msg msg--error">{{ formError }}</p>

      <div class="form-actions">
        <button type="submit" class="btn-submit" :disabled="saving">
          {{ saving ? t('admin.saving') : null === editingId ? t('adminCustomers.actAddButton') : t('admin.save') }}
        </button>
        <button type="button" class="btn-ghost" @click="closeForm">{{ t('adminUsers.cancel') }}</button>
      </div>
    </form>

    <!-- ── Timeline ───────────────────────────────────────────────── -->
    <p v-if="activities.length === 0" class="state">{{ t('adminCustomers.actEmpty') }}</p>

    <ul v-else class="timeline">
      <li
        v-for="a in activities"
        :key="a.id"
        class="tl-item"
        :class="{ 'is-open-task': a.isOpenTask, 'is-overdue': isOverdue(a), 'is-done': null !== a.completedAt }"
      >
        <span class="tl-icon" :title="typeLabel(a.type)">{{ ICONS[a.type] }}</span>
        <div class="tl-body">
          <div class="tl-top">
            <label class="tl-check" :title="null !== a.completedAt ? t('adminCustomers.actReopen') : t('adminCustomers.actClose')">
              <input type="checkbox" :checked="null !== a.completedAt" @change="onToggleDone(a)" />
            </label>
            <span class="tl-subject">{{ a.subject }}</span>
            <span
              class="tl-badge"
              :class="{ 'tl-badge--overdue': isOverdue(a), 'tl-badge--done': null !== a.completedAt }"
            >
              {{
                null !== a.completedAt
                  ? t('adminCustomers.actClosed')
                  : isOverdue(a)
                    ? t('adminCustomers.actOverdue')
                    : t('adminCustomers.actOpen')
              }}
            </span>
          </div>
          <div class="tl-meta">
            <span class="tl-time">{{ formatDateTime(a.occurredAt) }}</span>
            <span class="tl-type">{{ typeLabel(a.type) }}</span>
            <span v-if="contactName(a)" class="tl-chip">{{ contactName(a) }}</span>
            <span v-if="a.opportunityTitle && null === fixedOpportunityId" class="tl-chip tl-chip--opp">{{ a.opportunityTitle }}</span>
            <span v-if="a.createdByName" class="tl-by">· {{ a.createdByName }}</span>
          </div>
          <p v-if="a.body" class="tl-text">{{ a.body }}</p>
        </div>
        <div class="tl-actions">
          <button type="button" class="btn-icon" :title="t('admin.edit')" :aria-label="t('admin.edit')" @click="openEdit(a)">
            <IconEdit />
          </button>
          <button type="button" class="btn-icon btn-icon--danger" :title="t('admin.delete')" :aria-label="t('admin.delete')" @click="onDelete(a)">
            <IconDelete />
          </button>
        </div>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.act-head {
  display: flex;
  justify-content: flex-end;
  margin-bottom: 1.1rem;
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

/* ── Form ─────────────────────────────────────────────────────────── */
.act-form {
  margin-bottom: 1.6rem;
  padding: 1.1rem 1.2rem 1.3rem;
  background: #fff;
  border: 1px dashed #d4dae6;
  border-radius: 0.7rem;
}

.act-form h4 {
  margin: 0 0 0.9rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-weight: 700;
}

.act-form-grid {
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

/* ── Timeline ─────────────────────────────────────────────────────── */
.timeline {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
}

.tl-item {
  display: flex;
  gap: 0.7rem;
  padding: 0.7rem 0.8rem;
  background: #fff;
  border: 1px solid #e3e7ee;
  border-left: 3px solid #9aa6bd;
  border-radius: 0.55rem;
}

.tl-item.is-open-task {
  border-left-color: #2b59c3;
  background: #f6f9ff;
}

.tl-item.is-overdue {
  border-left-color: #b3122e;
  background: #fef6f7;
}

.tl-item.is-done {
  opacity: 0.7;
}

.tl-icon {
  flex-shrink: 0;
  font-size: 1.1rem;
  line-height: 1.4;
}

.tl-body {
  flex: 1;
  min-width: 0;
}

.tl-top {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.tl-check {
  display: inline-flex;
  align-items: center;
}

.tl-check input[type='checkbox'] {
  width: 1rem;
  height: 1rem;
  accent-color: #1c7a45;
  cursor: pointer;
}

.tl-subject {
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-weight: 700;
  word-break: break-word;
}

.is-done .tl-subject {
  text-decoration: line-through;
}

.tl-badge {
  padding: 0.05rem 0.45rem;
  background: #e7eefc;
  border-radius: 0.4rem;
  color: #2b59c3;
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.tl-badge--overdue {
  background: #fde8ec;
  color: #b3122e;
}

.tl-badge--done {
  background: #e3f6ec;
  color: #1c7a45;
}

.tl-meta {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
  margin-top: 0.2rem;
  font-size: 0.78rem;
  color: #8b94a6;
}

.tl-time {
  font-weight: 700;
  color: #545f71;
}

.tl-chip {
  padding: 0.02rem 0.4rem;
  background: #eef1f6;
  border-radius: 0.4rem;
  color: #545f71;
  font-weight: 600;
}

.tl-chip--opp {
  background: #fdeef1;
  color: #b3122e;
}

.tl-text {
  margin: 0.4rem 0 0;
  color: #545f71;
  font-size: 0.85rem;
  word-break: break-word;
  white-space: pre-line;
}

.tl-actions {
  display: flex;
  gap: 0.35rem;
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

.state {
  margin: 0;
  padding: 1.3rem;
  background: #f7f8fb;
  border-radius: 0.7rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 1rem;
}

@media (max-width: 767.98px) {
  .act-form-grid {
    grid-template-columns: 1fr;
  }
}
</style>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import { useNotesStore, formatNoteDate, type Note, type NoteFields, type SendNoteFields } from '@/stores/notes'
import { useCustomersStore } from '@/stores/customers'
import { useOpportunitiesStore } from '@/stores/opportunities'
import { useUsersStore } from '@/stores/users'
import { useActivitiesStore } from '@/stores/activities'
import AppSelect from '@/components/AppSelect.vue'
import RichTextEditor, { type MentionUser } from '@/components/RichTextEditor.vue'
import { htmlToText } from '@/utils/richText'

const { t } = useI18n()
const store = useNotesStore()
const customersStore = useCustomersStore()
const opportunitiesStore = useOpportunitiesStore()
const usersStore = useUsersStore()
const activitiesStore = useActivitiesStore()
const { folders, notes, loading, error } = storeToRefs(store)
const { customers } = storeToRefs(customersStore)
const { users } = storeToRefs(usersStore)

// Template ref to the body editor, so we can confirm/cancel the @-mention
// chip once the task pop-up resolves.
const editorRef = ref<InstanceType<typeof RichTextEditor> | null>(null)

// Users offered by the editor's "@" picker (full name, fallback to email).
const mentionUsers = computed<MentionUser[]>(() =>
  [...users.value]
    .map((u) => ({ id: u.id, label: `${u.lastName} ${u.firstName}`.trim() || u.email }))
    .sort((a, b) => a.label.localeCompare(b.label, 'hu')),
)

// ── Folder tree ─────────────────────────────────────────────────────
// 'all' = every note, 'uncat' = notes with no folder, number = a folder.
type Selection = 'all' | 'uncat' | number
const selection = ref<Selection>('all')

interface TreeRow {
  folder: { id: number; name: string; parentId: number | null }
  depth: number
}

const tree = computed<TreeRow[]>(() => {
  const byParent = new Map<number | null, typeof folders.value>()
  for (const f of folders.value) {
    const list = byParent.get(f.parentId) ?? []
    list.push(f)
    byParent.set(f.parentId, list)
  }
  for (const list of byParent.values()) {
    list.sort((a, b) => a.position - b.position || a.name.localeCompare(b.name, 'hu'))
  }
  const out: TreeRow[] = []
  const walk = (parentId: number | null, depth: number): void => {
    for (const f of byParent.get(parentId) ?? []) {
      out.push({ folder: f, depth })
      walk(f.id, depth + 1)
    }
  }
  walk(null, 0)
  return out
})

function noteCount(sel: Selection): number {
  if ('all' === sel) return notes.value.length
  if ('uncat' === sel) return notes.value.filter((n) => null === n.folderId).length
  return notes.value.filter((n) => n.folderId === sel).length
}

// ── Note list (filtered by selection) ───────────────────────────────
const filteredNotes = computed<Note[]>(() => {
  if ('all' === selection.value) return notes.value
  if ('uncat' === selection.value) return notes.value.filter((n) => null === n.folderId)
  return notes.value.filter((n) => n.folderId === selection.value)
})

const selectedNoteId = ref<number | null>(null)
const selectedNote = computed(() => notes.value.find((n) => n.id === selectedNoteId.value) ?? null)

function noteTitle(n: Note): string {
  if ('' !== n.title.trim()) return n.title
  const firstLine = htmlToText(n.body).split('\n')[0]?.trim() ?? ''
  return '' !== firstLine ? firstLine : t('adminNotes.untitled')
}

function noteSnippet(n: Note): string {
  const body = htmlToText(n.body).replace(/\s+/g, ' ').trim()
  return body.length > 90 ? body.slice(0, 90) + '…' : body
}

// ── Editor ──────────────────────────────────────────────────────────
const form = reactive<NoteFields>({ title: '', body: null, folderId: null })
const saving = ref(false)
const savedFlash = ref(false)

function loadForm(n: Note | null): void {
  form.title = n?.title ?? ''
  form.body = n?.body ?? null
  form.folderId = n?.folderId ?? null
}

const dirty = computed(() => {
  const n = selectedNote.value
  if (!n) return false
  return (form.title ?? '') !== n.title || (form.body ?? '') !== (n.body ?? '') || form.folderId !== n.folderId
})

async function selectNote(id: number): Promise<void> {
  if (id === selectedNoteId.value) return
  if (dirty.value) await save()
  selectedNoteId.value = id
  loadForm(selectedNote.value)
}

async function save(): Promise<void> {
  const n = selectedNote.value
  if (!n || saving.value) return
  saving.value = true
  const result = await store.updateNote(n.id, {
    title: form.title.trim(),
    body: null === form.body || '' === form.body.trim() ? null : form.body,
    folderId: form.folderId,
  })
  saving.value = false
  if (result.ok) {
    savedFlash.value = true
    window.setTimeout(() => (savedFlash.value = false), 1500)
  } else {
    window.alert(result.error ?? t('admin.saveFailed'))
  }
}

const folderSelectOptions = computed<{ value: number | null; label: string }[]>(() => [
  { value: null, label: t('adminNotes.uncategorised') },
  ...tree.value.map((r) => ({ value: r.folder.id, label: ' '.repeat(r.depth * 2) + r.folder.name })),
])

async function createNote(): Promise<void> {
  if (dirty.value) await save()
  const folderId = 'number' === typeof selection.value ? selection.value : null
  const note = await store.createNote({ title: '', body: null, folderId })
  if (note) {
    selectedNoteId.value = note.id
    loadForm(note)
  } else {
    window.alert(t('admin.saveFailed'))
  }
}

async function removeNote(): Promise<void> {
  const n = selectedNote.value
  if (!n) return
  if (!window.confirm(t('adminNotes.confirmDeleteNote'))) return
  const result = await store.deleteNote(n.id)
  if (result.ok) {
    selectedNoteId.value = null
  } else {
    window.alert(result.error ?? t('admin.saveFailed'))
  }
}

// ── @-mention → task ─────────────────────────────────────────────────
// Picking a user from the editor's "@" list opens this pop-up; confirming
// creates a standalone task (assignee = the mentioned user) that shows up
// on the Tasks page, then drops an @name chip into the note as a marker.
const mentionTask = reactive<{
  open: boolean
  user: MentionUser | null
  subject: string
  due: string
  saving: boolean
  error: string | null
}>({ open: false, user: null, subject: '', due: '', saving: false, error: null })

function onMentionPick(payload: { user: MentionUser; suggestedSubject: string }): void {
  mentionTask.user = payload.user
  mentionTask.subject = payload.suggestedSubject
  mentionTask.due = ''
  mentionTask.error = null
  mentionTask.open = true
}

async function confirmMentionTask(): Promise<void> {
  if (null === mentionTask.user) return
  const subject = mentionTask.subject.trim()
  if ('' === subject) {
    mentionTask.error = t('adminNotes.mentionSubjectRequired')
    return
  }
  mentionTask.saving = true
  mentionTask.error = null
  const result = await activitiesStore.createTask({
    subject,
    body: null,
    occurredAt: mentionTask.due,
    assigneeId: mentionTask.user.id,
    customerId: null,
  })
  mentionTask.saving = false
  if (result.ok) {
    editorRef.value?.confirmPendingMention()
    mentionTask.open = false
  } else {
    mentionTask.error = result.error ?? t('admin.saveFailed')
  }
}

function cancelMentionTask(): void {
  editorRef.value?.cancelPendingMention()
  mentionTask.open = false
}

// ── Folder create / rename / delete ─────────────────────────────────
const editingFolderId = ref<number | null>(null)
const folderNameDraft = ref('')
const creatingFolder = ref(false)
const newFolderName = ref('')

function startCreateFolder(): void {
  creatingFolder.value = true
  newFolderName.value = ''
}

async function confirmCreateFolder(): Promise<void> {
  const name = newFolderName.value.trim()
  if ('' === name) {
    creatingFolder.value = false
    return
  }
  // New folders nest under the currently selected folder, if any.
  const parentId = 'number' === typeof selection.value ? selection.value : null
  const result = await store.createFolder({ name, parentId })
  if (!result.ok) window.alert(result.error ?? t('admin.saveFailed'))
  creatingFolder.value = false
}

function startRenameFolder(id: number, name: string): void {
  editingFolderId.value = id
  folderNameDraft.value = name
}

async function confirmRenameFolder(id: number, parentId: number | null): Promise<void> {
  const name = folderNameDraft.value.trim()
  editingFolderId.value = null
  if ('' === name) return
  const result = await store.updateFolder(id, { name, parentId })
  if (!result.ok) window.alert(result.error ?? t('admin.saveFailed'))
}

async function removeFolder(id: number): Promise<void> {
  if (!window.confirm(t('adminNotes.confirmDeleteFolder'))) return
  const result = await store.deleteFolder(id)
  if (result.ok) {
    if (selection.value === id) selection.value = 'all'
  } else {
    window.alert(result.error ?? t('admin.saveFailed'))
  }
}

// ── Send to customer modal ──────────────────────────────────────────
const showSend = ref(false)
const sendForm = reactive<{ customerId: number | null; contactId: number | null; opportunityId: number | null }>({
  customerId: null,
  contactId: null,
  opportunityId: null,
})
const sending = ref(false)
const sendError = ref<string | null>(null)

const customerSelectOptions = computed<{ value: number | null; label: string }[]>(() => [
  { value: null, label: t('adminNotes.choose') },
  ...[...customers.value]
    .sort((a, b) => a.name.localeCompare(b.name, 'hu'))
    .map((c) => ({ value: c.id, label: c.name })),
])

const sendCustomer = computed(() => customers.value.find((c) => c.id === sendForm.customerId) ?? null)

const contactSelectOptions = computed<{ value: number | null; label: string }[]>(() => [
  { value: null, label: t('adminNotes.noContact') },
  ...(sendCustomer.value?.contacts ?? []).map((c) => ({
    value: c.id,
    label: `${c.lastName} ${c.firstName}`.trim() || (c.email ?? '—'),
  })),
])

const opportunitySelectOptions = computed<{ value: number | null; label: string }[]>(() => [
  { value: null, label: t('adminNotes.noOpportunity') },
  ...(sendForm.customerId ? opportunitiesStore.list(sendForm.customerId) : []).map((o) => ({
    value: o.id,
    label: o.title,
  })),
])

async function openSend(): Promise<void> {
  if (!selectedNote.value) return
  if (dirty.value) await save()
  sendForm.customerId = null
  sendForm.contactId = null
  sendForm.opportunityId = null
  sendError.value = null
  showSend.value = true
  if (0 === customers.value.length) void customersStore.fetchCustomers()
}

// When the customer changes, load its contacts + opportunities and reset
// the dependent pickers.
watch(
  () => sendForm.customerId,
  (id) => {
    sendForm.contactId = null
    sendForm.opportunityId = null
    if (null !== id) {
      void customersStore.fetchCustomer(id)
      void opportunitiesStore.fetchOpportunities(id)
    }
  },
)

async function confirmSend(): Promise<void> {
  const n = selectedNote.value
  if (!n || null === sendForm.customerId) {
    sendError.value = t('adminNotes.customerRequired')
    return
  }
  sending.value = true
  sendError.value = null
  const payload: SendNoteFields = {
    customerId: sendForm.customerId,
    contactId: sendForm.contactId,
    opportunityId: sendForm.opportunityId,
  }
  const result = await store.sendToCustomer(n.id, payload)
  sending.value = false
  if (result.ok) {
    showSend.value = false
  } else {
    sendError.value = result.error ?? t('admin.saveFailed')
  }
}

onMounted(async () => {
  // Load the users behind the editor's "@" picker (assignees for tasks).
  if (0 === users.value.length) void usersStore.fetchUsers()
  await store.fetchAll()
  // Open the first note so the editor isn't empty on arrival.
  const first = notes.value[0]
  if (null === selectedNoteId.value && first) {
    selectedNoteId.value = first.id
    loadForm(selectedNote.value)
  }
})
</script>

<template>
  <section class="admin">
    <div class="container-lg">
      <div class="admin-head">
        <span class="eyebrow">{{ t('admin.eyebrow') }}</span>
        <h1>{{ t('nav.adminNotes') }}</h1>
        <p>{{ t('adminNotes.subtitle') }}</p>
      </div>

      <p v-if="loading" class="state">{{ t('adminNotes.loading') }}</p>

      <div v-else-if="error" class="state state--error">
        <strong>{{ error }}</strong>
        <button type="button" class="btn-retry" @click="store.fetchAll()">{{ t('common.retry') }}</button>
      </div>

      <div v-else class="nt-layout">
        <!-- ── Folder tree ──────────────────────────────────────────── -->
        <aside class="nt-tree">
          <div class="nt-tree-head">
            <span>{{ t('adminNotes.folders') }}</span>
            <button type="button" class="nt-icon-btn" :title="t('adminNotes.newFolder')" @click="startCreateFolder">＋</button>
          </div>

          <button type="button" class="nt-folder" :class="{ 'is-active': selection === 'all' }" @click="selection = 'all'">
            <span class="nt-folder-name">📚 {{ t('adminNotes.allNotes') }}</span>
            <span class="nt-folder-count">{{ noteCount('all') }}</span>
          </button>
          <button type="button" class="nt-folder" :class="{ 'is-active': selection === 'uncat' }" @click="selection = 'uncat'">
            <span class="nt-folder-name">🗂️ {{ t('adminNotes.uncategorised') }}</span>
            <span class="nt-folder-count">{{ noteCount('uncat') }}</span>
          </button>

          <div v-if="creatingFolder" class="nt-folder-edit">
            <input
              v-model="newFolderName"
              type="text"
              maxlength="255"
              :placeholder="t('adminNotes.folderNamePlaceholder')"
              @keyup.enter="confirmCreateFolder"
              @keyup.esc="creatingFolder = false"
            />
            <button type="button" class="nt-icon-btn" @click="confirmCreateFolder">✓</button>
          </div>

          <div
            v-for="row in tree"
            :key="row.folder.id"
            class="nt-folder-row"
            :style="{ paddingLeft: 0.5 + row.depth * 0.9 + 'rem' }"
          >
            <template v-if="editingFolderId === row.folder.id">
              <input
                v-model="folderNameDraft"
                type="text"
                maxlength="255"
                class="nt-folder-rename"
                @keyup.enter="confirmRenameFolder(row.folder.id, row.folder.parentId)"
                @keyup.esc="editingFolderId = null"
                @blur="confirmRenameFolder(row.folder.id, row.folder.parentId)"
              />
            </template>
            <template v-else>
              <button
                type="button"
                class="nt-folder nt-folder--nested"
                :class="{ 'is-active': selection === row.folder.id }"
                @click="selection = row.folder.id"
              >
                <span class="nt-folder-name">📁 {{ row.folder.name }}</span>
                <span class="nt-folder-count">{{ noteCount(row.folder.id) }}</span>
              </button>
              <span class="nt-folder-actions">
                <button type="button" class="nt-icon-btn" :title="t('adminNotes.rename')" @click="startRenameFolder(row.folder.id, row.folder.name)">✎</button>
                <button type="button" class="nt-icon-btn" :title="t('adminNotes.delete')" @click="removeFolder(row.folder.id)">🗑</button>
              </span>
            </template>
          </div>
        </aside>

        <!-- ── Note list ────────────────────────────────────────────── -->
        <div class="nt-list">
          <div class="nt-list-head">
            <span>{{ t('adminNotes.notes') }}</span>
            <button type="button" class="btn-new" @click="createNote">+ {{ t('adminNotes.newNote') }}</button>
          </div>

          <p v-if="filteredNotes.length === 0" class="nt-empty">{{ t('adminNotes.noNotes') }}</p>

          <ul v-else class="nt-items">
            <li
              v-for="n in filteredNotes"
              :key="n.id"
              class="nt-item"
              :class="{ 'is-active': n.id === selectedNoteId }"
              @click="selectNote(n.id)"
            >
              <div class="nt-item-top">
                <span class="nt-item-title">{{ noteTitle(n) }}</span>
                <span v-if="n.submissions.length" class="nt-sent-badge" :title="t('adminNotes.sentBadge')">✓</span>
              </div>
              <span v-if="noteSnippet(n)" class="nt-item-snippet">{{ noteSnippet(n) }}</span>
              <span class="nt-item-date">{{ formatNoteDate(n.updatedAt) }}</span>
            </li>
          </ul>
        </div>

        <!-- ── Editor ───────────────────────────────────────────────── -->
        <div class="nt-editor">
          <div v-if="!selectedNote" class="nt-editor-empty">
            <p>{{ t('adminNotes.selectOrCreate') }}</p>
            <button type="button" class="btn-new" @click="createNote">+ {{ t('adminNotes.newNote') }}</button>
          </div>

          <template v-else>
            <div class="nt-editor-bar">
              <AppSelect v-model="form.folderId" :options="folderSelectOptions" compact />
              <span class="nt-spacer" />
              <span v-if="savedFlash" class="nt-saved">{{ t('adminNotes.saved') }}</span>
              <button type="button" class="btn-submit" :disabled="saving || !dirty" @click="save">
                {{ saving ? t('admin.saving') : t('adminNotes.save') }}
              </button>
              <button type="button" class="btn-send" @click="openSend">↗ {{ t('adminNotes.sendToCustomer') }}</button>
              <button type="button" class="nt-icon-btn nt-del" :title="t('adminNotes.delete')" @click="removeNote">🗑</button>
            </div>

            <input
              v-model="form.title"
              type="text"
              maxlength="255"
              class="nt-title"
              :placeholder="t('adminNotes.titlePlaceholder')"
            />
            <RichTextEditor
              ref="editorRef"
              v-model="form.body"
              class="nt-body"
              :placeholder="t('adminNotes.bodyPlaceholder')"
              :mention-users="mentionUsers"
              @mention-pick="onMentionPick"
            />

            <!-- Submission history -->
            <div v-if="selectedNote.submissions.length" class="nt-sent">
              <h3>{{ t('adminNotes.sentHistory') }}</h3>
              <ul>
                <li v-for="s in selectedNote.submissions" :key="s.id">
                  <RouterLink
                    v-if="s.customerId"
                    :to="{ name: 'admin-customer-detail', params: { id: s.customerId } }"
                    class="nt-sent-cust"
                  >
                    {{ s.customerName }}
                  </RouterLink>
                  <span v-else class="nt-sent-cust nt-sent-cust--gone">{{ s.customerName }}</span>
                  <span class="nt-sent-date">{{ formatNoteDate(s.sentAt) }}</span>
                </li>
              </ul>
            </div>
          </template>
        </div>
      </div>
    </div>

    <!-- ── Send to customer modal ───────────────────────────────────── -->
    <div v-if="showSend" class="nt-modal-backdrop" @click.self="showSend = false">
      <div class="nt-modal" role="dialog" aria-modal="true">
        <h2>{{ t('adminNotes.sendToCustomer') }}</h2>
        <p class="nt-modal-sub">{{ t('adminNotes.sendHint') }}</p>

        <label class="field">
          <span>{{ t('adminNotes.customer') }} *</span>
          <AppSelect v-model="sendForm.customerId" :options="customerSelectOptions" />
        </label>
        <label class="field">
          <span>{{ t('adminNotes.contact') }}</span>
          <AppSelect v-model="sendForm.contactId" :options="contactSelectOptions" :disabled="!sendForm.customerId" />
        </label>
        <label class="field">
          <span>{{ t('adminNotes.opportunity') }}</span>
          <AppSelect v-model="sendForm.opportunityId" :options="opportunitySelectOptions" :disabled="!sendForm.customerId" />
        </label>

        <p v-if="sendError" class="msg msg--error">{{ sendError }}</p>

        <div class="nt-modal-actions">
          <button type="button" class="btn-submit" :disabled="sending || !sendForm.customerId" @click="confirmSend">
            {{ sending ? t('admin.saving') : t('adminNotes.sendButton') }}
          </button>
          <button type="button" class="btn-ghost" @click="showSend = false">{{ t('adminUsers.cancel') }}</button>
        </div>
      </div>
    </div>

    <!-- ── @-mention → new task modal ───────────────────────────────── -->
    <div v-if="mentionTask.open" class="nt-modal-backdrop" @click.self="cancelMentionTask">
      <div class="nt-modal" role="dialog" aria-modal="true">
        <h2>{{ t('adminNotes.mentionTaskTitle') }}</h2>
        <p class="nt-modal-sub">{{ t('adminNotes.mentionTaskHint') }}</p>

        <label class="field">
          <span>{{ t('adminNotes.mentionAssignee') }}</span>
          <div class="nt-mention-who">👤 {{ mentionTask.user?.label }}</div>
        </label>
        <label class="field">
          <span>{{ t('adminNotes.mentionSubject') }} *</span>
          <input
            v-model="mentionTask.subject"
            type="text"
            maxlength="255"
            :placeholder="t('adminNotes.mentionSubjectPlaceholder')"
            @keyup.enter="confirmMentionTask"
          />
        </label>
        <label class="field">
          <span>{{ t('adminNotes.mentionDue') }}</span>
          <input v-model="mentionTask.due" type="datetime-local" />
        </label>

        <p v-if="mentionTask.error" class="msg msg--error">{{ mentionTask.error }}</p>

        <div class="nt-modal-actions">
          <button type="button" class="btn-submit" :disabled="mentionTask.saving" @click="confirmMentionTask">
            {{ mentionTask.saving ? t('admin.saving') : t('adminNotes.mentionCreate') }}
          </button>
          <button type="button" class="btn-ghost" @click="cancelMentionTask">{{ t('adminUsers.cancel') }}</button>
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
  margin-bottom: 1.6rem;
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
}

/* ── Three-column notebook ────────────────────────────────────────── */
.nt-layout {
  display: grid;
  grid-template-columns: 16rem 20rem 1fr;
  gap: 1rem;
  align-items: start;
  min-height: 32rem;
}

.nt-tree,
.nt-list,
.nt-editor {
  background: #fff;
  border-radius: 1.1rem;
  box-shadow: 0 12px 32px rgba(12, 28, 64, 0.08);
}

.nt-tree {
  padding: 1rem 0.75rem;
}

.nt-tree-head,
.nt-list-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin: 0 0.25rem 0.7rem;
  color: #8b94a6;
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.nt-folder {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
  padding: 0.45rem 0.55rem;
  background: none;
  border: none;
  border-radius: 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.92rem;
  font-weight: 600;
  text-align: left;
  cursor: pointer;
}

.nt-folder:hover {
  background: #f6f7fb;
}

.nt-folder.is-active {
  background: #fdeef1;
  color: var(--login-primary, #ed2044);
}

.nt-folder-name {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.nt-folder-count {
  flex-shrink: 0;
  min-width: 1.4rem;
  padding: 0 0.4rem;
  background: #eef1f6;
  border-radius: 100vw;
  color: #545f71;
  font-size: 0.78rem;
  font-weight: 700;
  text-align: center;
}

.nt-folder-row {
  position: relative;
  display: flex;
  align-items: center;
}

.nt-folder--nested {
  flex: 1;
}

.nt-folder-actions {
  display: none;
  flex-shrink: 0;
  gap: 0.1rem;
}

.nt-folder-row:hover .nt-folder-actions {
  display: flex;
}

.nt-icon-btn {
  padding: 0.2rem 0.35rem;
  background: none;
  border: none;
  border-radius: 0.35rem;
  color: #8b94a6;
  font-size: 0.85rem;
  cursor: pointer;
}

.nt-icon-btn:hover {
  background: #eef1f6;
  color: var(--login-secondary, #0c1c40);
}

.nt-del:hover {
  color: var(--login-primary, #ed2044);
}

.nt-folder-edit {
  display: flex;
  gap: 0.3rem;
  padding: 0.3rem 0.25rem;
}

.nt-folder-edit input,
.nt-folder-rename {
  flex: 1;
  min-width: 0;
  padding: 0.35rem 0.5rem;
  background: #f7f8fb;
  border: 1px solid #d4dae6;
  border-radius: 0.45rem;
  font-size: 0.9rem;
  font-family: inherit;
}

.nt-folder-rename {
  margin: 0.1rem 0;
}

/* ── Note list ────────────────────────────────────────────────────── */
.nt-list {
  padding: 1rem 0.75rem;
}

.nt-items {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.nt-item {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  padding: 0.6rem 0.7rem;
  border: 1px solid #e3e7ee;
  border-radius: 0.6rem;
  cursor: pointer;
}

.nt-item:hover {
  border-color: #c7cfdd;
}

.nt-item.is-active {
  border-color: var(--login-primary, #ed2044);
  background: #fdeef1;
}

.nt-item-top {
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

.nt-item-title {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.nt-sent-badge {
  flex-shrink: 0;
  width: 1.1rem;
  height: 1.1rem;
  background: #1c7a45;
  border-radius: 50%;
  color: #fff;
  font-size: 0.7rem;
  font-weight: 700;
  line-height: 1.1rem;
  text-align: center;
}

.nt-item-snippet {
  overflow: hidden;
  color: #8b94a6;
  font-size: 0.82rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.nt-item-date {
  color: #aab2c0;
  font-size: 0.75rem;
}

.nt-empty,
.nt-editor-empty {
  padding: 1.2rem;
  color: #8b94a6;
  font-size: 0.92rem;
}

.nt-editor-empty {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.8rem;
}

/* ── Editor ───────────────────────────────────────────────────────── */
.nt-editor {
  display: flex;
  flex-direction: column;
  padding: 1.2rem 1.3rem 1.5rem;
}

.nt-editor-bar {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.9rem;
}

.nt-spacer {
  flex: 1;
}

.nt-saved {
  color: #1c7a45;
  font-size: 0.82rem;
  font-weight: 700;
}

.nt-title {
  margin-bottom: 0.6rem;
  padding: 0.4rem 0;
  border: none;
  border-bottom: 1px solid #eef1f6;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.4rem;
  font-weight: 700;
  font-family: inherit;
}

.nt-title:focus {
  outline: none;
  border-bottom-color: var(--login-primary, #ed2044);
}

.nt-body {
  flex: 1;
  min-height: 18rem;
  padding: 0.6rem 0;
  border: none;
  resize: vertical;
  color: #2b3240;
  font-size: 1rem;
  line-height: 1.6;
  font-family: inherit;
}

.nt-body:focus {
  outline: none;
}

.nt-sent {
  margin-top: 1rem;
  padding-top: 0.9rem;
  border-top: 1px solid #eef1f6;
}

.nt-sent h3 {
  margin: 0 0 0.5rem;
  color: #8b94a6;
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.nt-sent ul {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}

.nt-sent li {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  font-size: 0.85rem;
}

.nt-sent-cust {
  color: var(--login-primary, #ed2044);
  font-weight: 700;
  text-decoration: none;
}

.nt-sent-cust:hover {
  text-decoration: underline;
}

.nt-sent-cust--gone {
  color: #aab2c0;
  text-decoration: line-through;
}

.nt-sent-date {
  color: #8b94a6;
}

/* ── Buttons ──────────────────────────────────────────────────────── */
.btn-new,
.btn-submit,
.btn-send {
  padding: 0.4rem 0.9rem;
  border: none;
  border-radius: 0.5rem;
  font-size: 0.85rem;
  font-weight: 700;
  cursor: pointer;
}

.btn-new,
.btn-submit {
  background: var(--login-primary, #ed2044);
  color: #fff;
}

.btn-submit:disabled {
  opacity: 0.5;
  cursor: default;
}

.btn-send {
  background: var(--login-secondary, #0c1c40);
  color: #fff;
}

.btn-ghost {
  padding: 0.45rem 1rem;
  background: #fff;
  border: 1px solid #d4dae6;
  border-radius: 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.85rem;
  font-weight: 700;
  cursor: pointer;
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

/* ── Send modal ───────────────────────────────────────────────────── */
.nt-modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1200;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  background: rgba(12, 28, 64, 0.45);
}

.nt-modal {
  width: 100%;
  max-width: 30rem;
  padding: 1.6rem 1.7rem;
  background: #fff;
  border-radius: 1rem;
  box-shadow: 0 24px 60px rgba(12, 28, 64, 0.3);
}

.nt-modal h2 {
  margin: 0 0 0.3rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.3rem;
  font-weight: 700;
}

.nt-modal-sub {
  margin: 0 0 1.1rem;
  color: #8b94a6;
  font-size: 0.9rem;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  margin-bottom: 0.9rem;
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

.nt-mention-who {
  padding: 0.5rem 0.7rem;
  background: #e7eefc;
  border-radius: 0.5rem;
  color: #2b59c3;
  font-size: 0.92rem;
  font-weight: 700;
}

.nt-modal-actions {
  display: flex;
  gap: 0.6rem;
  margin-top: 0.4rem;
}

.msg {
  margin: 0 0 0.8rem;
  font-size: 0.88rem;
  font-weight: 600;
}

.msg--error {
  color: #b3122e;
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

@media (max-width: 991.98px) {
  .nt-layout {
    grid-template-columns: 1fr;
  }
}
</style>

<script setup lang="ts">
import { onBeforeUnmount, reactive, watch } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Placeholder from '@tiptap/extension-placeholder'
import Mention from '@tiptap/extension-mention'
import { useI18n } from 'vue-i18n'

export interface MentionUser {
  id: number
  label: string
}

const props = defineProps<{
  modelValue: string | null
  placeholder?: string
  // When provided, typing "@" offers these users; picking one emits
  // `mentionPick` so the parent can turn the line into a task. Mentions
  // are always parsed (so existing chips survive edits) even when empty.
  mentionUsers?: MentionUser[]
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string | null]
  mentionPick: [payload: { user: MentionUser; suggestedSubject: string }]
}>()

const { t } = useI18n()

// Set while we push an external value into the editor, so the resulting
// onUpdate doesn't echo back and mark the parent form dirty.
let syncing = false

// The @-mention pick that is awaiting the parent's task pop-up. We delay the
// chip insertion until the parent confirms, so cancelling leaves no trace.
let pending: { range: { from: number; to: number }; user: MentionUser } | null = null

// Reactive state backing the suggestion dropdown.
interface MenuState {
  active: boolean
  items: MentionUser[]
  index: number
  top: number
  left: number
  command: ((item: MentionUser) => void) | null
}
const menu = reactive<MenuState>({ active: false, items: [], index: 0, top: 0, left: 0, command: null })

function setMenu(p: { items: MentionUser[]; command: (item: MentionUser) => void; clientRect?: (() => DOMRect | null) | null }): void {
  menu.active = true
  menu.items = p.items
  menu.command = p.command
  if (menu.index >= p.items.length) menu.index = 0
  const rect = p.clientRect?.()
  if (rect) {
    menu.top = rect.bottom
    menu.left = rect.left
  }
}

function selectMenuItem(i: number): void {
  const item = menu.items[i]
  if (item && menu.command) menu.command(item)
  menu.active = false
}

const editor = useEditor({
  content: props.modelValue ?? '',
  extensions: [
    StarterKit.configure({ heading: { levels: [1, 2, 3] } }),
    Placeholder.configure({ placeholder: () => props.placeholder ?? '' }),
    Mention.configure({
      HTMLAttributes: { class: 'mention' },
      suggestion: {
        char: '@',
        items: ({ query }) => {
          const list = props.mentionUsers ?? []
          const q = query.toLowerCase()
          return list.filter((u) => u.label.toLowerCase().includes(q)).slice(0, 8)
        },
        // Don't insert the chip yet — capture the line text and let the
        // parent open its task pop-up. Confirm inserts; cancel discards.
        command: ({ editor, range, props: item }) => {
          const $from = editor.state.doc.resolve(range.from)
          const before = editor.state.doc.textBetween($from.start(), range.from, ' ')
          const after = editor.state.doc.textBetween(range.to, $from.end(), ' ')
          const suggestedSubject = (before + ' ' + after).replace(/\s+/g, ' ').trim()
          const user = item as unknown as MentionUser
          pending = { range: { from: range.from, to: range.to }, user }
          emit('mentionPick', { user, suggestedSubject })
        },
        render: () => ({
          onStart: (p) => setMenu(p as never),
          onUpdate: (p) => setMenu(p as never),
          onKeyDown: (p) => {
            if (!menu.active || 0 === menu.items.length) return false
            const key = p.event.key
            if ('ArrowDown' === key) {
              menu.index = (menu.index + 1) % menu.items.length
              return true
            }
            if ('ArrowUp' === key) {
              menu.index = (menu.index + menu.items.length - 1) % menu.items.length
              return true
            }
            if ('Enter' === key) {
              selectMenuItem(menu.index)
              return true
            }
            if ('Escape' === key) {
              menu.active = false
              return true
            }
            return false
          },
          onExit: () => {
            menu.active = false
          },
        }),
      },
    }),
  ],
  editorProps: {
    attributes: { class: 'rte-content' },
  },
  onUpdate: ({ editor }) => {
    if (syncing) return
    emit('update:modelValue', editor.isEmpty ? null : editor.getHTML())
  },
})

// Insert the @-mention chip the parent confirmed (after the task is created).
function confirmPendingMention(): void {
  const ed = editor.value
  if (!ed || !pending) return
  ed.chain()
    .focus()
    .insertContentAt(pending.range, [
      { type: 'mention', attrs: { id: pending.user.id, label: pending.user.label } },
      { type: 'text', text: ' ' },
    ])
    .run()
  pending = null
}

// Drop the typed "@query" when the parent cancels the task pop-up.
function cancelPendingMention(): void {
  const ed = editor.value
  if (ed && pending) ed.chain().focus().deleteRange(pending.range).run()
  pending = null
}

defineExpose({ confirmPendingMention, cancelPendingMention })

// Keep the editor in sync when the parent swaps the bound value (e.g. the
// user selects a different note). Skip no-op writes to preserve the cursor.
watch(
  () => props.modelValue,
  (value) => {
    const ed = editor.value
    if (!ed) return
    const incoming = value ?? ''
    const current = ed.isEmpty ? '' : ed.getHTML()
    if (incoming === current) return
    syncing = true
    ed.commands.setContent(incoming, { emitUpdate: false })
    syncing = false
  },
)

onBeforeUnmount(() => editor.value?.destroy())

interface ToolButton {
  key: string
  label: string
  title: string
  isActive: () => boolean
  run: () => void
}

function buttons(): ToolButton[] {
  const ed = editor.value
  if (!ed) return []
  const chain = () => ed.chain().focus()
  return [
    { key: 'h1', label: 'H1', title: t('richText.heading1'), isActive: () => ed.isActive('heading', { level: 1 }), run: () => chain().toggleHeading({ level: 1 }).run() },
    { key: 'h2', label: 'H2', title: t('richText.heading2'), isActive: () => ed.isActive('heading', { level: 2 }), run: () => chain().toggleHeading({ level: 2 }).run() },
    { key: 'h3', label: 'H3', title: t('richText.heading3'), isActive: () => ed.isActive('heading', { level: 3 }), run: () => chain().toggleHeading({ level: 3 }).run() },
    { key: 'p', label: '¶', title: t('richText.paragraph'), isActive: () => ed.isActive('paragraph'), run: () => chain().setParagraph().run() },
    { key: 'bold', label: 'B', title: t('richText.bold'), isActive: () => ed.isActive('bold'), run: () => chain().toggleBold().run() },
    { key: 'italic', label: 'I', title: t('richText.italic'), isActive: () => ed.isActive('italic'), run: () => chain().toggleItalic().run() },
    { key: 'bullet', label: '•', title: t('richText.bulletList'), isActive: () => ed.isActive('bulletList'), run: () => chain().toggleBulletList().run() },
    { key: 'ordered', label: '1.', title: t('richText.orderedList'), isActive: () => ed.isActive('orderedList'), run: () => chain().toggleOrderedList().run() },
  ]
}
</script>

<template>
  <div class="rte">
    <div v-if="editor" class="rte-toolbar">
      <button
        v-for="b in buttons()"
        :key="b.key"
        type="button"
        class="rte-btn"
        :class="{ 'is-active': b.isActive(), ['rte-btn--' + b.key]: true }"
        :title="b.title"
        :aria-label="b.title"
        @click="b.run()"
      >
        {{ b.label }}
      </button>
    </div>
    <EditorContent :editor="editor" class="rte-editor" />

    <!-- @-mention suggestion dropdown (fixed to the caret) -->
    <ul
      v-if="menu.active && menu.items.length"
      class="rte-mention-menu"
      :style="{ top: menu.top + 4 + 'px', left: menu.left + 'px' }"
    >
      <li
        v-for="(u, i) in menu.items"
        :key="u.id"
        class="rte-mention-item"
        :class="{ 'is-active': i === menu.index }"
        @mousedown.prevent="selectMenuItem(i)"
      >
        👤 {{ u.label }}
      </li>
    </ul>
  </div>
</template>

<style scoped>
.rte {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
}

.rte-toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem;
  padding-bottom: 0.6rem;
  margin-bottom: 0.6rem;
  border-bottom: 1px solid #eef1f6;
}

.rte-btn {
  min-width: 1.9rem;
  height: 1.9rem;
  padding: 0 0.45rem;
  background: #fff;
  border: 1px solid #d4dae6;
  border-radius: 0.4rem;
  color: #545f71;
  font-size: 0.85rem;
  font-weight: 700;
  cursor: pointer;
}

.rte-btn:hover {
  border-color: var(--login-secondary, #0c1c40);
  color: var(--login-secondary, #0c1c40);
}

.rte-btn.is-active {
  background: var(--login-secondary, #0c1c40);
  border-color: var(--login-secondary, #0c1c40);
  color: #fff;
}

.rte-btn--bold {
  font-weight: 800;
}

.rte-btn--italic {
  font-style: italic;
}

.rte-editor {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
}

/* TipTap renders into a .rte-content (ProseMirror) node inside EditorContent. */
.rte-editor :deep(.rte-content) {
  min-height: 16rem;
  padding: 0.2rem 0;
  color: #2b3240;
  font-size: 1rem;
  line-height: 1.6;
  outline: none;
}

.rte-editor :deep(.rte-content h1) {
  margin: 0.6rem 0 0.4rem;
  font-size: 1.6rem;
  font-weight: 800;
  color: var(--login-secondary, #0c1c40);
}

.rte-editor :deep(.rte-content h2) {
  margin: 0.6rem 0 0.35rem;
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--login-secondary, #0c1c40);
}

.rte-editor :deep(.rte-content h3) {
  margin: 0.55rem 0 0.3rem;
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--login-secondary, #0c1c40);
}

.rte-editor :deep(.rte-content p) {
  margin: 0 0 0.55rem;
}

.rte-editor :deep(.rte-content ul),
.rte-editor :deep(.rte-content ol) {
  margin: 0 0 0.55rem;
  padding-left: 1.4rem;
}

.rte-editor :deep(.rte-content li) {
  margin: 0.1rem 0;
}

.rte-editor :deep(.rte-content blockquote) {
  margin: 0 0 0.55rem;
  padding-left: 0.9rem;
  border-left: 3px solid #d4dae6;
  color: #545f71;
}

/* @mention chip inside the editor. */
.rte-editor :deep(.rte-content .mention) {
  padding: 0.05rem 0.35rem;
  background: #e7eefc;
  border-radius: 0.4rem;
  color: #2b59c3;
  font-weight: 700;
}

/* Placeholder for the empty editor. */
.rte-editor :deep(.rte-content p.is-editor-empty:first-child::before) {
  content: attr(data-placeholder);
  float: left;
  height: 0;
  color: #aab2c0;
  pointer-events: none;
}

/* ── Mention dropdown ─────────────────────────────────────────────── */
.rte-mention-menu {
  position: fixed;
  z-index: 1300;
  min-width: 12rem;
  max-height: 14rem;
  overflow-y: auto;
  margin: 0;
  padding: 0.3rem;
  list-style: none;
  background: #fff;
  border: 1px solid #d4dae6;
  border-radius: 0.6rem;
  box-shadow: 0 14px 36px rgba(12, 28, 64, 0.18);
}

.rte-mention-item {
  padding: 0.4rem 0.55rem;
  border-radius: 0.4rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
}

.rte-mention-item:hover,
.rte-mention-item.is-active {
  background: #fdeef1;
  color: var(--login-primary, #ed2044);
}
</style>

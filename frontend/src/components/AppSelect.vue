<script setup lang="ts" generic="T extends string | number | null">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

/**
 * Custom select that ALWAYS opens downward — native <select> elements are
 * flipped upward by the browser near the bottom of the viewport, which the
 * users found confusing. The option list is teleported to <body> so the
 * scrollable table wrappers (overflow-x: auto) can't clip it; if the space
 * below the toggler is tight the list scrolls internally instead of flipping.
 */

export interface AppSelectOption<V> {
  value: V
  label: string
}

const props = withDefaults(
  defineProps<{
    modelValue: T
    options: AppSelectOption<T>[]
    disabled?: boolean
    /** Shown when no option matches the current value. */
    placeholder?: string
    /** Smaller paddings/font for table cells. */
    compact?: boolean
    id?: string
  }>(),
  {
    disabled: false,
    placeholder: '',
    compact: false,
    id: undefined,
  },
)

const emit = defineEmits<{
  (e: 'update:modelValue', value: T): void
  (e: 'change', value: T): void
}>()

const open = ref(false)
const highlighted = ref(-1)
const toggleEl = ref<HTMLElement | null>(null)
const menuEl = ref<HTMLElement | null>(null)
const menuStyle = ref<Record<string, string>>({})

const selected = computed(() =>
  props.options.find((o) => o.value === props.modelValue) ?? null,
)

/* ── Positioning ──────────────────────────────────────────────────
   Fixed positioning below the toggler; recomputed on scroll/resize so
   the list tracks its anchor. Never flips above. */
function reposition() {
  const anchor = toggleEl.value
  if (!anchor) return
  const rect = anchor.getBoundingClientRect()
  const spaceBelow = window.innerHeight - rect.bottom - 12
  menuStyle.value = {
    top: `${rect.bottom + 4}px`,
    left: `${rect.left}px`,
    minWidth: `${rect.width}px`,
    maxHeight: `${Math.max(120, Math.min(280, spaceBelow))}px`,
  }
}

function openMenu() {
  if (props.disabled) return
  open.value = true
  highlighted.value = props.options.findIndex((o) => o.value === props.modelValue)
  reposition()
  nextTick(() => {
    menuEl.value
      ?.querySelector('.app-select-option.active')
      ?.scrollIntoView({ block: 'nearest' })
  })
}

function closeMenu() {
  open.value = false
}

function toggleMenu() {
  if (open.value) {
    closeMenu()
  } else {
    openMenu()
  }
}

function choose(option: AppSelectOption<T>) {
  closeMenu()
  if (option.value !== props.modelValue) {
    emit('update:modelValue', option.value)
    emit('change', option.value)
  }
}

/* ── Keyboard support ─────────────────────────────────────────────── */
function moveHighlight(delta: number) {
  if (!props.options.length) return
  const next = Math.min(
    props.options.length - 1,
    Math.max(0, (highlighted.value < 0 ? -delta : highlighted.value) + delta),
  )
  highlighted.value = next
  nextTick(() => {
    menuEl.value
      ?.querySelectorAll('.app-select-option')
      [next]?.scrollIntoView({ block: 'nearest' })
  })
}

function onKeydown(e: KeyboardEvent) {
  if (props.disabled) return
  switch (e.key) {
    case 'ArrowDown':
      e.preventDefault()
      if (open.value) {
        moveHighlight(1)
      } else {
        openMenu()
      }
      break
    case 'ArrowUp':
      e.preventDefault()
      if (open.value) {
        moveHighlight(-1)
      } else {
        openMenu()
      }
      break
    case 'Enter':
    case ' ':
      e.preventDefault()
      if (!open.value) {
        openMenu()
      } else if (highlighted.value >= 0 && props.options[highlighted.value]) {
        choose(props.options[highlighted.value]!)
      }
      break
    case 'Escape':
    case 'Tab':
      closeMenu()
      break
    case 'Home':
      if (open.value) {
        e.preventDefault()
        highlighted.value = 0
      }
      break
    case 'End':
      if (open.value) {
        e.preventDefault()
        highlighted.value = props.options.length - 1
      }
      break
  }
}

/* ── Outside click / anchor tracking ─────────────────────────────── */
function onDocPointerDown(e: MouseEvent) {
  const target = e.target as Node
  if (
    open.value &&
    !toggleEl.value?.contains(target) &&
    !menuEl.value?.contains(target)
  ) {
    closeMenu()
  }
}

function onScrollOrResize() {
  if (open.value) reposition()
}

onMounted(() => {
  document.addEventListener('mousedown', onDocPointerDown)
  window.addEventListener('scroll', onScrollOrResize, true)
  window.addEventListener('resize', onScrollOrResize)
})
onBeforeUnmount(() => {
  document.removeEventListener('mousedown', onDocPointerDown)
  window.removeEventListener('scroll', onScrollOrResize, true)
  window.removeEventListener('resize', onScrollOrResize)
})

watch(
  () => props.disabled,
  (disabled) => {
    if (disabled) closeMenu()
  },
)
</script>

<template>
  <div class="app-select" :class="{ compact }">
    <button
      :id="id"
      ref="toggleEl"
      type="button"
      class="app-select-toggle"
      :class="{ open, 'is-placeholder': !selected }"
      :disabled="disabled"
      :aria-expanded="open"
      aria-haspopup="listbox"
      @click="toggleMenu"
      @keydown="onKeydown"
    >
      <span class="app-select-label">{{ selected ? selected.label : placeholder }}</span>
      <span class="app-select-caret" aria-hidden="true"></span>
    </button>

    <Teleport to="body">
      <div
        v-if="open"
        ref="menuEl"
        class="app-select-menu"
        :class="{ compact }"
        :style="menuStyle"
        role="listbox"
      >
        <button
          v-for="(option, i) in options"
          :key="`${option.value}`"
          type="button"
          class="app-select-option"
          :class="{ active: option.value === modelValue, highlighted: i === highlighted }"
          role="option"
          :aria-selected="option.value === modelValue"
          @mouseenter="highlighted = i"
          @click="choose(option)"
        >
          {{ option.label }}
        </button>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.app-select {
  display: inline-flex;
  min-width: 0;
}

/* Mirrors the shared admin input look (#f7f8fb bg, #d4dae6 border). */
.app-select-toggle {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
  min-width: 0;
  padding: 0.55rem 0.7rem;
  background: #f7f8fb;
  border: 1px solid #d4dae6;
  border-radius: 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-family: inherit;
  text-align: left;
  cursor: pointer;
}

.app-select.compact .app-select-toggle {
  padding: 0.35rem 0.5rem;
  border-radius: 0.45rem;
  font-size: 0.85rem;
}

.app-select-toggle:focus-visible,
.app-select-toggle.open {
  border-color: var(--login-primary, #ed2044);
  outline: none;
}

.app-select-toggle:disabled {
  background: #eef0f5;
  color: #8b94a6;
  cursor: not-allowed;
}

.app-select-toggle.is-placeholder .app-select-label {
  color: #8b94a6;
}

.app-select-label {
  flex: 1 1 auto;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.app-select-caret {
  width: 0;
  height: 0;
  flex-shrink: 0;
  border-right: 4px solid transparent;
  border-left: 4px solid transparent;
  border-top: 5px solid #8b94a6;
  transition: transform 0.15s ease;
}

.app-select-toggle.open .app-select-caret {
  transform: rotate(180deg);
}
</style>

<!-- The menu is teleported to <body>, so its styles must be global. -->
<style>
.app-select-menu {
  position: fixed;
  z-index: 2000;
  display: flex;
  flex-direction: column;
  padding: 0.3rem;
  overflow-y: auto;
  background: #fff;
  border: 1px solid #e3e7ee;
  border-radius: 0.6rem;
  box-shadow: 0 14px 34px rgba(12, 28, 64, 0.18);
  animation: app-select-drop 0.14s ease-out;
  transform-origin: top center;
}

@keyframes app-select-drop {
  from {
    opacity: 0;
    transform: translateY(-6px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.app-select-option {
  display: block;
  width: 100%;
  padding: 0.45rem 0.65rem;
  background: none;
  border: none;
  border-radius: 0.45rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.92rem;
  font-family: inherit;
  text-align: left;
  cursor: pointer;
}

.app-select-menu.compact .app-select-option {
  padding: 0.35rem 0.55rem;
  font-size: 0.85rem;
}

.app-select-option.highlighted {
  background: #f3f5f9;
}

.app-select-option.active {
  color: var(--login-primary, #ed2044);
  font-weight: 700;
}
</style>

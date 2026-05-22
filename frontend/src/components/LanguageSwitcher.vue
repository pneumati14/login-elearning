<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useI18n } from 'vue-i18n'
import type { AppLocale } from '@/i18n'
import { useAuthStore } from '@/stores/auth'

interface Lang {
  code: AppLocale
  flag: string
  label: string
}

const { locale } = useI18n()
const auth = useAuthStore()

const languages: Lang[] = [
  { code: 'hu', flag: '🇭🇺', label: 'Magyar' },
  { code: 'en', flag: '🇬🇧', label: 'English' },
]

const open = ref(false)
const root = ref<HTMLElement | null>(null)

const current = computed(
  () => languages.find((lang) => lang.code === locale.value) ?? languages[0]!,
)

function choose(code: AppLocale) {
  auth.setLanguage(code)
  open.value = false
}

function onDocPointerDown(e: MouseEvent) {
  if (open.value && root.value && !root.value.contains(e.target as Node)) {
    open.value = false
  }
}

onMounted(() => document.addEventListener('mousedown', onDocPointerDown))
onBeforeUnmount(() => document.removeEventListener('mousedown', onDocPointerDown))
</script>

<template>
  <div ref="root" class="lang-switcher">
    <button
      type="button"
      class="lang-toggle"
      :class="{ open }"
      :aria-expanded="open"
      title="Language"
      @click="open = !open"
    >
      <span class="lang-flag">{{ current.flag }}</span>
      <span class="lang-caret">▾</span>
    </button>

    <div v-if="open" class="lang-menu">
      <button
        v-for="lang in languages"
        :key="lang.code"
        type="button"
        class="lang-option"
        :class="{ active: lang.code === locale }"
        @click="choose(lang.code)"
      >
        <span class="lang-flag">{{ lang.flag }}</span>
        <span>{{ lang.label }}</span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.lang-switcher {
  position: relative;
}

.lang-toggle {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.3rem 0.45rem;
  background: none;
  border: 1px solid transparent;
  border-radius: 0.5rem;
  cursor: pointer;
}

.lang-toggle:hover,
.lang-toggle.open {
  border-color: #d4dae6;
  background: #f6f7fb;
}

.lang-flag {
  font-size: 1.25rem;
  line-height: 1;
}

.lang-caret {
  color: #8b94a6;
  font-size: 0.6rem;
}

.lang-menu {
  position: absolute;
  top: calc(100% + 0.5rem);
  right: 0;
  min-width: 150px;
  padding: 0.4rem;
  background: #fff;
  border-radius: 0.7rem;
  box-shadow: 0 16px 38px rgba(12, 28, 64, 0.2);
  z-index: 1100;
}

.lang-option {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  width: 100%;
  padding: 0.5rem 0.6rem;
  background: none;
  border: none;
  border-radius: 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.92rem;
  font-weight: 700;
  text-align: left;
  cursor: pointer;
}

.lang-option:hover {
  background: #f6f7fb;
}

.lang-option.active {
  color: var(--login-primary, #ed2044);
}
</style>

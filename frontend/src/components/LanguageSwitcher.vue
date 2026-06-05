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
  { code: 'hu', flag: '/frontend-files/images/flags/hu.svg', label: 'Magyar' },
  { code: 'en', flag: '/frontend-files/images/flags/gb.svg', label: 'English' },
  { code: 'de', flag: '/frontend-files/images/flags/de.svg', label: 'Deutsch' },
  { code: 'pt', flag: '/frontend-files/images/flags/pt.svg', label: 'Português' },
  { code: 'az', flag: '/frontend-files/images/flags/az.svg', label: 'Azərbaycan' },
  { code: 'tr', flag: '/frontend-files/images/flags/tr.svg', label: 'Türkçe' },
  { code: 'pl', flag: '/frontend-files/images/flags/pl.svg', label: 'Polski' },
  { code: 'es', flag: '/frontend-files/images/flags/es.svg', label: 'Español' },
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
      <img class="lang-flag" :src="current.flag" :alt="current.label" />
      <span class="lang-caret" aria-hidden="true"></span>
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
        <img class="lang-flag" :src="lang.flag" :alt="lang.label" />
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
  border-color: rgba(255, 255, 255, 0.25);
  background: rgba(255, 255, 255, 0.12);
}

/* Crisp SVG flag shown as a modern circular chip. */
.lang-flag {
  display: block;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  object-fit: cover;
  box-shadow: 0 0 0 1px rgba(12, 28, 64, 0.15);
}

.lang-caret {
  width: 0;
  height: 0;
  border-right: 4px solid transparent;
  border-left: 4px solid transparent;
  border-top: 5px solid rgba(255, 255, 255, 0.7);
  transition: transform 0.15s ease;
}

.lang-toggle.open .lang-caret {
  transform: rotate(180deg);
}

.lang-menu {
  position: absolute;
  top: calc(100% + 0.5rem);
  bottom: auto;
  right: 0;
  animation: menu-drop-down 0.16s ease-out;
  transform-origin: top center;
  min-width: 150px;
  padding: 0.4rem;
  background: #fff;
  border-radius: 0.7rem;
  box-shadow: 0 16px 38px rgba(12, 28, 64, 0.2);
  z-index: 1100;
}

@keyframes menu-drop-down {
  from {
    opacity: 0;
    transform: translateY(-8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
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

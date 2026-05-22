import { createI18n } from 'vue-i18n'
import hu from './locales/hu'
import en from './locales/en'

export type AppLocale = 'hu' | 'en'

const STORAGE_KEY = 'app-locale'

function initialLocale(): AppLocale {
  const saved = localStorage.getItem(STORAGE_KEY)
  // No saved choice → default to English.
  return saved === 'en' || saved === 'hu' ? saved : 'en'
}

export const i18n = createI18n({
  legacy: false,
  locale: initialLocale(),
  fallbackLocale: 'en',
  messages: { hu, en },
})

/** Switches the UI language and remembers the choice (works without an account). */
export function setLocale(locale: AppLocale): void {
  i18n.global.locale.value = locale
  localStorage.setItem(STORAGE_KEY, locale)
  document.documentElement.lang = locale
}

document.documentElement.lang = i18n.global.locale.value

import { createI18n } from 'vue-i18n'
import hu from './locales/hu'
import en from './locales/en'
import az from './locales/az'
import de from './locales/de'
import pt from './locales/pt'
import tr from './locales/tr'
import pl from './locales/pl'

export type AppLocale = 'hu' | 'en' | 'az' | 'de' | 'pt' | 'tr' | 'pl'

const STORAGE_KEY = 'app-locale'

function initialLocale(): AppLocale {
  const saved = localStorage.getItem(STORAGE_KEY)
  // No saved choice → default to English.
  return saved === 'en' ||
    saved === 'hu' ||
    saved === 'az' ||
    saved === 'de' ||
    saved === 'pt' ||
    saved === 'tr' ||
    saved === 'pl'
    ? saved
    : 'en'
}

export const i18n = createI18n({
  legacy: false,
  locale: initialLocale(),
  fallbackLocale: 'en',
  messages: { hu, en, az, de, pt, tr, pl },
})

/** Switches the UI language and remembers the choice (works without an account). */
export function setLocale(locale: AppLocale): void {
  i18n.global.locale.value = locale
  localStorage.setItem(STORAGE_KEY, locale)
  document.documentElement.lang = locale
}

document.documentElement.lang = i18n.global.locale.value

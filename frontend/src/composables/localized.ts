import { useI18n } from 'vue-i18n'

/**
 * Admin-entered text stored in English (the required base) and Hungarian
 * (optional). When a language is missing it falls back to English.
 * This is the shape returned by the API.
 */
export interface LocalizedText {
  en: string
  hu: string | null
}

/** A form-editable bilingual value — Hungarian normalised to a string. */
export interface LocalizedDraft {
  en: string
  hu: string
}

/** A blank bilingual value for new-entry forms. */
export function emptyLocalized(): LocalizedDraft {
  return { en: '', hu: '' }
}

/** A form-editable copy of a bilingual value from the API. */
export function toLocalizedDraft(field?: LocalizedText | null): LocalizedDraft {
  return { en: field?.en ?? '', hu: field?.hu ?? '' }
}

/**
 * Returns a picker `l()` that resolves a bilingual value for the active
 * UI locale, falling back to English. Call inside `setup()`; the result
 * is reactive to the language switcher.
 */
export function useLocalized() {
  const { locale } = useI18n()

  function l(field: LocalizedText | LocalizedDraft | null | undefined): string {
    if (!field) return ''
    if ('hu' === locale.value && field.hu) return field.hu
    return field.en
  }

  return { l }
}

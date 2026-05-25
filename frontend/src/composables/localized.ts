import { useI18n } from 'vue-i18n'

/**
 * Admin-entered text stored in English (the required base), Hungarian,
 * Azerbaijani, German, Portuguese, Turkish and Polish (all optional).
 * When a language is missing it falls back to English. This is the shape
 * returned by the API.
 */
export interface LocalizedText {
  en: string
  hu: string | null
  az: string | null
  de: string | null
  pt: string | null
  tr: string | null
  pl: string | null
}

/** A form-editable multilingual value — optional languages normalised to strings. */
export interface LocalizedDraft {
  en: string
  hu: string
  az: string
  de: string
  pt: string
  tr: string
  pl: string
}

/** A blank multilingual value for new-entry forms. */
export function emptyLocalized(): LocalizedDraft {
  return { en: '', hu: '', az: '', de: '', pt: '', tr: '', pl: '' }
}

/** A form-editable copy of a multilingual value from the API. */
export function toLocalizedDraft(field?: LocalizedText | null): LocalizedDraft {
  return {
    en: field?.en ?? '',
    hu: field?.hu ?? '',
    az: field?.az ?? '',
    de: field?.de ?? '',
    pt: field?.pt ?? '',
    tr: field?.tr ?? '',
    pl: field?.pl ?? '',
  }
}

/**
 * Returns a picker `l()` that resolves a multilingual value for the active
 * UI locale, falling back to English. Call inside `setup()`; the result
 * is reactive to the language switcher.
 */
export function useLocalized() {
  const { locale } = useI18n()

  function l(field: LocalizedText | LocalizedDraft | null | undefined): string {
    if (!field) return ''
    if ('hu' === locale.value && field.hu) return field.hu
    if ('az' === locale.value && field.az) return field.az
    if ('de' === locale.value && field.de) return field.de
    if ('pt' === locale.value && field.pt) return field.pt
    if ('tr' === locale.value && field.tr) return field.tr
    if ('pl' === locale.value && field.pl) return field.pl
    return field.en
  }

  return { l }
}

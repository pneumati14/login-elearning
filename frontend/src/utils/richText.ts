import DOMPurify from 'dompurify'

/**
 * Rich-text helpers shared by the notes editor and the activity timeline.
 *
 * Note bodies are now HTML produced by the TipTap editor (headings, lists,
 * paragraphs, bold/italic). The markup is constrained to the editor's schema
 * already, but we still sanitise on render (defence in depth) and provide a
 * plain-text projection for snippets, titles and the activity subject.
 */

// The tags/attributes the editor can ever emit. Anything else is dropped.
// `span` + the data-* below carry @mention chips (see RichTextEditor).
const ALLOWED_TAGS = [
  'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike',
  'h1', 'h2', 'h3', 'ul', 'ol', 'li', 'blockquote', 'a', 'code', 'pre', 'span',
]
const ALLOWED_ATTR = ['href', 'target', 'rel', 'class', 'data-type', 'data-id', 'data-label']

/** Sanitise editor HTML before injecting it with v-html. */
export function sanitizeHtml(html: string | null | undefined): string {
  if (!html) return ''
  return DOMPurify.sanitize(html, { ALLOWED_TAGS, ALLOWED_ATTR })
}

/**
 * Flatten HTML to plain text, turning block boundaries into newlines so a
 * collapsed snippet keeps word spacing (e.g. "Heading Item one Item two").
 */
export function htmlToText(html: string | null | undefined): string {
  if (!html) return ''
  const withBreaks = html
    .replace(/<\/(p|h1|h2|h3|li|div|blockquote|pre)>/gi, '\n')
    .replace(/<br\s*\/?>/gi, '\n')
  const el = document.createElement('div')
  el.innerHTML = withBreaks
  return (el.textContent ?? '').replace(/ /g, ' ')
}

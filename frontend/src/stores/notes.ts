import { ref } from 'vue'
import { defineStore } from 'pinia'
import type { MutationResult } from './auth'

const API_URL = import.meta.env.VITE_API_URL || '/api'

/** A folder in the private notebook tree. */
export interface NoteFolder {
  id: number
  name: string
  parentId: number | null
  position: number
  createdAt: string
  updatedAt: string
}

/** A record that a note was sent to a customer as an activity. */
export interface NoteSubmission {
  id: number
  customerId: number | null
  customerName: string
  activityId: number | null
  sentAt: string
}

/** A private note. */
export interface Note {
  id: number
  title: string
  body: string | null
  folderId: number | null
  submissions: NoteSubmission[]
  createdAt: string
  updatedAt: string
}

export interface NoteFields {
  title: string
  body: string | null
  folderId: number | null
}

export interface FolderFields {
  name: string
  parentId: number | null
}

/** Payload of the "send to customer" action. */
export interface SendNoteFields {
  customerId: number
  contactId: number | null
  opportunityId: number | null
}

async function readError(response: Response, fallback: string): Promise<string> {
  const data = (await response.json().catch(() => null)) as { error?: string } | null
  return data && data.error ? data.error : fallback
}

/**
 * The CRM notes page: per-user private notes and the folder tree. Notes
 * and folders are flat lists in state; the view builds the tree from the
 * parent links and filters notes by the selected folder.
 */
export const useNotesStore = defineStore('notes', () => {
  const folders = ref<NoteFolder[]>([])
  const notes = ref<Note[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  function sortNotes(list: Note[]): Note[] {
    return [...list].sort((a, b) => (a.updatedAt < b.updatedAt ? 1 : a.updatedAt > b.updatedAt ? -1 : b.id - a.id))
  }

  function upsertNote(note: Note): void {
    const idx = notes.value.findIndex((n) => n.id === note.id)
    notes.value = sortNotes(-1 === idx ? [...notes.value, note] : notes.value.map((n) => (n.id === note.id ? note : n)))
  }

  function upsertFolder(folder: NoteFolder): void {
    const idx = folders.value.findIndex((f) => f.id === folder.id)
    folders.value = -1 === idx ? [...folders.value, folder] : folders.value.map((f) => (f.id === folder.id ? folder : f))
  }

  // ── Loading ───────────────────────────────────────────────────────
  async function fetchAll(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const [foldersRes, notesRes] = await Promise.all([
        fetch(`${API_URL}/admin/note-folders`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' }),
        fetch(`${API_URL}/admin/notes`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' }),
      ])
      if (!foldersRes.ok || !notesRes.ok) {
        throw new Error('A jegyzetek betöltése nem sikerült.')
      }
      folders.value = (await foldersRes.json()) as NoteFolder[]
      notes.value = sortNotes((await notesRes.json()) as Note[])
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Ismeretlen hiba történt.'
    } finally {
      loading.value = false
    }
  }

  // ── Folders ───────────────────────────────────────────────────────
  async function createFolder(fields: FolderFields): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/note-folders`, {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(fields),
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, 'A mappa létrehozása nem sikerült.') }
      }
      upsertFolder((await response.json()) as NoteFolder)
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  async function updateFolder(id: number, fields: FolderFields): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/note-folders/${id}`, {
        method: 'PUT',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(fields),
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, 'A mentés nem sikerült.') }
      }
      upsertFolder((await response.json()) as NoteFolder)
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  async function deleteFolder(id: number): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/note-folders/${id}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, 'A törlés nem sikerült.') }
      }
      const deletedIds = new Set<number>()
      // The backend cascades sub-folders; mirror that locally and detach
      // any notes that were inside (they fall back to uncategorised).
      const collect = (parentId: number): void => {
        deletedIds.add(parentId)
        folders.value.filter((f) => f.parentId === parentId).forEach((f) => collect(f.id))
      }
      collect(id)
      folders.value = folders.value.filter((f) => !deletedIds.has(f.id))
      notes.value = notes.value.map((n) => (null !== n.folderId && deletedIds.has(n.folderId) ? { ...n, folderId: null } : n))
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  // ── Notes ─────────────────────────────────────────────────────────
  async function createNote(fields: NoteFields): Promise<Note | null> {
    try {
      const response = await fetch(`${API_URL}/admin/notes`, {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(fields),
      })
      if (!response.ok) return null
      const note = (await response.json()) as Note
      upsertNote(note)
      return note
    } catch {
      return null
    }
  }

  async function updateNote(id: number, fields: NoteFields): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/notes/${id}`, {
        method: 'PUT',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(fields),
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, 'A mentés nem sikerült.') }
      }
      upsertNote((await response.json()) as Note)
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  async function deleteNote(id: number): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/notes/${id}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, 'A törlés nem sikerült.') }
      }
      notes.value = notes.value.filter((n) => n.id !== id)
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  /** Send a copy of the note to a customer as an activity. Returns the
   *  updated note (with the new submission) on success. */
  async function sendToCustomer(id: number, fields: SendNoteFields): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/notes/${id}/send`, {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(fields),
      })
      if (!response.ok) {
        return { ok: false, error: await readError(response, 'A beküldés nem sikerült.') }
      }
      upsertNote((await response.json()) as Note)
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  return {
    folders,
    notes,
    loading,
    error,
    fetchAll,
    createFolder,
    updateFolder,
    deleteFolder,
    createNote,
    updateNote,
    deleteNote,
    sendToCustomer,
  }
})

/** Human-friendly date+time, e.g. "2026-06-03 14:30". */
export function formatNoteDate(iso: string): string {
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return iso
  const pad = (n: number): string => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`
}

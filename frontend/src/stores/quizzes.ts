import { defineStore } from 'pinia'
import type { MutationResult } from './auth'
import type { LocalizedText } from '@/composables/localized'

const API_URL = import.meta.env.VITE_API_URL || '/api'

// ── Quiz-taking shapes (no correct-answer data) ──────────────────────
export interface TakeOption {
  id: number
  text: string
}
export interface TakeQuestion {
  id: number
  text: string
  options: TakeOption[]
}
export interface TakeQuiz {
  id: number
  title: LocalizedText
  passThreshold: number
  questions: TakeQuestion[]
  lastAttempt: { score: number; total: number; passed: boolean } | null
}
export interface AttemptResult {
  score: number
  total: number
  percent: number
  passed: boolean
}

// ── Admin authoring shapes (with correct flags) ──────────────────────
export interface AdminOption {
  id: number
  text: string
  correct: boolean
  position: number
}
export interface AdminQuestion {
  id: number
  text: string
  position: number
  options: AdminOption[]
}
export interface AdminQuiz {
  id: number
  passThreshold: number
  questions: AdminQuestion[]
}
export interface QuizSavePayload {
  passThreshold: number
  questions: { text: string; options: { text: string; correct: boolean }[] }[]
}

export const useQuizzesStore = defineStore('quizzes', () => {
  /** Loads a quiz for taking (without the correct answers). */
  async function fetchTakeQuiz(id: number): Promise<TakeQuiz | null> {
    try {
      const response = await fetch(`${API_URL}/quizzes/${id}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
      return response.ok ? ((await response.json()) as TakeQuiz) : null
    } catch {
      return null
    }
  }

  async function submitAttempt(
    id: number,
    answers: Record<number, number>,
  ): Promise<{ ok: true; result: AttemptResult } | { ok: false; error: string }> {
    try {
      const response = await fetch(`${API_URL}/quizzes/${id}/attempt`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ answers }),
      })
      const raw = (await response.json().catch(() => null)) as Record<string, unknown> | null

      if (!response.ok) {
        const message = raw && typeof raw.error === 'string' ? raw.error : 'A beküldés nem sikerült.'
        return { ok: false, error: message }
      }

      return {
        ok: true,
        result: {
          score: Number(raw?.score ?? 0),
          total: Number(raw?.total ?? 0),
          percent: Number(raw?.percent ?? 0),
          passed: Boolean(raw?.passed),
        },
      }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  /** Creates (or returns) the quiz of a course/lesson; yields its id. */
  async function ensureQuiz(owner: 'courses' | 'lessons', ownerId: number): Promise<number | null> {
    try {
      const response = await fetch(`${API_URL}/admin/${owner}/${ownerId}/quiz`, {
        method: 'POST',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
      if (!response.ok) return null
      const data = (await response.json()) as { id: number }
      return data.id
    } catch {
      return null
    }
  }

  async function fetchAdminQuiz(id: number): Promise<AdminQuiz | null> {
    try {
      const response = await fetch(`${API_URL}/admin/quizzes/${id}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
      return response.ok ? ((await response.json()) as AdminQuiz) : null
    } catch {
      return null
    }
  }

  async function saveQuiz(id: number, payload: QuizSavePayload): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/quizzes/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
      })
      if (!response.ok) {
        const data = (await response.json().catch(() => null)) as { error?: string } | null
        return { ok: false, error: data?.error || 'A mentés nem sikerült.' }
      }
      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  async function deleteQuiz(id: number): Promise<MutationResult> {
    try {
      const response = await fetch(`${API_URL}/admin/quizzes/${id}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
      return response.ok
        ? { ok: true }
        : { ok: false, error: 'A törlés nem sikerült.' }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  return { fetchTakeQuiz, submitAttempt, ensureQuiz, fetchAdminQuiz, saveQuiz, deleteQuiz }
})

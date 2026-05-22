import { ref } from 'vue'
import { defineStore } from 'pinia'
import type { MutationResult } from './auth'
import type { LocalizedText } from '@/composables/localized'

const API_URL = import.meta.env.VITE_API_URL || '/api'

/** Compact quiz descriptor attached to a course or a lesson. */
export interface QuizRef {
  id: number
  questionCount: number
  passed: boolean
}

export interface CertificateRef {
  id: number
  code: string
}

/** A course as it appears in the listing. */
export interface Course {
  id: number
  title: LocalizedText
  slug: string
  description: LocalizedText
  lessonCount: number
  createdAt: string
  coverUrl: string | null
  enrolled: boolean
  completedLessons: number
}

export interface Lesson {
  id: number
  title: LocalizedText
  position: number
  content: LocalizedText
  youtubeUrl: string | null
  videoUrl: string | null
  pdfUrl: string | null
  coverUrl: string | null
  completed: boolean
  quiz: QuizRef | null
}

/** A single course with its lessons, returned by the detail endpoint. */
export interface CourseDetail {
  id: number
  title: LocalizedText
  slug: string
  description: LocalizedText
  lessonCount: number
  createdAt: string
  coverUrl: string | null
  enrolled: boolean
  certificate: CertificateRef | null
  quiz: QuizRef | null
  lessons: Lesson[]
}

export const useCoursesStore = defineStore('courses', () => {
  const courses = ref<Course[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  const current = ref<CourseDetail | null>(null)
  const currentLoading = ref(false)
  const currentError = ref<string | null>(null)

  async function fetchCourses(): Promise<void> {
    loading.value = true
    error.value = null

    try {
      const response = await fetch(`${API_URL}/courses`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })

      if (!response.ok) {
        throw new Error(`A szerver ${response.status} hibakóddal válaszolt.`)
      }

      courses.value = (await response.json()) as Course[]
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Ismeretlen hiba történt.'
    } finally {
      loading.value = false
    }
  }

  /**
   * Loads one course. In `silent` mode the previous data stays visible
   * while refreshing — used after an action to pick up new state.
   */
  async function fetchCourse(slug: string, silent = false): Promise<void> {
    if (!silent) {
      currentLoading.value = true
      current.value = null
    }
    currentError.value = null

    try {
      const response = await fetch(`${API_URL}/courses/${encodeURIComponent(slug)}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })

      if (response.status === 404) {
        if (!silent) currentError.value = 'A keresett kurzus nem található.'
        return
      }
      if (!response.ok) {
        throw new Error(`A szerver ${response.status} hibakóddal válaszolt.`)
      }

      current.value = (await response.json()) as CourseDetail
    } catch (e) {
      if (!silent) {
        currentError.value = e instanceof Error ? e.message : 'Ismeretlen hiba történt.'
      }
    } finally {
      if (!silent) currentLoading.value = false
    }
  }

  async function mutate(url: string, method: 'POST' | 'DELETE'): Promise<MutationResult> {
    try {
      const response = await fetch(url, {
        method,
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })

      if (!response.ok) {
        const data = (await response.json().catch(() => null)) as { error?: string } | null
        return { ok: false, error: data?.error || 'A művelet nem sikerült.' }
      }

      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  function applyEnrolled(courseId: number, enrolled: boolean): void {
    if (current.value && current.value.id === courseId) {
      current.value.enrolled = enrolled
    }
    const listed = courses.value.find((c) => c.id === courseId)
    if (listed) {
      listed.enrolled = enrolled
    }
  }

  async function enroll(courseId: number): Promise<MutationResult> {
    const result = await mutate(`${API_URL}/courses/${courseId}/enroll`, 'POST')
    if (result.ok) applyEnrolled(courseId, true)
    return result
  }

  async function unenroll(courseId: number): Promise<MutationResult> {
    const result = await mutate(`${API_URL}/courses/${courseId}/enroll`, 'DELETE')
    if (result.ok) applyEnrolled(courseId, false)
    return result
  }

  async function setLessonComplete(lessonId: number, done: boolean): Promise<MutationResult> {
    const result = await mutate(`${API_URL}/lessons/${lessonId}/complete`, done ? 'POST' : 'DELETE')
    if (result.ok && current.value) {
      const lesson = current.value.lessons.find((l) => l.id === lessonId)
      if (lesson) lesson.completed = done
      // Refresh in the background to pick up a possible new certificate.
      await fetchCourse(current.value.slug, true)
    }
    return result
  }

  return {
    courses,
    loading,
    error,
    current,
    currentLoading,
    currentError,
    fetchCourses,
    fetchCourse,
    enroll,
    unenroll,
    setLessonComplete,
  }
})

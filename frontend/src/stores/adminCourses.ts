import { defineStore } from 'pinia'
import type { MutationResult } from './auth'
import type { LocalizedText } from '@/composables/localized'

const API_URL = import.meta.env.VITE_API_URL || '/api'

/**
 * Admin-only write operations for courses, lessons and lesson media.
 * Each action returns a plain ok/error result; callers re-fetch the
 * read endpoints afterwards to keep the displayed data consistent.
 */
export const useAdminCoursesStore = defineStore('adminCourses', () => {
  async function send(
    url: string,
    method: 'POST' | 'PUT' | 'DELETE',
    body?: Record<string, unknown>,
  ): Promise<MutationResult> {
    try {
      const response = await fetch(url, {
        method,
        headers: body
          ? { 'Content-Type': 'application/json', Accept: 'application/json' }
          : { Accept: 'application/json' },
        credentials: 'same-origin',
        body: body ? JSON.stringify(body) : undefined,
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

  /** Multipart upload — the browser sets the Content-Type boundary itself. */
  async function sendFile(url: string, file: File): Promise<MutationResult> {
    const formData = new FormData()
    formData.append('file', file)
    try {
      const response = await fetch(url, {
        method: 'POST',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        body: formData,
      })

      if (!response.ok) {
        const data = (await response.json().catch(() => null)) as { error?: string } | null
        return { ok: false, error: data?.error || 'A feltöltés nem sikerült.' }
      }

      return { ok: true }
    } catch {
      return { ok: false, error: 'Nem sikerült elérni a szervert.' }
    }
  }

  const createCourse = (
    title: LocalizedText,
    description: LocalizedText,
  ): Promise<MutationResult> => send(`${API_URL}/admin/courses`, 'POST', { title, description })

  const updateCourse = (
    id: number,
    title: LocalizedText,
    description: LocalizedText,
  ): Promise<MutationResult> => send(`${API_URL}/admin/courses/${id}`, 'PUT', { title, description })

  const deleteCourse = (id: number): Promise<MutationResult> =>
    send(`${API_URL}/admin/courses/${id}`, 'DELETE')

  const createLesson = (
    courseId: number,
    title: LocalizedText,
    content: LocalizedText,
  ): Promise<MutationResult> =>
    send(`${API_URL}/admin/courses/${courseId}/lessons`, 'POST', { title, content })

  const updateLesson = (
    id: number,
    fields: {
      title: LocalizedText
      content: LocalizedText
      position?: number
      youtubeUrl?: string
    },
  ): Promise<MutationResult> => send(`${API_URL}/admin/lessons/${id}`, 'PUT', { ...fields })

  const deleteLesson = (id: number): Promise<MutationResult> =>
    send(`${API_URL}/admin/lessons/${id}`, 'DELETE')

  const uploadVideo = (lessonId: number, file: File): Promise<MutationResult> =>
    sendFile(`${API_URL}/admin/lessons/${lessonId}/video`, file)

  const deleteVideo = (lessonId: number): Promise<MutationResult> =>
    send(`${API_URL}/admin/lessons/${lessonId}/video`, 'DELETE')

  const uploadPdf = (lessonId: number, file: File): Promise<MutationResult> =>
    sendFile(`${API_URL}/admin/lessons/${lessonId}/pdf`, file)

  const deletePdf = (lessonId: number): Promise<MutationResult> =>
    send(`${API_URL}/admin/lessons/${lessonId}/pdf`, 'DELETE')

  const uploadLessonCover = (lessonId: number, file: File): Promise<MutationResult> =>
    sendFile(`${API_URL}/admin/lessons/${lessonId}/cover`, file)

  const deleteLessonCover = (lessonId: number): Promise<MutationResult> =>
    send(`${API_URL}/admin/lessons/${lessonId}/cover`, 'DELETE')

  const uploadCourseCover = (courseId: number, file: File): Promise<MutationResult> =>
    sendFile(`${API_URL}/admin/courses/${courseId}/cover`, file)

  const deleteCourseCover = (courseId: number): Promise<MutationResult> =>
    send(`${API_URL}/admin/courses/${courseId}/cover`, 'DELETE')

  return {
    createCourse,
    updateCourse,
    deleteCourse,
    createLesson,
    updateLesson,
    deleteLesson,
    uploadVideo,
    deleteVideo,
    uploadPdf,
    deletePdf,
    uploadLessonCover,
    deleteLessonCover,
    uploadCourseCover,
    deleteCourseCover,
  }
})

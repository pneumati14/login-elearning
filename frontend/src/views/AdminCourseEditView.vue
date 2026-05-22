<script setup lang="ts">
import { onMounted, ref, reactive, watch, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import { useCoursesStore } from '@/stores/courses'
import { useAdminCoursesStore } from '@/stores/adminCourses'
import { useQuizzesStore } from '@/stores/quizzes'
import { emptyLocalized, toLocalizedDraft, type LocalizedDraft } from '@/composables/localized'
import LocalizedInput from '@/components/LocalizedInput.vue'

const { t } = useI18n()

interface LessonDraft {
  id: number
  title: LocalizedDraft
  content: LocalizedDraft
  youtubeUrl: string
  videoUrl: string | null
  pdfUrl: string | null
  coverUrl: string | null
  position: number
  saving: boolean
  mediaBusy: boolean
  msg: string | null
  msgOk: boolean
}

const route = useRoute()
const router = useRouter()
const coursesStore = useCoursesStore()
const adminStore = useAdminCoursesStore()
const quizStore = useQuizzesStore()
const { current, currentLoading, currentError } = storeToRefs(coursesStore)

const slug = computed(() => String(route.params.slug))

const courseForm = reactive({ title: emptyLocalized(), description: emptyLocalized() })
const courseSaving = ref(false)
const courseMsg = ref<string | null>(null)

const drafts = ref<LessonDraft[]>([])
const pageError = ref<string | null>(null)

const newLesson = reactive({ title: emptyLocalized(), content: emptyLocalized() })
const addingLesson = ref(false)
const addError = ref<string | null>(null)

function load() {
  coursesStore.fetchCourse(slug.value)
}

onMounted(load)

// Keep the editable drafts in sync whenever the course is (re)loaded.
watch(current, (course) => {
  if (!course) return
  courseForm.title = toLocalizedDraft(course.title)
  courseForm.description = toLocalizedDraft(course.description)
  drafts.value = course.lessons.map((lesson) => ({
    id: lesson.id,
    title: toLocalizedDraft(lesson.title),
    content: toLocalizedDraft(lesson.content),
    youtubeUrl: lesson.youtubeUrl ?? '',
    videoUrl: lesson.videoUrl,
    pdfUrl: lesson.pdfUrl,
    coverUrl: lesson.coverUrl,
    position: lesson.position,
    saving: false,
    mediaBusy: false,
    msg: null,
    msgOk: true,
  }))
})

async function saveCourse() {
  if (!current.value) return
  courseMsg.value = null
  courseSaving.value = true
  const result = await adminStore.updateCourse(
    current.value.id,
    courseForm.title,
    courseForm.description,
  )
  courseSaving.value = false
  courseMsg.value = result.ok ? t('adminCourseEdit.courseSaved') : (result.error ?? t('admin.saveFailed'))
}

async function saveLesson(draft: LessonDraft) {
  draft.msg = null
  draft.saving = true
  const result = await adminStore.updateLesson(draft.id, {
    title: draft.title,
    content: draft.content,
    position: draft.position,
    youtubeUrl: draft.youtubeUrl.trim(),
  })
  draft.saving = false
  draft.msgOk = result.ok
  draft.msg = result.ok ? t('adminCourseEdit.lessonSaved') : (result.error ?? t('admin.saveFailed'))
}

type MediaKind = 'video' | 'pdf' | 'cover'

async function uploadMedia(draft: LessonDraft, kind: MediaKind, event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file) return

  draft.msg = null
  draft.mediaBusy = true
  const result =
    kind === 'video'
      ? await adminStore.uploadVideo(draft.id, file)
      : kind === 'pdf'
        ? await adminStore.uploadPdf(draft.id, file)
        : await adminStore.uploadLessonCover(draft.id, file)
  draft.mediaBusy = false

  if (result.ok) {
    if (kind === 'video') draft.videoUrl = `/api/lessons/${draft.id}/video`
    else if (kind === 'pdf') draft.pdfUrl = `/api/lessons/${draft.id}/pdf`
    else draft.coverUrl = `/api/lessons/${draft.id}/cover`
  } else {
    draft.msgOk = false
    draft.msg = result.error ?? t('adminCourseEdit.uploadFailed')
  }
}

async function removeMedia(draft: LessonDraft, kind: MediaKind) {
  draft.msg = null
  draft.mediaBusy = true
  const result =
    kind === 'video'
      ? await adminStore.deleteVideo(draft.id)
      : kind === 'pdf'
        ? await adminStore.deletePdf(draft.id)
        : await adminStore.deleteLessonCover(draft.id)
  draft.mediaBusy = false

  if (result.ok) {
    if (kind === 'video') draft.videoUrl = null
    else if (kind === 'pdf') draft.pdfUrl = null
    else draft.coverUrl = null
  } else {
    draft.msgOk = false
    draft.msg = result.error ?? t('admin.deleteFailed')
  }
}

const courseCoverBusy = ref(false)

async function uploadCourseCover(event: Event) {
  if (!current.value) return
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file) return

  courseCoverBusy.value = true
  const result = await adminStore.uploadCourseCover(current.value.id, file)
  courseCoverBusy.value = false
  if (result.ok) {
    current.value.coverUrl = `/api/courses/${current.value.id}/cover`
  } else {
    window.alert(result.error ?? t('adminCourseEdit.uploadFailed'))
  }
}

async function removeCourseCover() {
  if (!current.value) return
  courseCoverBusy.value = true
  const result = await adminStore.deleteCourseCover(current.value.id)
  courseCoverBusy.value = false
  if (result.ok) {
    current.value.coverUrl = null
  } else {
    window.alert(result.error ?? t('admin.deleteFailed'))
  }
}

async function removeLesson(draft: LessonDraft) {
  if (!window.confirm(t('adminCourseEdit.confirmDeleteLesson', { title: draft.title.en }))) return
  const result = await adminStore.deleteLesson(draft.id)
  if (result.ok) {
    load()
  } else {
    window.alert(result.error ?? t('admin.deleteFailed'))
  }
}

async function move(index: number, direction: -1 | 1) {
  const a = drafts.value[index]
  const b = drafts.value[index + direction]
  if (!a || !b) return

  pageError.value = null
  const [first, second] = await Promise.all([
    adminStore.updateLesson(a.id, { title: a.title, content: a.content, position: b.position }),
    adminStore.updateLesson(b.id, { title: b.title, content: b.content, position: a.position }),
  ])

  if (first.ok && second.ok) {
    load()
  } else {
    pageError.value = t('adminCourseEdit.reorderFailed')
  }
}

async function openQuiz(owner: 'courses' | 'lessons', ownerId: number) {
  pageError.value = null
  const quizId = await quizStore.ensureQuiz(owner, ownerId)
  if (null === quizId) {
    pageError.value = t('adminCourseEdit.openQuizFailed')
    return
  }
  router.push(`/admin/quizzes/${quizId}?course=${slug.value}`)
}

async function addLesson() {
  if (!current.value) return
  addError.value = null
  addingLesson.value = true
  const result = await adminStore.createLesson(
    current.value.id,
    newLesson.title,
    newLesson.content,
  )
  addingLesson.value = false
  if (result.ok) {
    newLesson.title = emptyLocalized()
    newLesson.content = emptyLocalized()
    load()
  } else {
    addError.value = result.error ?? t('adminCourseEdit.addLessonFailed')
  }
}
</script>

<template>
  <section class="editor">
    <div class="container-lg">
      <RouterLink to="/admin/courses" class="back-link">{{ t('adminCourseEdit.back') }}</RouterLink>

      <p v-if="currentLoading" class="state">{{ t('course.loading') }}</p>

      <div v-else-if="currentError" class="state state--error">
        <strong>{{ currentError }}</strong>
        <RouterLink to="/admin/courses" class="btn-submit">{{ t('course.coursesLink') }}</RouterLink>
      </div>

      <template v-else-if="current">
        <div class="editor-head">
          <span class="eyebrow">{{ t('admin.eyebrow') }}</span>
          <h1>{{ t('adminCourseEdit.title') }}</h1>
        </div>

        <!-- ── Course fields ─────────────────────────────────────── -->
        <div class="ed-panel">
          <h2>{{ t('adminCourseEdit.courseData') }}</h2>
          <LocalizedInput v-model="courseForm.title" :label="t('admin.title')" required />
          <LocalizedInput
            v-model="courseForm.description"
            :label="t('admin.description')"
            multiline
          />
          <div class="field">
            <span class="field-label">{{ t('adminCourseEdit.cover') }}</span>
            <div v-if="current.coverUrl" class="cover-state">
              <img :src="current.coverUrl" alt="" class="cover-thumb" />
              <button
                type="button"
                class="btn-mini"
                :disabled="courseCoverBusy"
                @click="removeCourseCover"
              >
                {{ t('account.remove') }}
              </button>
            </div>
            <input
              v-else
              type="file"
              accept="image/*"
              :disabled="courseCoverBusy"
              @change="uploadCourseCover($event)"
            />
          </div>
          <p v-if="courseMsg" class="msg msg--success">{{ courseMsg }}</p>
          <div class="panel-actions">
            <button type="button" class="btn-submit" :disabled="courseSaving" @click="saveCourse">
              {{ courseSaving ? t('admin.saving') : t('adminCourseEdit.saveCourse') }}
            </button>
            <button type="button" class="btn-quiz-link" @click="openQuiz('courses', current.id)">
              {{ t('adminCourseEdit.editFinalQuiz') }}
            </button>
          </div>
        </div>

        <!-- ── Lessons ───────────────────────────────────────────── -->
        <div class="ed-panel">
          <h2>{{ t('adminCourseEdit.lessonsCount', { count: drafts.length }) }}</h2>

          <p v-if="pageError" class="msg msg--error">{{ pageError }}</p>
          <p v-if="drafts.length === 0" class="state">{{ t('course.noLessons') }}</p>

          <div v-for="(draft, index) in drafts" :key="draft.id" class="lesson-block">
            <div class="lesson-block-head">
              <span class="lesson-num">{{ index + 1 }}.</span>
              <div class="pos-controls">
                <button
                  type="button"
                  class="pos-btn"
                  :disabled="index === 0"
                  :title="t('adminCourseEdit.moveUp')"
                  @click="move(index, -1)"
                >
                  ▲
                </button>
                <button
                  type="button"
                  class="pos-btn"
                  :disabled="index === drafts.length - 1"
                  :title="t('adminCourseEdit.moveDown')"
                  @click="move(index, 1)"
                >
                  ▼
                </button>
              </div>
            </div>

            <LocalizedInput
              v-model="draft.title"
              :label="t('adminCourseEdit.lessonTitle')"
              required
            />
            <LocalizedInput
              v-model="draft.content"
              :label="t('adminCourseEdit.lessonContent')"
              multiline
            />
            <label class="field">
              <span class="field-label">{{ t('adminCourseEdit.youtubeUrl') }}</span>
              <input
                v-model="draft.youtubeUrl"
                type="text"
                placeholder="https://www.youtube.com/watch?v=…"
              />
            </label>

            <div class="media-row">
              <div class="media-item">
                <span class="field-label">{{ t('adminCourseEdit.video') }}</span>
                <div v-if="draft.videoUrl" class="media-state">
                  <span class="media-ok">{{ t('adminCourseEdit.uploaded') }}</span>
                  <button
                    type="button"
                    class="btn-mini"
                    :disabled="draft.mediaBusy"
                    @click="removeMedia(draft, 'video')"
                  >
                    {{ t('account.remove') }}
                  </button>
                </div>
                <input
                  v-else
                  type="file"
                  accept="video/*"
                  :disabled="draft.mediaBusy"
                  @change="uploadMedia(draft, 'video', $event)"
                />
              </div>

              <div class="media-item">
                <span class="field-label">{{ t('adminCourseEdit.pdf') }}</span>
                <div v-if="draft.pdfUrl" class="media-state">
                  <span class="media-ok">{{ t('adminCourseEdit.uploaded') }}</span>
                  <button
                    type="button"
                    class="btn-mini"
                    :disabled="draft.mediaBusy"
                    @click="removeMedia(draft, 'pdf')"
                  >
                    {{ t('account.remove') }}
                  </button>
                </div>
                <input
                  v-else
                  type="file"
                  accept="application/pdf"
                  :disabled="draft.mediaBusy"
                  @change="uploadMedia(draft, 'pdf', $event)"
                />
              </div>

              <div class="media-item">
                <span class="field-label">{{ t('adminCourseEdit.cover') }}</span>
                <div v-if="draft.coverUrl" class="media-state">
                  <img :src="draft.coverUrl" alt="" class="cover-thumb-sm" />
                  <button
                    type="button"
                    class="btn-mini"
                    :disabled="draft.mediaBusy"
                    @click="removeMedia(draft, 'cover')"
                  >
                    {{ t('account.remove') }}
                  </button>
                </div>
                <input
                  v-else
                  type="file"
                  accept="image/*"
                  :disabled="draft.mediaBusy"
                  @change="uploadMedia(draft, 'cover', $event)"
                />
              </div>
            </div>

            <p v-if="draft.mediaBusy" class="media-busy">{{ t('adminCourseEdit.uploadingMedia') }}</p>
            <p v-if="draft.msg" class="msg" :class="draft.msgOk ? 'msg--success' : 'msg--error'">
              {{ draft.msg }}
            </p>

            <div class="lesson-block-actions">
              <button type="button" class="btn-submit" :disabled="draft.saving" @click="saveLesson(draft)">
                {{ draft.saving ? t('admin.saving') : t('adminCourseEdit.saveLesson') }}
              </button>
              <button type="button" class="btn-quiz-link" @click="openQuiz('lessons', draft.id)">
                {{ t('adminCourseEdit.lessonQuiz') }}
              </button>
              <button type="button" class="btn-delete" @click="removeLesson(draft)">
                {{ t('admin.delete') }}
              </button>
            </div>
          </div>
        </div>

        <!-- ── Add lesson ────────────────────────────────────────── -->
        <form class="ed-panel" @submit.prevent="addLesson">
          <h2>{{ t('adminCourseEdit.addLessonTitle') }}</h2>
          <LocalizedInput
            v-model="newLesson.title"
            :label="t('adminCourseEdit.lessonTitle')"
            required
          />
          <LocalizedInput
            v-model="newLesson.content"
            :label="t('adminCourseEdit.lessonContent')"
            multiline
          />
          <p class="hint">{{ t('adminCourseEdit.addLessonHint') }}</p>
          <p v-if="addError" class="msg msg--error">{{ addError }}</p>
          <button type="submit" class="btn-submit" :disabled="addingLesson">
            {{ addingLesson ? t('adminCourseEdit.adding') : t('adminCourseEdit.addLesson') }}
          </button>
        </form>
      </template>
    </div>
  </section>
</template>

<style scoped>
.editor {
  padding: 2.5rem 0 5rem;
}

.back-link {
  display: inline-block;
  margin-bottom: 1.6rem;
  color: #545f71;
  font-size: 0.95rem;
  font-weight: 700;
}

.back-link:hover {
  color: var(--login-primary, #ed2044);
}

.editor-head {
  margin-bottom: 1.8rem;
}

.eyebrow {
  display: inline-block;
  color: var(--login-primary, #ed2044);
  font-size: 0.9rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.editor-head h1 {
  margin: 0.35rem 0 0;
  color: var(--login-secondary, #0c1c40);
  font-size: 2.3rem;
  font-weight: 700;
}

.ed-panel {
  margin-bottom: 1.5rem;
  padding: 1.85rem;
  background: #fff;
  border-radius: 1.1rem;
  box-shadow: 0 12px 32px rgba(12, 28, 64, 0.08);
}

.ed-panel h2 {
  margin: 0 0 1.3rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.25rem;
  font-weight: 700;
}

.field {
  display: block;
  margin-bottom: 1rem;
}

.field-label {
  display: block;
  margin-bottom: 0.35rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.88rem;
  font-weight: 700;
}

.field input,
.field textarea {
  width: 100%;
  padding: 0.65rem 0.8rem;
  border: 1px solid #d4dae6;
  border-radius: 0.55rem;
  font-size: 0.98rem;
  font-family: inherit;
  color: var(--login-secondary, #0c1c40);
  background: #fff;
  resize: vertical;
}

.field input:focus,
.field textarea:focus {
  outline: none;
  border-color: var(--login-primary, #ed2044);
}

.msg {
  margin: 0 0 0.9rem;
  padding: 0.65rem 0.85rem;
  border-radius: 0.55rem;
  font-size: 0.9rem;
}

.msg--error {
  background: #fde8ec;
  color: #b3122e;
}

.msg--success {
  background: #e3f6ec;
  color: #1c7a45;
}

.hint {
  margin: 0 0 0.9rem;
  color: #8b94a6;
  font-size: 0.85rem;
}

.btn-submit {
  padding: 0.65rem 1.3rem;
  background: var(--login-primary, #ed2044);
  border: none;
  border-radius: 0.55rem;
  color: #fff;
  font-size: 0.98rem;
  font-weight: 700;
  cursor: pointer;
}

.btn-submit:disabled {
  opacity: 0.65;
  cursor: progress;
}

.lesson-block {
  margin-bottom: 1.1rem;
  padding: 1.3rem;
  background: #f7f8fb;
  border-radius: 0.8rem;
}

.lesson-block-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.7rem;
}

.lesson-num {
  color: var(--login-secondary, #0c1c40);
  font-size: 1.05rem;
  font-weight: 700;
}

.pos-controls {
  display: flex;
  gap: 0.35rem;
}

.pos-btn {
  width: 2rem;
  height: 2rem;
  background: #fff;
  border: 1px solid #d4dae6;
  border-radius: 0.45rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.7rem;
  cursor: pointer;
}

.pos-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.media-row {
  display: grid;
  gap: 1rem;
  grid-template-columns: 1fr 1fr 1fr;
  margin-bottom: 1rem;
}

.cover-state {
  display: flex;
  align-items: center;
  gap: 0.7rem;
  margin-top: 0.4rem;
}

.cover-thumb {
  width: 120px;
  height: 72px;
  object-fit: cover;
  border-radius: 0.45rem;
  border: 1px solid #d4dae6;
}

.cover-thumb-sm {
  width: 100%;
  max-width: 110px;
  height: 60px;
  object-fit: cover;
  border-radius: 0.4rem;
  border: 1px solid #d4dae6;
}

.media-item {
  padding: 0.8rem;
  background: #fff;
  border: 1px solid #e3e7ee;
  border-radius: 0.55rem;
}

.media-item input[type='file'] {
  width: 100%;
  margin-top: 0.4rem;
  font-size: 0.85rem;
}

.media-state {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  margin-top: 0.4rem;
}

.media-ok {
  color: #1c7a45;
  font-size: 0.9rem;
  font-weight: 700;
}

.btn-mini {
  padding: 0.25rem 0.6rem;
  background: #fff;
  border: 1px solid #e0a9b3;
  border-radius: 0.4rem;
  color: #b3122e;
  font-size: 0.78rem;
  font-weight: 700;
  cursor: pointer;
}

.btn-mini:disabled {
  opacity: 0.5;
  cursor: progress;
}

.media-busy {
  margin: 0 0 0.9rem;
  color: #8b94a6;
  font-size: 0.88rem;
  font-style: italic;
}

.lesson-block-actions {
  display: flex;
  gap: 0.5rem;
}

.btn-delete {
  padding: 0.65rem 1.1rem;
  background: #fff;
  border: 1px solid #e0a9b3;
  border-radius: 0.55rem;
  color: #b3122e;
  font-size: 0.98rem;
  font-weight: 700;
  cursor: pointer;
}

.btn-delete:hover {
  background: var(--login-primary, #ed2044);
  border-color: var(--login-primary, #ed2044);
  color: #fff;
}

.state {
  padding: 1.3rem;
  background: #f7f8fb;
  border-radius: 0.7rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 1rem;
}

.state--error {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.8rem;
}

.panel-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.6rem;
}

.btn-quiz-link {
  padding: 0.65rem 1.1rem;
  background: #fff;
  border: 1px solid var(--login-primary, #ed2044);
  border-radius: 0.55rem;
  color: var(--login-primary, #ed2044);
  font-size: 0.95rem;
  font-weight: 700;
  cursor: pointer;
}

.btn-quiz-link:hover {
  background: var(--login-primary, #ed2044);
  color: #fff;
}

@media (max-width: 575.98px) {
  .media-row {
    grid-template-columns: 1fr;
  }
}
</style>

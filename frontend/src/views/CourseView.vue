<script setup lang="ts">
import { onMounted, ref, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { useCoursesStore } from '@/stores/courses'
import { useLocalized } from '@/composables/localized'

const { t } = useI18n()
const { l } = useLocalized()
const route = useRoute()
const store = useCoursesStore()
const { current, currentLoading, currentError } = storeToRefs(store)

const openLessonId = ref<number | null>(null)
const actionError = ref<string | null>(null)
const busy = ref(false)

const slug = computed(() => String(route.params.slug))

function load() {
  store.fetchCourse(slug.value)
}

onMounted(load)
watch(slug, load)

const completedCount = computed(
  () => current.value?.lessons.filter((l) => l.completed).length ?? 0,
)
const totalCount = computed(() => current.value?.lessons.length ?? 0)
const progressPercent = computed(() =>
  totalCount.value === 0 ? 0 : Math.round((completedCount.value / totalCount.value) * 100),
)

function toggleLesson(id: number) {
  openLessonId.value = openLessonId.value === id ? null : id
}

async function toggleEnroll() {
  if (!current.value) return
  actionError.value = null
  busy.value = true
  const result = current.value.enrolled
    ? await store.unenroll(current.value.id)
    : await store.enroll(current.value.id)
  busy.value = false
  if (!result.ok) actionError.value = result.error ?? t('common.actionFailed')
}

async function toggleComplete(lessonId: number, done: boolean) {
  actionError.value = null
  const result = await store.setLessonComplete(lessonId, done)
  if (!result.ok) actionError.value = result.error ?? t('common.actionFailed')
}

/** Build an embeddable YouTube URL from the common link formats. */
function youtubeEmbedUrl(url: string | null): string | null {
  if (!url) return null
  const match = url.match(
    /(?:youtube\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/)|youtu\.be\/)([\w-]{11})/,
  )
  return match ? `https://www.youtube.com/embed/${match[1]}` : null
}
</script>

<template>
  <section class="course-detail">
    <div class="container-lg">
      <RouterLink to="/e-learning" class="back-link">← {{ t('course.back') }}</RouterLink>

      <p v-if="currentLoading" class="state">{{ t('course.loading') }}</p>

      <div v-else-if="currentError" class="state state--error">
        <strong>{{ currentError }}</strong>
        <RouterLink to="/e-learning" class="btn-primary">{{ t('course.coursesLink') }}</RouterLink>
      </div>

      <template v-else-if="current">
        <div class="course-hero">
          <img v-if="current.coverUrl" :src="current.coverUrl" alt="" class="hero-cover" />
          <span class="eyebrow">{{ t('courses.eyebrow') }}</span>
          <h1>{{ l(current.title) }}</h1>
          <p v-if="l(current.description)" class="course-desc">{{ l(current.description) }}</p>

          <div class="hero-actions">
            <button
              type="button"
              class="btn-enroll"
              :class="{ 'btn-enroll--out': current.enrolled }"
              :disabled="busy"
              @click="toggleEnroll"
            >
              {{ current.enrolled ? t('course.unenroll') : t('course.enroll') }}
            </button>
            <span v-if="current.enrolled" class="enrolled-tag">{{ t('courses.enrolled') }}</span>
          </div>

          <div v-if="current.enrolled && totalCount > 0" class="course-progress">
            <div class="progress-track">
              <div class="progress-fill" :style="{ width: progressPercent + '%' }"></div>
            </div>
            <span class="progress-label">
              {{ t('course.progress', { done: completedCount, total: totalCount, percent: progressPercent }) }}
            </span>
          </div>

          <RouterLink
            v-if="current.certificate"
            :to="`/certificates/${current.certificate.id}`"
            class="cert-banner"
          >
            🏅 {{ t('course.certBanner') }}
          </RouterLink>

          <p v-if="actionError" class="msg msg--error">{{ actionError }}</p>
        </div>

        <h2 class="lessons-title">{{ t('course.lessons') }}</h2>

        <p v-if="totalCount === 0" class="state">{{ t('course.noLessons') }}</p>

        <ul v-else class="lesson-list">
          <li
            v-for="lesson in current.lessons"
            :key="lesson.id"
            class="lesson-item"
            :class="{ 'lesson-item--done': lesson.completed }"
          >
            <button type="button" class="lesson-head" @click="toggleLesson(lesson.id)">
              <span class="lesson-mark" :class="{ 'lesson-mark--done': lesson.completed }">
                {{ lesson.completed ? '✓' : lesson.position }}
              </span>
              <span class="lesson-title">{{ l(lesson.title) }}</span>
              <span class="lesson-chevron">{{ openLessonId === lesson.id ? '▲' : '▼' }}</span>
            </button>

            <div v-if="openLessonId === lesson.id" class="lesson-body">
              <img v-if="lesson.coverUrl" :src="lesson.coverUrl" alt="" class="lesson-cover" />
              <p v-if="l(lesson.content)" class="lesson-content">{{ l(lesson.content) }}</p>

              <div v-if="youtubeEmbedUrl(lesson.youtubeUrl)" class="media-embed">
                <iframe
                  :src="youtubeEmbedUrl(lesson.youtubeUrl) || ''"
                  title="YouTube"
                  allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                  allowfullscreen
                ></iframe>
              </div>

              <video v-if="lesson.videoUrl" class="media-video" :src="lesson.videoUrl" controls></video>

              <a
                v-if="lesson.pdfUrl"
                :href="lesson.pdfUrl"
                target="_blank"
                rel="noopener"
                class="media-pdf"
              >
                📄 {{ t('course.openPdf') }}
              </a>

              <p
                v-if="!l(lesson.content) && !lesson.youtubeUrl && !lesson.videoUrl && !lesson.pdfUrl"
                class="lesson-content"
              >
                {{ t('course.noMaterial') }}
              </p>

              <div v-if="current.enrolled" class="lesson-actions">
                <button
                  v-if="!lesson.completed"
                  type="button"
                  class="btn-done"
                  @click="toggleComplete(lesson.id, true)"
                >
                  {{ t('course.markDone') }}
                </button>
                <button
                  v-else
                  type="button"
                  class="btn-undone"
                  @click="toggleComplete(lesson.id, false)"
                >
                  {{ t('course.markUndone') }}
                </button>
              </div>
              <p v-else class="lesson-hint">{{ t('course.enrollHint') }}</p>

              <RouterLink
                v-if="lesson.quiz"
                :to="`/quizzes/${lesson.quiz.id}`"
                class="quiz-link"
                :class="{ 'quiz-link--done': lesson.quiz.passed }"
              >
                {{
                  lesson.quiz.passed
                    ? t('course.lessonQuizDone')
                    : t('course.lessonQuizTake', { count: lesson.quiz.questionCount })
                }}
              </RouterLink>
            </div>
          </li>
        </ul>

        <div v-if="current.quiz" class="course-quiz">
          <div class="course-quiz-text">
            <h2>{{ t('course.finalQuiz') }}</h2>
            <p>
              {{
                current.quiz.passed
                  ? t('course.finalQuizDone', { count: current.quiz.questionCount })
                  : t('course.finalQuizTodo', { count: current.quiz.questionCount })
              }}
            </p>
          </div>
          <RouterLink
            :to="`/quizzes/${current.quiz.id}`"
            class="btn-quiz"
            :class="{ 'btn-quiz--done': current.quiz.passed }"
          >
            {{ current.quiz.passed ? t('course.quizRetake') : t('course.quizTake') }}
          </RouterLink>
        </div>
      </template>
    </div>
  </section>
</template>

<style scoped>
.course-detail {
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

.course-hero {
  margin-bottom: 2.4rem;
  padding: 2.2rem;
  background: #fff;
  border-radius: 1.1rem;
  box-shadow: 0 12px 32px rgba(12, 28, 64, 0.08);
  overflow: hidden;
}

.hero-cover {
  display: block;
  width: calc(100% + 4.4rem);
  height: 240px;
  margin: -2.2rem -2.2rem 1.5rem;
  object-fit: cover;
}

.lesson-cover {
  display: block;
  width: 100%;
  max-height: 260px;
  margin: 0.6rem 0 1rem;
  object-fit: cover;
  border-radius: 0.5rem;
}

.eyebrow {
  display: inline-block;
  color: var(--login-primary, #ed2044);
  font-size: 0.9rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.course-hero h1 {
  margin: 0.35rem 0 0.7rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 2.3rem;
  font-weight: 700;
}

.course-desc {
  margin: 0;
  color: #545f71;
  font-size: 1.08rem;
  line-height: 1.55;
}

.hero-actions {
  display: flex;
  align-items: center;
  gap: 0.9rem;
  margin-top: 1.5rem;
}

.btn-enroll {
  padding: 0.7rem 1.4rem;
  background: var(--login-primary, #ed2044);
  border: 1px solid var(--login-primary, #ed2044);
  border-radius: 0.6rem;
  color: #fff;
  font-size: 1rem;
  font-weight: 700;
  cursor: pointer;
  transition: transform 0.15s ease;
}

.btn-enroll:hover:not(:disabled) {
  transform: translateY(-2px);
}

.btn-enroll:disabled {
  opacity: 0.65;
  cursor: progress;
}

.btn-enroll--out {
  background: #fff;
  color: #545f71;
  border-color: #d4dae6;
}

.enrolled-tag {
  padding: 0.32rem 0.85rem;
  background: #e3f6ec;
  border-radius: 100vw;
  color: #1c7a45;
  font-size: 0.82rem;
  font-weight: 700;
}

.course-progress {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  margin-top: 1.5rem;
}

.progress-track {
  height: 10px;
  background: #eef1f6;
  border-radius: 100vw;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: #1c7a45;
  border-radius: 100vw;
  transition: width 0.25s ease;
}

.progress-label {
  color: #8b94a6;
  font-size: 0.85rem;
  font-weight: 700;
}

.msg {
  margin: 1rem 0 0;
  padding: 0.65rem 0.85rem;
  border-radius: 0.55rem;
  font-size: 0.9rem;
}

.msg--error {
  background: #fde8ec;
  color: #b3122e;
}

.lessons-title {
  margin: 0 0 1.1rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.5rem;
  font-weight: 700;
}

.lesson-list {
  display: flex;
  flex-direction: column;
  gap: 0.7rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.lesson-item {
  background: #fff;
  border-radius: 0.8rem;
  box-shadow: 0 8px 22px rgba(12, 28, 64, 0.07);
  overflow: hidden;
}

.lesson-head {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  width: 100%;
  padding: 1rem 1.2rem;
  background: none;
  border: none;
  cursor: pointer;
  text-align: left;
}

.lesson-mark {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 1.8rem;
  height: 1.8rem;
  background: #eef1f6;
  border-radius: 100vw;
  color: #545f71;
  font-size: 0.9rem;
  font-weight: 700;
}

.lesson-mark--done {
  background: #1c7a45;
  color: #fff;
}

.lesson-title {
  flex: 1;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.05rem;
  font-weight: 700;
}

.lesson-chevron {
  color: #8b94a6;
  font-size: 0.7rem;
}

.lesson-body {
  padding: 0 1.2rem 1.2rem;
}

.lesson-content {
  margin: 0;
  padding: 0.6rem 0 1rem;
  color: #404a5c;
  font-size: 1rem;
  line-height: 1.6;
  white-space: pre-wrap;
}

.media-embed {
  position: relative;
  width: 100%;
  margin: 0 0 1rem;
  padding-bottom: 56.25%;
  background: #000;
  border-radius: 0.6rem;
  overflow: hidden;
}

.media-embed iframe {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  border: 0;
}

.media-video {
  display: block;
  width: 100%;
  max-height: 460px;
  margin: 0 0 1rem;
  background: #000;
  border-radius: 0.6rem;
}

.media-pdf {
  display: inline-block;
  margin: 0 0 1rem;
  padding: 0.6rem 1.1rem;
  background: #f7f8fb;
  border: 1px solid #d4dae6;
  border-radius: 0.55rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-weight: 700;
}

.media-pdf:hover {
  border-color: var(--login-primary, #ed2044);
  color: var(--login-primary, #ed2044);
}

.lesson-actions {
  display: flex;
}

.btn-done,
.btn-undone {
  padding: 0.5rem 1.1rem;
  border-radius: 0.5rem;
  font-size: 0.9rem;
  font-weight: 700;
  cursor: pointer;
}

.btn-done {
  background: #1c7a45;
  border: 1px solid #1c7a45;
  color: #fff;
}

.btn-undone {
  background: #fff;
  border: 1px solid #d4dae6;
  color: #545f71;
}

.lesson-hint {
  margin: 0;
  color: #8b94a6;
  font-size: 0.88rem;
  font-style: italic;
}

.state {
  padding: 1.6rem;
  background: #fff;
  border-radius: 1.1rem;
  box-shadow: 0 12px 32px rgba(12, 28, 64, 0.08);
  color: var(--login-secondary, #0c1c40);
  font-size: 1.05rem;
}

.state--error {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.8rem;
}

.btn-primary {
  display: inline-block;
  padding: 0.5rem 1.1rem;
  background: var(--login-primary, #ed2044);
  border-radius: 0.5rem;
  color: #fff;
  font-weight: 700;
}

.cert-banner {
  display: block;
  margin-top: 1.5rem;
  padding: 0.9rem 1.1rem;
  background: #e3f6ec;
  border-radius: 0.6rem;
  color: #1c7a45;
  font-size: 0.98rem;
  font-weight: 700;
}

.cert-banner:hover {
  background: #d3efe0;
}

.quiz-link {
  display: inline-block;
  margin-top: 0.9rem;
  padding: 0.55rem 1rem;
  background: #fff;
  border: 1px solid var(--login-primary, #ed2044);
  border-radius: 0.5rem;
  color: var(--login-primary, #ed2044);
  font-size: 0.92rem;
  font-weight: 700;
}

.quiz-link:hover {
  background: var(--login-primary, #ed2044);
  color: #fff;
}

.quiz-link--done {
  border-color: #1c7a45;
  color: #1c7a45;
}

.quiz-link--done:hover {
  background: #1c7a45;
  color: #fff;
}

.course-quiz {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 1rem;
  margin-top: 1.5rem;
  padding: 1.6rem 1.8rem;
  background: #fff;
  border-radius: 1rem;
  box-shadow: 0 12px 32px rgba(12, 28, 64, 0.08);
}

.course-quiz-text h2 {
  margin: 0 0 0.2rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.3rem;
  font-weight: 700;
}

.course-quiz-text p {
  margin: 0;
  color: #545f71;
  font-size: 0.95rem;
}

.btn-quiz {
  flex-shrink: 0;
  padding: 0.7rem 1.4rem;
  background: var(--login-primary, #ed2044);
  border-radius: 0.6rem;
  color: #fff;
  font-size: 1rem;
  font-weight: 700;
}

.btn-quiz--done {
  background: #1c7a45;
}

@media (max-width: 575.98px) {
  .course-hero h1 {
    font-size: 1.8rem;
  }

  .hero-actions {
    flex-wrap: wrap;
  }
}
</style>

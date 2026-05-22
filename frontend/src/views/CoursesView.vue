<script setup lang="ts">
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { useCoursesStore, type Course } from '@/stores/courses'
import { useLocalized } from '@/composables/localized'

const { t } = useI18n()
const { l } = useLocalized()
const store = useCoursesStore()
const { courses, loading, error } = storeToRefs(store)

onMounted(() => {
  store.fetchCourses()
})

function progressPercent(course: Course): number {
  if (course.lessonCount === 0) return 0
  return Math.round((course.completedLessons / course.lessonCount) * 100)
}
</script>

<template>
  <section class="courses">
    <div class="container-lg">
      <div class="courses-head">
        <span class="eyebrow">{{ t('courses.eyebrow') }}</span>
        <h1>{{ t('courses.title') }}</h1>
        <p>{{ t('courses.subtitle') }}</p>
      </div>

      <p v-if="loading" class="state">{{ t('courses.loading') }}</p>

      <div v-else-if="error" class="state state--error">
        <strong>{{ t('courses.loadError') }}</strong>
        <span>{{ error }}</span>
        <button type="button" class="btn-retry" @click="store.fetchCourses()">
          {{ t('common.retry') }}
        </button>
      </div>

      <p v-else-if="courses.length === 0" class="state">{{ t('courses.empty') }}</p>

      <ul v-else class="course-grid">
        <li v-for="course in courses" :key="course.id">
          <RouterLink :to="`/e-learning/${course.slug}`" class="course-card">
            <img v-if="course.coverUrl" :src="course.coverUrl" alt="" class="course-cover" />

            <span v-if="course.enrolled" class="badge-enrolled">{{ t('courses.enrolled') }}</span>

            <div class="course-card-head">
              <h2>{{ l(course.title) }}</h2>
              <span class="lesson-count">
                {{ t('courses.lessons', { count: course.lessonCount }, course.lessonCount) }}
              </span>
            </div>
            <p v-if="l(course.description)" class="course-desc">{{ l(course.description) }}</p>

            <div v-if="course.enrolled" class="course-progress">
              <div class="progress-track">
                <div class="progress-fill" :style="{ width: progressPercent(course) + '%' }"></div>
              </div>
              <span class="progress-label">
                {{ t('courses.progress', { done: course.completedLessons, total: course.lessonCount }) }}
              </span>
            </div>

            <span class="course-cta">{{ t('courses.open') }}</span>
          </RouterLink>
        </li>
      </ul>
    </div>
  </section>
</template>

<style scoped>
.courses {
  padding: 3.5rem 0 5rem;
}

.courses-head {
  margin-bottom: 2.75rem;
}

.eyebrow {
  display: inline-block;
  color: var(--login-primary, #ed2044);
  font-size: 0.95rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.courses-head h1 {
  margin: 0.35rem 0 0.6rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 2.6rem;
  font-weight: 700;
}

.courses-head p {
  max-width: 640px;
  margin: 0;
  color: #545f71;
  font-size: 1.1rem;
  line-height: 1.5;
}

.course-grid {
  display: grid;
  gap: 1.5rem;
  grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
  margin: 0;
  padding: 0;
  list-style: none;
}

.course-card {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
  height: 100%;
  padding: 1.85rem;
  background: #fff;
  border-radius: 1.1rem;
  box-shadow: 0 12px 32px rgba(12, 28, 64, 0.08);
  overflow: hidden;
  transition:
    transform 0.15s ease,
    box-shadow 0.15s ease;
}

.course-cover {
  display: block;
  width: calc(100% + 3.7rem);
  height: 160px;
  margin: -1.85rem -1.85rem 0;
  object-fit: cover;
}

.course-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 18px 44px rgba(12, 28, 64, 0.16);
}

.course-card-head {
  display: flex;
  align-items: baseline;
  flex-wrap: wrap;
  gap: 0.2rem 0.6rem;
}

.course-card h2 {
  margin: 0;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.3rem;
  font-weight: 700;
}

.course-desc {
  flex: 1;
  margin: 0;
  color: #545f71;
  font-size: 1rem;
  line-height: 1.55;
}

.lesson-count {
  color: #8b94a6;
  font-size: 0.9rem;
  font-weight: 700;
  white-space: nowrap;
}

.badge-enrolled {
  align-self: flex-start;
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
  gap: 0.35rem;
}

.progress-track {
  height: 8px;
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
  font-size: 0.82rem;
  font-weight: 700;
}

.course-cta {
  color: var(--login-primary, #ed2044);
  font-size: 0.95rem;
  font-weight: 700;
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
  gap: 0.7rem;
}

.btn-retry {
  padding: 0.4rem 0.9rem;
  background: var(--login-primary, #ed2044);
  border: none;
  border-radius: 0.45rem;
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}

@media (max-width: 575.98px) {
  .courses-head h1 {
    font-size: 2rem;
  }
}
</style>

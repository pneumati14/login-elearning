<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import { useCoursesStore } from '@/stores/courses'
import { useAdminCoursesStore } from '@/stores/adminCourses'
import { emptyLocalized, useLocalized } from '@/composables/localized'
import LocalizedInput from '@/components/LocalizedInput.vue'

const { t } = useI18n()
const { l } = useLocalized()
const coursesStore = useCoursesStore()
const adminStore = useAdminCoursesStore()
const { courses, loading, error } = storeToRefs(coursesStore)

const form = reactive({ title: emptyLocalized(), description: emptyLocalized() })
const submitting = ref(false)
const formError = ref<string | null>(null)
const formSuccess = ref<string | null>(null)

onMounted(() => coursesStore.fetchCourses())

async function onCreate() {
  formError.value = null
  formSuccess.value = null
  submitting.value = true

  const result = await adminStore.createCourse(form.title, form.description)
  submitting.value = false

  if (result.ok) {
    formSuccess.value = t('adminCourses.created')
    form.title = emptyLocalized()
    form.description = emptyLocalized()
    await coursesStore.fetchCourses()
  } else {
    formError.value = result.error ?? t('adminCourses.createFailed')
  }
}

async function onDelete(id: number, title: string) {
  if (!window.confirm(t('adminCourses.confirmDelete', { title }))) return

  const result = await adminStore.deleteCourse(id)
  if (result.ok) {
    await coursesStore.fetchCourses()
  } else {
    window.alert(result.error ?? t('admin.deleteFailed'))
  }
}
</script>

<template>
  <section class="admin">
    <div class="container-lg">
      <div class="admin-head">
        <span class="eyebrow">{{ t('admin.eyebrow') }}</span>
        <h1>{{ t('nav.adminCourses') }}</h1>
        <p>{{ t('adminCourses.subtitle') }}</p>
      </div>

      <form class="ac-panel" @submit.prevent="onCreate">
        <h2>{{ t('adminCourses.newCourse') }}</h2>
        <LocalizedInput
          v-model="form.title"
          :label="t('admin.title')"
          :placeholder="t('adminCourses.titlePlaceholder')"
          required
        />
        <LocalizedInput
          v-model="form.description"
          :label="t('admin.description')"
          :placeholder="t('adminCourses.descPlaceholder')"
          multiline
        />

        <p v-if="formError" class="msg msg--error">{{ formError }}</p>
        <p v-if="formSuccess" class="msg msg--success">{{ formSuccess }}</p>

        <button type="submit" class="btn-submit" :disabled="submitting">
          {{ submitting ? t('admin.creating') : t('adminCourses.create') }}
        </button>
      </form>

      <div class="ac-panel">
        <h2>{{ t('adminCourses.existing') }}</h2>

        <p v-if="loading" class="state">{{ t('courses.loading') }}</p>

        <div v-else-if="error" class="state state--error">
          <strong>{{ t('courses.loadError') }}</strong>
          <button type="button" class="btn-retry" @click="coursesStore.fetchCourses()">
            {{ t('common.retry') }}
          </button>
        </div>

        <p v-else-if="courses.length === 0" class="state">{{ t('adminCourses.empty') }}</p>

        <ul v-else class="course-rows">
          <li v-for="course in courses" :key="course.id" class="course-row">
            <div class="course-row-main">
              <span class="course-row-title">{{ l(course.title) }}</span>
              <span class="course-row-meta">{{
                t('courses.lessons', { count: course.lessonCount })
              }}</span>
            </div>
            <div class="course-row-actions">
              <RouterLink :to="`/admin/courses/${course.slug}`" class="btn-ghost">
                {{ t('admin.edit') }}
              </RouterLink>
              <button type="button" class="btn-delete" @click="onDelete(course.id, l(course.title))">
                {{ t('admin.delete') }}
              </button>
            </div>
          </li>
        </ul>
      </div>
    </div>
  </section>
</template>

<style scoped>
.admin {
  padding: 3.5rem 0 5rem;
}

.admin-head {
  margin-bottom: 2.2rem;
}

.eyebrow {
  display: inline-block;
  color: var(--login-primary, #ed2044);
  font-size: 0.9rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.admin-head h1 {
  margin: 0.35rem 0 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 2.4rem;
  font-weight: 700;
}

.admin-head p {
  margin: 0;
  color: #545f71;
  font-size: 1.05rem;
}

.ac-panel {
  margin-bottom: 1.5rem;
  padding: 1.85rem;
  background: #fff;
  border-radius: 1.1rem;
  box-shadow: 0 12px 32px rgba(12, 28, 64, 0.08);
}

.ac-panel h2 {
  margin: 0 0 1.3rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.25rem;
  font-weight: 700;
}

.form-row {
  display: grid;
  gap: 1rem;
  grid-template-columns: 1fr 1.4fr;
}

.field {
  display: block;
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
  margin: 1rem 0 0;
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

.btn-submit {
  margin-top: 1.1rem;
  padding: 0.7rem 1.4rem;
  background: var(--login-primary, #ed2044);
  border: none;
  border-radius: 0.55rem;
  color: #fff;
  font-size: 1rem;
  font-weight: 700;
  cursor: pointer;
}

.btn-submit:disabled {
  opacity: 0.65;
  cursor: progress;
}

.course-rows {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.course-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.9rem 1.1rem;
  background: #f7f8fb;
  border-radius: 0.7rem;
}

.course-row-main {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}

.course-row-title {
  color: var(--login-secondary, #0c1c40);
  font-size: 1.02rem;
  font-weight: 700;
}

.course-row-meta {
  color: #8b94a6;
  font-size: 0.82rem;
  font-weight: 700;
}

.course-row-actions {
  display: flex;
  gap: 0.5rem;
  flex-shrink: 0;
}

.btn-ghost {
  padding: 0.4rem 0.9rem;
  background: #fff;
  border: 1px solid #d4dae6;
  border-radius: 0.45rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.85rem;
  font-weight: 700;
  cursor: pointer;
}

.btn-ghost:hover {
  border-color: var(--login-secondary, #0c1c40);
}

.btn-delete {
  padding: 0.4rem 0.9rem;
  background: #fff;
  border: 1px solid #e0a9b3;
  border-radius: 0.45rem;
  color: #b3122e;
  font-size: 0.85rem;
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
  gap: 0.6rem;
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
  .form-row {
    grid-template-columns: 1fr;
  }

  .course-row {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>

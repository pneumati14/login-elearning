<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useQuizzesStore } from '@/stores/quizzes'

const { t } = useI18n()

interface EditOption {
  text: string
  correct: boolean
}
interface EditQuestion {
  text: string
  options: EditOption[]
}

const route = useRoute()
const store = useQuizzesStore()

const quizId = computed(() => Number(route.params.id))
const backLink = computed(() => {
  const slug = route.query.course
  return typeof slug === 'string' && slug !== '' ? `/admin/courses/${slug}` : '/admin/courses'
})

const loading = ref(true)
const loadError = ref(false)
const passThreshold = ref(60)
const questions = ref<EditQuestion[]>([])

const saving = ref(false)
const message = ref<string | null>(null)
const messageOk = ref(true)

onMounted(async () => {
  const quiz = await store.fetchAdminQuiz(quizId.value)
  loading.value = false
  if (!quiz) {
    loadError.value = true
    return
  }
  passThreshold.value = quiz.passThreshold
  questions.value = quiz.questions.map((q) => ({
    text: q.text,
    options: q.options.map((o) => ({ text: o.text, correct: o.correct })),
  }))
})

function addQuestion() {
  questions.value.push({
    text: '',
    options: [
      { text: '', correct: true },
      { text: '', correct: false },
    ],
  })
}

function removeQuestion(index: number) {
  questions.value.splice(index, 1)
}

function moveQuestion(index: number, direction: -1 | 1) {
  const list = questions.value
  const target = index + direction
  if (target < 0 || target >= list.length) return

  const moved = list[index]
  const other = list[target]
  if (!moved || !other) return

  list[index] = other
  list[target] = moved
}

function addOption(question: EditQuestion) {
  question.options.push({ text: '', correct: false })
}

function removeOption(question: EditQuestion, index: number) {
  question.options.splice(index, 1)
}

function setCorrect(question: EditQuestion, index: number) {
  question.options.forEach((option, i) => {
    option.correct = i === index
  })
}

async function save() {
  message.value = null
  saving.value = true
  const result = await store.saveQuiz(quizId.value, {
    passThreshold: passThreshold.value,
    questions: questions.value
      .filter((q) => q.text.trim() !== '')
      .map((q) => ({
        text: q.text.trim(),
        options: q.options
          .filter((o) => o.text.trim() !== '')
          .map((o) => ({ text: o.text.trim(), correct: o.correct })),
      })),
  })
  saving.value = false
  messageOk.value = result.ok
  message.value = result.ok ? t('adminQuizEdit.quizSaved') : (result.error ?? t('admin.saveFailed'))
}
</script>

<template>
  <section class="qedit">
    <div class="container-lg">
      <RouterLink :to="backLink" class="back-link">{{ t('adminQuizEdit.back') }}</RouterLink>

      <p v-if="loading" class="state">{{ t('quiz.loading') }}</p>

      <div v-else-if="loadError" class="state state--error">
        <strong>{{ t('quiz.loadError') }}</strong>
        <RouterLink to="/admin/courses" class="btn-submit">{{ t('course.coursesLink') }}</RouterLink>
      </div>

      <template v-else>
        <div class="qedit-head">
          <span class="eyebrow">{{ t('admin.eyebrow') }}</span>
          <h1>{{ t('adminQuizEdit.title') }}</h1>
        </div>

        <div class="qe-panel">
          <label class="field field--inline">
            <span class="field-label">{{ t('adminQuizEdit.passThreshold') }}</span>
            <input v-model.number="passThreshold" type="number" min="1" max="100" />
          </label>
        </div>

        <div
          v-for="(question, qi) in questions"
          :key="qi"
          class="qe-panel q-block"
        >
          <div class="q-head">
            <span class="q-num">{{ t('adminQuizEdit.questionNum', { num: qi + 1 }) }}</span>
            <div class="q-controls">
              <button type="button" class="icon-btn" :disabled="qi === 0" @click="moveQuestion(qi, -1)">▲</button>
              <button
                type="button"
                class="icon-btn"
                :disabled="qi === questions.length - 1"
                @click="moveQuestion(qi, 1)"
              >
                ▼
              </button>
              <button type="button" class="btn-mini" @click="removeQuestion(qi)">
                {{ t('adminQuizEdit.deleteQuestion') }}
              </button>
            </div>
          </div>

          <label class="field">
            <span class="field-label">{{ t('adminQuizEdit.questionText') }}</span>
            <textarea v-model="question.text" rows="2"></textarea>
          </label>

          <span class="field-label">{{ t('adminQuizEdit.options') }}</span>
          <div v-for="(option, oi) in question.options" :key="oi" class="opt-row">
            <input
              type="radio"
              :name="`correct-${qi}`"
              :checked="option.correct"
              :title="t('adminQuizEdit.correctAnswer')"
              @change="setCorrect(question, oi)"
            />
            <input
              v-model="option.text"
              type="text"
              class="opt-input"
              :placeholder="t('adminQuizEdit.answerPlaceholder')"
            />
            <button
              type="button"
              class="btn-mini"
              :disabled="question.options.length <= 2"
              @click="removeOption(question, oi)"
            >
              ✕
            </button>
          </div>
          <button type="button" class="btn-ghost" @click="addOption(question)">
            {{ t('adminQuizEdit.addOption') }}
          </button>
        </div>

        <button type="button" class="btn-ghost btn-add-q" @click="addQuestion">
          {{ t('adminQuizEdit.addQuestion') }}
        </button>

        <div class="qe-panel">
          <p v-if="message" class="msg" :class="messageOk ? 'msg--success' : 'msg--error'">
            {{ message }}
          </p>
          <button type="button" class="btn-submit" :disabled="saving" @click="save">
            {{ saving ? t('admin.saving') : t('adminQuizEdit.saveQuiz') }}
          </button>
        </div>
      </template>
    </div>
  </section>
</template>

<style scoped>
.qedit {
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

.qedit-head {
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

.qedit-head h1 {
  margin: 0.35rem 0 0;
  color: var(--login-secondary, #0c1c40);
  font-size: 2.3rem;
  font-weight: 700;
}

.qe-panel {
  margin-bottom: 1.1rem;
  padding: 1.6rem;
  background: #fff;
  border-radius: 1rem;
  box-shadow: 0 10px 26px rgba(12, 28, 64, 0.08);
}

.field {
  display: block;
  margin-bottom: 0.9rem;
}

.field--inline {
  margin-bottom: 0;
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
  padding: 0.6rem 0.8rem;
  border: 1px solid #d4dae6;
  border-radius: 0.5rem;
  font-size: 0.98rem;
  font-family: inherit;
  color: var(--login-secondary, #0c1c40);
  resize: vertical;
}

.field--inline input {
  max-width: 120px;
}

.field input:focus,
.field textarea:focus {
  outline: none;
  border-color: var(--login-primary, #ed2044);
}

.q-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.9rem;
}

.q-num {
  color: var(--login-secondary, #0c1c40);
  font-size: 1.05rem;
  font-weight: 700;
}

.q-controls {
  display: flex;
  gap: 0.35rem;
}

.icon-btn {
  width: 1.9rem;
  height: 1.9rem;
  background: #fff;
  border: 1px solid #d4dae6;
  border-radius: 0.4rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.65rem;
  cursor: pointer;
}

.icon-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.opt-row {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  margin-bottom: 0.5rem;
}

.opt-input {
  flex: 1;
  padding: 0.5rem 0.7rem;
  border: 1px solid #d4dae6;
  border-radius: 0.5rem;
  font-size: 0.95rem;
  font-family: inherit;
  color: var(--login-secondary, #0c1c40);
}

.opt-input:focus {
  outline: none;
  border-color: var(--login-primary, #ed2044);
}

.btn-mini {
  padding: 0.3rem 0.65rem;
  background: #fff;
  border: 1px solid #e0a9b3;
  border-radius: 0.4rem;
  color: #b3122e;
  font-size: 0.8rem;
  font-weight: 700;
  cursor: pointer;
}

.btn-mini:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.btn-ghost {
  margin-top: 0.4rem;
  padding: 0.5rem 1rem;
  background: #fff;
  border: 1px dashed #c2cad8;
  border-radius: 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.9rem;
  font-weight: 700;
  cursor: pointer;
}

.btn-ghost:hover {
  border-color: var(--login-primary, #ed2044);
  color: var(--login-primary, #ed2044);
}

.btn-add-q {
  display: block;
  width: 100%;
  margin: 0 0 1.1rem;
  padding: 0.8rem;
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

.btn-submit {
  padding: 0.7rem 1.5rem;
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

.state {
  padding: 1.4rem;
  background: #fff;
  border-radius: 1rem;
  box-shadow: 0 10px 26px rgba(12, 28, 64, 0.08);
  color: var(--login-secondary, #0c1c40);
  font-size: 1.02rem;
}

.state--error {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.8rem;
}
</style>

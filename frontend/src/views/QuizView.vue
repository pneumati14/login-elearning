<script setup lang="ts">
import { onMounted, ref, reactive, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useQuizzesStore, type TakeQuiz, type AttemptResult } from '@/stores/quizzes'
import { useLocalized } from '@/composables/localized'

const { t } = useI18n()
const { l } = useLocalized()
const route = useRoute()
const store = useQuizzesStore()

const quizId = computed(() => Number(route.params.id))
const quiz = ref<TakeQuiz | null>(null)
const loading = ref(true)
const loadError = ref(false)

const answers = reactive<Record<number, number>>({})
const submitting = ref(false)
const submitError = ref<string | null>(null)
const result = ref<AttemptResult | null>(null)

onMounted(async () => {
  quiz.value = await store.fetchTakeQuiz(quizId.value)
  loading.value = false
  loadError.value = quiz.value === null
})

const allAnswered = computed(
  () => quiz.value !== null && quiz.value.questions.every((q) => q.id in answers),
)

async function submit() {
  if (!quiz.value) return
  submitError.value = null
  submitting.value = true
  const outcome = await store.submitAttempt(quiz.value.id, { ...answers })
  submitting.value = false
  if (outcome.ok) {
    result.value = outcome.result
    window.scrollTo({ top: 0, behavior: 'smooth' })
  } else {
    submitError.value = outcome.error
  }
}

function retry() {
  result.value = null
  for (const key of Object.keys(answers)) {
    delete answers[Number(key)]
  }
}
</script>

<template>
  <section class="quiz">
    <div class="container-lg">
      <RouterLink to="/e-learning" class="back-link">← {{ t('course.back') }}</RouterLink>

      <p v-if="loading" class="state">{{ t('quiz.loading') }}</p>

      <div v-else-if="loadError" class="state state--error">
        <strong>{{ t('quiz.loadError') }}</strong>
        <RouterLink to="/e-learning" class="btn-primary">{{ t('course.coursesLink') }}</RouterLink>
      </div>

      <template v-else-if="quiz">
        <div class="quiz-head">
          <span class="eyebrow">{{ t('quiz.eyebrow') }}</span>
          <h1>{{ l(quiz.title) }}</h1>
          <p>{{ t('quiz.meta', { count: quiz.questions.length, threshold: quiz.passThreshold }) }}</p>
        </div>

        <!-- ── Result ────────────────────────────────────────────── -->
        <div v-if="result" class="result" :class="result.passed ? 'result--pass' : 'result--fail'">
          <h2>{{ result.passed ? t('quiz.resultPass') : t('quiz.resultFail') }}</h2>
          <p class="result-score">
            {{ t('quiz.score', { score: result.score, total: result.total, percent: result.percent }) }}
          </p>
          <p class="result-note">
            {{
              result.passed
                ? t('quiz.notePass')
                : t('quiz.noteFail', { threshold: quiz.passThreshold })
            }}
          </p>
          <button type="button" class="btn-primary" @click="retry">{{ t('common.retry') }}</button>
        </div>

        <!-- ── Questions ─────────────────────────────────────────── -->
        <form v-else @submit.prevent="submit">
          <ol class="question-list">
            <li v-for="(question, qi) in quiz.questions" :key="question.id" class="question-card">
              <p class="question-text">
                <span class="question-num">{{ qi + 1 }}.</span> {{ question.text }}
              </p>
              <label
                v-for="option in question.options"
                :key="option.id"
                class="option"
                :class="{ 'option--selected': answers[question.id] === option.id }"
              >
                <input
                  v-model="answers[question.id]"
                  type="radio"
                  :name="`question-${question.id}`"
                  :value="option.id"
                />
                <span>{{ option.text }}</span>
              </label>
            </li>
          </ol>

          <p v-if="submitError" class="msg msg--error">{{ submitError }}</p>
          <p v-if="!allAnswered" class="msg msg--hint">{{ t('quiz.hint') }}</p>

          <button type="submit" class="btn-primary btn-lg" :disabled="submitting">
            {{ submitting ? t('quiz.submitting') : t('quiz.submit') }}
          </button>
        </form>
      </template>
    </div>
  </section>
</template>

<style scoped>
.quiz {
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

.quiz-head {
  margin-bottom: 2rem;
}

.eyebrow {
  display: inline-block;
  color: var(--login-primary, #ed2044);
  font-size: 0.9rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.quiz-head h1 {
  margin: 0.35rem 0 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 2.2rem;
  font-weight: 700;
}

.quiz-head p {
  margin: 0;
  color: #545f71;
  font-size: 1rem;
}

.question-list {
  margin: 0 0 1.5rem;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.question-card {
  padding: 1.5rem;
  background: #fff;
  border-radius: 0.9rem;
  box-shadow: 0 8px 22px rgba(12, 28, 64, 0.07);
}

.question-text {
  margin: 0 0 1rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.1rem;
  font-weight: 700;
}

.question-num {
  color: var(--login-primary, #ed2044);
}

.option {
  display: flex;
  align-items: center;
  gap: 0.7rem;
  margin-bottom: 0.55rem;
  padding: 0.7rem 0.9rem;
  border: 1px solid #d4dae6;
  border-radius: 0.55rem;
  cursor: pointer;
  color: #404a5c;
  font-size: 1rem;
  transition:
    border-color 0.15s ease,
    background 0.15s ease;
}

.option:last-child {
  margin-bottom: 0;
}

.option--selected {
  border-color: var(--login-primary, #ed2044);
  background: #fdeef1;
}

.msg {
  margin: 0 0 1rem;
  padding: 0.7rem 0.9rem;
  border-radius: 0.55rem;
  font-size: 0.92rem;
}

.msg--error {
  background: #fde8ec;
  color: #b3122e;
}

.msg--hint {
  background: #fff6e0;
  color: #8a6400;
}

.btn-primary {
  display: inline-block;
  padding: 0.6rem 1.3rem;
  background: var(--login-primary, #ed2044);
  border: none;
  border-radius: 0.6rem;
  color: #fff;
  font-size: 1rem;
  font-weight: 700;
  cursor: pointer;
}

.btn-primary:disabled {
  opacity: 0.65;
  cursor: progress;
}

.btn-lg {
  padding: 0.85rem 1.8rem;
  font-size: 1.05rem;
}

.result {
  padding: 2.2rem;
  border-radius: 1.1rem;
  text-align: center;
}

.result--pass {
  background: #e3f6ec;
}

.result--fail {
  background: #fde8ec;
}

.result h2 {
  margin: 0 0 0.6rem;
  font-size: 1.8rem;
  font-weight: 700;
  color: var(--login-secondary, #0c1c40);
}

.result-score {
  margin: 0 0 0.3rem;
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--login-secondary, #0c1c40);
}

.result-note {
  margin: 0 0 1.4rem;
  color: #545f71;
  font-size: 1rem;
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
</style>

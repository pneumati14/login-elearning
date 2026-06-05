<script setup lang="ts">
import { reactive, ref, computed, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import { usePositionsStore } from '@/stores/positions'
import { useLocalized } from '@/composables/localized'
import AppSelect from '@/components/AppSelect.vue'

const { t } = useI18n()
const { l } = useLocalized()

const benefitKeys = ['meaningful', 'grow', 'stack', 'balance'] as const
const benefitIcons: Record<string, string> = {
  meaningful: '🎯',
  grow: '🌱',
  stack: '💻',
  balance: '⚖️',
}
const benefits = computed(() =>
  benefitKeys.map((key) => ({
    key,
    icon: benefitIcons[key],
    title: t(`career.benefits.${key}.title`),
    text: t(`career.benefits.${key}.text`),
  })),
)

// Open positions are admin-managed — fetched from the API.
const positionsStore = usePositionsStore()
const { positions, loading: positionsLoading, error: positionsError } = storeToRefs(positionsStore)
onMounted(() => positionsStore.fetchPositions())

// Options for the downward-opening AppSelect (labels unchanged).
const positionSelectOptions = computed<{ value: string; label: string }[]>(() => [
  { value: '', label: t('career.openApplication') },
  ...positions.value.map((p) => ({ value: l(p.title), label: l(p.title) })),
])

const positionsSection = ref<HTMLElement | null>(null)
const applySection = ref<HTMLElement | null>(null)

const form = reactive({
  fullName: '',
  email: '',
  phone: '',
  position: '',
  cvLink: '',
  message: '',
  accept: false,
})
const submitted = ref(false)
const formError = ref<string | null>(null)

function scrollTo(el: HTMLElement | null) {
  el?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

function apply(positionTitle: string) {
  form.position = positionTitle
  scrollTo(applySection.value)
}

function onSubmit() {
  formError.value = null

  if (!form.fullName.trim() || !form.email.trim()) {
    formError.value = t('career.errNoNameEmail')
    return
  }
  if (!/^\S+@\S+\.\S+$/.test(form.email.trim())) {
    formError.value = t('career.errBadEmail')
    return
  }
  if (!form.accept) {
    formError.value = t('career.errNoAccept')
    return
  }

  submitted.value = true
  scrollTo(applySection.value)
}
</script>

<template>
  <div class="career">
    <!-- ── Hero ──────────────────────────────────────────────────── -->
    <section class="career-hero">
      <div class="container-lg">
        <span class="eyebrow">{{ t('career.eyebrow') }}</span>
        <h1>{{ t('career.heroTitle') }}</h1>
        <p class="hero-lead">{{ t('career.heroLead') }}</p>
        <button type="button" class="btn-primary" @click="scrollTo(positionsSection)">
          {{ t('career.viewPositions') }}
        </button>
      </div>
    </section>

    <!-- ── Why join us ───────────────────────────────────────────── -->
    <section class="section">
      <div class="container-lg">
        <h2 class="section-title">{{ t('career.whyJoin') }}</h2>
        <ul class="benefit-grid">
          <li v-for="benefit in benefits" :key="benefit.key" class="benefit-card">
            <span class="benefit-icon">{{ benefit.icon }}</span>
            <h3>{{ benefit.title }}</h3>
            <p>{{ benefit.text }}</p>
          </li>
        </ul>
      </div>
    </section>

    <!-- ── Open positions ────────────────────────────────────────── -->
    <section ref="positionsSection" class="section section--alt">
      <div class="container-lg">
        <h2 class="section-title">{{ t('career.openPositions') }}</h2>

        <p v-if="positionsLoading" class="positions-state">{{ t('career.positionsLoading') }}</p>

        <div v-else-if="positionsError" class="positions-state positions-state--error">
          <strong>{{ t('career.positionsError') }}</strong>
          <button type="button" class="btn-apply" @click="positionsStore.fetchPositions()">
            {{ t('common.retry') }}
          </button>
        </div>

        <p v-else-if="positions.length === 0" class="positions-state">
          {{ t('career.positionsEmpty') }}
        </p>

        <ul v-else class="position-list">
          <li v-for="position in positions" :key="position.id" class="position-card">
            <div class="position-main">
              <h3>{{ l(position.title) }}</h3>
              <div v-if="l(position.location) || l(position.type)" class="position-tags">
                <span v-if="l(position.location)" class="tag">{{ l(position.location) }}</span>
                <span v-if="l(position.type)" class="tag tag--type">{{ l(position.type) }}</span>
              </div>
              <p>{{ l(position.summary) }}</p>
            </div>
            <button type="button" class="btn-apply" @click="apply(l(position.title))">
              {{ t('career.apply') }}
            </button>
          </li>
        </ul>
      </div>
    </section>

    <!-- ── Application form ──────────────────────────────────────── -->
    <section ref="applySection" class="section apply-section">
      <div class="container-lg">
        <h2 class="section-title">{{ t('career.applyTitle') }}</h2>

        <div v-if="submitted" class="thank-you">
          <h3>{{ t('career.thankYouTitle', { name: form.fullName.trim() }) }}</h3>
          <p>
            {{
              t('career.thankYouText', {
                position: form.position
                  ? t('career.thankYouPosition', { position: form.position })
                  : '',
              })
            }}
          </p>
        </div>

        <form v-else class="apply-form" @submit.prevent="onSubmit">
          <div class="form-row">
            <label class="field">
              <span class="field-label">{{ t('career.fullName') }}</span>
              <input v-model="form.fullName" type="text" required />
            </label>
            <label class="field">
              <span class="field-label">{{ t('career.email') }}</span>
              <input v-model="form.email" type="email" required />
            </label>
            <label class="field">
              <span class="field-label">{{ t('career.phone') }}</span>
              <input v-model="form.phone" type="text" />
            </label>
            <label class="field">
              <span class="field-label">{{ t('career.position') }}</span>
              <AppSelect
                v-model="form.position"
                :options="positionSelectOptions"
                :placeholder="t('career.openApplication')"
              />
            </label>
          </div>

          <label class="field">
            <span class="field-label">{{ t('career.cvLink') }}</span>
            <input v-model="form.cvLink" type="text" placeholder="https://…" />
          </label>

          <label class="field">
            <span class="field-label">{{ t('career.messageLabel') }}</span>
            <textarea v-model="form.message" rows="5"></textarea>
          </label>

          <label class="checkbox-row">
            <input v-model="form.accept" type="checkbox" />
            <span>{{ t('career.consent') }}</span>
          </label>

          <p v-if="formError" class="form-error">{{ formError }}</p>

          <button type="submit" class="btn-primary btn-lg">{{ t('career.submit') }}</button>
        </form>
      </div>
    </section>
  </div>
</template>

<style scoped>
.career {
  padding-bottom: 4rem;
}

.eyebrow {
  display: inline-block;
  color: var(--login-primary, #ed2044);
  font-size: 0.95rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

/* ── Hero ───────────────────────────────────────────────────────── */
.career-hero {
  padding: 4rem 0 3.5rem;
}

.career-hero h1 {
  max-width: 720px;
  margin: 0.5rem 0 1rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 2.8rem;
  font-weight: 700;
  line-height: 1.15;
}

.hero-lead {
  max-width: 620px;
  margin: 0 0 1.8rem;
  color: #545f71;
  font-size: 1.15rem;
  line-height: 1.55;
}

.btn-primary {
  display: inline-block;
  padding: 0.85rem 1.7rem;
  background: var(--login-primary, #ed2044);
  border: none;
  border-radius: 0.6rem;
  color: #fff;
  font-size: 1.02rem;
  font-weight: 700;
  cursor: pointer;
}

.btn-primary:hover {
  filter: brightness(1.08);
}

.btn-lg {
  padding: 0.95rem 2rem;
  font-size: 1.05rem;
}

/* ── Sections ───────────────────────────────────────────────────── */
.section {
  padding: 3.2rem 0;
}

.section--alt {
  background: #f6f7fb;
}

.section-title {
  margin: 0 0 2rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 2rem;
  font-weight: 700;
}

/* ── Benefits ───────────────────────────────────────────────────── */
.benefit-grid {
  display: grid;
  gap: 1.4rem;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  margin: 0;
  padding: 0;
  list-style: none;
}

.benefit-card {
  padding: 1.7rem;
  background: #fff;
  border-radius: 1rem;
  box-shadow: 0 12px 32px rgba(12, 28, 64, 0.08);
}

.benefit-icon {
  font-size: 2rem;
}

.benefit-card h3 {
  margin: 0.6rem 0 0.4rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.15rem;
  font-weight: 700;
}

.benefit-card p {
  margin: 0;
  color: #545f71;
  font-size: 0.98rem;
  line-height: 1.55;
}

/* ── Positions ──────────────────────────────────────────────────── */
.positions-state {
  margin: 0;
  padding: 1.5rem 1.7rem;
  background: #fff;
  border-radius: 1rem;
  box-shadow: 0 12px 32px rgba(12, 28, 64, 0.08);
  color: #545f71;
  font-size: 1rem;
}

.positions-state--error {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.8rem;
  color: var(--login-secondary, #0c1c40);
}

.position-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.position-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.5rem;
  padding: 1.6rem 1.8rem;
  background: #fff;
  border-radius: 1rem;
  box-shadow: 0 12px 32px rgba(12, 28, 64, 0.08);
}

.position-main h3 {
  margin: 0 0 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.2rem;
  font-weight: 700;
}

.position-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
  margin-bottom: 0.6rem;
}

.tag {
  padding: 0.25rem 0.7rem;
  background: #eef1f6;
  border-radius: 100vw;
  color: #545f71;
  font-size: 0.8rem;
  font-weight: 700;
}

.tag--type {
  background: #fdeef1;
  color: var(--login-primary, #ed2044);
}

.position-main p {
  margin: 0;
  max-width: 560px;
  color: #545f71;
  font-size: 0.98rem;
  line-height: 1.55;
}

.btn-apply {
  flex-shrink: 0;
  padding: 0.65rem 1.5rem;
  background: var(--login-primary, #ed2044);
  border: none;
  border-radius: 0.55rem;
  color: #fff;
  font-size: 0.98rem;
  font-weight: 700;
  cursor: pointer;
}

.btn-apply:hover {
  filter: brightness(1.08);
}

/* ── Application form ───────────────────────────────────────────── */
.apply-form {
  max-width: 680px;
}

.form-row {
  display: grid;
  gap: 1rem;
  grid-template-columns: 1fr 1fr;
}

.field {
  display: block;
  margin-bottom: 1rem;
}

.field-label {
  display: block;
  margin-bottom: 0.35rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.9rem;
  font-weight: 700;
}

.field input,
.field select,
.field textarea {
  width: 100%;
  padding: 0.7rem 0.85rem;
  border: 1px solid #d4dae6;
  border-radius: 0.55rem;
  font-size: 1rem;
  font-family: inherit;
  color: var(--login-secondary, #0c1c40);
  background: #fff;
  resize: vertical;
}

.field input:focus,
.field select:focus,
.field textarea:focus {
  outline: none;
  border-color: var(--login-primary, #ed2044);
}

/* The application form uses full-width white inputs; match the AppSelect
   toggle to that look (its scoped default is the grey admin style). */
.field :deep(.app-select) {
  display: flex;
  width: 100%;
}

.field :deep(.app-select-toggle) {
  width: 100%;
  padding: 0.7rem 0.85rem;
  background: #fff;
  border-color: #d4dae6;
  font-size: 1rem;
}

.checkbox-row {
  display: flex;
  gap: 0.6rem;
  margin: 0.4rem 0 1.2rem;
  color: #545f71;
  font-size: 0.92rem;
  line-height: 1.5;
}

.checkbox-row input {
  margin-top: 0.2rem;
  flex-shrink: 0;
}

.form-error {
  margin: 0 0 1rem;
  padding: 0.7rem 0.9rem;
  background: #fde8ec;
  border-radius: 0.55rem;
  color: #b3122e;
  font-size: 0.92rem;
}

.thank-you {
  max-width: 680px;
  padding: 2.2rem;
  background: #e3f6ec;
  border-radius: 1.1rem;
}

.thank-you h3 {
  margin: 0 0 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.5rem;
  font-weight: 700;
}

.thank-you p {
  margin: 0;
  color: #1c7a45;
  font-size: 1.05rem;
  line-height: 1.55;
}

@media (max-width: 767.98px) {
  .career-hero h1 {
    font-size: 2.1rem;
  }

  .position-card {
    flex-direction: column;
    align-items: flex-start;
  }

  .form-row {
    grid-template-columns: 1fr;
  }
}
</style>

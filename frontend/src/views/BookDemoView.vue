<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppSelect from '@/components/AppSelect.vue'

const { t } = useI18n()

const LA = 'https://www.loginautonom.com'

// Labels are resolved from the `bookDemo.software.<value>` locale namespace.
const softwareOptions = [
  { value: 'hrbase', logo: 'logo-hrbase' },
  { value: 'cplatform', logo: 'logo-cplatform' },
  { value: 'holiday', logo: 'logo-holiday' },
  { value: 'workhour', logo: 'logo-workhour' },
  { value: 'productivity', logo: 'logo-productivity' },
  { value: 'competency', logo: 'logo-competency' },
  { value: 'shift', logo: 'logo-shift' },
  { value: 'workwear', logo: 'logo-workwear' },
  { value: 'access', logo: 'logo-access' },
  { value: 'guest', logo: 'logo-guest' },
]

const form = reactive({
  software: {} as Record<string, boolean>,
  fullName: '',
  company: '',
  email: '',
  companySize: '',
  phone: '',
  message: '',
  agree: false,
  accept: false,
})

// Options for the downward-opening AppSelect dropdown (labels unchanged).
const companySizeOptions = computed<{ value: string; label: string }[]>(() => [
  { value: '', label: t('bookDemo.selectOption') },
  { value: '5', label: '5' },
  { value: '10', label: '10' },
  { value: '15', label: '15' },
  { value: '20', label: '20' },
  { value: '20+', label: '20+' },
])

const submitted = ref(false)
const error = ref<string | null>(null)

function onSubmit() {
  error.value = null
  const chosen = Object.keys(form.software).filter((k) => form.software[k])

  if (chosen.length === 0) {
    error.value = t('bookDemo.errNoSoftware')
    return
  }
  if (!form.fullName.trim() || !form.email.trim()) {
    error.value = t('bookDemo.errNoNameEmail')
    return
  }
  if (!form.accept) {
    error.value = t('bookDemo.errNoAccept')
    return
  }

  // The backend mail endpoint is not wired yet — acknowledge client-side.
  submitted.value = true
  window.scrollTo({ top: 0, behavior: 'smooth' })
}
</script>

<template>
  <div class="book-demo-page">
    <section class="promobox flush">
      <div class="container-lg">
        <div class="row g-4 align-items-center justify-content-center">
          <div class="col-sm-12 col-md-7 col-lg-6 order-sm-2 order-md-1">
            <h2 class="sub-title">{{ t('bookDemo.heroTitle') }}</h2>
            <p>{{ t('bookDemo.heroLead') }}</p>
          </div>
          <div class="col-8 col-sm-6 col-md-5 col-lg-6 order-sm-1 order-md-2">
            <img src="/frontend-files/images/image-book.png" class="img-fluid d-block mx-auto" alt="" />
          </div>
        </div>
      </div>
    </section>

    <section class="page-container">
      <div class="container container-min-2">
        <div class="py-5">
          <div v-if="submitted" class="alert alert-success">
            <h3 class="fw-bold">{{ t('bookDemo.thankYouTitle') }}</h3>
            <p class="mb-0">{{ t('bookDemo.thankYouText') }}</p>
          </div>

          <form v-else @submit.prevent="onSubmit">
            <div class="row g-3 g-lg-4">
              <div class="col-sm-6">
                <h3 class="text-uppercase fw-bold fs-5 mb-4">{{ t('bookDemo.selectSoftware') }}</h3>
                <div class="module-list module-check">
                  <div v-for="opt in softwareOptions" :key="opt.value" class="form-check">
                    <input
                      :id="`check_${opt.value}`"
                      v-model="form.software[opt.value]"
                      class="form-check-input"
                      type="checkbox"
                    />
                    <label class="form-check-label" :for="`check_${opt.value}`">
                      <span class="icon">
                        <img :src="`/frontend-files/images/solutions/${opt.logo}.svg`" alt="" />
                      </span>
                      <span class="text">{{ t(`bookDemo.software.${opt.value}`) }}</span>
                    </label>
                  </div>
                </div>
              </div>

              <div class="col-sm-6">
                <div class="form-floating mb-3">
                  <input id="booking_full_name" v-model="form.fullName" type="text" class="form-control" :placeholder="t('bookDemo.fullName')" />
                  <label for="booking_full_name">{{ t('bookDemo.fullName') }}</label>
                </div>
                <div class="form-floating mb-3">
                  <input id="booking_company" v-model="form.company" type="text" class="form-control" :placeholder="t('bookDemo.company')" />
                  <label for="booking_company">{{ t('bookDemo.company') }}</label>
                </div>
                <div class="form-floating mb-3">
                  <input id="booking_email" v-model="form.email" type="email" class="form-control" :placeholder="t('bookDemo.email')" />
                  <label for="booking_email">{{ t('bookDemo.email') }}</label>
                </div>
                <div class="mb-3">
                  <label class="company-size-label" for="booking_company_size">{{ t('bookDemo.companySize') }}</label>
                  <AppSelect
                    id="booking_company_size"
                    v-model="form.companySize"
                    class="form-select"
                    :options="companySizeOptions"
                    :placeholder="t('bookDemo.selectOption')"
                  />
                </div>
                <div class="form-floating mb-3">
                  <input id="booking_phone" v-model="form.phone" type="text" class="form-control" :placeholder="t('bookDemo.phone')" />
                  <label for="booking_phone">{{ t('bookDemo.phone') }}</label>
                </div>
                <div class="form-floating mb-3">
                  <textarea
                    id="booking_message"
                    v-model="form.message"
                    class="form-control"
                    style="height: 130px"
                    :placeholder="t('bookDemo.message')"
                  ></textarea>
                  <label for="booking_message">{{ t('bookDemo.message') }}</label>
                </div>

                <div class="form-check">
                  <input id="agree" v-model="form.agree" type="checkbox" class="form-check-input" />
                  <label class="form-check-label" for="agree">{{ t('bookDemo.consentNewsletter') }}</label>
                </div>
                <div class="form-check">
                  <input id="accept" v-model="form.accept" type="checkbox" class="form-check-input" />
                  <label class="form-check-label" for="accept">
                    {{ t('bookDemo.consentAcceptPre') }}
                    <a
                      :href="`${LA}/frontend-files/uploads/files/adatvedelmi-szabalyzat.pdf`"
                      target="_blank"
                      rel="noopener"
                    >{{ t('bookDemo.privacyPolicy') }}</a>
                  </label>
                </div>

                <p v-if="error" class="alert alert-danger mt-3">{{ error }}</p>

                <div class="button-wrapper mt-4">
                  <button type="submit" class="btn btn-default w-100">{{ t('bookDemo.submit') }}</button>
                </div>
              </div>
            </div>
          </form>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
/* The public booking form uses white inputs; carry that look onto the
   AppSelect toggle (its scoped defaults are the grey admin look). */
.company-size-label {
  display: block;
  margin-bottom: 0.35rem;
  font-size: 0.95rem;
}

.form-select.app-select {
  display: flex;
  width: 100%;
}

.form-select :deep(.app-select-toggle) {
  width: 100%;
  background: #fff;
  border-color: #ced4da;
  padding: 0.75rem 0.75rem;
  font-size: 1rem;
}
</style>

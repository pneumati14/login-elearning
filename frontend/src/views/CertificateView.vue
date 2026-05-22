<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useCertificatesStore, type CertificateSummary } from '@/stores/certificates'
import { useLocalized } from '@/composables/localized'

const { t, locale } = useI18n()
const { l } = useLocalized()
const route = useRoute()
const store = useCertificatesStore()

const certificate = ref<CertificateSummary | null>(null)
const loading = ref(true)

const certId = computed(() => Number(route.params.id))

onMounted(async () => {
  certificate.value = await store.fetchCertificate(certId.value)
  loading.value = false
})

function formattedDate(iso: string): string {
  return new Date(iso).toLocaleDateString(locale.value, {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
}

function print() {
  window.print()
}
</script>

<template>
  <section class="cert-page">
    <div class="container-lg">
      <RouterLink to="/certificates" class="back-link no-print">
        ← {{ t('profile.myCertificates') }}
      </RouterLink>

      <p v-if="loading" class="state">{{ t('certificate.loading') }}</p>

      <div v-else-if="!certificate" class="state state--error">
        <strong>{{ t('certificate.notFound') }}</strong>
        <RouterLink to="/certificates" class="btn-primary">
          {{ t('profile.myCertificates') }}
        </RouterLink>
      </div>

      <template v-else>
        <div class="certificate">
          <div class="cert-inner">
            <div class="cert-brand">LOGIN AUTONOM · E-LEARNING</div>
            <h1 class="cert-title">{{ t('certificate.title') }}</h1>
            <p class="cert-line">{{ t('certificate.line1') }}</p>
            <p class="cert-name">{{ certificate.recipientName }}</p>
            <p class="cert-line">{{ t('certificate.line2') }}</p>
            <p class="cert-course">{{ l(certificate.courseTitle) }}</p>
            <div class="cert-foot">
              <span>{{ t('certificate.issued', { date: formattedDate(certificate.issuedAt) }) }}</span>
              <span>{{ t('certificate.code', { code: certificate.code }) }}</span>
            </div>
          </div>
        </div>

        <div class="cert-actions no-print">
          <button type="button" class="btn-primary" @click="print">{{ t('certificate.print') }}</button>
        </div>
      </template>
    </div>
  </section>
</template>

<style scoped>
.cert-page {
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

.certificate {
  padding: 0.6rem;
  background: linear-gradient(135deg, #ed2044, #0c1c40);
  border-radius: 0.6rem;
}

.cert-inner {
  padding: 3.2rem 2.4rem;
  background: #fff;
  border: 2px solid #fbc83b;
  border-radius: 0.4rem;
  text-align: center;
}

.cert-brand {
  color: var(--login-primary, #ed2044);
  font-size: 0.85rem;
  font-weight: 700;
  letter-spacing: 0.18em;
}

.cert-title {
  margin: 0.6rem 0 1.8rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 2.8rem;
  font-weight: 700;
  letter-spacing: 0.02em;
}

.cert-line {
  margin: 0;
  color: #545f71;
  font-size: 1.05rem;
}

.cert-name {
  margin: 0.5rem 0 1.4rem;
  padding-bottom: 0.5rem;
  border-bottom: 2px solid #fbc83b;
  display: inline-block;
  color: var(--login-secondary, #0c1c40);
  font-size: 2.1rem;
  font-weight: 700;
}

.cert-course {
  margin: 0.5rem 0 2.4rem;
  color: var(--login-primary, #ed2044);
  font-size: 1.6rem;
  font-weight: 700;
}

.cert-foot {
  display: flex;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 0.5rem;
  color: #8b94a6;
  font-size: 0.9rem;
  font-weight: 700;
}

.cert-actions {
  margin-top: 1.6rem;
  text-align: center;
}

.btn-primary {
  display: inline-block;
  padding: 0.75rem 1.6rem;
  background: var(--login-primary, #ed2044);
  border: none;
  border-radius: 0.6rem;
  color: #fff;
  font-size: 1rem;
  font-weight: 700;
  cursor: pointer;
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

@media (max-width: 575.98px) {
  .cert-inner {
    padding: 2.2rem 1.3rem;
  }

  .cert-title {
    font-size: 2.1rem;
  }

  .cert-name {
    font-size: 1.6rem;
  }
}

@media print {
  .cert-page {
    padding: 0;
  }

  .certificate {
    margin-top: 1.5rem;
  }
}
</style>

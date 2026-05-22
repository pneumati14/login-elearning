<script setup lang="ts">
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { useCertificatesStore } from '@/stores/certificates'
import { useLocalized } from '@/composables/localized'

const { t, locale } = useI18n()
const { l } = useLocalized()
const store = useCertificatesStore()
const { certificates, loading, error } = storeToRefs(store)

onMounted(() => store.fetchCertificates())

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString(locale.value, {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
}
</script>

<template>
  <section class="certs">
    <div class="container-lg">
      <div class="certs-head">
        <span class="eyebrow">{{ t('courses.eyebrow') }}</span>
        <h1>{{ t('profile.myCertificates') }}</h1>
        <p>{{ t('certificates.subtitle') }}</p>
      </div>

      <p v-if="loading" class="state">{{ t('certificates.loading') }}</p>

      <div v-else-if="error" class="state state--error">
        <strong>{{ t('certificates.loadError') }}</strong>
        <button type="button" class="btn-retry" @click="store.fetchCertificates()">
          {{ t('common.retry') }}
        </button>
      </div>

      <p v-else-if="certificates.length === 0" class="state">{{ t('certificates.empty') }}</p>

      <ul v-else class="cert-list">
        <li v-for="cert in certificates" :key="cert.id">
          <RouterLink :to="`/certificates/${cert.id}`" class="cert-card">
            <span class="cert-seal">🏅</span>
            <span class="cert-body">
              <span class="cert-course">{{ l(cert.courseTitle) }}</span>
              <span class="cert-meta">
                {{ t('certificates.meta', { date: formatDate(cert.issuedAt), code: cert.code }) }}
              </span>
            </span>
            <span class="cert-open">{{ t('courses.open') }}</span>
          </RouterLink>
        </li>
      </ul>
    </div>
  </section>
</template>

<style scoped>
.certs {
  padding: 3.5rem 0 5rem;
}

.certs-head {
  margin-bottom: 2.5rem;
}

.eyebrow {
  display: inline-block;
  color: var(--login-primary, #ed2044);
  font-size: 0.95rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.certs-head h1 {
  margin: 0.35rem 0 0.6rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 2.6rem;
  font-weight: 700;
}

.certs-head p {
  margin: 0;
  color: #545f71;
  font-size: 1.1rem;
}

.cert-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.cert-card {
  display: flex;
  align-items: center;
  gap: 1.1rem;
  padding: 1.4rem 1.6rem;
  background: #fff;
  border-radius: 1rem;
  box-shadow: 0 12px 32px rgba(12, 28, 64, 0.08);
  transition:
    transform 0.15s ease,
    box-shadow 0.15s ease;
}

.cert-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 18px 44px rgba(12, 28, 64, 0.16);
}

.cert-seal {
  font-size: 2.4rem;
}

.cert-body {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  flex: 1;
}

.cert-course {
  color: var(--login-secondary, #0c1c40);
  font-size: 1.2rem;
  font-weight: 700;
}

.cert-meta {
  color: #8b94a6;
  font-size: 0.88rem;
  font-weight: 700;
}

.cert-open {
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
  line-height: 1.5;
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
  .certs-head h1 {
    font-size: 2rem;
  }

  .cert-card {
    flex-wrap: wrap;
  }
}
</style>

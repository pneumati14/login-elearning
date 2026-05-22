<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const auth = useAuthStore()
const { loading, error } = storeToRefs(auth)
const route = useRoute()
const router = useRouter()

const email = ref('')
const password = ref('')

async function onSubmit() {
  const ok = await auth.login(email.value.trim(), password.value)
  if (!ok) return

  const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/e-learning'
  router.push(redirect)
}
</script>

<template>
  <section class="login">
    <div class="container-lg">
      <div class="login-card">
        <span class="eyebrow">{{ t('login.eyebrow') }}</span>
        <h1>{{ t('login.title') }}</h1>
        <p class="lead">{{ t('login.lead') }}</p>

        <form @submit.prevent="onSubmit">
          <label class="field">
            <span class="field-label">{{ t('login.email') }}</span>
            <input
              v-model="email"
              type="email"
              autocomplete="username"
              required
              placeholder="nev@pelda.hu"
            />
          </label>

          <label class="field">
            <span class="field-label">{{ t('login.password') }}</span>
            <input
              v-model="password"
              type="password"
              autocomplete="current-password"
              required
              placeholder="••••••••"
            />
          </label>

          <p v-if="error" class="form-error">{{ error }}</p>

          <button type="submit" class="btn-submit" :disabled="loading">
            {{ loading ? t('login.submitting') : t('login.submit') }}
          </button>
        </form>

        <p class="note">{{ t('login.note') }}</p>
      </div>
    </div>
  </section>
</template>

<style scoped>
.login {
  padding: 4rem 0 5.5rem;
}

.login-card {
  max-width: 440px;
  margin: 0 auto;
  padding: 2.6rem 2.4rem;
  background: #fff;
  border-radius: 1.1rem;
  box-shadow: 0 18px 44px rgba(12, 28, 64, 0.12);
}

.eyebrow {
  display: inline-block;
  color: var(--login-primary, #ed2044);
  font-size: 0.9rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.login-card h1 {
  margin: 0.35rem 0 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 2rem;
  font-weight: 700;
}

.lead {
  margin: 0 0 1.8rem;
  color: #545f71;
  font-size: 1rem;
  line-height: 1.5;
}

.field {
  display: block;
  margin-bottom: 1.15rem;
}

.field-label {
  display: block;
  margin-bottom: 0.4rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.92rem;
  font-weight: 700;
}

.field input {
  width: 100%;
  padding: 0.75rem 0.9rem;
  border: 1px solid #d4dae6;
  border-radius: 0.6rem;
  font-size: 1rem;
  color: var(--login-secondary, #0c1c40);
  transition: border-color 0.15s ease;
}

.field input:focus {
  outline: none;
  border-color: var(--login-primary, #ed2044);
}

.form-error {
  margin: 0 0 1rem;
  padding: 0.7rem 0.9rem;
  background: #fde8ec;
  border-radius: 0.6rem;
  color: #b3122e;
  font-size: 0.92rem;
}

.btn-submit {
  width: 100%;
  padding: 0.8rem 1rem;
  background: var(--login-primary, #ed2044);
  border: none;
  border-radius: 0.6rem;
  color: #fff;
  font-size: 1.02rem;
  font-weight: 700;
  cursor: pointer;
  transition:
    transform 0.15s ease,
    opacity 0.15s ease;
}

.btn-submit:hover:not(:disabled) {
  transform: translateY(-2px);
}

.btn-submit:disabled {
  opacity: 0.65;
  cursor: progress;
}

.note {
  margin: 1.6rem 0 0;
  color: #8b94a6;
  font-size: 0.86rem;
  text-align: center;
}
</style>

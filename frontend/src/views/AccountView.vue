<script setup lang="ts">
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import type { AppLocale } from '@/i18n'

const { t, locale } = useI18n()
const auth = useAuthStore()
const { user } = storeToRefs(auth)

// ── Language preference ───────────────────────────────────────────
const languages: { code: AppLocale; flag: string; label: string }[] = [
  { code: 'hu', flag: '🇭🇺', label: 'Magyar' },
  { code: 'en', flag: '🇬🇧', label: 'English' },
  { code: 'de', flag: '🇩🇪', label: 'Deutsch' },
  { code: 'pt', flag: '🇵🇹', label: 'Português' },
  { code: 'az', flag: '🇦🇿', label: 'Azərbaycan' },
  { code: 'tr', flag: '🇹🇷', label: 'Türkçe' },
  { code: 'pl', flag: '🇵🇱', label: 'Polski' },
]

function chooseLanguage(code: AppLocale) {
  auth.setLanguage(code)
}

const initials = computed(() => {
  const u = user.value
  if (!u) return ''
  return (u.firstName.charAt(0) + u.lastName.charAt(0)).toUpperCase()
})

const avatarUrl = computed(() => user.value?.avatarUrl ?? null)

// ── Profile picture ───────────────────────────────────────────────
const avatarBusy = ref(false)
const avatarError = ref<string | null>(null)

async function onAvatarChange(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file) return

  avatarError.value = null
  avatarBusy.value = true
  const result = await auth.uploadAvatar(file)
  avatarBusy.value = false
  if (!result.ok) {
    avatarError.value = result.error ?? t('account.uploadFailed')
  }
}

async function removeAvatar() {
  avatarError.value = null
  avatarBusy.value = true
  const result = await auth.deleteAvatar()
  avatarBusy.value = false
  if (!result.ok) {
    avatarError.value = result.error ?? t('account.deleteFailed')
  }
}

// ── Password change ───────────────────────────────────────────────
const currentPassword = ref('')
const newPassword = ref('')
const confirmPassword = ref('')
const pwSubmitting = ref(false)
const pwError = ref<string | null>(null)
const pwSuccess = ref(false)

async function onChangePassword() {
  pwError.value = null
  pwSuccess.value = false

  if (newPassword.value.length < 8) {
    pwError.value = t('account.errMinLength')
    return
  }
  if (newPassword.value !== confirmPassword.value) {
    pwError.value = t('account.errMismatch')
    return
  }

  pwSubmitting.value = true
  const result = await auth.changePassword(currentPassword.value, newPassword.value)
  pwSubmitting.value = false

  if (result.ok) {
    pwSuccess.value = true
    currentPassword.value = ''
    newPassword.value = ''
    confirmPassword.value = ''
  } else {
    pwError.value = result.error ?? t('account.errChangeFailed')
  }
}
</script>

<template>
  <section class="account">
    <div class="container-lg">
      <div class="account-head">
        <span class="eyebrow">{{ t('account.eyebrow') }}</span>
        <h1>{{ t('profile.myProfile') }}</h1>
        <p>{{ t('account.subtitle') }}</p>
      </div>

      <!-- ── Profile picture ───────────────────────────────────────── -->
      <div class="account-panel">
        <h2>{{ t('account.pictureTitle') }}</h2>
        <div class="avatar-row">
          <span class="avatar-lg">
            <img v-if="avatarUrl" :src="avatarUrl" alt="" />
            <span v-else class="avatar-initials-lg">{{ initials }}</span>
          </span>
          <div class="avatar-controls">
            <label class="btn-submit" :class="{ 'btn-submit--busy': avatarBusy }">
              {{ avatarBusy ? t('account.uploading') : t('account.uploadPicture') }}
              <input
                type="file"
                accept="image/*"
                :disabled="avatarBusy"
                hidden
                @change="onAvatarChange"
              />
            </label>
            <button
              v-if="avatarUrl"
              type="button"
              class="btn-ghost"
              :disabled="avatarBusy"
              @click="removeAvatar"
            >
              {{ t('account.remove') }}
            </button>
            <p class="avatar-hint">{{ t('account.pictureHint') }}</p>
            <p v-if="avatarError" class="msg msg--error">{{ avatarError }}</p>
          </div>
        </div>
      </div>

      <!-- ── Language ──────────────────────────────────────────────── -->
      <div class="account-panel">
        <h2>{{ t('account.languageTitle') }}</h2>
        <p class="lang-hint">{{ t('account.languageHint') }}</p>
        <div class="lang-options">
          <button
            v-for="lang in languages"
            :key="lang.code"
            type="button"
            class="lang-choice"
            :class="{ active: locale === lang.code }"
            @click="chooseLanguage(lang.code)"
          >
            <span class="lang-flag">{{ lang.flag }}</span>
            <span>{{ lang.label }}</span>
          </button>
        </div>
      </div>

      <!-- ── Password ──────────────────────────────────────────────── -->
      <form class="account-panel" @submit.prevent="onChangePassword">
        <h2>{{ t('account.passwordTitle') }}</h2>

        <label class="field">
          <span class="field-label">{{ t('account.currentPassword') }}</span>
          <input v-model="currentPassword" type="password" autocomplete="current-password" required />
        </label>
        <label class="field">
          <span class="field-label">{{ t('account.newPassword') }}</span>
          <input
            v-model="newPassword"
            type="password"
            autocomplete="new-password"
            required
            minlength="8"
            :placeholder="t('account.minChars')"
          />
        </label>
        <label class="field">
          <span class="field-label">{{ t('account.confirmPassword') }}</span>
          <input v-model="confirmPassword" type="password" autocomplete="new-password" required />
        </label>

        <p v-if="pwError" class="msg msg--error">{{ pwError }}</p>
        <p v-if="pwSuccess" class="msg msg--success">{{ t('account.passwordChanged') }}</p>

        <button type="submit" class="btn-submit" :disabled="pwSubmitting">
          {{ pwSubmitting ? t('account.saving') : t('account.passwordTitle') }}
        </button>
      </form>
    </div>
  </section>
</template>

<style scoped>
.account {
  padding: 3.5rem 0 5rem;
}

.account-head {
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

.account-head h1 {
  margin: 0.35rem 0 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 2.4rem;
  font-weight: 700;
}

.account-head p {
  margin: 0;
  color: #545f71;
  font-size: 1.05rem;
}

.account-panel {
  max-width: 560px;
  margin-bottom: 1.5rem;
  padding: 1.85rem;
  background: #fff;
  border-radius: 1.1rem;
  box-shadow: 0 12px 32px rgba(12, 28, 64, 0.08);
}

.account-panel h2 {
  margin: 0 0 1.3rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.25rem;
  font-weight: 700;
}

/* ── Avatar ─────────────────────────────────────────────────────── */
.avatar-row {
  display: flex;
  align-items: center;
  gap: 1.5rem;
}

.avatar-lg {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 110px;
  height: 110px;
  border-radius: 50%;
  overflow: hidden;
  background: var(--login-secondary, #0c1c40);
}

.avatar-lg img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.avatar-initials-lg {
  color: #fff;
  font-size: 2.4rem;
  font-weight: 700;
}

.avatar-controls {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.6rem;
}

.avatar-hint {
  margin: 0;
  color: #8b94a6;
  font-size: 0.82rem;
}

/* ── Form ───────────────────────────────────────────────────────── */
.field {
  display: block;
  margin-bottom: 1.1rem;
}

.field-label {
  display: block;
  margin-bottom: 0.4rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.9rem;
  font-weight: 700;
}

.field input {
  width: 100%;
  padding: 0.7rem 0.85rem;
  border: 1px solid #d4dae6;
  border-radius: 0.55rem;
  font-size: 1rem;
  color: var(--login-secondary, #0c1c40);
}

.field input:focus {
  outline: none;
  border-color: var(--login-primary, #ed2044);
}

.msg {
  margin: 0.2rem 0 0;
  padding: 0.6rem 0.85rem;
  border-radius: 0.55rem;
  font-size: 0.9rem;
}

.msg--error {
  background: #fde8ec;
  color: #b3122e;
}

.msg--success {
  margin-bottom: 1rem;
  background: #e3f6ec;
  color: #1c7a45;
}

.btn-submit {
  display: inline-block;
  padding: 0.7rem 1.4rem;
  background: var(--login-primary, #ed2044);
  border: none;
  border-radius: 0.55rem;
  color: #fff;
  font-size: 1rem;
  font-weight: 700;
  cursor: pointer;
}

.btn-submit:disabled,
.btn-submit--busy {
  opacity: 0.65;
  cursor: progress;
}

.btn-ghost {
  padding: 0.5rem 1.1rem;
  background: #fff;
  border: 1px solid #d4dae6;
  border-radius: 0.5rem;
  color: #545f71;
  font-size: 0.92rem;
  font-weight: 700;
  cursor: pointer;
}

.btn-ghost:hover:not(:disabled) {
  border-color: var(--login-secondary, #0c1c40);
}

.btn-ghost:disabled {
  opacity: 0.6;
}

/* ── Language ───────────────────────────────────────────────────── */
.lang-hint {
  margin: 0 0 1rem;
  color: #545f71;
  font-size: 0.95rem;
  line-height: 1.5;
}

.lang-options {
  display: flex;
  flex-wrap: wrap;
  gap: 0.7rem;
}

.lang-choice {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0.7rem 1.2rem;
  background: #fff;
  border: 1px solid #d4dae6;
  border-radius: 0.6rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.98rem;
  font-weight: 700;
  cursor: pointer;
}

.lang-choice:hover {
  border-color: var(--login-secondary, #0c1c40);
}

.lang-choice.active {
  border-color: var(--login-primary, #ed2044);
  background: #fdeef1;
  color: var(--login-primary, #ed2044);
}

.lang-flag {
  font-size: 1.3rem;
  line-height: 1;
}

@media (max-width: 575.98px) {
  .avatar-row {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>

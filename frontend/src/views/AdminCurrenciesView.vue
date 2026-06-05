<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import { useCurrencySettingsStore, type CurrencySetting } from '@/stores/currencySettings'

const { t } = useI18n()
const store = useCurrencySettingsStore()
const { settings, loading, error } = storeToRefs(store)

// Local editable copy; saved in one batch.
const draft = ref<CurrencySetting[]>([])
watch(
  settings,
  (next) => (draft.value = next.map((s) => ({ ...s }))),
  { immediate: true },
)

onMounted(() => {
  store.fetchSettings()
})

const saving = ref(false)
const saved = ref(false)
const saveError = ref<string | null>(null)

async function onSave(): Promise<void> {
  saving.value = true
  saveError.value = null
  saved.value = false
  const result = await store.updateSettings(draft.value.map((s) => ({ ...s, decimals: Number(s.decimals) })))
  saving.value = false
  if (result.ok) {
    saved.value = true
    window.setTimeout(() => (saved.value = false), 2500)
  } else {
    saveError.value = result.error ?? t('admin.saveFailed')
  }
}

/** Live preview of the rounding using a sample amount. */
function preview(s: CurrencySetting): string {
  const probe = { ...s, decimals: Number(s.decimals) }
  const idx = settings.value.findIndex((x) => x.currency === s.currency)
  if (-1 === idx) return ''
  // Format with the draft decimals without mutating the store.
  return new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency: probe.currency,
    minimumFractionDigits: Math.max(0, Math.min(4, probe.decimals || 0)),
    maximumFractionDigits: Math.max(0, Math.min(4, probe.decimals || 0)),
  }).format(1234.5678)
}
</script>

<template>
  <section class="admin">
    <div class="container-lg">
      <div class="admin-head">
        <span class="eyebrow">{{ t('admin.eyebrow') }}</span>
        <h1>{{ t('nav.adminCurrencies') }}</h1>
        <p>{{ t('adminCurrencies.subtitle') }}</p>
      </div>

      <div class="cur-panel">
        <p v-if="loading && settings.length === 0" class="state">{{ t('adminCurrencies.loading') }}</p>

        <div v-else-if="error" class="state state--error">
          <strong>{{ t('adminCurrencies.loadError') }}</strong>
          <button type="button" class="btn-retry" @click="store.fetchSettings()">{{ t('common.retry') }}</button>
        </div>

        <form v-else @submit.prevent="onSave">
          <table class="cur-table">
            <thead>
              <tr>
                <th>{{ t('adminCurrencies.colCurrency') }}</th>
                <th>{{ t('adminCurrencies.colDecimals') }}</th>
                <th>{{ t('adminCurrencies.colPreview') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="s in draft" :key="s.currency">
                <td class="cur-code">{{ s.currency }}</td>
                <td>
                  <input v-model.number="s.decimals" type="number" min="0" max="4" step="1" required />
                </td>
                <td class="cur-preview">{{ preview(s) }}</td>
              </tr>
            </tbody>
          </table>

          <p v-if="saveError" class="msg msg--error">{{ saveError }}</p>
          <div class="form-actions">
            <button type="submit" class="btn-submit" :disabled="saving">
              {{ saving ? t('admin.saving') : t('admin.save') }}
            </button>
            <span v-if="saved" class="cur-saved">✓ {{ t('adminCustomers.billingSaved') }}</span>
          </div>
        </form>
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
  margin: 0.2rem 0 0.5rem;
  color: var(--login-secondary, #0c1c40);
}

.admin-head p {
  margin: 0;
  color: #545f71;
}

.cur-panel {
  max-width: 560px;
  padding: 1.6rem 1.7rem;
  background: #fff;
  border-radius: 1rem;
  box-shadow: 0 10px 34px rgba(12, 28, 64, 0.08);
}

.cur-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 1.1rem;
  font-size: 0.95rem;
}

.cur-table th {
  padding: 0.55rem 0.7rem;
  border-bottom: 2px solid #e3e7ef;
  color: #8b94a6;
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-align: left;
  text-transform: uppercase;
}

.cur-table td {
  padding: 0.6rem 0.7rem;
  border-bottom: 1px solid #eef1f6;
  color: var(--login-secondary, #0c1c40);
  vertical-align: middle;
}

.cur-code {
  font-weight: 700;
}

.cur-table input {
  width: 90px;
  padding: 0.5rem 0.7rem;
  background: #f7f8fb;
  border: 1px solid #d4dae6;
  border-radius: 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-family: inherit;
}

.cur-table input:focus {
  outline: 2px solid var(--login-primary, #ed2044);
  outline-offset: -1px;
}

.cur-preview {
  color: #545f71;
}

.form-actions {
  display: flex;
  align-items: center;
  gap: 0.8rem;
}

.cur-saved {
  color: #198754;
  font-size: 0.92rem;
  font-weight: 700;
}

.state {
  margin: 0;
  padding: 1rem 0;
  color: #8b94a6;
}

.state--error {
  display: flex;
  align-items: center;
  gap: 1rem;
  color: #b3122e;
}

.btn-retry {
  padding: 0.4rem 0.9rem;
  background: #fff;
  border: 1px solid #d4dae6;
  border-radius: 0.45rem;
  font-size: 0.85rem;
  font-weight: 700;
  cursor: pointer;
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

.btn-submit {
  padding: 0.6rem 1.3rem;
  background: var(--login-primary, #ed2044);
  border: none;
  border-radius: 0.55rem;
  color: #fff;
  font-size: 0.95rem;
  font-weight: 700;
  cursor: pointer;
}

.btn-submit:disabled {
  opacity: 0.65;
  cursor: progress;
}
</style>

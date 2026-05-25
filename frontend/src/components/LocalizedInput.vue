<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { LocalizedDraft } from '@/composables/localized'

defineProps<{
  label: string
  multiline?: boolean
  placeholder?: string
  required?: boolean
}>()

const model = defineModel<LocalizedDraft>({ required: true })
const { t } = useI18n()
</script>

<template>
  <div class="loc-field">
    <span class="loc-label">
      {{ label }}
      <span v-if="required" class="loc-req">*</span>
    </span>
    <div class="loc-grid">
      <label class="loc-cell">
        <span class="loc-lang">{{ t('localized.en') }}</span>
        <textarea
          v-if="multiline"
          v-model="model.en"
          rows="3"
          :placeholder="placeholder"
        ></textarea>
        <input v-else v-model="model.en" type="text" :placeholder="placeholder" />
      </label>
      <label class="loc-cell">
        <span class="loc-lang">{{ t('localized.hu') }} <em>{{ t('localized.optional') }}</em></span>
        <textarea
          v-if="multiline"
          v-model="model.hu"
          rows="3"
          :placeholder="placeholder"
        ></textarea>
        <input v-else v-model="model.hu" type="text" :placeholder="placeholder" />
      </label>
      <label class="loc-cell">
        <span class="loc-lang">{{ t('localized.de') }} <em>{{ t('localized.optional') }}</em></span>
        <textarea
          v-if="multiline"
          v-model="model.de"
          rows="3"
          :placeholder="placeholder"
        ></textarea>
        <input v-else v-model="model.de" type="text" :placeholder="placeholder" />
      </label>
      <label class="loc-cell">
        <span class="loc-lang">{{ t('localized.pt') }} <em>{{ t('localized.optional') }}</em></span>
        <textarea
          v-if="multiline"
          v-model="model.pt"
          rows="3"
          :placeholder="placeholder"
        ></textarea>
        <input v-else v-model="model.pt" type="text" :placeholder="placeholder" />
      </label>
      <label class="loc-cell">
        <span class="loc-lang">{{ t('localized.az') }} <em>{{ t('localized.optional') }}</em></span>
        <textarea
          v-if="multiline"
          v-model="model.az"
          rows="3"
          :placeholder="placeholder"
        ></textarea>
        <input v-else v-model="model.az" type="text" :placeholder="placeholder" />
      </label>
      <label class="loc-cell">
        <span class="loc-lang">{{ t('localized.tr') }} <em>{{ t('localized.optional') }}</em></span>
        <textarea
          v-if="multiline"
          v-model="model.tr"
          rows="3"
          :placeholder="placeholder"
        ></textarea>
        <input v-else v-model="model.tr" type="text" :placeholder="placeholder" />
      </label>
      <label class="loc-cell">
        <span class="loc-lang">{{ t('localized.pl') }} <em>{{ t('localized.optional') }}</em></span>
        <textarea
          v-if="multiline"
          v-model="model.pl"
          rows="3"
          :placeholder="placeholder"
        ></textarea>
        <input v-else v-model="model.pl" type="text" :placeholder="placeholder" />
      </label>
    </div>
  </div>
</template>

<style scoped>
.loc-field {
  display: block;
  margin-bottom: 1rem;
}

.loc-label {
  display: block;
  margin-bottom: 0.35rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.88rem;
  font-weight: 700;
}

.loc-req {
  color: var(--login-primary, #ed2044);
}

.loc-grid {
  display: grid;
  gap: 0.7rem;
  grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
}

.loc-cell {
  display: block;
}

.loc-lang {
  display: block;
  margin-bottom: 0.2rem;
  color: #8b94a6;
  font-size: 0.74rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.loc-lang em {
  font-style: normal;
  text-transform: none;
  letter-spacing: 0;
  font-weight: 600;
}

.loc-cell input,
.loc-cell textarea {
  width: 100%;
  padding: 0.6rem 0.8rem;
  border: 1px solid #d4dae6;
  border-radius: 0.55rem;
  font-size: 0.96rem;
  font-family: inherit;
  color: var(--login-secondary, #0c1c40);
  background: #fff;
  resize: vertical;
}

.loc-cell input:focus,
.loc-cell textarea:focus {
  outline: none;
  border-color: var(--login-primary, #ed2044);
}

@media (max-width: 575.98px) {
  .loc-grid {
    grid-template-columns: 1fr;
  }
}
</style>

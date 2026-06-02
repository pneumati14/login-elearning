<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { countries } from '@/data/countries'
import huPostalData from '@/data/hu-postal-codes.json'

interface PostalEntry { zip: string; city: string }
const huPostal = huPostalData as PostalEntry[]

// Build lookup maps once, at module load (~3.5k entries; negligible cost).
const byZip = new Map<string, Set<string>>()
const byCity = new Map<string, Set<string>>()
for (const { zip, city } of huPostal) {
  let zs = byZip.get(zip); if (!zs) { zs = new Set(); byZip.set(zip, zs) }
  zs.add(city)
  let cs = byCity.get(city); if (!cs) { cs = new Set(); byCity.set(city, cs) }
  cs.add(zip)
}
const huCities = [...byCity.keys()].sort((a, b) => a.localeCompare(b, 'hu'))
const huZips = [...byZip.keys()].sort()

/** A zip maps to a single city → return it; otherwise null. */
function singleCityFor(zip: string): string | null {
  const s = byZip.get(zip)
  if (!s || 1 !== s.size) return null
  return s.values().next().value ?? null
}
/** A city has a single zip → return it; otherwise null (Budapest has 161). */
function singleZipFor(city: string): string | null {
  const s = byCity.get(city)
  if (!s || 1 !== s.size) return null
  return s.values().next().value ?? null
}

export interface AddressValue {
  country: string | null
  city: string | null
  postalCode: string | null
  street: string | null
}

const props = defineProps<{
  modelValue: AddressValue
  /** Optional id stem so multiple fieldsets on one page have distinct list ids. */
  idStem?: string
  disabled?: boolean
}>()

const emit = defineEmits<{ 'update:modelValue': [AddressValue] }>()

const { locale } = useI18n()

type LocaleKey = 'hu' | 'en' | 'de' | 'az' | 'es' | 'pl' | 'pt' | 'tr'
const ensureLocale = (code: string): LocaleKey =>
  (['hu', 'en', 'de', 'az', 'es', 'pl', 'pt', 'tr'] as const).includes(code as LocaleKey)
    ? (code as LocaleKey)
    : 'en'

const sortedCountries = computed(() => {
  const key = ensureLocale(locale.value)
  return [...countries].sort((a, b) => {
    // Hungary first by convention, then alphabetical by localized name.
    if ('HU' === a.code && 'HU' !== b.code) return -1
    if ('HU' === b.code && 'HU' !== a.code) return 1
    return a.names[key].localeCompare(b.names[key], key)
  })
})

const localizedName = (code: string): string => {
  const c = countries.find((x) => x.code === code)
  return c ? c.names[ensureLocale(locale.value)] : code
}

const isHungary = computed(() => 'HU' === props.modelValue.country)

const cityListId = computed(() => `${props.idStem ?? 'addr'}-hu-cities`)
const zipListId = computed(() => `${props.idStem ?? 'addr'}-hu-zips`)

// Remember whatever we autofilled so we can update it on later changes
// — but never overwrite a value the user typed themselves.
const autofilledCity = ref<string | null>(null)
const autofilledPostal = ref<string | null>(null)

function patch(partial: Partial<AddressValue>): void {
  emit('update:modelValue', { ...props.modelValue, ...partial })
}

function onCountry(e: Event): void {
  const v = (e.target as HTMLSelectElement).value
  patch({ country: '' === v ? null : v })
}

function onCity(e: Event): void {
  const v = (e.target as HTMLInputElement).value.trim()
  const next: Partial<AddressValue> = { city: '' === v ? null : v }

  if (isHungary.value && '' !== v && byCity.has(v)) {
    const zip = singleZipFor(v)
    if (null !== zip) {
      const current = props.modelValue.postalCode
      if (null === current || '' === current || current === autofilledPostal.value) {
        next.postalCode = zip
        autofilledPostal.value = zip
      }
    }
  }
  patch(next)
}

function onPostal(e: Event): void {
  const v = (e.target as HTMLInputElement).value.trim()
  const next: Partial<AddressValue> = { postalCode: '' === v ? null : v }

  if (isHungary.value && '' !== v && byZip.has(v)) {
    const city = singleCityFor(v)
    if (null !== city) {
      const current = props.modelValue.city
      if (null === current || '' === current || current === autofilledCity.value) {
        next.city = city
        autofilledCity.value = city
      }
    }
  }
  patch(next)
}

function onStreet(e: Event): void {
  const v = (e.target as HTMLInputElement).value
  patch({ street: '' === v.trim() ? null : v })
}
</script>

<template>
  <div class="addr-grid">
    <label class="addr-field addr-field--country">
      <span>{{ $t('address.country') }}</span>
      <select :value="modelValue.country ?? ''" :disabled="disabled" @change="onCountry">
        <option value="">{{ $t('address.countryPlaceholder') }}</option>
        <option v-for="c in sortedCountries" :key="c.code" :value="c.code">
          {{ localizedName(c.code) }}
        </option>
      </select>
    </label>

    <label class="addr-field addr-field--city">
      <span>{{ $t('address.city') }}</span>
      <input
        :value="modelValue.city ?? ''"
        :list="isHungary ? cityListId : undefined"
        :disabled="disabled"
        type="text"
        maxlength="120"
        autocomplete="address-level2"
        @change="onCity"
        @blur="onCity"
      />
      <datalist v-if="isHungary" :id="cityListId">
        <option v-for="city in huCities" :key="city" :value="city" />
      </datalist>
    </label>

    <label class="addr-field addr-field--postal">
      <span>{{ $t('address.postalCode') }}</span>
      <input
        :value="modelValue.postalCode ?? ''"
        :list="isHungary ? zipListId : undefined"
        :disabled="disabled"
        type="text"
        maxlength="16"
        autocomplete="postal-code"
        @input="onPostal"
      />
      <datalist v-if="isHungary" :id="zipListId">
        <option v-for="zip in huZips" :key="zip" :value="zip" />
      </datalist>
    </label>

    <label class="addr-field addr-field--street">
      <span>{{ $t('address.street') }}</span>
      <input
        :value="modelValue.street ?? ''"
        :disabled="disabled"
        type="text"
        maxlength="255"
        autocomplete="street-address"
        @input="onStreet"
      />
    </label>
  </div>
</template>

<style scoped>
.addr-grid {
  display: grid;
  /* country | postal | street; city sits on a second row */
  grid-template-columns: 1fr 8rem 2fr;
  gap: 0.9rem 1rem;
}

.addr-field {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  min-width: 0;
}

.addr-field--country {
  grid-column: 1;
  grid-row: 1;
}

.addr-field--postal {
  grid-column: 2;
  grid-row: 1;
}

.addr-field--city {
  grid-column: 3;
  grid-row: 1;
}

.addr-field--street {
  grid-column: 1 / -1;
  grid-row: 2;
}

.addr-field span {
  color: var(--login-secondary, #0c1c40);
  font-size: 0.85rem;
  font-weight: 700;
}

.addr-field input,
.addr-field select {
  padding: 0.55rem 0.7rem;
  background: #f7f8fb;
  border: 1px solid #d4dae6;
  border-radius: 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-family: inherit;
}

.addr-field input:focus,
.addr-field select:focus {
  outline: 2px solid var(--login-primary, #ed2044);
  outline-offset: -1px;
  background: #fff;
}

.addr-field input:disabled,
.addr-field select:disabled {
  background: #ecedf2;
  color: #8b94a6;
  cursor: not-allowed;
}

@media (max-width: 767.98px) {
  .addr-grid {
    grid-template-columns: 1fr;
  }

  .addr-field--country,
  .addr-field--postal,
  .addr-field--city,
  .addr-field--street {
    grid-column: 1;
    grid-row: auto;
  }
}
</style>

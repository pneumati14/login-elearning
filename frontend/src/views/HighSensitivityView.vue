<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { highSensitivityPages } from '@/data/highSensitivity'

// `slug` is supplied per route (see router/index.ts).
const props = defineProps<{ slug: string }>()

const { t } = useI18n()

const page = computed(() => {
  const found = highSensitivityPages.find((p) => p.slug === props.slug)
  if (!found) {
    throw new Error(`Unknown High Sensitivity Solutions slug: ${props.slug}`)
  }
  return found
})

// Areas without published contacts yet fall back to a placeholder.
const hasContacts = computed(() => page.value.contacts.length > 0)

/** "Dr. András Fehér, PhD" → "ÁF" — drops the Dr./PhD honorifics. */
function initials(name: string): string {
  return name
    .replace(/,.*$/, '')
    .split(/\s+/)
    .filter((word) => !/^(dr\.?|phd|prof\.?)$/i.test(word))
    .slice(0, 2)
    .map((word) => word.charAt(0).toUpperCase())
    .join('')
}

const telHref = (phone: string) => `tel:${phone.replace(/\s+/g, '')}`
</script>

<template>
  <div class="hss-page">
    <!-- ── Themed hero — one line-art motif per area ─────────────── -->
    <header class="hss-hero">
      <div class="container-lg hss-hero-inner">
        <div class="hss-hero-text">
          <span class="eyebrow">{{ t('highSensitivity.eyebrow') }}</span>
          <h1>{{ t(page.titleKey) }}</h1>
        </div>

        <div class="hss-hero-art" aria-hidden="true">
          <!-- Health — heart with an ECG pulse line. -->
          <svg v-if="page.slug === 'health'" viewBox="0 0 160 160" class="hss-art-svg">
            <circle class="art-disc" cx="80" cy="80" r="66" />
            <path
              class="art-stroke"
              d="M80 130 C44 102 24 82 24 57 C24 41 37 29 52 29 C64 29 74 36 80 47 C86 36 96 29 108 29 C123 29 136 41 136 57 C136 82 116 102 80 130 Z"
            />
            <path class="art-accent" d="M33 74 H56 L66 51 L80 97 L90 74 H127" />
          </svg>

          <!-- Aviation — a paper plane climbing along a flight path. -->
          <svg
            v-else-if="page.slug === 'aviation'"
            viewBox="0 0 160 160"
            class="hss-art-svg"
          >
            <circle class="art-disc" cx="80" cy="80" r="66" />
            <path class="art-accent art-dashed" d="M26 130 C54 86 104 60 138 30" />
            <circle class="art-dot" cx="26" cy="130" r="6" />
            <path
              class="art-fill"
              transform="rotate(-15 80 80) translate(44 40) scale(3.15)"
              d="M2 21l21-9L2 3v7l15 2-15 2v7z"
            />
          </svg>

          <!-- Defense — a layered, double-outline shield. -->
          <svg v-else viewBox="0 0 160 160" class="hss-art-svg">
            <circle class="art-disc" cx="80" cy="80" r="66" />
            <path
              class="art-stroke"
              d="M80 24 L128 41 V80 C128 109 108 129 80 140 C52 129 32 109 32 80 V41 Z"
            />
            <path
              class="art-accent"
              d="M80 39 L114 52 V80 C114 102 98 118 80 126 C62 118 46 102 46 80 V52 Z"
            />
          </svg>
        </div>
      </div>
    </header>

    <div class="container-lg hss-body">
      <template v-if="hasContacts">
        <!-- The custom-order statement, carried over from the old site. -->
        <p class="hss-intro">{{ t(page.introKey) }}</p>

        <h2 class="hss-contact-heading">{{ t('highSensitivity.contactHeading') }}</h2>

        <div class="hss-contacts">
          <article
            v-for="contact in page.contacts"
            :key="contact.email"
            class="contact-card"
          >
            <span class="contact-avatar">
              <img
                v-if="contact.photo"
                class="contact-photo"
                :src="contact.photo"
                :alt="contact.name"
              />
              <span v-else>{{ initials(contact.name) }}</span>
            </span>
            <h3 class="contact-name">{{ contact.name }}</h3>
            <p class="contact-role">{{ t('highSensitivity.contactRole') }}</p>

            <div class="contact-actions">
              <a class="contact-link" :href="`mailto:${contact.email}`">
                <svg class="contact-icon" viewBox="0 0 16 16" aria-hidden="true">
                  <use xlink:href="/frontend-files/images/icons.svg#mailat"></use>
                </svg>
                <span>{{ contact.email }}</span>
              </a>
              <a class="contact-link" :href="telHref(contact.phone)">
                <svg class="contact-icon" viewBox="0 0 16 16" aria-hidden="true">
                  <use xlink:href="/frontend-files/images/icons.svg#call"></use>
                </svg>
                <span>{{ contact.phone }}</span>
              </a>
            </div>
          </article>
        </div>
      </template>

      <div v-else class="hss-placeholder">
        <span class="hss-placeholder-icon">🔒</span>
        <p>{{ t('highSensitivity.comingSoon') }}</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* ── Themed hero ────────────────────────────────────────────────── */
.hss-hero {
  position: relative;
  overflow: hidden;
  background: linear-gradient(135deg, #f6f8fc 0%, #eceffa 100%);
  border-bottom: 1px solid #e6e9f2;
}

/* Soft decorative shapes — the same brand-coloured glow on every page. */
.hss-hero::before,
.hss-hero::after {
  content: '';
  position: absolute;
  border-radius: 50%;
  pointer-events: none;
}

.hss-hero::before {
  top: -120px;
  right: -70px;
  width: 320px;
  height: 320px;
  background: radial-gradient(
    circle,
    rgba(237, 32, 68, 0.1) 0%,
    rgba(237, 32, 68, 0) 70%
  );
}

.hss-hero::after {
  bottom: -150px;
  left: -110px;
  width: 360px;
  height: 360px;
  background: radial-gradient(
    circle,
    rgba(12, 28, 64, 0.08) 0%,
    rgba(12, 28, 64, 0) 70%
  );
}

.hss-hero-inner {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 2.5rem;
  padding: 3.25rem 0;
}

.hss-hero-text {
  min-width: 0;
}

.eyebrow {
  display: inline-block;
  color: var(--login-primary, #ed2044);
  font-size: 0.95rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.hss-hero-text h1 {
  margin: 0.4rem 0 0;
  color: var(--login-secondary, #0c1c40);
  font-size: 3rem;
  font-weight: 700;
}

.hss-hero-art {
  flex-shrink: 0;
  width: 210px;
  height: 210px;
}

.hss-art-svg {
  display: block;
  width: 100%;
  height: 100%;
}

/* Motif parts — brand red / navy line art. */
.art-disc {
  fill: rgba(12, 28, 64, 0.05);
}

.art-stroke {
  fill: none;
  stroke: var(--login-secondary, #0c1c40);
  stroke-width: 7;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.art-accent {
  fill: none;
  stroke: var(--login-primary, #ed2044);
  stroke-width: 7;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.art-dashed {
  stroke-width: 5;
  stroke-dasharray: 1 12;
}

.art-fill {
  fill: var(--login-secondary, #0c1c40);
}

.art-dot {
  fill: var(--login-primary, #ed2044);
}

/* ── Body ───────────────────────────────────────────────────────── */
.hss-body {
  padding: 2.75rem 0 5rem;
}

/* ── Intro callout ──────────────────────────────────────────────── */
.hss-intro {
  max-width: 760px;
  margin: 0 0 2.75rem;
  padding: 1.4rem 1.7rem;
  border-left: 4px solid var(--login-primary, #ed2044);
  border-radius: 0.25rem 0.9rem 0.9rem 0.25rem;
  background: linear-gradient(120deg, #ffffff 0%, #fdf1f3 100%);
  box-shadow: 0 12px 32px rgba(12, 28, 64, 0.08);
  color: var(--login-secondary, #0c1c40);
  font-size: 1.22rem;
  line-height: 1.6;
}

/* ── Contact ────────────────────────────────────────────────────── */
.hss-contact-heading {
  margin: 0 0 1.25rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.5rem;
  font-weight: 700;
}

.hss-contacts {
  display: grid;
  gap: 1.5rem;
  grid-template-columns: repeat(auto-fit, minmax(300px, 380px));
}

.contact-card {
  padding: 2rem 1.8rem;
  background: #fff;
  border-radius: 1.1rem;
  box-shadow: 0 16px 40px rgba(12, 28, 64, 0.1);
  transition:
    transform 0.18s ease,
    box-shadow 0.18s ease;
}

.contact-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 22px 52px rgba(12, 28, 64, 0.16);
}

.contact-avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 96px;
  height: 96px;
  margin-bottom: 1.2rem;
  border-radius: 50%;
  overflow: hidden;
  background: linear-gradient(
    135deg,
    var(--login-secondary, #0c1c40) 0%,
    var(--login-primary, #ed2044) 100%
  );
  color: #fff;
  font-size: 1.9rem;
  font-weight: 700;
  box-shadow:
    0 0 0 1px rgba(12, 28, 64, 0.06),
    0 10px 24px rgba(12, 28, 64, 0.16);
}

.contact-photo {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.contact-name {
  margin: 0 0 0.3rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.2rem;
  font-weight: 700;
}

.contact-role {
  margin: 0 0 1.2rem;
  color: #8b94a6;
  font-size: 0.9rem;
}

.contact-actions {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  padding-top: 1.1rem;
  border-top: 1px solid #eef1f6;
}

.contact-link {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  padding: 0.55rem 0.7rem;
  border-radius: 0.55rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-weight: 700;
  transition:
    background 0.15s ease,
    color 0.15s ease;
}

.contact-link:hover {
  background: #fdeef1;
  color: var(--login-primary, #ed2044);
}

.contact-icon {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
  color: var(--login-primary, #ed2044);
  fill: var(--login-primary, #ed2044);
}

/* ── Placeholder (areas without published content yet) ──────────── */
.hss-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.9rem;
  padding: 3rem 1.6rem;
  background: #fff;
  border-radius: 1.1rem;
  box-shadow: 0 12px 32px rgba(12, 28, 64, 0.08);
  text-align: center;
}

.hss-placeholder-icon {
  font-size: 2.6rem;
}

.hss-placeholder p {
  margin: 0;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.05rem;
  font-weight: 700;
}

@media (max-width: 767.98px) {
  .hss-hero-inner {
    flex-direction: column;
    align-items: flex-start;
    gap: 1.5rem;
    padding: 2.5rem 0;
  }

  .hss-hero-art {
    align-self: center;
    width: 150px;
    height: 150px;
  }

  .hss-hero-text h1 {
    font-size: 2.2rem;
  }
}

@media (max-width: 575.98px) {
  .hss-intro {
    font-size: 1.08rem;
  }

  .hss-contacts {
    grid-template-columns: 1fr;
  }
}
</style>

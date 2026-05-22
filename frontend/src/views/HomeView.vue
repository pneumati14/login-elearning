<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const partners = [
  'airbus', 'velux', 'bridgestone', 'bourns', 'carrier', 'corinthia', 'creaton',
  'danubius-hotel', 'dbschenker', 'eisberg', 'flex', 'kuehne-nagel', 'lg', 'obo',
  'praktiker', 'rosenberger', 'rossmann', 'schrack', 'zeiss',
]

const flags = ['at', 'cz', 'de', 'es', 'fr', 'gb', 'hr', 'hu', 'it', 'pl', 'pt', 'ro', 'sk']

// Labels resolved from the `home.devices.<key>` locale namespace.
const devices = [
  { img: 'kiosk.png', key: 'kiosk' },
  { img: 'laptop.png', key: 'laptop' },
  { img: 'dektop.png', key: 'desktop' },
  { img: 'tablet.png', key: 'tablet' },
  { img: 'mobile.png', key: 'mobile' },
]

// Content resolved from the `home.benefits.<key>` locale namespace.
const benefitKeys = [
  'efficiency', 'collaboration', 'accuracy', 'compliance', 'productivity', 'insights', 'satisfaction',
] as const
const benefits = computed(() =>
  benefitKeys.map((key) => ({
    key,
    t: t(`home.benefits.${key}.t`),
    d: t(`home.benefits.${key}.d`),
  })),
)

interface Module {
  id: string
  icon: string
  logo: string
  link: string
  color: string
  bg: string
  activeBg: string
}

// Structural data only; title/text live in the `home.modules.<id>` namespace.
const modules: Module[] = [
  { id: 'ma1', icon: 'icon-hrbase', logo: 'logo-text-round-hrbase',
    link: '/hrbase', color: '#ffffff', bg: 'var(--login-hrbase)', activeBg: 'var(--login-hrbase-alt)' },
  { id: 'ma2', icon: 'icon-cplatform', logo: 'logo-text-round-cplatform',
    link: '/cplatform', color: '#000000', bg: 'var(--login-cplatform)', activeBg: 'var(--login-cplatform-alt)' },
  { id: 'ma3', icon: 'icon-holiday', logo: 'logo-text-round-holiday',
    link: '/holiday', color: '#ffffff', bg: 'var(--login-holiday)', activeBg: 'var(--login-holiday-alt)' },
  { id: 'ma4', icon: 'icon-workhour', logo: 'logo-text-round-workhour',
    link: '/workhour', color: '#ffffff', bg: 'var(--login-workhour)', activeBg: 'var(--login-workhour-alt)' },
  { id: 'ma5', icon: 'icon-productivity', logo: 'logo-text-round-productivity',
    link: '/productivity', color: '#ffffff', bg: 'var(--login-productivity)', activeBg: 'var(--login-productivity-alt)' },
  { id: 'ma6', icon: 'icon-competency', logo: 'logo-text-round-competency',
    link: '/competency', color: '#ffffff', bg: 'var(--login-competency)', activeBg: 'var(--login-competency-alt)' },
  { id: 'ma7', icon: 'icon-shift', logo: 'logo-text-round-shift',
    link: '/shift', color: '#ffffff', bg: 'var(--login-shift)', activeBg: 'var(--login-shift-alt)' },
  { id: 'ma8', icon: 'icon-workwear', logo: 'logo-text-round-workwear',
    link: '/workwear', color: '#ffffff', bg: 'var(--login-workwear)', activeBg: 'var(--login-workwear-alt)' },
  { id: 'ma9', icon: 'icon-app', logo: 'logo-text-round-app',
    link: '/app', color: '#ffffff', bg: 'var(--login-app)', activeBg: 'var(--login-app-alt)' },
  { id: 'ma10', icon: 'icon-ai', logo: 'logo-text-round-ai',
    link: '/ai', color: '#ffffff', bg: 'var(--login-ai)', activeBg: 'var(--login-ai-alt)' },
]

const openModule = ref<string | null>(null)
function toggleModule(id: string) {
  openModule.value = openModule.value === id ? null : id
}

function moduleStyle(m: Module): Record<string, string> {
  return {
    '--login-accordion-color': m.color,
    '--login-accordion-bg': m.bg,
    '--login-accordion-active-bg': m.activeBg,
  }
}
const tabStyle: Record<string, string> = {
  '--login-accordion-color': '#000',
  '--login-accordion-bg': '#e9e6d2',
  '--login-accordion-active-bg': '#dfdcc7',
}

const img = (name: string) => `/frontend-files/images/${name}`

onMounted(() => document.body.classList.add('home'))
onBeforeUnmount(() => document.body.classList.remove('home'))
</script>

<template>
  <div class="home-page">
    <!-- ── Hero ─────────────────────────────────────────────────── -->
    <section class="promobox">
      <div class="container-lg">
        <div class="row g-4 align-items-center justify-content-center">
          <div class="col-sm-12 col-md-7 col-lg-6 order-sm-2 order-md-1">
            <h2 class="sub-title">
              {{ t('home.heroTitle') }}
              <img src="/frontend-files/images/easeplusplus-b.svg" class="img-fluid m-1" alt="ease++" />
            </h2>
            <p>{{ t('home.heroIntro') }}</p>
          </div>
          <div class="col-8 col-sm-6 col-md-5 col-lg-6 order-sm-1 order-md-2">
            <img src="/frontend-files/images/group-3350.png" class="img-fluid d-block mx-auto" alt="" />
          </div>
        </div>
      </div>
    </section>

    <!-- ── Main lead (navy) ─────────────────────────────────────── -->
    <section class="main-lead">
      <div class="main-lead-inner">
        <div class="partners py-4 py-lg-5">
          <div class="sub-title">{{ t('home.trustedBy') }}</div>
          <div class="container-fluid">
            <div class="marquee">
              <div class="marquee-track">
                <img
                  v-for="(p, i) in [...partners, ...partners]"
                  :key="i"
                  :src="img(p + '.png')"
                  :alt="p"
                />
              </div>
            </div>
          </div>
        </div>

        <div class="container container-min-1">
          <div class="bg-schedule">
            <div class="row g-4 justify-content-center justify-content-lg-between align-items-center py-4 py-lg-5">
              <div class="col-md-6 col-lg-5 col-xl-5">{{ t('home.leadSchedule') }}</div>
              <div class="col-4 col-md-6 col-lg-5 col-xl-5">
                <img src="/frontend-files/images/easeplusplus-w.svg" class="img-fluid w-100" alt="ease++" />
              </div>
            </div>
          </div>

          <div class="row g-4 justify-content-around py-5">
            <div class="col-auto">
              <img src="/frontend-files/images/iso-9001.png" class="img-fluid" alt="ISO 9001" />
            </div>
            <div class="col-auto">
              <img src="/frontend-files/images/iso-27001.png" class="img-fluid" alt="ISO 27001" />
            </div>
          </div>

          <div class="pb-4 pb-lg-5">
            <p v-html="t('home.leadAdvanced')"></p>
          </div>

          <h2 class="main-title text-yellow text-center">{{ t('home.ourSolution') }}</h2>
          <div class="row g-4 justify-content-between">
            <div class="col-md-6 col-lg-5">
              <div class="pb-4 pb-lg-5" v-html="t('home.solutionCustomer')"></div>
              <img src="/frontend-files/images/building.png" class="img-fluid d-block mx-auto mb-2" alt="" />
              <h4 class="fw-bold text-center mb-4">{{ t('home.customer') }}</h4>
              <div class="py-4 py-lg-5" v-html="t('home.solutionPartner')"></div>
              <img src="/frontend-files/images/handshake.png" class="img-fluid d-block mx-auto mb-2" alt="" />
              <h4 class="fw-bold text-center mb-4">{{ t('home.partner') }}</h4>
            </div>
            <div class="col-md-6 col-lg-5">
              <img src="/frontend-files/images/foldgomb.png" class="img-fluid d-block mx-auto mb-2" alt="" />
              <h4 class="fw-bold text-center mb-4">{{ t('home.country') }}</h4>
              <div class="flags-widget">
                <span v-for="f in flags" :key="f" class="flag-item">
                  <span><img :src="img('flags/' + f + '.svg')" class="img-fluid" :alt="f" /></span>
                </span>
              </div>
              <div class="py-4 py-lg-5">{{ t('home.solutionCountry') }}</div>
              <img src="/frontend-files/images/people.png" class="img-fluid d-block mx-auto mb-2" alt="" />
              <h4 class="fw-bold text-center mb-4">{{ t('home.user') }}</h4>
              <div class="py-4 py-lg-5" v-html="t('home.solutionUser')"></div>
            </div>
          </div>

          <div class="fw-bold text-yellow py-4 py-lg-5">{{ t('home.integration') }}</div>

          <h2 class="main-title text-yellow text-center">{{ t('home.availableOn') }}</h2>
          <div class="row gx-2 gy-4 py-4 justify-content-center">
            <div v-for="d in devices" :key="d.key" class="col-6 col-sm-4 col-lg">
              <div class="d-flex flex-column h-100 text-center">
                <div class="image">
                  <img :src="img(d.img)" class="img-fluid mb-2" :alt="d.key" />
                </div>
                <div class="fw-medium mt-auto">{{ t('home.devices.' + d.key) }}</div>
              </div>
            </div>
          </div>

          <div class="py-4 py-lg-5 bg-event">
            <div v-for="b in benefits" :key="b.key" class="mb-5 line-height-normal">
              <div class="fw-bold text-yellow">{{ b.t }}</div>
              <div class="fs-5">{{ b.d }}</div>
            </div>
          </div>

          <div class="row g-4 justify-content-center">
            <div class="col-sm-8 col-md-7 col-lg d-flex flex-column pe-lg-4 order-2 order-sm-1">
              <div v-html="t('home.expertise')"></div>
            </div>
            <div class="col-6 col-sm-4 col-md-5 col-lg-auto order-1 order-sm-2">
              <img src="/frontend-files/images/group-3352.png" class="img-fluid d-block mx-auto" alt="" />
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ── Ease++ Software / modules ────────────────────────────── -->
    <section class="page-container">
      <div class="container container-min-2">
        <h2 class="main-title text-yellow text-center">{{ t('home.easeSoftware') }}</h2>

        <div class="py-4 py-lg-5">
          <div class="accordion accordion-modules">
            <div
              v-for="m in modules"
              :key="m.id"
              class="accordion-item"
              :class="{ active: openModule === m.id }"
              :style="moduleStyle(m)"
            >
              <h2 class="accordion-header">
                <button
                  class="accordion-button"
                  :class="{ collapsed: openModule !== m.id }"
                  type="button"
                  @click="toggleModule(m.id)"
                >
                  <span class="icon">
                    <svg viewBox="0 0 16 16"><use :href="`/frontend-files/images/icons.svg#${m.icon}`"></use></svg>
                  </span>
                  <span>{{ t(`home.modules.${m.id}.title`) }}</span>
                  <span class="logo">
                    <img :src="`/frontend-files/images/solutions/${m.logo}.svg`" class="img-fluid" alt="" />
                  </span>
                </button>
              </h2>
              <div class="accordion-collapse collapse" :class="{ show: openModule === m.id }">
                <div class="accordion-body">
                  <div class="row g-3 g-lg-4 align-items-center">
                    <div class="col-3">
                      <svg viewBox="0 0 16 16" class="ma-img">
                        <use :href="`/frontend-files/images/icons.svg#${m.icon}`"></use>
                      </svg>
                    </div>
                    <div class="col-9">{{ t(`home.modules.${m.id}.text`) }}</div>
                  </div>
                  <div class="button-wrapper mt-5 text-center">
                    <RouterLink :to="m.link" class="btn btn-primary">{{ t('home.moreInfo') }}</RouterLink>
                  </div>
                </div>
              </div>
            </div>

            <div
              class="accordion-item"
              :class="{ active: openModule === 'ma-tab' }"
              :style="tabStyle"
            >
              <h2 class="accordion-header">
                <button
                  class="accordion-button"
                  :class="{ collapsed: openModule !== 'ma-tab' }"
                  type="button"
                  @click="toggleModule('ma-tab')"
                >
                  <span class="icon">
                    <svg viewBox="0 0 16 16"><use href="/frontend-files/images/icons.svg#icon-modulestab"></use></svg>
                  </span>
                  <span>{{ t('home.allModules') }}</span>
                  <span class="logo">
                    <img src="/frontend-files/images/solutions/logo-text-round-modulestab.svg" class="img-fluid" alt="" />
                  </span>
                </button>
              </h2>
              <div class="accordion-collapse collapse" :class="{ show: openModule === 'ma-tab' }">
                <div class="accordion-body">
                  <div>
                    <img src="/frontend-files/images/main-modul-tab.png" class="img-fluid d-block mx-auto" alt="" />
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="py-4 py-lg-5">
          <div class="row g-4 justify-content-center align-items-center">
            <div class="col-sm-6 col-md-4">
              <img src="/frontend-files/images/mikroszkop.png" class="img-fluid d-block mx-auto" alt="" />
            </div>
            <div class="col-sm-12 col-md-8">
              <div class="text-uppercase line-height-normal fs-6">
                <h3 class="fs-2 fw-bold">{{ t('home.researchArea') }}</h3>
                {{ t('home.researchAreaText') }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="container container-min-1">
        <div class="request-demo py-4 py-lg-5">
          <div class="row g-4 justify-content-between align-items-center">
            <div class="col-12 col-md-7 col-lg-7">
              <h3 class="fs-4 fw-bold">{{ t('productPage.requestDemo') }}</h3>
              <p>{{ t('productPage.requestDemoLead') }}</p>
            </div>
            <div class="col-12 col-md-auto col-lg-auto">
              <div class="button-wrapper d-flex flex-column gap-3">
                <RouterLink to="/book-a-demo" class="btn btn-primary btn-icon btn-lg">
                  <span class="icon"><svg viewBox="0 0 16 16"><use href="/frontend-files/images/icons.svg#mailat"></use></svg></span>
                  <span class="text">{{ t('productPage.contact') }}</span>
                </RouterLink>
                <RouterLink to="/book-a-demo" class="btn btn-primary btn-icon btn-lg">
                  <span class="icon"><svg viewBox="0 0 16 16"><use href="/frontend-files/images/icons.svg#call"></use></svg></span>
                  <span class="text">{{ t('productPage.callback') }}</span>
                </RouterLink>
              </div>
            </div>
          </div>
        </div>

        <div class="py-5">
          <img src="/frontend-files/images/group-3061.png" class="img-fluid d-block mx-auto" alt="" />
        </div>

        <div class="app-widget d-flex justify-content-center justify-content-lg-end align-items-center gap-3 pb-5">
          <a
            href="https://apps.apple.com/es/developer/login-autonom-korltolt-felelssg-trsasg/id1465102568"
            target="_blank"
            rel="noopener"
          >
            <img src="/frontend-files/images/appstore.png" class="img-fluid" alt="App Store" />
          </a>
          <a href="https://play.google.com/store/apps/developer?id=Login+Autonom" target="_blank" rel="noopener">
            <img src="/frontend-files/images/googleplay.png" class="img-fluid" alt="Google Play" />
          </a>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
/* "trusted by" logo strip — CSS marquee in place of the original Swiper carousel. */
.marquee {
  overflow: hidden;
  width: 100%;
  -webkit-mask-image: linear-gradient(90deg, transparent, #000 6%, #000 94%, transparent);
  mask-image: linear-gradient(90deg, transparent, #000 6%, #000 94%, transparent);
}

.marquee-track {
  display: flex;
  align-items: center;
  gap: 3.5rem;
  width: max-content;
  animation: marquee-scroll 48s linear infinite;
}

.marquee-track img {
  flex: 0 0 auto;
  height: 42px;
  width: auto;
}

@keyframes marquee-scroll {
  from {
    transform: translateX(0);
  }
  to {
    transform: translateX(-50%);
  }
}

@media (prefers-reduced-motion: reduce) {
  .marquee-track {
    animation: none;
    flex-wrap: wrap;
    justify-content: center;
  }
}
</style>

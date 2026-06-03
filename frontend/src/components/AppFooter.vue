<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const LA = 'https://www.loginautonom.com'

const solutions = [
  { label: 'HR Base', href: `${LA}/hrbase` },
  { label: 'Productivity', href: `${LA}/productivity` },
  { label: 'Workwear', href: `${LA}/workwear` },
  { label: 'Cplatform', href: `${LA}/cplatform` },
  { label: 'Competency', href: `${LA}/competency` },
  { label: 'Workhour', href: `${LA}/workhour` },
  { label: 'Shift', href: `${LA}/shift` },
  { label: 'Holiday', href: `${LA}/holiday` },
]

function scrollTop() {
  window.scrollTo({ top: 0, behavior: 'smooth' })
}
</script>

<template>
  <footer class="footer-primary">
    <div class="footer-inner pt-4 pt-lg-5">
      <div class="container-lg">
        <div class="logo">
          <img src="/frontend-files/images/logo-lg.svg" class="img-fluid" alt="Login Autonom" />
        </div>

        <a href="#" class="btn-scroll scroll-up" @click.prevent="scrollTop">
          <svg viewBox="0 0 16 16"><use xlink:href="/frontend-files/images/icons.svg#caret-circle-up"></use></svg>
        </a>

        <div class="row g-4">
          <div class="col-md-12 col-lg-6">
            <h4 class="sub-title">{{ t('footer.elearning') }}</h4>
            <ul class="nav nav-footer">
              <li class="nav-item">
                <RouterLink to="/e-learning" class="nav-link">{{ t('footer.courses') }}</RouterLink>
              </li>
            </ul>

            <h4 class="sub-title">{{ t('footer.solutions') }}</h4>
            <ul class="nav nav-footer nav-column">
              <li v-for="item in solutions" :key="item.label" class="nav-item">
                <a :href="item.href" target="_blank" rel="noopener" class="nav-link">{{ item.label }}</a>
              </li>
            </ul>
          </div>

          <div class="col-md-6 col-lg-auto">
            <h4 class="sub-title">{{ t('footer.security') }}</h4>
            <ul class="nav nav-footer">
              <li class="nav-item">
                <a href="https://beleptetorendszer.com/" target="_blank" rel="noopener" class="nav-link">{{ t('footer.thermalCameras') }}</a>
              </li>
              <li class="nav-item">
                <a href="https://beleptetorendszer.com/" target="_blank" rel="noopener" class="nav-link">{{ t('footer.terminals') }}</a>
              </li>
              <li class="nav-item">
                <a href="https://beleptetorendszer.com/" target="_blank" rel="noopener" class="nav-link">{{ t('footer.accessSystems') }}</a>
              </li>
            </ul>
          </div>

          <div class="col-md-6 col-lg-auto ms-lg-auto">
            <h4 class="sub-title">{{ t('footer.contact') }}</h4>
            <ul class="nav nav-footer contact">
              <li>
                <a href="mailto:contact@loginautonom.com">contact@loginautonom.com</a>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <div class="footer-bottom">
        <div class="copyright">
          &copy; 2026. {{ t('footer.rights') }} | <span>Login Autonom GmbH</span>
        </div>
        <div class="createdby">{{ t('footer.platform') }}</div>
      </div>
    </div>
  </footer>
</template>

<style scoped>
/*
 * The decorative footer wave (`.footer-primary::before`) is pulled up over
 * the preceding section with a large negative margin. That invisible
 * overhang otherwise swallows clicks on content sitting in the bottom
 * ~60–160px of the page — most visibly the quiz's submit button. The wave
 * is purely cosmetic, so the footer ignores pointer events and only its
 * real content (`.footer-inner`, which holds every link) takes them back.
 */
.footer-primary {
  pointer-events: none;
}

.footer-primary .footer-inner {
  pointer-events: auto;
}
</style>

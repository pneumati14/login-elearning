<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import LanguageSwitcher from './LanguageSwitcher.vue'

interface MenuItem {
  label: string
  icon: string
  type: 'panel' | 'link' | 'route'
  child?: string
  href?: string
  to?: string
}
interface Panel {
  parent?: string
  items: MenuItem[]
}

const auth = useAuthStore()
const router = useRouter()
const { t } = useI18n()

// The hamburger menu mirrors loginautonom.com, with E-Learning and the
// admin-only Admin submenu added. Profile / language / logout live in
// the separate profile dropdown, not here.
const panels = computed<Record<string, Panel>>(() => {
  const rootItems: MenuItem[] = [
    { label: t('nav.elearning'), icon: 'e-learning.svg', type: 'route', to: '/e-learning' },
    { label: t('nav.software'), icon: 'software.svg', type: 'panel', child: 'panel-6' },
    { label: t('nav.teamCareer'), icon: 'team-career.svg', type: 'route', to: '/teams-and-career' },
    { label: t('nav.research'), icon: 'download-research.svg', type: 'route', to: '/research' },
    { label: t('nav.connect'), icon: 'connect.svg', type: 'route', to: '/book-a-demo' },
    { label: t('nav.share'), icon: 'share.svg', type: 'link', href: 'https://www.linkedin.com/company/login-autonom/' },
    { label: t('nav.followUs'), icon: 'follow-us.svg', type: 'panel', child: 'panel-16' },
  ]

  const result: Record<string, Panel> = {
    'panel-1': { items: rootItems },
    'panel-6': {
      parent: 'panel-1',
      items: [
        { label: t('nav.bookDemo'), icon: 'book-a-demo.svg', type: 'route', to: '/book-a-demo' },
        { label: 'HR Base', icon: 'hr-base.svg', type: 'route', to: '/hrbase' },
        { label: 'Cplatform', icon: 'cplatform.svg', type: 'route', to: '/cplatform' },
        { label: 'Holiday', icon: 'holiday.svg', type: 'route', to: '/holiday' },
        { label: 'Workhour', icon: 'workhour.svg', type: 'route', to: '/workhour' },
        { label: 'Productivity', icon: 'productivity.svg', type: 'route', to: '/productivity' },
        { label: 'Competency', icon: 'competency.svg', type: 'route', to: '/competency' },
        { label: 'Shift', icon: 'shift.svg', type: 'route', to: '/shift' },
        { label: 'Workwear', icon: 'workwear.svg', type: 'route', to: '/workwear' },
        { label: 'Application', icon: 'application.svg', type: 'route', to: '/app' },
        { label: 'AI', icon: 'ai_module.svg', type: 'route', to: '/ai' },
      ],
    },
    'panel-16': {
      parent: 'panel-1',
      items: [
        { label: 'Linkedin', icon: 'linkedin.svg', type: 'link', href: 'https://www.linkedin.com/company/login-autonom/' },
        { label: 'Facebook', icon: 'facebook.svg', type: 'link', href: 'https://www.facebook.com/HRinformatika' },
      ],
    },
  }

  // The Admin submenu is only built — and only shown — for administrators.
  if (auth.isAdmin) {
    rootItems.push({ label: t('nav.admin'), icon: 'about-us.svg', type: 'panel', child: 'panel-admin' })
    result['panel-admin'] = {
      parent: 'panel-1',
      items: [
        { label: t('nav.adminCourses'), icon: 'competency.svg', type: 'route', to: '/admin/courses' },
        { label: t('nav.adminUsers'), icon: 'team-career.svg', type: 'route', to: '/admin/users' },
        { label: t('nav.adminPublications'), icon: 'publications.svg', type: 'route', to: '/admin/publications' },
        { label: t('nav.adminPositions'), icon: 'book-a-demo.svg', type: 'route', to: '/admin/positions' },
      ],
    }
  }

  return result
})

// ── Hamburger menu state ──────────────────────────────────────────
const menuOpen = ref(false)
const activePanel = ref('panel-1')
const root = ref<HTMLElement | null>(null)

// ── Profile dropdown state ────────────────────────────────────────
const profileOpen = ref(false)
const profileRoot = ref<HTMLElement | null>(null)

const initials = computed(() => {
  const user = auth.user
  if (!user) return ''
  return (user.firstName.charAt(0) + user.lastName.charAt(0)).toUpperCase()
})

const avatarUrl = computed(() => auth.user?.avatarUrl ?? null)

function toggleMenu() {
  menuOpen.value = !menuOpen.value
  if (menuOpen.value) {
    activePanel.value = 'panel-1'
    profileOpen.value = false
  }
}
function closeMenu() {
  menuOpen.value = false
}
function openPanel(id: string) {
  activePanel.value = id
}
function goBack(parent?: string) {
  if (parent) activePanel.value = parent
}
function toggleProfile() {
  profileOpen.value = !profileOpen.value
  if (profileOpen.value) menuOpen.value = false
}
function closeProfile() {
  profileOpen.value = false
}
async function logout() {
  closeProfile()
  await auth.logout()
  if (router.currentRoute.value.meta.requiresAuth) {
    router.push('/')
  }
}
function onDocPointerDown(e: MouseEvent) {
  const target = e.target as Node
  if (menuOpen.value && root.value && !root.value.contains(target)) {
    closeMenu()
  }
  if (profileOpen.value && profileRoot.value && !profileRoot.value.contains(target)) {
    closeProfile()
  }
}

onMounted(() => document.addEventListener('mousedown', onDocPointerDown))
onBeforeUnmount(() => document.removeEventListener('mousedown', onDocPointerDown))

const iconUrl = (name: string) => `/frontend-files/images/menu-images/${name}`
</script>

<template>
  <header class="header-primary sticky-top">
    <div class="container-fluid">
      <nav class="navbar justify-content-between">
        <RouterLink class="navbar-brand" to="/" title="Login Autonom">
          <img src="/frontend-files/images/logo.svg" class="img-fluid" alt="Login Autonom" />
        </RouterLink>

        <div class="header-actions">
          <LanguageSwitcher />

          <!-- ── Profile (top-right) ──────────────────────────────── -->
          <div v-if="auth.isAuthenticated" ref="profileRoot" class="profile">
            <button
              type="button"
              class="profile-toggle"
              :class="{ open: profileOpen }"
              :aria-expanded="profileOpen"
              title="Profil"
              @click="toggleProfile"
            >
              <span class="avatar">
                <img v-if="avatarUrl" :src="avatarUrl" alt="" />
                <span v-else class="avatar-initials">{{ initials }}</span>
              </span>
            </button>

            <div v-if="profileOpen" class="profile-menu">
              <div class="profile-head">
                <span class="avatar avatar--lg">
                  <img v-if="avatarUrl" :src="avatarUrl" alt="" />
                  <span v-else class="avatar-initials">{{ initials }}</span>
                </span>
                <div class="profile-id">
                  <span class="profile-name">{{ auth.user?.fullName }}</span>
                  <span class="profile-email">{{ auth.user?.email }}</span>
                </div>
              </div>

              <div class="profile-divider"></div>
              <RouterLink to="/account" class="profile-link" @click="closeProfile">
                {{ t('profile.myProfile') }}
              </RouterLink>
              <RouterLink to="/certificates" class="profile-link" @click="closeProfile">
                {{ t('profile.myCertificates') }}
              </RouterLink>

              <div class="profile-divider"></div>
              <button type="button" class="profile-logout" @click="logout">
                {{ t('profile.logout') }}
              </button>
            </div>
          </div>

          <RouterLink v-else to="/login" class="login-btn">{{ t('profile.login') }}</RouterLink>

          <!-- ── Hamburger menu ───────────────────────────────────── -->
          <div ref="root" class="top-menu">
            <div class="dropdown">
              <a
                href="javascript:void(0);"
                class="menu-toggler dropdown-toggle nav-link"
                :class="{ show: menuOpen }"
                :aria-expanded="menuOpen"
                @click="toggleMenu"
              >
                <svg viewBox="0 0 16 16"><use xlink:href="/frontend-files/images/icons.svg#menu"></use></svg>
              </a>

              <div class="dropdown-menu dropdown-menu-end" :class="{ show: menuOpen }">
                <div
                  v-for="(panel, id) in panels"
                  :id="id"
                  :key="id"
                  class="panel"
                  :class="{ active: activePanel === id }"
                >
                  <div class="panel-body">
                    <template v-for="(item, i) in panel.items" :key="i">
                      <span
                        v-if="item.type === 'panel'"
                        class="dropdown-item"
                        @click="openPanel(item.child!)"
                      >
                        <span class="icon"><img :src="iconUrl(item.icon)" class="img-fluid" alt="" /></span>
                        {{ item.label }}
                        <svg class="icon-right" viewBox="0 0 16 16">
                          <use xlink:href="/frontend-files/images/icons.svg#caret-circle-right"></use>
                        </svg>
                      </span>

                      <RouterLink
                        v-else-if="item.type === 'route'"
                        class="dropdown-item"
                        :to="item.to!"
                        @click="closeMenu"
                      >
                        <span class="icon"><img :src="iconUrl(item.icon)" class="img-fluid" alt="" /></span>
                        {{ item.label }}
                      </RouterLink>

                      <a
                        v-else
                        class="dropdown-item"
                        :href="item.href"
                        target="_blank"
                        rel="noopener"
                        @click="closeMenu"
                      >
                        <span class="icon"><img :src="iconUrl(item.icon)" class="img-fluid" alt="" /></span>
                        {{ item.label }}
                      </a>
                    </template>
                  </div>

                  <div v-if="panel.parent" class="panel-footer">
                    <button class="dropdown-item btn-back" type="button" @click="goBack(panel.parent)">
                      <span class="icon">
                        <svg viewBox="0 0 16 16"><use xlink:href="/frontend-files/images/icons.svg#arrow-left"></use></svg>
                      </span>
                      {{ t('nav.back') }}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </nav>
    </div>
  </header>
</template>

<style scoped>
.header-actions {
  display: flex;
  align-items: center;
  gap: 0.7rem;
}

/* The dropdown panel is positioned without Popper, so pin it under the toggler. */
.top-menu .dropdown {
  position: relative;
}
.top-menu .dropdown-menu {
  top: 100%;
  right: 0;
  left: auto;
  margin-top: 0;
}
.top-menu .dropdown-item {
  padding-right: 1rem;
}
.top-menu .dropdown-item .icon-right {
  margin-left: auto;
}
.header-primary .navbar-brand img {
  height: 38px;
  width: auto;
}

/* ── Profile dropdown ──────────────────────────────────────────── */
.profile {
  position: relative;
}

.profile-toggle {
  display: block;
  padding: 0;
  background: none;
  border: none;
  cursor: pointer;
}

.avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  border-radius: 50%;
  overflow: hidden;
  background: var(--login-secondary, #0c1c40);
}

.avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.avatar-initials {
  color: #fff;
  font-size: 0.85rem;
  font-weight: 700;
}

.avatar--lg {
  width: 52px;
  height: 52px;
  flex-shrink: 0;
}

.avatar--lg .avatar-initials {
  font-size: 1.15rem;
}

.profile-toggle:hover .avatar,
.profile-toggle.open .avatar {
  box-shadow: 0 0 0 3px rgba(237, 32, 68, 0.25);
}

.profile-menu {
  position: absolute;
  top: calc(100% + 0.55rem);
  right: 0;
  width: 256px;
  padding: 0.6rem;
  background: #fff;
  border-radius: 0.85rem;
  box-shadow: 0 18px 44px rgba(12, 28, 64, 0.2);
  z-index: 1100;
}

.profile-head {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.5rem;
}

.profile-id {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.profile-name {
  color: var(--login-secondary, #0c1c40);
  font-size: 0.97rem;
  font-weight: 700;
}

.profile-email {
  overflow: hidden;
  color: #8b94a6;
  font-size: 0.8rem;
  text-overflow: ellipsis;
}

.profile-divider {
  height: 1px;
  margin: 0.4rem 0;
  background: #eef1f6;
}

.profile-link {
  display: block;
  padding: 0.55rem 0.6rem;
  border-radius: 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.92rem;
  font-weight: 700;
}

.profile-link:hover {
  background: #f6f7fb;
}

.profile-logout {
  width: 100%;
  padding: 0.55rem 0.6rem;
  background: none;
  border: none;
  border-radius: 0.5rem;
  color: var(--login-primary, #ed2044);
  font-size: 0.92rem;
  font-weight: 700;
  text-align: left;
  cursor: pointer;
}

.profile-logout:hover {
  background: #fdeef1;
}

.login-btn {
  padding: 0.5rem 1.1rem;
  background: var(--login-primary, #ed2044);
  border-radius: 0.5rem;
  color: #fff;
  font-size: 0.92rem;
  font-weight: 700;
}
</style>

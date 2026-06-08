<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import LanguageSwitcher from './LanguageSwitcher.vue'

interface MenuItem {
  label: string
  icon: string
  type: 'submenu' | 'link' | 'route'
  /** Stable id for tracking which submenu is expanded. */
  key?: string
  children?: MenuItem[]
  href?: string
  to?: string
}

const auth = useAuthStore()
const router = useRouter()
const { t } = useI18n()

// The hamburger menu mirrors loginautonom.com, with E-Learning and the
// admin-only Admin submenu added. Profile / language / logout live in
// the separate profile dropdown, not here. Submenus expand IN PLACE,
// below their parent item (accordion) — the users found the original
// site's panel-swap (where the sublist restarts at the top of the
// dropdown) disorienting.
const menuItems = computed<MenuItem[]>(() => {
  const items: MenuItem[] = [
    { label: t('nav.elearning'), icon: 'e-learning.svg', type: 'route', to: '/e-learning' },
    {
      label: t('nav.software'),
      icon: 'software.svg',
      type: 'submenu',
      key: 'software',
      children: [
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
    { label: t('nav.teamCareer'), icon: 'team-career.svg', type: 'route', to: '/teams-and-career' },
    { label: t('nav.research'), icon: 'download-research.svg', type: 'route', to: '/research' },
    { label: t('nav.connect'), icon: 'connect.svg', type: 'route', to: '/book-a-demo' },
    { label: t('nav.share'), icon: 'share.svg', type: 'link', href: 'https://www.linkedin.com/company/login-autonom/' },
    {
      label: t('nav.followUs'),
      icon: 'follow-us.svg',
      type: 'submenu',
      key: 'follow',
      children: [
        { label: 'Linkedin', icon: 'linkedin.svg', type: 'link', href: 'https://www.linkedin.com/company/login-autonom/' },
        { label: 'Facebook', icon: 'facebook.svg', type: 'link', href: 'https://www.facebook.com/HRinformatika' },
      ],
    },
  ]

  // The CRM submenu is shown to anyone with CRM access (salesperson,
  // sales manager or admin). Customers and tasks are open to all of them;
  // the catalogue config (opportunity types, products) is admins only.
  // Salespeople and sales managers see no other admin menu.
  if (auth.hasCrmAccess) {
    const crmItems: MenuItem[] = [
      { label: t('nav.adminCustomers'), icon: 'team-career.svg', type: 'route', to: '/admin/customers' },
      { label: t('nav.adminTasks'), icon: 'book-a-demo.svg', type: 'route', to: '/admin/tasks' },
      { label: t('nav.adminReports'), icon: 'download-research.svg', type: 'route', to: '/admin/reports' },
      { label: t('nav.adminTimeline'), icon: 'workhour.svg', type: 'route', to: '/admin/timeline' },
      { label: t('nav.adminFulfillment'), icon: 'application.svg', type: 'route', to: '/admin/fulfillment' },
      { label: t('nav.adminBilling'), icon: 'productivity.svg', type: 'route', to: '/admin/billing' },
    ]
    if (auth.canManageCatalog) {
      crmItems.push(
        { label: t('nav.adminOpportunityTypes'), icon: 'competency.svg', type: 'route', to: '/admin/opportunity-types' },
        { label: t('nav.adminProducts'), icon: 'publications.svg', type: 'route', to: '/admin/products' },
        { label: t('nav.adminProductCategories'), icon: 'competency.svg', type: 'route', to: '/admin/product-categories' },
        { label: t('nav.adminSuppliers'), icon: 'connect.svg', type: 'route', to: '/admin/suppliers' },
        { label: t('nav.adminFeeTitles'), icon: 'productivity.svg', type: 'route', to: '/admin/fee-titles' },
        { label: t('nav.adminIntegrations'), icon: 'cplatform.svg', type: 'route', to: '/admin/integrations' },
        { label: t('nav.adminCurrencies'), icon: 'workhour.svg', type: 'route', to: '/admin/currencies' },
        { label: t('nav.adminFulfillmentTypes'), icon: 'icon-app.svg', type: 'route', to: '/admin/fulfillment-types' },
      )
    }
    items.push({ label: t('nav.crm'), icon: 'team-career.svg', type: 'submenu', key: 'crm', children: crmItems })
  }

  // The Admin submenu (non-CRM administration) is only built — and only
  // shown — for administrators.
  if (auth.isAdmin) {
    items.push({
      label: t('nav.admin'),
      icon: 'about-us.svg',
      type: 'submenu',
      key: 'admin',
      children: [
        { label: t('nav.adminCourses'), icon: 'competency.svg', type: 'route', to: '/admin/courses' },
        { label: t('nav.adminUsers'), icon: 'team-career.svg', type: 'route', to: '/admin/users' },
        { label: t('nav.adminPublications'), icon: 'publications.svg', type: 'route', to: '/admin/publications' },
        { label: t('nav.adminPositions'), icon: 'book-a-demo.svg', type: 'route', to: '/admin/positions' },
      ],
    })
  }

  return items
})

// ── Hamburger menu state ──────────────────────────────────────────
const menuOpen = ref(false)
// Key of the submenu currently expanded in place (accordion: one at a time).
const expandedKey = ref<string | null>(null)
const root = ref<HTMLElement | null>(null)

// ── High Sensitivity Solutions dropdown ───────────────────────────
// A header element kept separate from the hamburger menu — these are
// not part of the standard service offering.
const hssOpen = ref(false)
const hssRoot = ref<HTMLElement | null>(null)
const hssItems = [
  { label: 'nav.hssHealth', to: '/high-sensitivity/health', icon: 'health' },
  { label: 'nav.hssDefense', to: '/high-sensitivity/defense', icon: 'defense' },
  { label: 'nav.hssAviation', to: '/high-sensitivity/aviation', icon: 'aviation' },
] as const

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
    expandedKey.value = null
    profileOpen.value = false
    hssOpen.value = false
  }
}
function closeMenu() {
  menuOpen.value = false
}
function toggleSubmenu(key: string) {
  expandedKey.value = expandedKey.value === key ? null : key
}
function toggleProfile() {
  profileOpen.value = !profileOpen.value
  if (profileOpen.value) {
    menuOpen.value = false
    hssOpen.value = false
  }
}
function closeProfile() {
  profileOpen.value = false
}
function toggleHss() {
  hssOpen.value = !hssOpen.value
  if (hssOpen.value) {
    menuOpen.value = false
    profileOpen.value = false
  }
}
function closeHss() {
  hssOpen.value = false
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
  if (hssOpen.value && hssRoot.value && !hssRoot.value.contains(target)) {
    closeHss()
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
        <div class="header-left">
          <!-- ── Hamburger menu (top-left) ─────────────────────────── -->
          <div ref="root" class="top-menu">
            <div class="dropdown">
              <a
                href="#"
                class="menu-toggler dropdown-toggle nav-link"
                :class="{ show: menuOpen }"
                :aria-expanded="menuOpen"
                @click.prevent="toggleMenu"
              >
                <svg viewBox="0 0 16 16"><use xlink:href="/frontend-files/images/icons.svg#menu"></use></svg>
              </a>

              <div class="dropdown-menu" :class="{ show: menuOpen }">
                <template v-for="(item, i) in menuItems" :key="item.key ?? i">
                  <!-- Submenu parent: expands its children in place, below itself. -->
                  <template v-if="item.type === 'submenu'">
                    <span
                      class="dropdown-item has-sub"
                      :class="{ expanded: expandedKey === item.key }"
                      role="button"
                      :aria-expanded="expandedKey === item.key"
                      @click="toggleSubmenu(item.key!)"
                    >
                      <span class="icon"><img :src="iconUrl(item.icon)" class="img-fluid" alt="" /></span>
                      {{ item.label }}
                      <svg class="icon-right" viewBox="0 0 16 16">
                        <use xlink:href="/frontend-files/images/icons.svg#caret-circle-right"></use>
                      </svg>
                    </span>

                    <div v-if="expandedKey === item.key" class="submenu">
                      <template v-for="(child, j) in item.children" :key="j">
                        <RouterLink
                          v-if="child.type === 'route'"
                          class="dropdown-item"
                          :to="child.to!"
                          @click="closeMenu"
                        >
                          <span class="icon"><img :src="iconUrl(child.icon)" class="img-fluid" alt="" /></span>
                          {{ child.label }}
                        </RouterLink>

                        <a
                          v-else
                          class="dropdown-item"
                          :href="child.href"
                          target="_blank"
                          rel="noopener"
                          @click="closeMenu"
                        >
                          <span class="icon"><img :src="iconUrl(child.icon)" class="img-fluid" alt="" /></span>
                          {{ child.label }}
                        </a>
                      </template>
                    </div>
                  </template>

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
            </div>
          </div>

          <RouterLink class="navbar-brand" to="/" title="Login Autonom">
            <img src="/frontend-files/images/logo.svg" class="img-fluid" alt="Login Autonom" />
          </RouterLink>

          <!-- ── High Sensitivity Solutions ───────────────────────────
               Kept separate from the hamburger menu — not part of the
               standard service offering. -->
          <div ref="hssRoot" class="hss">
            <button
              type="button"
              class="hss-toggle"
              :class="{ open: hssOpen }"
              :aria-expanded="hssOpen"
              @click="toggleHss"
            >
              <svg class="hss-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 1.5 3.5 4.8v6.4c0 5.3 3.6 9.9 8.5 11.3 4.9-1.4 8.5-6 8.5-11.3V4.8L12 1.5z" />
              </svg>
              <span class="hss-label hss-label--full">{{ t('nav.hss') }}</span>
              <span class="hss-label hss-label--short">{{ t('nav.hssShort') }}</span>
              <span class="hss-caret" aria-hidden="true"></span>
            </button>

            <div v-if="hssOpen" class="hss-menu">
              <RouterLink
                v-for="item in hssItems"
                :key="item.to"
                :to="item.to"
                class="hss-link"
                @click="closeHss"
              >
                <span class="hss-link-icon" aria-hidden="true">
                  <svg v-if="item.icon === 'health'" viewBox="0 0 24 24">
                    <path
                      d="M12 20.7C5.1 15.3 2 11.6 2 7.7 2 4.8 4.2 2.6 7 2.6 8.8 2.6 10.5 3.6 12 5.6 13.5 3.6 15.2 2.6 17 2.6 19.8 2.6 22 4.8 22 7.7 22 11.6 18.9 15.3 12 20.7Z"
                    />
                  </svg>
                  <svg v-else-if="item.icon === 'defense'" viewBox="0 0 24 24">
                    <path
                      d="M12 2 19.5 4.8V11C19.5 16 16.3 19.7 12 21.2 7.7 19.7 4.5 16 4.5 11V4.8Z"
                    />
                  </svg>
                  <svg v-else viewBox="0 0 24 24">
                    <path d="M2 21L23 12L2 3V10L17 12L2 14Z" />
                  </svg>
                </span>
                {{ t(item.label) }}
              </RouterLink>
            </div>
          </div>
        </div>

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
        </div>
      </nav>
    </div>
  </header>
</template>

<style scoped>
.header-left,
.header-actions {
  display: flex;
  align-items: center;
  gap: 0.7rem;
}

/* The dropdown panel is positioned without Popper, so pin it under the toggler.
   The hamburger lives on the left, so the panel opens from the left edge.
   `bottom: auto` guards against any global rule flipping the menu upward —
   every menu in the header must drop DOWN, never up. */
.top-menu .dropdown {
  position: relative;
}
.top-menu .dropdown-menu {
  top: 100%;
  bottom: auto;
  left: 0;
  right: auto;
  margin-top: 0;
  /* The accordion can grow taller than the viewport (e.g. Software with
     its 11 children) — cap it and scroll inside, like the old .panel did. */
  max-height: clamp(100px, calc(100dvh - var(--header-height, 63px) - 4rem), 800px);
  overflow-y: auto;
}
.top-menu .dropdown-menu.show {
  animation: menu-drop-down 0.16s ease-out;
  transform-origin: top center;
}

/* The marketing CSS only adds spacing between ADJACENT .dropdown-item
   siblings; the inline .submenu wrapper breaks that chain, so restore
   the 5px rhythm around it ourselves. */
.top-menu .submenu,
.top-menu .submenu + .dropdown-item {
  margin-top: 5px;
}

/* ── In-place submenu (accordion) ─────────────────────────────────
   Children open BELOW their parent item, indented and marked with a
   guide line, instead of swapping the whole panel. */
.top-menu .submenu {
  margin-left: 1.6rem;
  padding-left: 0.8rem;
  border-left: 2px solid rgba(12, 28, 64, 0.15);
  animation: submenu-open 0.18s ease-out;
  transform-origin: top center;
}

@keyframes submenu-open {
  from {
    opacity: 0;
    transform: translateY(-6px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* The caret-circle arrow turns to point down while its submenu is open. */
.top-menu .dropdown-item.has-sub .icon-right {
  transition: transform 0.18s ease;
}
.top-menu .dropdown-item.has-sub.expanded .icon-right {
  transform: rotate(90deg);
}

/* Shared "drop down from the toggler" entrance used by all header menus. */
@keyframes menu-drop-down {
  from {
    opacity: 0;
    transform: translateY(-8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
.top-menu .dropdown-item {
  padding-right: 1rem;
}
.top-menu .dropdown-item .icon-right {
  margin-left: auto;
}

/* Slightly enlarged hamburger icon. */
.top-menu .menu-toggler svg {
  width: 30px;
  height: 30px;
}

.header-primary .navbar-brand img {
  height: 38px;
  width: auto;
}

/* ── High Sensitivity Solutions ────────────────────────────────────
   A premium, brand-coloured pill that stands apart from the standard
   nav — a gradient in the brand red with a soft glow and a shield mark. */
.hss {
  position: relative;
}

.hss-toggle {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1.1rem;
  background: linear-gradient(135deg, var(--login-primary, #ed2044) 0%, #b3122f 100%);
  border: 1px solid rgba(255, 255, 255, 0.28);
  border-radius: 100vw;
  color: #fff;
  font-size: 0.9rem;
  font-weight: 700;
  letter-spacing: 0.015em;
  white-space: nowrap;
  cursor: pointer;
  box-shadow: 0 6px 18px rgba(237, 32, 68, 0.4);
  transition:
    transform 0.15s ease,
    box-shadow 0.15s ease,
    filter 0.15s ease;
}

.hss-toggle:hover,
.hss-toggle.open {
  transform: translateY(-1px);
  box-shadow: 0 9px 26px rgba(237, 32, 68, 0.55);
  filter: brightness(1.07);
}

.hss-icon {
  width: 16px;
  height: 16px;
  flex-shrink: 0;
  fill: currentColor;
}

.hss-label--short {
  display: none;
}

.hss-caret {
  width: 0;
  height: 0;
  border-right: 4px solid transparent;
  border-left: 4px solid transparent;
  border-top: 5px solid currentColor;
  transition: transform 0.15s ease;
}

.hss-toggle.open .hss-caret {
  transform: rotate(180deg);
}

.hss-menu {
  position: absolute;
  top: calc(100% + 0.6rem);
  bottom: auto;
  left: 0;
  animation: menu-drop-down 0.16s ease-out;
  transform-origin: top center;
  min-width: 210px;
  padding: 0.5rem;
  background: #fff;
  border-top: 3px solid var(--login-primary, #ed2044);
  border-radius: 0.75rem;
  box-shadow: 0 18px 44px rgba(12, 28, 64, 0.2);
  z-index: 1100;
}

.hss-link {
  display: flex;
  align-items: center;
  padding: 0.55rem 0.7rem;
  border-radius: 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.92rem;
  font-weight: 700;
}

.hss-link-icon {
  display: inline-flex;
  width: 18px;
  height: 18px;
  margin-right: 0.6rem;
  flex-shrink: 0;
}

.hss-link-icon svg {
  width: 100%;
  height: 100%;
  fill: currentColor;
}

.hss-link:hover {
  background: #fdeef1;
  color: var(--login-primary, #ed2044);
}

/* On narrow screens the full label would crowd the header — show "HSS". */
@media (max-width: 767.98px) {
  .hss-label--full {
    display: none;
  }
  .hss-label--short {
    display: inline;
  }
  .hss-toggle {
    padding: 0.4rem 0.7rem;
    font-size: 0.82rem;
  }
  .top-menu .menu-toggler svg {
    width: 26px;
    height: 26px;
  }
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
  bottom: auto;
  right: 0;
  animation: menu-drop-down 0.16s ease-out;
  transform-origin: top center;
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

/* Deliberately understated — a quiet text link, not a loud filled button. */
.login-btn {
  padding: 0.4rem 0.3rem;
  color: #fff;
  font-size: 0.92rem;
  font-weight: 700;
}

.login-btn:hover {
  text-decoration: underline;
}
</style>

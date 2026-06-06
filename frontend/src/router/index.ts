import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'
import { products } from '../data/products'
import { highSensitivityPages } from '../data/highSensitivity'
import { useAuthStore } from '../stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'home',
      component: HomeView,
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('../views/LoginView.vue'),
    },
    {
      path: '/e-learning',
      name: 'e-learning',
      component: () => import('../views/CoursesView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/e-learning/:slug',
      name: 'course',
      component: () => import('../views/CourseView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/quizzes/:id',
      name: 'quiz',
      component: () => import('../views/QuizView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/certificates',
      name: 'certificates',
      component: () => import('../views/CertificatesView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/certificates/:id',
      name: 'certificate',
      component: () => import('../views/CertificateView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/admin/users',
      name: 'admin-users',
      component: () => import('../views/AdminUsersView.vue'),
      meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
      path: '/admin/courses',
      name: 'admin-courses',
      component: () => import('../views/AdminCoursesView.vue'),
      meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
      path: '/admin/courses/:slug',
      name: 'admin-course-edit',
      component: () => import('../views/AdminCourseEditView.vue'),
      meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
      path: '/admin/quizzes/:id',
      name: 'admin-quiz-edit',
      component: () => import('../views/AdminQuizEditView.vue'),
      meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
      path: '/admin/publications',
      name: 'admin-publications',
      component: () => import('../views/AdminPublicationsView.vue'),
      meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
      path: '/admin/positions',
      name: 'admin-positions',
      component: () => import('../views/AdminPositionsView.vue'),
      meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
      path: '/admin/customers',
      name: 'admin-customers',
      component: () => import('../views/AdminCustomersView.vue'),
      meta: { requiresAuth: true, requiresCrm: true },
    },
    {
      path: '/admin/customers/:id',
      name: 'admin-customer-detail',
      component: () => import('../views/AdminCustomerDetailView.vue'),
      meta: { requiresAuth: true, requiresCrm: true },
    },
    {
      path: '/admin/tasks',
      name: 'admin-tasks',
      component: () => import('../views/AdminTasksView.vue'),
      meta: { requiresAuth: true, requiresCrm: true },
    },
    {
      path: '/admin/reports',
      name: 'admin-reports',
      component: () => import('../views/AdminReportsView.vue'),
      meta: { requiresAuth: true, requiresCrm: true },
    },
    {
      path: '/admin/fulfillment',
      name: 'admin-fulfillment',
      component: () => import('../views/AdminFulfillmentView.vue'),
      meta: { requiresAuth: true, requiresCrm: true },
    },
    {
      path: '/admin/billing',
      name: 'admin-billing',
      component: () => import('../views/AdminBillingView.vue'),
      meta: { requiresAuth: true, requiresCrm: true },
    },
    {
      path: '/admin/fulfillment-types',
      name: 'admin-fulfillment-types',
      component: () => import('../views/AdminFulfillmentTypesView.vue'),
      meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
      path: '/admin/products',
      name: 'admin-products',
      component: () => import('../views/AdminProductsView.vue'),
      meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
      path: '/admin/suppliers',
      name: 'admin-suppliers',
      component: () => import('../views/AdminSuppliersView.vue'),
      meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
      path: '/admin/fee-titles',
      name: 'admin-fee-titles',
      component: () => import('../views/AdminFeeTitlesView.vue'),
      meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
      path: '/admin/integrations',
      name: 'admin-integrations',
      component: () => import('../views/AdminIntegrationsView.vue'),
      meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
      path: '/admin/currencies',
      name: 'admin-currencies',
      component: () => import('../views/AdminCurrenciesView.vue'),
      meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
      path: '/admin/opportunity-types',
      name: 'admin-opportunity-types',
      component: () => import('../views/AdminOpportunityTypesView.vue'),
      meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
      path: '/admin/opportunity-types/:id',
      name: 'admin-opportunity-type-detail',
      component: () => import('../views/AdminOpportunityTypeDetailView.vue'),
      meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
      path: '/account',
      name: 'account',
      component: () => import('../views/AccountView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/book-a-demo',
      name: 'book-a-demo',
      component: () => import('../views/BookDemoView.vue'),
    },
    {
      path: '/teams-and-career',
      name: 'teams-and-career',
      component: () => import('../views/CareerView.vue'),
    },
    {
      path: '/research',
      name: 'research',
      component: () => import('../views/ResearchView.vue'),
    },
    // High Sensitivity Solutions — separate from the standard service.
    // One route per slug, generated from the high-sensitivity data.
    ...highSensitivityPages.map((p) => ({
      path: `/high-sensitivity/${p.slug}`,
      name: p.routeName,
      component: () => import('../views/HighSensitivityView.vue'),
      props: { slug: p.slug },
    })),
    // One route per product page, generated from the product data.
    ...products.map((p) => ({
      path: `/${p.slug}`,
      name: p.slug,
      component: () => import('../views/ProductView.vue'),
      props: { slug: p.slug },
    })),
  ],
  scrollBehavior() {
    return { top: 0 }
  },
})

// Gate the e-learning area behind authentication. The marketing pages
// (home, products, book-a-demo) stay public.
router.beforeEach(async (to) => {
  const auth = useAuthStore()

  // On the first navigation, find out whether a session already exists.
  if (!auth.ready) {
    await auth.fetchMe()
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.requiresAdmin && !auth.isAdmin) {
    return { name: 'e-learning' }
  }

  // CRM area: admins, sales managers and salespeople. The admin-only CRM
  // screens (products, opportunity types) keep requiresAdmin above.
  if (to.meta.requiresCrm && !auth.hasCrmAccess) {
    return { name: 'e-learning' }
  }

  // A logged-in user has no reason to see the login page.
  if (to.name === 'login' && auth.isAuthenticated) {
    return { name: 'e-learning' }
  }
})

export default router

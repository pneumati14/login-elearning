export interface Product {
  slug: string
  promoImage: string
  moduleLogo: string
  icon: string
  bodyImage: string
  tailImage: string | null
}

/**
 * Structural data for the product pages (slug + images), one entry per slug.
 * Drives the generic ProductView.vue, one route per slug.
 *
 * Translatable text (heroName, heroIntro, leadText, featureText,
 * bodyParagraphs) lives in the `productContent.<slug>` locale namespace —
 * see src/locales/hu.ts and src/locales/en.ts.
 */
export const products: Product[] = [
  {
    slug: 'hrbase',
    promoImage: '/frontend-files/images/hr-base-promo.png',
    moduleLogo: '/frontend-files/images/solutions/logo-text-hrbase.svg',
    icon: '/frontend-files/images/solutions/icon-hrbase.svg',
    bodyImage: '/frontend-files/images/group-3499.png',
    tailImage: '/frontend-files/images/login_loading 1.png',
  },
  {
    slug: 'cplatform',
    promoImage: '/frontend-files/images/cplatform-promo.png',
    moduleLogo: '/frontend-files/images/solutions/logo-text-cplatform.svg',
    icon: '/frontend-files/images/solutions/icon-cplatform.svg',
    bodyImage: '/frontend-files/images/software_cplatform_.png',
    tailImage: '/frontend-files/images/sofware_cplatrorm_legalso.png',
  },
  {
    slug: 'holiday',
    promoImage: '/frontend-files/images/holiday-promo.png',
    moduleLogo: '/frontend-files/images/solutions/logo-text-holiday.svg',
    icon: '/frontend-files/images/solutions/icon-holiday.svg',
    bodyImage: '/frontend-files/images/group-3500.png',
    tailImage: '/frontend-files/images/holiday-also.png',
  },
  {
    slug: 'workhour',
    promoImage: '/frontend-files/images/579x526_software_workhour_legfelso.png',
    moduleLogo: '/frontend-files/images/solutions/logo-text-workhour.svg',
    icon: '/frontend-files/images/solutions/icon-workhour.svg',
    bodyImage: '/frontend-files/images/workhour1-1.png',
    tailImage: '/frontend-files/images/group-3498.png',
  },
  {
    slug: 'productivity',
    promoImage: '/frontend-files/images/group-3503.png',
    moduleLogo: '/frontend-files/images/solutions/logo-text-productivity.svg',
    icon: '/frontend-files/images/solutions/icon-productivity.svg',
    bodyImage: '/frontend-files/images/image-39.png',
    tailImage: '/frontend-files/images/pr-loading.png',
  },
  {
    slug: 'competency',
    promoImage: '/frontend-files/images/competency1-1.png',
    moduleLogo: '/frontend-files/images/solutions/logo-text-competency.svg',
    icon: '/frontend-files/images/solutions/icon-competency.svg',
    bodyImage: '/frontend-files/images/competency-3-1.png',
    tailImage: '/frontend-files/images/competency-2-1.png',
  },
  {
    slug: 'shift',
    promoImage: '/frontend-files/images/shift-1-1.png',
    moduleLogo: '/frontend-files/images/solutions/logo-text-shift.svg',
    icon: '/frontend-files/images/solutions/icon-shift.svg',
    bodyImage: '/frontend-files/images/shift-load.png',
    tailImage: null,
  },
  {
    slug: 'workwear',
    promoImage: '/frontend-files/images/image-40.png',
    moduleLogo: '/frontend-files/images/solutions/logo-text-workwear.svg',
    icon: '/frontend-files/images/solutions/icon-workwear.svg',
    bodyImage: '/frontend-files/images/loading-bg.png',
    tailImage: null,
  },
  {
    slug: 'app',
    promoImage: '/frontend-files/images/software_aoo_legfelso.png',
    moduleLogo: '/frontend-files/images/solutions/logo-text-app.svg',
    icon: '/frontend-files/images/solutions/icon-app.svg',
    bodyImage: '/frontend-files/images/loading-bg.png',
    tailImage: '/frontend-files/images/software_app_legalso.png',
  },
  {
    slug: 'ai',
    promoImage: '/frontend-files/images/nothing-promo.png',
    moduleLogo: '/frontend-files/images/solutions/logo-text-ai.svg',
    icon: '/frontend-files/images/solutions/icon-ai.svg',
    bodyImage: '/frontend-files/images/loading-bg.png',
    tailImage: null,
  },
]

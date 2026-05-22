export interface HssContact {
  name: string
  email: string
  phone: string
  /** Headshot path under public/. Falls back to initials when absent. */
  photo?: string
}

export interface HssPage {
  slug: string
  routeName: string
  /** i18n key for the page title (the area name). */
  titleKey: string
  /** i18n key for the custom-order intro statement. */
  introKey: string
  /**
   * People to contact about this area. An empty list makes the page show a
   * "coming soon" placeholder instead of the intro + contact cards.
   */
  contacts: HssContact[]
}

// The specialists who handle high-sensitivity enquiries (see login.hu).
const feherAndras: HssContact = {
  name: 'Dr. András Fehér, PhD',
  email: 'feher.andras@login.hu',
  phone: '+36 20 925 5355',
  photo: '/frontend-files/images/contact-feher-andras.jpg',
}

const ottiCsaba: HssContact = {
  name: 'Dr. Csaba Otti, PhD',
  email: 'otti.csaba@login.hu',
  phone: '+36 70 314 6077',
  photo: '/frontend-files/images/contact-otti-csaba.jpg',
}

/**
 * Structural data for the High Sensitivity Solutions pages, one entry per
 * slug. Drives the generic HighSensitivityView.vue, one route per slug.
 *
 * Translatable text (the intro statement, the contact role) lives in the
 * `highSensitivity` locale namespace — see src/locales/hu.ts and en.ts.
 */
export const highSensitivityPages: HssPage[] = [
  {
    slug: 'health',
    routeName: 'hss-health',
    titleKey: 'nav.hssHealth',
    introKey: 'highSensitivity.introHealth',
    contacts: [feherAndras],
  },
  {
    slug: 'defense',
    routeName: 'hss-defense',
    titleKey: 'nav.hssDefense',
    introKey: 'highSensitivity.introDefense',
    contacts: [feherAndras, ottiCsaba],
  },
  {
    slug: 'aviation',
    routeName: 'hss-aviation',
    titleKey: 'nav.hssAviation',
    introKey: 'highSensitivity.introAviation',
    contacts: [feherAndras],
  },
]

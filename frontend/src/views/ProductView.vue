<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { products, type Product } from '@/data/products'
import PartnersMarquee from '@/components/PartnersMarquee.vue'

const props = defineProps<{ slug: string }>()
const { t, tm, rt } = useI18n()

// Routes are generated from `products`, so a match is guaranteed;
// the fallback only satisfies the type checker.
const product = computed<Product>(
  () => products.find((p) => p.slug === props.slug) ?? products[0]!,
)

// Per-slug translatable text lives in the `productContent` locale namespace.
const c = computed(() => `productContent.${product.value.slug}`)
const bodyParagraphs = computed(() => tm(`${c.value}.bodyParagraphs`) as string[])
</script>

<template>
  <div class="product-page" :key="product.slug">
    <!-- Hero -->
    <section class="promobox">
      <div class="container-lg">
        <div class="row g-4 align-items-center justify-content-center">
          <div class="col-sm-12 col-md-7 col-lg-6 order-sm-2 order-md-1">
            <h2 class="sub-title">{{ t(`${c}.heroName`) }}</h2>
            <p>{{ t(`${c}.heroIntro`) }}</p>
          </div>
          <div class="col-8 col-sm-6 col-md-5 col-lg-6 order-sm-1 order-md-2">
            <img :src="product.promoImage" class="img-fluid d-block mx-auto" alt="" />
          </div>
        </div>
      </div>
    </section>

    <!-- Navy lead -->
    <section class="main-lead">
      <div class="main-lead-inner">
        <div class="module-logo">
          <img :src="product.moduleLogo" class="img-fluid" alt="" />
        </div>
        <div class="container container-min-1">
          <div class="py-4 py-lg-5">{{ t(`${c}.leadText`) }}</div>
        </div>
      </div>
    </section>

    <!-- Body -->
    <section class="page-container">
      <div class="container container-min-1">
        <div class="pb-4 pb-lg-5">
          <img :src="product.bodyImage" class="img-fluid d-block mx-auto" alt="" />
        </div>

        <div class="py-4 py-lg-5">
          <div class="row justify-content-center g-4 align-items-center">
            <div class="col-6 col-sm-3 col-md-4 col-lg-4">
              <img :src="product.icon" class="img-fluid d-block mx-auto mx-sm-0" alt="" />
            </div>
            <div class="col-sm-9 col-md-8 col-lg-8">{{ t(`${c}.featureText`) }}</div>
          </div>
        </div>

        <div v-for="(para, i) in bodyParagraphs" :key="i" class="pb-4 pb-lg-5">
          <p>{{ rt(para) }}</p>
        </div>

        <div class="py-4 py-lg-5 text-center">
          <RouterLink to="/book-a-demo" class="btn btn-primary">{{ t('productPage.getDemo') }}</RouterLink>
        </div>
      </div>

      <PartnersMarquee on-light />

      <div v-if="product.tailImage" class="container container-min-1">
        <div class="py-4 py-lg-5">
          <img :src="product.tailImage" class="img-fluid d-block mx-auto" alt="" />
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
      </div>
    </section>
  </div>
</template>

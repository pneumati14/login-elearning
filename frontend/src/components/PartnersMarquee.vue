<script setup lang="ts">
// `onLight` darkens the (white) logos so they stay visible on a light background.
defineProps<{ onLight?: boolean }>()

const partners = [
  'airbus', 'velux', 'bridgestone', 'bourns', 'carrier', 'corinthia', 'creaton',
  'danubius-hotel', 'dbschenker', 'eisberg', 'flex', 'kuehne-nagel', 'lg', 'obo',
  'praktiker', 'rosenberger', 'rossmann', 'schrack', 'zeiss',
]
</script>

<template>
  <div class="partners py-4 py-lg-5" :class="{ 'partners--on-light': onLight }">
    <div class="sub-title">trusted by</div>
    <div class="container-fluid">
      <div class="marquee">
        <div class="marquee-track">
          <img
            v-for="(p, i) in [...partners, ...partners]"
            :key="i"
            :src="`/frontend-files/images/${p}.png`"
            :alt="p"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
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

/* On a light background the white partner logos need darkening. */
.partners--on-light .marquee-track img {
  filter: brightness(0);
  opacity: 0.5;
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

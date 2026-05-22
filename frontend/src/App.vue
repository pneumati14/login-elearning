<script setup lang="ts">
import AppHeader from '@/components/AppHeader.vue'
import AppFooter from '@/components/AppFooter.vue'
</script>

<template>
  <AppHeader />
  <main class="page-main">
    <RouterView />
  </main>
  <AppFooter />
</template>

<style scoped>
.page-main {
  min-height: 62vh;
}
</style>

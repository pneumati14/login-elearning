<script setup lang="ts">
import { computed } from 'vue'
import { sanitizeHtml } from '@/utils/richText'

const props = defineProps<{ html: string | null | undefined }>()

const safe = computed(() => sanitizeHtml(props.html))
</script>

<template>
  <!-- eslint-disable-next-line vue/no-v-html -- sanitised via DOMPurify -->
  <div class="rich-text" v-html="safe" />
</template>

<style scoped>
.rich-text {
  word-break: break-word;
}

.rich-text :deep(h1) {
  margin: 0.5rem 0 0.3rem;
  font-size: 1.25rem;
  font-weight: 800;
  color: var(--login-secondary, #0c1c40);
}

.rich-text :deep(h2) {
  margin: 0.5rem 0 0.3rem;
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--login-secondary, #0c1c40);
}

.rich-text :deep(h3) {
  margin: 0.45rem 0 0.25rem;
  font-size: 1rem;
  font-weight: 700;
  color: var(--login-secondary, #0c1c40);
}

.rich-text :deep(p) {
  margin: 0 0 0.4rem;
}

.rich-text :deep(p:last-child) {
  margin-bottom: 0;
}

.rich-text :deep(ul),
.rich-text :deep(ol) {
  margin: 0 0 0.4rem;
  padding-left: 1.3rem;
}

.rich-text :deep(li) {
  margin: 0.08rem 0;
}

.rich-text :deep(blockquote) {
  margin: 0 0 0.4rem;
  padding-left: 0.8rem;
  border-left: 3px solid #d4dae6;
  color: #6b7488;
}

.rich-text :deep(a) {
  color: var(--login-primary, #ed2044);
}

.rich-text :deep(.mention) {
  padding: 0.03rem 0.3rem;
  background: #e7eefc;
  border-radius: 0.4rem;
  color: #2b59c3;
  font-weight: 700;
}
</style>

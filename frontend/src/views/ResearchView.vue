<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import { usePublicationsStore } from '@/stores/publications'
import { useLocalized } from '@/composables/localized'

const { t } = useI18n()
const { l } = useLocalized()
const store = usePublicationsStore()
const { publications, loading, error } = storeToRefs(store)

const topicFilter = ref('')
const authorFilter = ref('')

onMounted(() => store.fetchPublications())

const filtered = computed(() => {
  const topic = topicFilter.value.trim().toLowerCase()
  const author = authorFilter.value.trim().toLowerCase()

  return publications.value.filter((pub) => {
    const topicOk = '' === topic || l(pub.topic).toLowerCase().includes(topic)
    const authorOk = '' === author || l(pub.author).toLowerCase().includes(author)
    return topicOk && authorOk
  })
})

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('en-GB', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
}
</script>

<template>
  <section class="research">
    <div class="container-lg">
      <div class="research-head">
        <span class="eyebrow">{{ t('research.eyebrow') }}</span>
        <h1>{{ t('research.title') }}</h1>
        <p>{{ t('research.subtitle') }}</p>
      </div>

      <!-- ── Search ────────────────────────────────────────────────── -->
      <div class="search-bar">
        <label class="search-field">
          <span class="search-label">{{ t('research.searchTopic') }}</span>
          <input
            v-model="topicFilter"
            type="text"
            :placeholder="t('research.searchTopicPlaceholder')"
          />
        </label>
        <label class="search-field">
          <span class="search-label">{{ t('research.searchAuthor') }}</span>
          <input
            v-model="authorFilter"
            type="text"
            :placeholder="t('research.searchAuthorPlaceholder')"
          />
        </label>
      </div>

      <!-- ── List ──────────────────────────────────────────────────── -->
      <p v-if="loading" class="state">{{ t('research.loading') }}</p>

      <div v-else-if="error" class="state state--error">
        <strong>{{ t('research.loadError') }}</strong>
        <button type="button" class="btn-retry" @click="store.fetchPublications()">
          {{ t('common.retry') }}
        </button>
      </div>

      <p v-else-if="publications.length === 0" class="state">{{ t('research.empty') }}</p>

      <p v-else-if="filtered.length === 0" class="state">{{ t('research.noMatch') }}</p>

      <ul v-else class="pub-list">
        <li v-for="pub in filtered" :key="pub.id" class="pub-card">
          <span class="pub-icon">📄</span>
          <div class="pub-body">
            <h3>{{ l(pub.title) }}</h3>
            <div class="pub-meta">
              <span v-if="l(pub.topic)" class="pub-tag">{{ l(pub.topic) }}</span>
              <span v-if="l(pub.author)" class="pub-author">{{
                t('research.by', { author: l(pub.author) })
              }}</span>
              <span class="pub-date">{{ formatDate(pub.createdAt) }}</span>
            </div>
            <p v-if="l(pub.description)" class="pub-desc">{{ l(pub.description) }}</p>
          </div>
          <a :href="pub.fileUrl" target="_blank" rel="noopener" class="btn-open">
            {{ t('research.openPdf') }}
          </a>
        </li>
      </ul>
    </div>
  </section>
</template>

<style scoped>
.research {
  padding: 3.5rem 0 5rem;
}

.research-head {
  margin-bottom: 2rem;
}

.eyebrow {
  display: inline-block;
  color: var(--login-primary, #ed2044);
  font-size: 0.95rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.research-head h1 {
  margin: 0.35rem 0 0.6rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 2.6rem;
  font-weight: 700;
}

.research-head p {
  max-width: 640px;
  margin: 0;
  color: #545f71;
  font-size: 1.1rem;
  line-height: 1.5;
}

/* ── Search ─────────────────────────────────────────────────────── */
.search-bar {
  display: grid;
  gap: 1rem;
  grid-template-columns: 1fr 1fr;
  margin-bottom: 2rem;
  padding: 1.4rem 1.6rem;
  background: #fff;
  border-radius: 1rem;
  box-shadow: 0 12px 32px rgba(12, 28, 64, 0.08);
}

.search-field {
  display: block;
}

.search-label {
  display: block;
  margin-bottom: 0.35rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.85rem;
  font-weight: 700;
}

.search-field input {
  width: 100%;
  padding: 0.65rem 0.85rem;
  border: 1px solid #d4dae6;
  border-radius: 0.55rem;
  font-size: 0.98rem;
  color: var(--login-secondary, #0c1c40);
}

.search-field input:focus {
  outline: none;
  border-color: var(--login-primary, #ed2044);
}

/* ── Publication list ───────────────────────────────────────────── */
.pub-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.pub-card {
  display: flex;
  align-items: center;
  gap: 1.1rem;
  padding: 1.4rem 1.6rem;
  background: #fff;
  border-radius: 1rem;
  box-shadow: 0 12px 32px rgba(12, 28, 64, 0.08);
}

.pub-icon {
  font-size: 2rem;
}

.pub-body {
  flex: 1;
  min-width: 0;
}

.pub-body h3 {
  margin: 0 0 0.4rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.15rem;
  font-weight: 700;
}

.pub-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.4rem;
}

.pub-tag {
  padding: 0.2rem 0.65rem;
  background: #fdeef1;
  border-radius: 100vw;
  color: var(--login-primary, #ed2044);
  font-size: 0.78rem;
  font-weight: 700;
}

.pub-author {
  color: var(--login-secondary, #0c1c40);
  font-size: 0.85rem;
  font-weight: 700;
}

.pub-date {
  color: #8b94a6;
  font-size: 0.83rem;
  font-weight: 700;
}

.pub-desc {
  margin: 0;
  color: #545f71;
  font-size: 0.97rem;
  line-height: 1.5;
}

.btn-open {
  flex-shrink: 0;
  padding: 0.55rem 1.1rem;
  background: var(--login-primary, #ed2044);
  border-radius: 0.5rem;
  color: #fff;
  font-size: 0.92rem;
  font-weight: 700;
}

.state {
  padding: 1.6rem;
  background: #fff;
  border-radius: 1.1rem;
  box-shadow: 0 12px 32px rgba(12, 28, 64, 0.08);
  color: var(--login-secondary, #0c1c40);
  font-size: 1.05rem;
}

.state--error {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.7rem;
}

.btn-retry {
  padding: 0.4rem 0.9rem;
  background: var(--login-primary, #ed2044);
  border: none;
  border-radius: 0.45rem;
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}

@media (max-width: 575.98px) {
  .research-head h1 {
    font-size: 2rem;
  }

  .search-bar {
    grid-template-columns: 1fr;
  }

  .pub-card {
    flex-wrap: wrap;
  }
}
</style>

<script setup lang="ts">
import { useData, withBase } from 'vitepress'
import { ref, onBeforeUnmount } from 'vue'

const { page } = useData()
const state = ref<'idle' | 'copied' | 'error'>('idle')
let timer: ReturnType<typeof setTimeout> | undefined

// Each page's source is published next to its HTML (see buildEnd in config.ts).
async function copy() {
  try {
    const res = await fetch(withBase('/' + page.value.relativePath))
    if (!res.ok) throw new Error(String(res.status))
    const source = await res.text()
    // Frontmatter is site metadata, not content.
    await navigator.clipboard.writeText(source.replace(/^---\r?\n[\s\S]*?\r?\n---\r?\n+/, ''))
    state.value = 'copied'
  } catch {
    state.value = 'error'
  }
  clearTimeout(timer)
  timer = setTimeout(() => (state.value = 'idle'), 2000)
}

onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
  <button class="copy-markdown" type="button" @click="copy">
    <svg v-if="state === 'copied'" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
    <svg v-else xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
    <span role="status">{{ state === 'copied' ? 'Copied' : state === 'error' ? 'Copy failed' : 'Copy as Markdown' }}</span>
  </button>
</template>

<style scoped>
.copy-markdown {
  display: inline-flex;
  align-self: flex-start;
  align-items: center;
  gap: 6px;
  margin-bottom: 12px;
  padding: 4px 10px;
  font-size: 13px;
  font-weight: 500;
  line-height: 20px;
  color: var(--vp-c-text-2);
  background-color: var(--vp-c-bg-soft);
  border: 1px solid var(--vp-c-divider);
  border-radius: 6px;
  cursor: pointer;
  transition: color 0.2s, border-color 0.2s, background-color 0.2s;
}

.copy-markdown:hover,
.copy-markdown:focus-visible {
  color: var(--vp-c-brand-1);
  border-color: var(--vp-c-brand-1);
  background-color: var(--vp-c-brand-soft);
  outline: none;
}
</style>

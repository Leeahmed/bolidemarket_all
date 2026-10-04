<script setup>
import { computed } from 'vue'
const props = defineProps({ meta: Object })
defineEmits(['change'])
const pages = computed(() => { const current = props.meta?.current_page || 1, last = props.meta?.last_page || 1; return [...new Set([1, current - 1, current, current + 1, last])].filter(n => n >= 1 && n <= last) })
</script>
<template><nav v-if="meta?.last_page > 1" class="pagination" aria-label="Pagination"><button :disabled="meta.current_page <= 1" aria-label="Page précédente" @click="$emit('change', meta.current_page - 1)">←</button><button v-for="page in pages" :key="page" :aria-current="page === meta.current_page ? 'page' : undefined" :aria-label="`Page ${page}`" @click="$emit('change', page)">{{ page }}</button><button :disabled="meta.current_page >= meta.last_page" aria-label="Page suivante" @click="$emit('change', meta.current_page + 1)">→</button></nav></template>

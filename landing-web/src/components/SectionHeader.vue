<script setup>
defineProps({ title: String, subtitle: String, href: String, link: String })
</script>
<template><div class="section-heading" data-reveal><div><h2>{{ title }}</h2><p v-if="subtitle">{{ subtitle }}</p></div><a v-if="href" :href="href" class="text-link">{{ link }} <span aria-hidden="true">→</span></a></div></template>

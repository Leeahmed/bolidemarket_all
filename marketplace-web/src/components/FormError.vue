<script setup>
defineProps({ error: Object })
</script>
<template><div v-if="error" class="form-error" role="alert" tabindex="-1"><p>{{ error.message }}</p><ul v-if="Object.keys(error.fields || {}).length"><li v-for="(messages, field) in error.fields" :key="field">{{ Array.isArray(messages) ? messages.join(' ') : messages }}</li></ul></div></template>


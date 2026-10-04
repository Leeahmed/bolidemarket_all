<script setup>
import { ref, watch } from 'vue'
const props = defineProps({ user: Object, src: String })
const broken = ref(false)
watch(() => [props.src, props.user?.avatar_url], () => { broken.value = false })
</script>
<template><span class="user-avatar"><img v-if="(src || user?.avatar_url) && !broken" :src="src || user.avatar_url" :alt="'Photo de ' + (user?.first_name || 'profil')" @error="broken = true" /><span v-else>{{ ((user?.first_name?.[0] || '') + (user?.last_name?.[0] || '')).toUpperCase() || 'BM' }}</span></span></template>

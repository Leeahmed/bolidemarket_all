<script setup>
import { useRoute } from 'vue-router'
import AdminShell from './components/AdminShell.vue'
import { auth } from './stores/auth'
const route=useRoute()
</script>
<template><AdminShell v-if="auth.user?.role==='admin' && !['/login','/forbidden'].includes(route.path)"><RouterView /></AdminShell><RouterView v-else /></template>

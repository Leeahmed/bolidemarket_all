<script setup>
import { onMounted,onBeforeUnmount } from 'vue'
import { auth,roleDestination } from '../stores/auth'
let timer
onMounted(()=>{timer=setTimeout(()=>window.location.assign(roleDestination(auth.user?.role)),2500)})
onBeforeUnmount(()=>clearTimeout(timer))
</script>
<template><main class="state forbidden"><img src="/images/logo-horizontal.webp" alt="BolideMarket"><span class="eyebrow">403 · Accès réservé</span><h1>Votre compte ne donne pas accès à l’administration.</h1><p>Redirection vers votre espace…</p><a class="button" :href="roleDestination(auth.user?.role)">Ouvrir mon espace</a></main></template>

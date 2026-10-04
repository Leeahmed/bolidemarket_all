<script setup>
import AppHeader from './components/AppHeader.vue'
import ToastStack from './components/ToastStack.vue'
import { config } from './config'
</script>
<template><a class="skip-link" href="#main">Aller au contenu</a><AppHeader /><ToastStack /><main id="main" tabindex="-1"><RouterView /></main><footer class="app-footer"><div class="container footer-inner"><a :href="config.landingUrl"><img src="/images/logo-horizontal.webp" width="840" height="175" alt="BolideMarket" /></a><p>Achetez. Louez. Roulez.</p><span>© 2026 BolideMarket</span></div><p class="container demo-note">Les offres signalées « démo », leurs boutiques, prix et données sont des données de démonstration.</p></footer></template>

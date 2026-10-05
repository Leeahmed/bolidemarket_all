<script setup>
import { watch, onBeforeUnmount } from 'vue'
import { auth } from './stores/auth'
import { realtime } from './services/realtime'
import { useRealtime } from './composables/useRealtime'
import { notify } from './stores/toasts'
watch(() => auth.user?.id, userId => realtime.connect({ userId, publicChannel: true }), { immediate: true, flush: 'sync' })
onBeforeUnmount(() => realtime.disconnect())
useRealtime('private', event => {
  if (event.type === 'PriceOfferAccepted') notify('Votre proposition de prix a été acceptée. Retrouvez-la dans Mes propositions.')
  if (event.type === 'PriceOfferRejected') notify('Le vendeur a refusé votre proposition de prix.')
  if (event.type === 'ReservationConfirmed') notify('Votre réservation a été confirmée.')
  if (event.type === 'OrderCompleted') notify('Votre commande a été finalisée.')
})
import AppHeader from './components/AppHeader.vue'
import ToastStack from './components/ToastStack.vue'
import { config } from './config'
</script>
<template><a class="skip-link" href="#main">Aller au contenu</a><AppHeader /><ToastStack /><main id="main" tabindex="-1"><RouterView /></main><footer class="app-footer"><div class="container footer-inner"><a :href="config.landingUrl"><img src="/images/logo-horizontal.webp" width="840" height="175" alt="BolideMarket" /></a><p>Achetez. Louez. Roulez.</p><span>© 2026 BolideMarket</span></div><p class="container demo-note">Les offres signalées « démo », leurs boutiques, prix et données sont des données de démonstration.</p></footer></template>

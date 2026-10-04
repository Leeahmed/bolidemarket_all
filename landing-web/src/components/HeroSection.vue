<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { config, marketplaceLink } from '../config'
import { useReveal } from '../composables/useReveal'
import HeroSearch from './HeroSearch.vue'
import ValueStrip from './ValueStrip.vue'
import AppIcon from './AppIcon.vue'
const root = ref(null)
const canPlay = ref(false)
let media
function syncMotion() { canPlay.value = Boolean(config.heroVideoUrl) && !media.matches }
onMounted(() => { media = window.matchMedia('(prefers-reduced-motion: reduce)'); syncMotion(); media.addEventListener('change', syncMotion) })
onBeforeUnmount(() => media?.removeEventListener('change', syncMotion))
useReveal(root, true)
</script>
<template>
  <section id="accueil" ref="root" class="hero" aria-labelledby="hero-title">
    <div class="hero-media" aria-hidden="true"><img src="/images/hero-auto-poster.webp" width="816" height="600" alt="" fetchpriority="high" /><video v-if="canPlay" :src="config.heroVideoUrl" poster="/images/hero-auto-poster.webp" autoplay muted loop playsinline preload="none" @error="canPlay = false" /></div>
    <div class="hero-shade" />
    <div class="container hero-content"><div class="hero-copy"><p class="eyebrow" data-reveal>ACHETEZ · LOUEZ · ROULEZ</p><h1 id="hero-title" data-reveal>Votre prochain bolide<br class="desktop-break" /> est plus proche que<br class="desktop-break" /> vous ne le pensez.</h1><p class="hero-subtitle" data-reveal>Des véhicules proposés par des professionnels,<br class="desktop-break" /> près de chez vous et partout ailleurs.</p><div class="button-row" data-reveal><a class="button button-primary" :href="marketplaceLink({ listing_type: 'sale' })">Acheter un véhicule <AppIcon /></a><a class="button button-outline" :href="marketplaceLink({ listing_type: 'rental' })">Louer un véhicule <AppIcon /></a></div></div><HeroSearch /><ValueStrip /></div>
  </section>
</template>

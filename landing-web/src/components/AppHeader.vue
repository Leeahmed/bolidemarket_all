<script setup>
import { inject, onBeforeUnmount, onMounted, ref } from 'vue'
import { config, marketplaceLink } from '../config'
import { landingKey } from '../composables/useLandingData'
import BrandLogo from './BrandLogo.vue'
import AppIcon from './AppIcon.vue'
const { location } = inject(landingKey)
const open = ref(false)
const scrolled = ref(false)
const menuButton = ref(null)
const updateScroll = () => { scrolled.value = window.scrollY > 24 }
function close(restore = false) { open.value = false; if (restore) menuButton.value?.focus() }
onMounted(() => { updateScroll(); window.addEventListener('scroll', updateScroll, { passive: true }) })
onBeforeUnmount(() => window.removeEventListener('scroll', updateScroll))
</script>
<template>
  <header class="app-header" :class="{ scrolled, 'menu-open': open }" @keydown.esc="close(true)">
    <div class="container header-inner">
      <BrandLogo />
      <button ref="menuButton" class="icon-button menu-toggle" :aria-expanded="open" aria-controls="main-navigation" :aria-label="open ? 'Fermer le menu' : 'Ouvrir le menu'" @click="open = !open"><AppIcon :name="open ? 'close' : 'menu'" /></button>
      <nav id="main-navigation" class="main-navigation" :class="{ open }" aria-label="Navigation principale" @click="close()">
        <a :href="marketplaceLink({ listing_type: 'sale' })">Acheter</a><a :href="marketplaceLink({ listing_type: 'rental' })">Louer</a><a href="#professionnels">Professionnels</a><a href="#comment-ca-marche">À propos</a>
      </nav>
      <div class="header-actions"><a class="header-location" href="#recherche"><AppIcon name="pin" /><span>{{ location?.label || 'Choisir une ville' }}</span><AppIcon name="chevron" /></a><a class="button button-outline" :href="config.merchantUrl">Espace professionnel</a></div>
    </div>
  </header>
</template>

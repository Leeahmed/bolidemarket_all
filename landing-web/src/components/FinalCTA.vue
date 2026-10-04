<script setup>
import { config, marketplaceLink } from '../config'
import AppIcon from './AppIcon.vue'
</script>
<template><section class="final-cta"><img src="/images/final-auto.webp" alt="" loading="lazy" width="321" height="87" /><div class="container" data-reveal><h2>Votre prochain bolide vous attend.</h2><div class="button-row"><a class="button button-primary" :href="marketplaceLink()">Explorer le marché <AppIcon /></a><a class="button button-outline" :href="config.merchantUrl">Je suis professionnel</a></div></div></section></template>

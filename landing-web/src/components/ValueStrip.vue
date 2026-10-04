<script setup>
import AppIcon from './AppIcon.vue'
const values = [ ['shield', 'Achat & location', 'Des offres à explorer'], ['users', 'Professionnels', 'Des vendeurs à découvrir'], ['pin', 'Proximité', 'Les véhicules les plus proches d’abord'], ['chart', 'Réseau global', 'Des opportunités partout'] ]
</script>
<template><div class="value-strip"><div v-for="[icon, title, text] in values" :key="title" class="value-item"><AppIcon :name="icon" /><div><strong>{{ title }}</strong><p>{{ text }}</p></div></div></div></template>

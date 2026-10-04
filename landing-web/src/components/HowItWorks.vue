<script setup>
import AppIcon from './AppIcon.vue'
const steps = [ ['pin', 'Localisez-vous', 'Indiquez votre ville ou votre quartier.'], ['search', 'Trouvez votre véhicule', 'Parcourez les offres des professionnels.'], ['users', 'Comparez les professionnels', 'Découvrez les boutiques et leurs véhicules.'], ['document', 'Achetez ou réservez', 'Contactez le professionnel et préparez votre projet.'] ]
</script>
<template><section id="comment-ca-marche" class="section container how-it-works"><h2 data-reveal>Votre prochain véhicule en quelques étapes.</h2><ol class="steps"><li v-for="([icon, title, text], index) in steps" :key="title" data-reveal><AppIcon :name="icon" /><span class="step-number">0{{ index + 1 }}</span><div><h3>{{ title }}</h3><p>{{ text }}</p></div></li></ol></section></template>

<script setup>
defineProps({handover:Object})
const modes={self:'Retrait en personne',proxy:'Retrait par une personne mandatée',delivery:'Livraison demandée par chauffeur'}
</script>
<template><section v-if="handover" class="handover-summary"><h3>{{ modes[handover.mode] }}</h3><p><strong>Rendez-vous souhaité :</strong> {{ new Date(handover.scheduled_at).toLocaleString('fr-FR',{timeZone:handover.timezone}) }} ({{ handover.timezone }})</p><p>{{ handover.contact_name }} · {{ handover.contact_phone }}</p><p v-if="handover.mode==='delivery'">{{ handover.address }} · {{ handover.city }}</p><p v-if="handover.latitude!=null && handover.longitude!=null">Coordonnées : {{ handover.latitude }}, {{ handover.longitude }}</p><p v-if="handover.notes">{{ handover.notes }}</p><p class="help">Horaire et modalités à confirmer avec le professionnel.</p></section></template>

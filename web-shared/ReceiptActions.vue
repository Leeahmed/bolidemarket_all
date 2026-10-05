<script setup>
defineProps({reference:String,apiBase:String,scope:{type:String,default:'me'},detailBase:String})
const url=(base,scope,reference,inline=false)=>base+'/'+scope+'/receipts/'+encodeURIComponent(reference)+'/pdf'+(inline?'?disposition=inline':'')
</script>
<template><div v-if="reference" class="receipt-actions"><RouterLink v-if="detailBase" class="button secondary" :to="detailBase+'/'+reference">Voir le reçu</RouterLink><a class="button" :href="url(apiBase,scope,reference)" :download="'BolideMarket_'+reference+'.pdf'">Télécharger le PDF</a><a class="button secondary" :href="url(apiBase,scope,reference,true)" target="_blank" rel="noopener">Imprimer</a></div></template>
<style scoped>.receipt-actions{display:flex;flex-wrap:wrap;gap:12px;margin:16px 0}.receipt-actions .button{font-size:12px}</style>

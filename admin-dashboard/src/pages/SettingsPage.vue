<script setup>
import { ref,onMounted } from 'vue'
import { get } from '../services/api'
import LoadState from '../components/LoadState.vue'
import DataFields from '../components/DataFields.vue'
const data=ref(null),busy=ref(false),error=ref(null)
async function load(){busy.value=true;error.value=null;try{data.value=(await get('/admin/settings')).data}catch(e){error.value=e}finally{busy.value=false}}
onMounted(load)
</script>
<template><div class="page-title"><div><span class="eyebrow">Configuration serveur</span><h1>Paramètres</h1></div><span class="badge">Lecture uniquement</span></div><p class="lead">Référentiels et règles de sécurité. Aucune modification de devise historique ni de rôle depuis cet écran.</p><LoadState :busy="busy" :error="error" @retry="load"><section v-if="data" class="panel"><DataFields :data="data"/></section></LoadState></template>

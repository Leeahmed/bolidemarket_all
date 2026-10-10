<script setup>
import { ref,watch,onBeforeUnmount } from 'vue'
import { useRoute } from 'vue-router'
import { get } from '../services/api'
import { entities,routeFor } from '../entities'
import LoadState from '../components/LoadState.vue'
const route=useRoute(),data=ref(null),busy=ref(false),error=ref(null)
let controller,version=0
async function load(){const v=++version;controller?.abort();controller=new AbortController();busy.value=true;error.value=null;data.value=null;try{const r=await get('/admin/search',{q:route.query.q||''},controller.signal);if(v===version)data.value=r.data}catch(e){if(v===version&&!controller.signal.aborted)error.value=e}finally{if(v===version)busy.value=false}}
watch(()=>route.query.q,load,{immediate:true});onBeforeUnmount(()=>{version++;controller?.abort()})
</script>
<template><span class="eyebrow">Recherche globale</span><h1>Résultats pour « {{ route.query.q }} »</h1><p class="lead">Jusqu’à 5 résultats récents par catégorie. Les listes permettent de poursuivre la recherche.</p><LoadState :busy="busy" :error="error" :empty="data && !data.length" @retry="load"><section v-if="data" class="panel"><ul class="related"><li v-for="row in data" :key="row.type+row.id"><span class="badge">{{ entities[row.type].singular }}</span><RouterLink :to="routeFor(row.type,row)">{{ row.label }}</RouterLink><RouterLink :to="{path:'/'+row.type,query:{q:route.query.q}}">Tous →</RouterLink></li></ul></section></LoadState></template>

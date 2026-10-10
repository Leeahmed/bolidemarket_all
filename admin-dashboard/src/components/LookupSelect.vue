<script setup>
import { ref,watch,onBeforeUnmount } from 'vue'
import { get } from '../services/api'
const props=defineProps({kind:String,modelValue:[String,Number],country:String})
const emit=defineEmits(['update:modelValue'])
const query=ref(''),options=ref([]),busy=ref(false),error=ref(''),chosen=ref(null)
let timer,controller,version=0
async function load(){const v=++version;controller?.abort();controller=new AbortController();busy.value=true;error.value='';try{const p={type:props.kind,...(query.value?{q:query.value}:{}),...(props.country?{country_code:props.country}:{})};const r=await get('/admin/lookup',p,controller.signal);if(v!==version)return;options.value=r.data;if(props.modelValue&&!options.value.some(o=>o.id===String(props.modelValue))){const selected=chosen.value?.id===String(props.modelValue)?chosen.value:(await get('/admin/lookup',{type:props.kind,id:props.modelValue},controller.signal)).data[0];if(v!==version)return;if(selected)options.value.unshift(selected)}}catch(e){if(v===version&&!controller.signal.aborted)error.value=e.message}finally{if(v===version)busy.value=false}}
watch(()=>[props.kind,props.country],load,{immediate:true})
watch(query,()=>{clearTimeout(timer);timer=setTimeout(load,250)})
function choose(event){chosen.value=options.value.find(o=>o.id===event.target.value)||null;emit('update:modelValue',event.target.value)}
onBeforeUnmount(()=>{version++;clearTimeout(timer);controller?.abort()})
</script>
<template><div class="lookup"><input v-model="query" maxlength="120" placeholder="Rechercher un nom…" :aria-label="'Rechercher '+(kind==='cities'?'une ville':kind==='shops'?'une boutique':'un professionnel')"><select :value="modelValue||''" :aria-label="kind==='cities'?'Ville':kind==='shops'?'Boutique':'Professionnel'" @change="choose"><option value="">Tous</option><option v-for="option in options" :key="option.id" :value="option.id">{{ option.name }}</option></select><small v-if="busy" role="status">Recherche…</small><small v-else-if="error" role="alert">{{ error }}</small></div></template>

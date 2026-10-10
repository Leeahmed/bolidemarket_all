<script setup>
import { ref,watch,nextTick } from 'vue'
import { post } from '../services/api'
import { actionLabels } from '../entities'
const props=defineProps({target:Object});const emit=defineEmits(['close','done'])
const dialog=ref(null),reason=ref(''),busy=ref(false),error=ref(null)
let previous
watch(()=>props.target,async value=>{if(value){previous=document.activeElement;reason.value='';error.value=null;await nextTick();dialog.value?.showModal()}else{dialog.value?.close();previous?.focus()}},{immediate:true})
function close(){if(!busy.value)emit('close')}
async function submit(){if(busy.value)return;busy.value=true;error.value=null;try{await post('/admin/'+props.target.type+'/'+props.target.id+'/actions',{action:props.target.action,reason:reason.value.trim()});emit('done');emit('close')}catch(e){error.value=e}finally{busy.value=false}}
</script>
<template><dialog ref="dialog" aria-labelledby="moderation-title" @cancel.prevent="close"><form @submit.prevent="submit"><span class="eyebrow">Décision administrative</span><h2 id="moderation-title">{{ actionLabels[target?.action] }} · {{ target?.label }}</h2><p>Cette action sera enregistrée dans le journal d’activité. Les transactions historiques restent conservées.</p><label for="moderation-reason">Motif obligatoire</label><textarea id="moderation-reason" v-model="reason" required minlength="5" maxlength="500" rows="4" autofocus placeholder="Contenu incorrect, doublon, prix suspect…"/><p v-if="error" class="error" role="alert">{{ error.fields?.reason?.[0] || error.fields?.action?.[0] || error.message }}</p><div class="actions"><button type="button" class="secondary" :disabled="busy" @click="close">Annuler</button><button :disabled="busy || reason.trim().length<5">{{ busy?'Enregistrement…':'Confirmer' }}</button></div></form></dialog></template>

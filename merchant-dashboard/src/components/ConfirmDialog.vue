<script setup>
import { ref, watch, nextTick, useId } from 'vue'
const props = defineProps({ open:Boolean, title:String, busy:Boolean })
const emit = defineEmits(['confirm','close'])
const dialog = ref(null), titleId = useId()
watch(() => props.open, async value => { await nextTick(); if (value) dialog.value?.showModal(); else dialog.value?.close() })
</script><template><dialog ref="dialog" :aria-labelledby="titleId" @cancel.prevent="!busy && emit('close')"><h2 :id="titleId">{{ title }}</h2><slot/><div class="actions"><button class="button secondary" autofocus :disabled="busy" @click="emit('close')">Annuler</button><button class="button" :disabled="busy" @click="emit('confirm')">{{ busy ? 'En cours…' : 'Confirmer' }}</button></div></dialog></template>
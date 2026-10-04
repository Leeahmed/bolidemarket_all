<script setup>
import FormError from './FormError.vue'
defineProps({ busy:Boolean, error:Object })
defineEmits(['retry'])
</script><template><div v-if="busy" class="loading" role="status">Chargement des informations…</div><div v-else-if="error" class="panel"><FormError :error="error"/><button class="button secondary" @click="$emit('retry')">Réessayer</button></div><slot v-else/></template>
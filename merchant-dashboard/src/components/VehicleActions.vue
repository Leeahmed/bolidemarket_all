<script setup>
import { ref } from 'vue'
import { merchantVehicleService as api } from '../services/merchant'
import { labels } from '../utils/pro'
import { notify } from '../stores/toasts'
import FormError from './FormError.vue'
import ConfirmDialog from './ConfirmDialog.vue'
const props=defineProps({vehicle:Object}),emit=defineEmits(['updated','deleted'])
const action=ref(''),value=ref(''),busy=ref(false),error=ref(null)
function choose(type){action.value=type;error.value=null;value.value=type==='inventory'?props.vehicle.inventory_status:props.vehicle.publication_status}
async function confirm(){busy.value=true;error.value=null;try{if(action.value==='delete'){await api.remove(props.vehicle.id);emit('deleted');notify('Véhicule supprimé.')}else{await api.status(props.vehicle.id,{[action.value==='inventory'?'inventory_status':'publication_status']:value.value});emit('updated');notify('Statut mis à jour.')}action.value=''}catch(e){error.value=e}finally{busy.value=false}}
</script><template><div class="row-actions"><RouterLink :to="'/vehicles/'+vehicle.id">Voir</RouterLink><RouterLink :to="'/vehicles/'+vehicle.id+'/edit'">Modifier</RouterLink><details><summary aria-label="Autres actions du véhicule">•••</summary><div class="action-popover"><button @click="choose('inventory')">Disponibilité</button><button @click="choose('publication')">Publication</button><button @click="choose('delete')">Supprimer</button></div></details></div><ConfirmDialog :open="!!action" :title="action==='delete'?'Supprimer ce véhicule ?':'Modifier le statut'" :busy="busy" @close="action=''" @confirm="confirm"><FormError :error="error"/><p v-if="action==='delete'">L’annonce sera retirée. L’historique des transactions sera conservé.</p><template v-else><label>Nouveau statut<select v-model="value"><option v-for="state in action==='inventory'?['available','other']:['draft','published','archived']" :key="state" :value="state">{{ labels[state] }}</option></select></label><p class="help">Loué et vendu sont mis à jour lors de la remise ou de la livraison. Les engagements en cours restent contrôlés par le serveur.</p></template></ConfirmDialog></template>
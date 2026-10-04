<script setup>
import { computed, ref, watch, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { useResource } from '../composables/useResource'
import { commerceService } from '../services/commerceService'
import { canCancelReservation } from '../utils/commerce'
import { notify } from '../stores/toasts'
import FormError from '../components/FormError.vue'
import CommerceRecord from '../components/CommerceRecord.vue'
const route = useRoute(), { data, state, error, load } = useResource()
const dialog = ref(null), busy = ref(false), actionError = ref(null), now = ref(Date.now())
const record = computed(() => data.value?.data)
const canCancel = computed(() => route.meta.kind === 'reservations' && record.value && canCancelReservation(record.value, now.value))
const reload = () => load(signal => commerceService.detail(route.meta.kind, route.params.id, signal))
watch(() => route.fullPath, () => { actionError.value = null; reload() }, { immediate: true })
let timer
onMounted(() => { timer = setInterval(() => { now.value = Date.now() }, 1000) })
onUnmounted(() => clearInterval(timer))
async function cancel() {
  if (busy.value) return
  busy.value = true; actionError.value = null
  try { const result = await commerceService.cancel(record.value.id); data.value = result; dialog.value.close(); notify('Réservation annulée.') }
  catch (e) { actionError.value = e; if (e.status === 409) await reload() }
  finally { busy.value = false }
}
</script>
<template><div :class="{ 'container page-content confirmation-page': route.meta.confirmation }"><p class="eyebrow">{{ route.meta.confirmation ? 'DEMANDE ENREGISTRÉE' : 'VOTRE SUIVI' }}</p><h1>{{ route.meta.confirmation ? route.meta.kind === 'reservations' ? 'Réservation créée.' : 'Achat enregistré.' : route.meta.kind === 'reservations' ? 'Votre réservation' : 'Votre commande' }}</h1><p class="page-lead">Le statut affiché ci-dessous est celui communiqué par le professionnel via BolideMarket.</p><p class="demo-notice">Simulation BolideMarket · Aucun paiement réel.</p><p v-if="state === 'loading'" role="status">Chargement du détail…</p><template v-else-if="state === 'error'"><FormError :error="error" /><button class="button" @click="reload">Réessayer</button></template><template v-else-if="record"><CommerceRecord :record="record" :kind="route.meta.kind" detailed /><div class="action-row"><RouterLink class="button" :to="'/account/' + route.meta.kind">{{ route.meta.kind === 'reservations' ? 'Voir mes réservations' : 'Voir mes achats' }}</RouterLink><RouterLink class="button secondary" to="/vehicles">Retour au marché</RouterLink><button v-if="canCancel" class="button secondary" @click="dialog.showModal()">Annuler cette réservation</button><button class="text-button" @click="reload">Actualiser le statut</button></div></template><dialog ref="dialog" class="confirm-dialog" aria-labelledby="cancel-title" @cancel="busy && $event.preventDefault()"><h2 id="cancel-title">Annuler cette réservation ?</h2><p>Les dates seront libérées. Un éventuel paiement démo reste dans l’historique ; aucun remboursement n’est simulé.</p><FormError :error="actionError" /><div class="action-row"><button class="button secondary" :disabled="busy" autofocus @click="dialog.close()">Garder ma réservation</button><button class="button" :disabled="busy" @click="cancel">{{ busy ? 'Annulation en cours…' : 'Confirmer l’annulation' }}</button></div></dialog></div></template>


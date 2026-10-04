<script setup>
import { computed, ref, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { auth } from '../stores/auth'
import { useResource } from '../composables/useResource'
import { vehicleService } from '../services/vehicleService'
import { commerceService } from '../services/commerceService'
import { formatMoney, locationLabel } from '../utils/format'
import { vehicleMedia } from '../utils/demoMedia'
import { paymentMethods, dateInZone, addDays, rangeAvailable, canTransact, recordMoney, displayDate } from '../utils/commerce'
import { notify } from '../stores/toasts'
import FormError from '../components/FormError.vue'
import DateCalendar from '../components/DateCalendar.vue'
import SafeImage from '../components/SafeImage.vue'
const route = useRoute(), router = useRouter(), { data, state, error, load } = useResource()
const rental = computed(() => route.meta.checkout === 'reserve'), vehicle = computed(() => data.value?.data)
const media = computed(() => vehicle.value ? vehicleMedia(vehicle.value) : {})
const availability = ref(null), calendarError = ref(null), calendarBusy = ref(false)
const start = ref(''), end = ref(''), quote = ref(null), method = ref('MOBILE_MONEY_DEMO'), step = ref(1)
const busy = ref(false), actionError = ref(null), attempt = ref(null), uncertain = ref(false)
const eligible = computed(() => canTransact(vehicle.value, rental.value ? 'reserve' : 'buy'))
const keyName = computed(() => 'bm-attempt:' + auth.user?.id + ':' + route.meta.checkout + ':' + route.params.slug)
function saveAttempt() { try { if (attempt.value) sessionStorage.setItem(keyName.value, JSON.stringify({ ...attempt.value, quote: quote.value, start: start.value, end: end.value })); else sessionStorage.removeItem(keyName.value) } catch { /* In-memory replay still works when storage is unavailable. */ } }
async function loadCalendar() {
  calendarBusy.value = true; calendarError.value = null
  try {
    // First read obtains the authoritative shop timezone. Then request complete civil days.
    const initial = await commerceService.availability(route.params.slug)
    const from = dateInZone(Date.now(), initial.timezone)
    availability.value = await commerceService.availability(route.params.slug, { from, to: addDays(from, 365) })
  } catch (e) { calendarError.value = e; availability.value = null }
  finally { calendarBusy.value = false }
}
async function reload() {
  await load(signal => vehicleService.detail(route.params.slug, signal))
  if (state.value === 'success' && rental.value) await loadCalendar()
}
watch(() => route.fullPath, async () => {
  start.value = ''; end.value = ''; quote.value = null; attempt.value = null; uncertain.value = false; step.value = 1; actionError.value = null; availability.value = null
  try {
    const saved = JSON.parse(sessionStorage.getItem(keyName.value) || 'null')
    if (saved?.key && saved?.body) {
      attempt.value = { key: saved.key, body: saved.body }; quote.value = saved.quote
      start.value = saved.start; end.value = saved.end; method.value = saved.body.payment_method
      step.value = 3; uncertain.value = true
    }
  } catch { /* Invalid local state is ignored; no authority over server data. */ }
  await reload()
}, { immediate: true })
watch([start, end], () => { if (!uncertain.value) quote.value = null })
async function summarize() {
  if (busy.value) return
  busy.value = true; actionError.value = null
  try {
    if (rental.value) {
      if (!rangeAvailable(start.value, end.value, availability.value)) throw new Error('Choisissez une période disponible, avec un retour après le départ.')
      quote.value = (await commerceService.quote({ vehicle_id: vehicle.value.id, start_date: start.value, end_date: end.value })).data
    }
    step.value = 2
  } catch (e) { await handleError(e) }
  finally { busy.value = false }
}
async function handleError(e) {
  actionError.value = e
  if (e.status === 409) {
    if (e.code === 'VEHICLE_UNAVAILABLE') {
      step.value = 1
      actionError.value = { message: rental.value ? 'Ces dates viennent de devenir indisponibles. Veuillez choisir une autre période.' : 'Ce véhicule n’est plus disponible à la vente.' }
      if (rental.value) { start.value = ''; end.value = ''; await loadCalendar() }
      else await reload()
    }
    if (['VEHICLE_UNAVAILABLE', 'QUOTE_EXPIRED', 'PRICE_CHANGED'].includes(e.code)) { step.value = 1; quote.value = null }
  }
  await nextTick(); document.querySelector('.checkout-main .form-error')?.focus()
}
async function confirm() {
  if (busy.value) return
  busy.value = true; actionError.value = null
  try {
    if (!attempt.value) {
      if (rental.value && (!quote.value || Date.parse(quote.value.expires_at) <= Date.now())) {
        step.value = 1; throw new Error('Le devis a expiré. Demandez un nouveau résumé.')
      }
      attempt.value = { key: crypto.randomUUID(), body: rental.value ? { quote_id: quote.value.id, payment_method: method.value, conditions_version: quote.value.conditions_version } : { vehicle_id: vehicle.value.id, payment_method: method.value, expected_price_minor: vehicle.value.sale_price.amount_minor, currency: vehicle.value.sale_price.currency } }
      saveAttempt()
    }
    const result = rental.value ? await commerceService.reserve(attempt.value.body, attempt.value.key) : await commerceService.order(attempt.value.body, attempt.value.key)
    attempt.value = null; uncertain.value = false; saveAttempt()
    notify(rental.value ? 'Réservation créée.' : 'Achat enregistré.')
    await router.replace('/' + (rental.value ? 'reservation' : 'order') + '-confirmation/' + result.data.id)
  } catch (e) {
    // A timeout/5xx may follow a committed transaction. Keep the same key and body for a safe retry.
    uncertain.value = !e.status || e.status >= 500
    if (!uncertain.value) { attempt.value = null; saveAttempt() }
    await handleError(e)
  } finally { busy.value = false }
}
</script>
<template><div class="container page-content checkout"><nav class="breadcrumbs" aria-label="Fil d’Ariane"><RouterLink :to="'/vehicles/' + route.params.slug">← Retour au véhicule</RouterLink></nav><p class="eyebrow">{{ rental ? 'VOTRE PROCHAINE ESCAPADE' : 'VOTRE PROCHAIN BOLIDE' }}</p><h1>{{ rental ? 'Réserver ce véhicule.' : 'Votre achat, simplement.' }}</h1><p class="page-lead">Simulation BolideMarket · Aucun paiement réel.</p><p v-if="state === 'loading'" role="status">Chargement du véhicule…</p><template v-else-if="state === 'error'"><FormError :error="error" /><button class="button" @click="reload">Réessayer</button></template><div v-else-if="vehicle" class="checkout-grid"><section class="checkout-main"><ol class="checkout-steps" aria-label="Étapes"><li :aria-current="step === 1 ? 'step' : undefined">01 {{ rental ? 'Dates' : 'Véhicule' }}</li><li :aria-current="step === 2 ? 'step' : undefined">02 Résumé</li><li :aria-current="step === 3 ? 'step' : undefined">03 Paiement démo</li><li>04 Confirmation</li></ol><FormError :error="actionError" /><div v-if="uncertain" class="notice" role="status">Une demande est en attente de vérification. Réessayez avec la même demande pour éviter un doublon, ou consultez votre historique.</div><div v-if="auth.user?.email_verification_required" class="notice">Vérifiez votre adresse e-mail avant de continuer. <RouterLink to="/account/profile">Mon profil →</RouterLink></div><div v-if="!eligible && !attempt" class="notice" role="alert">{{ vehicle.inventory_status === 'sold' ? 'Ce véhicule a été vendu.' : 'Ce véhicule n’est pas disponible pour cette demande.' }} <RouterLink to="/vehicles">Explorer le marché</RouterLink></div><template v-else><div v-if="step === 1"><h2>{{ rental ? 'Choisissez vos dates' : 'Vérifiez votre véhicule' }}</h2><template v-if="rental"><p v-if="calendarBusy" role="status">Chargement des disponibilités…</p><template v-else-if="calendarError"><FormError :error="calendarError" /><button class="button" @click="loadCalendar">Recharger les disponibilités</button></template><DateCalendar v-else-if="availability" v-model:start="start" v-model:end="end" :availability="availability" /></template><p v-else class="page-lead">Vous demandez l’achat de ce {{ vehicle.brand.name }} {{ vehicle.model.name }} auprès de {{ vehicle.shop.name }}. La validation finale appartient au professionnel.</p><button class="button full" :disabled="busy || auth.user?.email_verification_required || (rental && (!start || !end || calendarBusy || !!calendarError))" @click="summarize">{{ busy ? 'Calcul du devis…' : 'Voir le résumé' }}</button></div><div v-else-if="step === 2"><h2>Votre demande en détail</h2><dl class="record-details"><template v-if="rental && quote"><div><dt>Dates</dt><dd>{{ displayDate(quote.starts_at, quote.shop_timezone) }} → {{ displayDate(quote.ends_at, quote.shop_timezone) }}</dd></div><div><dt>Durée</dt><dd>{{ quote.billable_days }} jour(s)</dd></div><div><dt>Tarif journalier</dt><dd>{{ formatMoney(recordMoney(quote, 'daily_price_minor')) }}</dd></div><div><dt>Sous-total</dt><dd>{{ formatMoney(recordMoney(quote)) }}</dd></div><div><dt>Frais de démonstration</dt><dd>Aucun</dd></div></template><div><dt>Total {{ rental ? 'du devis' : 'affiché' }}</dt><dd class="record-price">{{ rental ? formatMoney(recordMoney(quote)) : formatMoney(vehicle.sale_price) }}</dd></div><div><dt>Client</dt><dd>{{ auth.user?.name }}</dd></div><div><dt>Professionnel</dt><dd>{{ vehicle.shop.name }}</dd></div></dl><p class="help">{{ rental ? 'Le devis expire après 5 minutes. Aucun créneau n’est retenu avant l’envoi.' : 'Le prix sera vérifié au moment de l’envoi. Tout changement vous sera signalé.' }}</p><div class="action-row"><button class="button secondary" @click="step = 1">Modifier</button><button class="button" @click="step = 3">Choisir le paiement démo</button></div></div><div v-else><h2>Paiement de démonstration</h2><p class="notice">Aucun paiement réel ne sera effectué. Aucune donnée bancaire ne vous sera demandée.</p><fieldset class="payment-choices" :disabled="busy || uncertain"><legend>Choisissez un mode de paiement</legend><label v-for="(label, value) in paymentMethods" :key="value" :class="{ selected: method === value }"><input v-model="method" type="radio" name="payment_method" :value="value" />{{ label }}</label></fieldset><p class="checkout-total">Total {{ rental ? formatMoney(recordMoney(quote)) : formatMoney(vehicle.sale_price) }}</p><p class="help">Votre demande sera enregistrée en attente. Retenue de 15 minutes, puis confirmation par le professionnel. La simulation du paiement intervient à sa confirmation.</p><div class="action-row"><button v-if="!uncertain" class="button secondary" :disabled="busy" @click="step = 2">Revoir le résumé</button><button class="button" :disabled="busy || auth.user?.email_verification_required" @click="confirm">{{ busy ? 'Confirmation en cours…' : uncertain ? 'Vérifier ma demande' : rental ? 'Confirmer la réservation' : 'Confirmer l’achat' }}</button></div><RouterLink v-if="uncertain" :to="'/account/' + (rental ? 'reservations' : 'orders')" class="text-button">Consulter mon historique</RouterLink></div></template></section><aside class="checkout-summary"><SafeImage :src="media.src" :alt="vehicle.brand.name + ' ' + vehicle.model.name" /><div><span class="pill">{{ rental ? 'À louer' : 'À vendre' }}</span><h2>{{ vehicle.brand.name }} {{ vehicle.model.name }}</h2><p>{{ vehicle.year }} · {{ locationLabel(vehicle.location) }}</p><p class="record-price">{{ formatMoney(rental ? vehicle.rental_daily_price : vehicle.sale_price) }}<small v-if="rental"> / jour</small></p><hr /><p class="eyebrow">VOTRE PROFESSIONNEL</p><RouterLink :to="'/shops/' + vehicle.shop.slug">{{ vehicle.shop.name }} ↗</RouterLink><p class="help">{{ vehicle.shop.address }}</p><p v-if="vehicle.is_demo" class="demo-note">Véhicule, prix et transaction de démonstration.</p></div></aside></div></div></template>

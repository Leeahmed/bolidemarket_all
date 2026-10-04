<script setup>
import { computed, onMounted } from 'vue'
import { auth } from '../stores/auth'
import { favorites, loadFavorites } from '../stores/favorites'
import { appConfig } from '../stores/appConfig'
import { useResource } from '../composables/useResource'
import { commerceService } from '../services/commerceService'
import { get } from '../services/api'
import CommerceRecord from '../components/CommerceRecord.vue'
import VehicleCard from '../components/VehicleCard.vue'
import FormError from '../components/FormError.vue'
import VehicleSilhouette from '../components/VehicleSilhouette.vue'
import AppIcon from '../components/AppIcon.vue'
const { data, state, error, load } = useResource()
const upcoming = computed(() => (data.value?.reservations || []).filter(r => ['pending','confirmed','active'].includes(r.status) && new Date(r.ends_at) > new Date()).sort((a,b) => new Date(a.starts_at) - new Date(b.starts_at)))
const sales = computed(() => (data.value?.orders || []).filter(r => r.kind === 'sale'))
const nearLabel = computed(() => auth.user?.city?.name ? 'Près de ' + auth.user.city.name : auth.user?.country?.name ? 'À découvrir en ' + auth.user.country.name : 'Suggestions pour vous')
async function all(kind, signal) { let page = 1, result, rows = []; do { result = await commerceService.list(kind, { per_page: 100, page }, signal); rows.push(...result.data); page++ } while (page <= result.meta.last_page); return rows }
function reload() { load(async signal => {
  const [reservations, orders, suggestions] = await Promise.all([all('reservations', signal), all('orders', signal), get('/vehicles', { country_code: auth.user?.country_code || appConfig.default_country, city_id: auth.user?.city_id, status: 'available', per_page: 3 }, signal), loadFavorites(true)])
  return { reservations, orders, suggestions: suggestions.data }
}) }
onMounted(reload)
</script>
<template><div class="account-welcome"><div><p class="eyebrow">VOTRE ROUTE COMMENCE ICI</p><h1>Bonjour, {{ auth.user?.first_name }} <span aria-hidden="true">👋</span></h1><p>Prêt pour votre prochain trajet ?</p><RouterLink class="button" to="/vehicles">Explorer le marché <AppIcon /></RouterLink></div><VehicleSilhouette /></div><p v-if="auth.user?.email_verification_required" class="notice">Vérifiez votre e-mail pour réserver ou acheter. <RouterLink to="/account/profile">Mon profil →</RouterLink></p><p v-if="state === 'loading'" role="status">Chargement de votre espace…</p><template v-else-if="state === 'error'"><FormError :error="error" /><button class="button" @click="reload">Réessayer</button></template><template v-else-if="data"><div class="account-stats"><RouterLink v-for="[path, icon, count, title] in [['favorites','heart',favorites.items.length,'Favoris'],['reservations','calendar',data.reservations.length,'Réservations'],['orders','car',sales.length,'Achats']]" :key="path" :to="'/account/' + path"><AppIcon :name="icon" /><strong>{{ count }}</strong><span>{{ title }} <span aria-hidden="true">↗</span></span></RouterLink></div><section class="account-section"><div class="section-heading"><h2>Mes prochaines réservations</h2><RouterLink to="/account/reservations">Tout voir →</RouterLink></div><CommerceRecord v-for="record in upcoming.slice(0,2)" :key="record.id" :record="record" kind="reservations" /><p v-if="!upcoming.length" class="account-empty">Aucun trajet programmé. <RouterLink to="/vehicles?listing_type=rental">Trouvez votre prochaine escapade →</RouterLink></p></section><section class="account-section"><div class="section-heading"><h2>Mes favoris récents</h2><RouterLink to="/account/favorites">Tout voir →</RouterLink></div><div class="vehicle-grid account-favorites"><VehicleCard v-for="vehicle in favorites.items.slice(0,3)" :key="vehicle.id" :vehicle="vehicle" /></div><p v-if="!favorites.items.length" class="account-empty">Un coup de cœur ? Enregistrez vos véhicules préférés pour les retrouver ici.</p></section><section class="account-section"><div class="section-heading"><h2>Mes achats récents</h2><RouterLink to="/account/orders">Tout voir →</RouterLink></div><CommerceRecord v-for="record in sales.slice(0,2)" :key="record.id" :record="record" kind="orders" /><p v-if="!sales.length" class="account-empty">Votre prochain bolide vous attend. <RouterLink to="/vehicles?listing_type=sale">Voir les offres →</RouterLink></p></section><section class="account-section"><div class="section-heading"><h2>{{ nearLabel }}</h2><RouterLink to="/vehicles">Explorer →</RouterLink></div><div class="vehicle-grid account-favorites"><VehicleCard v-for="vehicle in data.suggestions" :key="vehicle.id" :vehicle="vehicle" /></div><p v-if="!data.suggestions.length" class="account-empty">Aucune offre dans votre localisation pour le moment. Explorez les autres marchés.</p></section></template></template>

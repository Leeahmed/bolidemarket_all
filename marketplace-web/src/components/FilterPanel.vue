<script setup>
import { computed, ref, watch } from 'vue'
import { useReferences } from '../composables/useReferences'
import { filterKeys, cleanQuery, majorToMinor, minorToMajor } from '../utils/filters'
import { fuelLabel, transmissionLabel } from '../utils/format'
import LocationFields from './LocationFields.vue'
const props = defineProps({ query: { type: Object, required: true } })
const emit = defineEmits(['apply', 'reset'])
const { references, state, load } = useReferences()
const draft = ref({}), minimum = ref(''), maximum = ref(''), error = ref('')
const unit = computed(() => Number(references.currencies.find(x => x.code === draft.value.currency)?.minor_unit || 0))
function sync() { draft.value = { ...Object.fromEntries(filterKeys.map(key => [key, ''])), ...cleanQuery(props.query) }; minimum.value = minorToMajor(draft.value.min_price, unit.value); maximum.value = minorToMajor(draft.value.max_price, unit.value); error.value = '' }
watch(() => props.query, sync, { immediate: true, deep: true })
watch(() => references.currencies, sync)
const models = computed(() => { const brand = references.brands.find(x => x.name === draft.value.brand); return references.models.filter(x => x.brand_id === brand?.id) })
function apply() {
  error.value = ''
  try {
    if ((minimum.value !== '' || maximum.value !== '') && (!draft.value.currency || !draft.value.listing_type)) throw new Error('Choisissez une offre et une devise pour filtrer les prix.')
    const min = majorToMinor(minimum.value, unit.value), max = majorToMinor(maximum.value, unit.value)
    if (min && max && BigInt(min) > BigInt(max)) throw new Error('Le prix maximum doit être supérieur au minimum.')
    if (draft.value.year_min && draft.value.year_max && Number(draft.value.year_min) > Number(draft.value.year_max)) throw new Error('Vérifiez les bornes des années.')
    let sort = draft.value.sort
    if (sort?.startsWith('price') && (!draft.value.currency || !draft.value.listing_type)) sort = ''
    emit('apply', cleanQuery({ ...draft.value, min_price: min, max_price: max, sort, page: '' }))
  } catch (reason) { error.value = reason.message }
}
</script>
<template><form class="filter-form" aria-label="Filtres du catalogue" @submit.prevent="apply"><div class="filter-heading"><h2>Affiner la recherche</h2><button type="button" class="text-button" @click="$emit('reset')">Tout effacer</button></div><p v-if="state === 'error'" role="alert">Options indisponibles. <button type="button" @click="load">Réessayer</button></p><fieldset><legend>Offre</legend><select v-model="draft.listing_type" aria-label="Offre"><option value="">Acheter & louer</option><option value="sale">Acheter</option><option value="rental">Louer</option></select></fieldset><LocationFields v-model="draft" /><fieldset><legend>Votre véhicule</legend><label>Marque<select v-model="draft.brand" @change="draft.model = ''"><option value="">Toutes les marques</option><option v-for="item in references.brands" :key="item.id" :value="item.name">{{ item.name }}</option></select></label><label>Modèle<select v-model="draft.model" :disabled="!draft.brand"><option value="">Tous les modèles</option><option v-for="item in models" :key="item.id" :value="item.name">{{ item.name }}</option></select></label><label>Catégorie<select v-model="draft.category"><option value="">Toutes les catégories</option><option v-for="item in references.categories" :key="item.id" :value="item.slug">{{ item.label }}</option></select></label><label>Condition<select v-model="draft.condition"><option value="">Toutes</option><option value="new">Neuf</option><option value="used">Occasion</option></select></label></fieldset><fieldset><legend>{{ draft.listing_type === 'rental' ? 'Budget par jour' : 'Budget' }}</legend><label>Devise<select v-model="draft.currency" @change="minimum = ''; maximum = ''"><option value="">Toutes les devises</option><option v-for="item in references.currencies" :key="item.code" :value="item.code">{{ item.code }}</option></select></label><div class="field-pair"><label>Prix minimum<input v-model="minimum" inputmode="decimal" placeholder="Minimum" /></label><label>Prix maximum<input v-model="maximum" inputmode="decimal" placeholder="Maximum" /></label></div><small>Montants dans la devise choisie, sans conversion.</small></fieldset><fieldset><legend>Caractéristiques</legend><div class="field-pair"><label>Année minimum<input v-model="draft.year_min" type="number" min="1886" max="2100" placeholder="De" /></label><label>Année maximum<input v-model="draft.year_max" type="number" min="1886" max="2100" placeholder="À" /></label></div><label>Carburant<select v-model="draft.fuel_type"><option value="">Tous</option><option v-for="(label, value) in fuelLabel" :key="value" :value="value">{{ label }}</option></select></label><label>Transmission<select v-model="draft.transmission"><option value="">Toutes</option><option v-for="(label, value) in transmissionLabel" :key="value" :value="value">{{ label }}</option></select></label><label class="check-label"><input v-model="draft.is_certified" type="checkbox" true-value="1" false-value="" />Véhicules certifiés uniquement</label><label class="check-label"><input v-model="draft.status" type="checkbox" true-value="available" false-value="" />Disponibles uniquement</label></fieldset><p v-if="error" class="form-error" role="alert">{{ error }}</p><div class="filter-actions"><button class="button" type="submit">Appliquer les filtres</button><button type="button" class="text-button" @click="$emit('reset')">Réinitialiser</button></div></form></template>

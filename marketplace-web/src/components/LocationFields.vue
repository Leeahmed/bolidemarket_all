<script setup>
import { computed, ref } from 'vue'
import { useReferences } from '../composables/useReferences'
import { locationService } from '../services/locationService'
import { locationText } from '../utils/filters'
import AppIcon from './AppIcon.vue'
const props = defineProps({ modelValue: { type: Object, required: true } })
const emit = defineEmits(['update:modelValue'])
const { references } = useReferences()
const busy = ref(false), message = ref('')
const cities = computed(() => references.cities.filter(x => !props.modelValue.country_code || x.country_code === props.modelValue.country_code))
const districts = computed(() => references.districts.filter(x => x.city_id === props.modelValue.city_id))
function set(key, value) {
  const next = { ...props.modelValue, [key]: value, latitude: '', longitude: '', radius: '' }
  if (next.sort === 'distance') next.sort = ''
  if (key === 'country_code') { next.city_id = ''; next.district_id = ''; next.market = value ? '' : 'all' }
  if (key === 'city_id') { next.district_id = ''; next.country_code = references.cities.find(x => x.id === value)?.country_code || next.country_code }
  emit('update:modelValue', next)
}
async function locate() {
  busy.value = true; message.value = ''
  try { const coordinates = await locationService.locate(); emit('update:modelValue', { ...props.modelValue, ...coordinates, country_code: '', city_id: '', district_id: '', location_mode: 'rank', sort: 'distance' }); message.value = 'Position obtenue. Validez pour afficher les véhicules proches.' }
  catch (error) { message.value = error.message } finally { busy.value = false }
}
</script>
<template><fieldset class="location-fields"><legend>Localisation</legend><p class="current-location"><AppIcon name="pin" />{{ locationText(modelValue, references) }}</p><button class="button secondary locate" type="button" :disabled="busy" @click="locate">{{ busy ? 'Localisation…' : 'Utiliser ma position' }}</button><p v-if="message" class="help" role="status">{{ message }}</p><label>Pays<select :value="modelValue.country_code || ''" @change="set('country_code', $event.target.value)"><option value="">Tous les pays</option><option v-for="item in references.countries" :key="item.code" :value="item.code">{{ item.name }}</option></select></label><label>Ville<select :value="modelValue.city_id || ''" @change="set('city_id', $event.target.value)"><option value="">Toutes les villes</option><option v-for="item in cities" :key="item.id" :value="item.id">{{ item.name }}</option></select></label><label>Commune / quartier<select :value="modelValue.district_id || ''" :disabled="!modelValue.city_id" @change="set('district_id', $event.target.value)"><option value="">Toutes les communes</option><option v-for="item in districts" :key="item.id" :value="item.id">{{ item.name }}</option></select></label><label v-if="!modelValue.latitude">Usage de la localisation<select :value="modelValue.location_mode || 'filter'" @change="emit('update:modelValue', { ...modelValue, location_mode: $event.target.value })"><option value="filter">Uniquement dans ce lieu</option><option value="rank">Privilégier ce lieu et ses environs</option></select></label></fieldset></template>

<script setup>
import { computed } from 'vue'
import { useReferences } from '../composables/useReferences'
const props = defineProps({ modelValue: Object, required: Boolean })
const emit = defineEmits(['update:modelValue'])
const { references } = useReferences()
const cities = computed(() => references.cities.filter(c => c.country_code === props.modelValue.country_code))
const districts = computed(() => references.districts.filter(d => String(d.city_id) === String(props.modelValue.city_id)))
function city(value) { emit('update:modelValue', { ...props.modelValue, city_id: value, district_id: '' }) }
</script>
<template><div class="form-pair"><label>Ville<select aria-label="Ville" :value="modelValue.city_id || ''" @change="city($event.target.value)" :required="required"><option value="">Choisir une ville</option><option v-for="c in cities" :key="c.id" :value="c.id">{{ c.name }}</option></select></label><label>Commune / quartier<select aria-label="Commune / quartier" :value="modelValue.district_id || ''" :disabled="!districts.length" @change="emit('update:modelValue', { ...modelValue, district_id: $event.target.value })"><option value="">Non renseigné</option><option v-for="d in districts" :key="d.id" :value="d.id">{{ d.name }}</option></select></label></div></template>

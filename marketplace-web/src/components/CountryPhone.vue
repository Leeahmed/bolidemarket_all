<script setup>
import { computed, ref, watch } from 'vue'
import { getCountryCallingCode, parsePhoneNumberFromString } from 'libphonenumber-js/max'
import { useReferences } from '../composables/useReferences'
import CountryFlag from './CountryFlag.vue'
const props = defineProps({ country: String, modelValue: String })
const emit = defineEmits(['update:country', 'update:modelValue'])
const { references, state, error, load } = useReferences()
const search = ref(''), national = ref(''), touched = ref(false)
const flag = code => [...code].map(c => String.fromCodePoint(c.charCodeAt(0) + 127397)).join('')
const prefix = computed(() => { try { return '+' + getCountryCallingCode(props.country) } catch { return '' } })
const countries = computed(() => references.countries.filter(c => (c.name + c.code + c.phone_code).toLocaleLowerCase().includes(search.value.toLocaleLowerCase())))
const parsed = computed(() => { try { return parsePhoneNumberFromString(national.value, props.country) } catch { return null } })
const valid = computed(() => parsed.value?.isValid() && parsed.value?.country === props.country)
watch(() => props.modelValue, value => {
  const p = value ? parsePhoneNumberFromString(value, props.country) : null
  if (p?.number !== parsed.value?.number) national.value = p?.nationalNumber || value || ''
}, { immediate: true })
function input(value) { national.value = value; touched.value = true; emit('update:modelValue', parsed.value?.number || prefix.value + value.replace(/\D/g, '')) }
function changeCountry(value) { emit('update:country', value); national.value = ''; emit('update:modelValue', ''); touched.value = false; search.value = '' }
</script>
<template><div class="country-phone"><label>Rechercher un pays<input v-model="search" type="search" placeholder="Nom, code ou indicatif" autocomplete="off" /></label><label>Pays<span class="country-select"><CountryFlag :code="country" /><select aria-label="Pays" :value="country" name="country_code" required @change="changeCountry($event.target.value)"><option value="" disabled>Choisir un pays</option><option v-for="c in countries" :key="c.code" :value="c.code">{{ flag(c.code) }} {{ c.name }} ({{ c.phone_code }})</option></select></span></label><p v-if="state === 'error'" class="help">{{ error }} <button type="button" @click="load">Réessayer</button></p><label>Téléphone<span class="phone-control"><span class="phone-prefix">{{ prefix }}</span><input :value="national" @input="input($event.target.value)" type="tel" aria-label="Téléphone" name="phone" autocomplete="tel-national" placeholder="Numéro national" required :aria-invalid="touched && !valid" /></span></label><p v-if="touched && national && !valid" class="help" role="status">Saisissez un numéro valide pour le pays choisi.</p></div></template>

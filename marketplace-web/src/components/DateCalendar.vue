<script setup>
import { computed, ref, watch } from 'vue'
import { dateInZone, addDays, rangeAvailable } from '../utils/commerce'
const props = defineProps({ availability: { type: Object, required: true }, start: String, end: String })
const emit = defineEmits(['update:start', 'update:end'])
const today = computed(() => dateInZone(Date.now(), props.availability.timezone))
const month = ref(today.value.slice(0, 7) + '-01'), choosing = ref('start')
watch(() => props.availability.timezone, () => { month.value = today.value.slice(0, 7) + '-01' })
const monthTitle = computed(() => new Intl.DateTimeFormat('fr-FR', { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(month.value)))
const limit = computed(() => dateInZone(props.availability.to, props.availability.timezone))
const cells = computed(() => {
  const first = new Date(month.value + 'T12:00:00Z'), offset = (first.getUTCDay() + 6) % 7
  const count = new Date(Date.UTC(first.getUTCFullYear(), first.getUTCMonth() + 1, 0)).getUTCDate()
  return [...Array(offset).fill(null), ...Array.from({ length: count }, (_, i) => addDays(month.value, i))]
})
function disabled(day) {
  if (!day || day < today.value || day > limit.value) return true
  return choosing.value === 'end' && props.start ? !rangeAvailable(props.start, day, props.availability) : !rangeAvailable(day, addDays(day, 1), props.availability)
}
function select(day) {
  if (disabled(day)) return
  if (choosing.value === 'start') { emit('update:start', day); emit('update:end', ''); choosing.value = 'end' }
  else { emit('update:end', day); choosing.value = 'start' }
}
function move(amount) {
  const date = new Date(month.value + 'T12:00:00Z'); date.setUTCMonth(date.getUTCMonth() + amount)
  month.value = date.toISOString().slice(0, 10)
}
function fullDate(day) { return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'full', timeZone: 'UTC' }).format(new Date(day + 'T12:00:00Z')) }
</script>
<template><div class="date-calendar"><div class="date-choice"><button type="button" :class="{ current: choosing === 'start' }" @click="choosing = 'start'"><small>Départ</small><strong>{{ start ? fullDate(start) : 'Choisir une date' }}</strong></button><button type="button" :class="{ current: choosing === 'end' }" :disabled="!start" @click="choosing = 'end'"><small>Retour</small><strong>{{ end ? fullDate(end) : 'Choisir une date' }}</strong></button></div><div class="calendar-heading"><button type="button" class="icon-button" aria-label="Mois précédent" :disabled="month <= today.slice(0, 7) + '-01'" @click="move(-1)">←</button><h3 aria-live="polite">{{ monthTitle }}</h3><button type="button" class="icon-button" aria-label="Mois suivant" :disabled="month.slice(0, 7) >= limit.slice(0, 7)" @click="move(1)">→</button></div><p class="help" aria-live="polite">Sélectionnez votre {{ choosing === 'start' ? 'départ' : 'retour' }}. Fuseau : {{ availability.timezone }}.</p><div class="calendar-week" aria-hidden="true"><span v-for="day in ['Lu', 'Ma', 'Me', 'Je', 'Ve', 'Sa', 'Di']" :key="day">{{ day }}</span></div><div class="calendar-days" role="group" aria-label="Dates de location"><template v-for="(day, i) in cells" :key="day || i"><button v-if="day" type="button" :data-date="day" :disabled="disabled(day)" :aria-label="fullDate(day)" :aria-pressed="day === start || day === end" :class="{ selected: day === start || day === end, within: start && end && day > start && day < end }" @click="select(day)">{{ Number(day.slice(-2)) }}</button><span v-else /></template></div><p class="help calendar-legend">Dates grisées : indisponibles pour votre sélection. Le jour de retour est exclu de la location.</p></div></template>


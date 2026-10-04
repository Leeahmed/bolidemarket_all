<script setup>
import { computed, ref, watch } from 'vue'
import { vehicleMedia } from '../utils/demoMedia'
import SafeImage from './SafeImage.vue'
import AppIcon from './AppIcon.vue'
const props = defineProps({ vehicle: { type: Object, required: true } })
const index = ref(0), dialog = ref(null)
const pictures = computed(() => { const media = vehicleMedia(props.vehicle); const photos = props.vehicle.images?.filter(x => !x.is_placeholder) || []; return photos.length ? photos.map(x => ({ src: x.url, alt: x.alt_text || props.vehicle.title, illustration: false })) : [{ ...media, alt: `${props.vehicle.brand.name} ${props.vehicle.model.name}` }] })
watch(() => props.vehicle.id, () => { index.value = 0 })
function step(delta) { index.value = (index.value + delta + pictures.value.length) % pictures.value.length }
</script>
<template><section class="gallery" aria-label="Photos du véhicule"><button class="gallery-main" aria-label="Agrandir la photo" @click="dialog.showModal()"><SafeImage :src="pictures[index].src" :alt="pictures[index].alt" eager /><span class="gallery-count">{{ index + 1 }} / {{ pictures.length }} · Agrandir</span><span v-if="pictures[index].illustration" class="photo-note">Illustration de démonstration</span></button><div v-if="pictures.length > 1" class="gallery-navigation"><button class="icon-button" aria-label="Photo précédente" @click="step(-1)">←</button><button v-for="(picture, n) in pictures" :key="n" class="thumbnail" :aria-label="`Photo ${n + 1}`" :aria-pressed="index === n" @click="index = n"><SafeImage :src="picture.src" alt="" /></button><button class="icon-button" aria-label="Photo suivante" @click="step(1)">→</button></div><dialog ref="dialog" class="lightbox" aria-label="Photo agrandie" @keydown.left.prevent="step(-1)" @keydown.right.prevent="step(1)"><button class="icon-button lightbox-close" aria-label="Fermer la photo" @click="dialog.close()"><AppIcon name="close" /></button><SafeImage :src="pictures[index].src" :alt="pictures[index].alt" eager /><div v-if="pictures.length > 1" class="lightbox-controls"><button class="button secondary" aria-label="Photo précédente" @click="step(-1)">←</button><span>{{ index + 1 }} / {{ pictures.length }}</span><button class="button secondary" aria-label="Photo suivante" @click="step(1)">→</button></div></dialog></section></template>

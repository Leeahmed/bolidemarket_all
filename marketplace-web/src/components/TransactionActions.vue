<script setup>
import { useRouter, useRoute } from 'vue-router'
import { auth } from '../stores/auth'
import { canTransact } from '../utils/commerce'
const props = defineProps({ vehicle: Object, type: String })
const router = useRouter(), route = useRoute()
function go() {
  if (!auth.user) return router.push({ path: '/login', query: { redirect: route.fullPath } })
  router.push('/vehicles/' + props.vehicle.slug + '/' + props.type)
}
</script>
<template><button class="button full" :disabled="!canTransact(vehicle, type)" @click="go">{{ vehicle.inventory_status === 'sold' ? 'Vendu' : type === 'reserve' ? 'Réserver ce véhicule' : 'Acheter ce véhicule' }}</button></template>


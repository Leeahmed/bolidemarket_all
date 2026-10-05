<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { auth, logout } from '../stores/auth'
import { locationLabel } from '../utils/format'
import { notify } from '../stores/toasts'
import VehicleSilhouette from './VehicleSilhouette.vue'
import UserAvatar from './UserAvatar.vue'
import AppIcon from './AppIcon.vue'
const router = useRouter(), busy = ref(false)
const links = [['/account', 'Vue d’ensemble', 'chart'], ['/account/favorites', 'Favoris', 'heart'], ['/account/reservations', 'Réservations', 'calendar'], ['/account/orders', 'Achats', 'car'], ['/account/price-offers', 'Propositions de prix', 'chart'], ['/account/receipts', 'Reçus', 'chart'], ['/account/profile', 'Profil', 'users']]
async function signOut() { busy.value = true; try { await logout(); await router.replace('/login') } catch(e) { notify(e.message) } finally { busy.value = false } }
</script>
<template><div class="container account-layout"><aside class="account-sidebar"><div class="account-identity"><UserAvatar :user="auth.user" /><div><p class="eyebrow">MON GARAGE PERSONNEL</p><h2>{{ auth.user?.name }}</h2><p class="help"><AppIcon name="pin" />{{ locationLabel(auth.user) }}</p></div></div><nav aria-label="Navigation du compte"><RouterLink v-for="[path, label, icon] in links" :key="path" :to="path" :class="{ current: $route.path === path }"><AppIcon :name="icon" />{{ label }}</RouterLink></nav><div class="marketplace-card"><VehicleSilhouette /><p class="eyebrow">MARKETPLACE</p><h3>Trouver votre prochain bolide</h3><RouterLink class="button" to="/vehicles">Explorer les véhicules <AppIcon /></RouterLink></div><button class="sidebar-logout" :disabled="busy" @click="signOut"><AppIcon name="logout" />{{ busy ? 'Déconnexion…' : 'Se déconnecter' }}</button></aside><section class="account-content"><RouterView /></section></div></template>

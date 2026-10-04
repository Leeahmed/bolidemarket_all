<script setup>
import { ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { auth, logout } from '../stores/auth'
import { notify } from '../stores/toasts'
import UserAvatar from './UserAvatar.vue'
import { roleHome } from '../stores/auth'
import AppIcon from './AppIcon.vue'
const route = useRoute(), router = useRouter(), menu = ref(null), busy = ref(false)
watch(() => route.fullPath, () => { if (menu.value) menu.value.open = false })
async function signOut() {
  if (busy.value) return
  busy.value = true
  try { await logout(); notify('Vous êtes déconnecté.'); router.push('/login') }
  catch (e) { notify(e.message) } finally { busy.value = false }
}
</script>
<template><RouterLink v-if="!auth.user" to="/login" class="icon-button" aria-label="Compte"><AppIcon name="users" /></RouterLink><details v-else ref="menu" class="account-menu" @keydown.esc="menu.open = false"><summary><UserAvatar :user="auth.user" /><span class="account-name">{{ auth.user.first_name }}</span><span aria-hidden="true">⌄</span></summary><nav aria-label="Mon espace client"><RouterLink :to="roleHome()">Mon espace</RouterLink><RouterLink to="/account/favorites">Mes favoris</RouterLink><RouterLink to="/account/reservations">Mes réservations</RouterLink><RouterLink to="/account/orders">Mes achats</RouterLink><button :disabled="busy" @click="signOut"><AppIcon name="logout" />{{ busy ? 'Déconnexion…' : 'Se déconnecter' }}</button></nav></details></template>


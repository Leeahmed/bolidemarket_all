<script setup>
import { computed, reactive, ref, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { auth, login, register, loginDestination } from '../stores/auth'
import { csrfCookie, post } from '../services/api'
import { notify } from '../stores/toasts'
import CountryPhone from '../components/CountryPhone.vue'
import { appConfig } from '../stores/appConfig'
import FormError from '../components/FormError.vue'
const route = useRoute(), router = useRouter()
const mode = computed(() => route.meta.authMode || 'login')
const titles = { login: 'Bon retour parmi nous.', register: 'Votre prochain départ commence ici.', forgot: 'Retrouvons votre accès.', reset: 'Un nouveau départ.' }
const fields = reactive({ first_name: '', last_name: '', email: '', phone: '', country_code: 'CI', password: '', password_confirmation: '' })
const busy = ref(false), error = ref(null), message = ref('')
watch(mode, () => { error.value = null; message.value = ''; fields.password = ''; fields.password_confirmation = ''; fields.email = typeof route.query.email === 'string' ? route.query.email : '' }, { immediate: true })
async function submit() {
  if (busy.value) return
  busy.value = true; error.value = null; message.value = ''
  try {
    if (mode.value === 'login') {
      await login({ email: fields.email, password: fields.password })
      notify('Connexion réussie.')
      await router.replace(loginDestination(route.query.redirect))
    } else if (mode.value === 'register') {
      await register({ ...fields })
      notify('Compte créé. Connectez-vous pour continuer.')
      await router.replace({ path: '/login', query: { ...(route.query.redirect ? { redirect: route.query.redirect } : {}), registered: '1' } })
    } else {
      await csrfCookie()
      const reset = mode.value === 'reset'
      await post(reset ? '/auth/reset-password' : '/auth/forgot-password', reset ? { email: fields.email, password: fields.password, password_confirmation: fields.password_confirmation, token: route.query.token || '' } : { email: fields.email })
      message.value = reset ? 'Mot de passe réinitialisé. Vous pouvez vous connecter.' : 'Si ce compte existe, un lien de réinitialisation sera envoyé.'
    }
    fields.password = ''; fields.password_confirmation = ''
  } catch (e) {
    error.value = e
    await nextTick(); document.querySelector('.auth-form .form-error')?.focus()
  } finally { busy.value = false }
}
function fieldError(name) { return Boolean(error.value?.fields?.[name]) }
</script>
<template><div class="auth-shell" :class="{ 'auth-register': mode === 'register' }"><aside class="auth-scene"><img :src="mode === 'register' ? '/images/auth-register.webp' : '/images/auth-login.webp'" :alt="mode === 'register' ? 'Véhicule dans un showroom premium' : 'Berline en mouvement à l’heure bleue'" /><div class="auth-scene-copy"><p class="eyebrow">VOTRE PROCHAINE DESTINATION</p><h2>{{ mode === 'register' ? 'Votre prochain départ commence ici.' : 'Reprenez la route.' }}</h2><p>Achetez. Louez. Roulez.</p></div><span class="auth-scene-caption">L’univers BolideMarket · Illustration de démonstration</span></aside><section class="auth-form"><RouterLink to="/vehicles" class="text-button">← Retour au marché</RouterLink><p class="eyebrow">ESPACE CLIENT</p><h1>{{ titles[mode] }}</h1><p class="muted">{{ mode === 'register' ? 'Un compte pour retrouver vos favoris et suivre vos demandes.' : mode === 'login' ? 'Retrouvez vos favoris, vos réservations et vos achats.' : 'Nous vous accompagnons pour retrouver votre compte.' }}</p><p v-if="route.query.registered && mode === 'login'" role="status" class="notice">Votre compte est créé. Connectez-vous pour continuer.<span v-if="!appConfig.demo_mode"> Vérifiez votre adresse e-mail avant votre première demande.</span></p><p v-if="auth.error" class="notice">{{ auth.error.message }}</p><FormError :error="error" /><p v-if="message" role="status" class="notice">{{ message }}</p><form @submit.prevent="submit" :aria-busy="busy"><div v-if="mode === 'register'" class="form-pair"><label>Prénom<input v-model="fields.first_name" name="first_name" autocomplete="given-name" required :aria-invalid="fieldError('first_name')" /></label><label>Nom<input v-model="fields.last_name" name="last_name" autocomplete="family-name" required :aria-invalid="fieldError('last_name')" /></label></div><label>Adresse e-mail<input v-model="fields.email" name="email" type="email" autocomplete="email" required :aria-invalid="fieldError('email')" /></label><CountryPhone v-if="mode === 'register'" v-model:country="fields.country_code" v-model="fields.phone" /><label v-if="mode !== 'forgot'">Mot de passe<input v-model="fields.password" name="password" type="password" :autocomplete="mode === 'login' ? 'current-password' : 'new-password'" required :minlength="mode === 'login' ? undefined : 12" :aria-invalid="fieldError('password')" /></label><p v-if="mode === 'register' || mode === 'reset'" class="help">Au moins 12 caractères, avec majuscule, minuscule, chiffre et symbole.</p><label v-if="mode === 'register' || mode === 'reset'">Confirmer le mot de passe<input v-model="fields.password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required :aria-invalid="fieldError('password_confirmation')" /></label><RouterLink v-if="mode === 'login'" to="/forgot-password" class="auth-forgot">Mot de passe oublié ?</RouterLink><button class="button full" :disabled="busy" type="submit">{{ busy ? 'Veuillez patienter…' : { login: 'Se connecter', register: 'Créer mon compte', forgot: 'Recevoir un lien', reset: 'Enregistrer le mot de passe' }[mode] }} <span aria-hidden="true">→</span></button></form><p class="auth-switch">{{ mode === 'login' ? 'Pas encore de compte ?' : 'Déjà un compte ?' }} <RouterLink :to="{ path: mode === 'login' ? '/register' : '/login', query: route.query.redirect ? { redirect: route.query.redirect } : {} }">{{ mode === 'login' ? 'Créer un compte' : 'Se connecter' }}</RouterLink></p><div class="auth-pro"><p>Vous souhaitez vendre ou louer des véhicules ?</p><RouterLink to="/pro/register">Créer un compte professionnel →</RouterLink></div></section></div></template>


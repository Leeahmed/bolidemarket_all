<script setup>
import { computed, ref, onBeforeUnmount } from 'vue'
import { auth, restoreAuth } from '../stores/auth'
import { request, post } from '../services/api'
import { notify } from '../stores/toasts'
import FormError from '../components/FormError.vue'
import UserAvatar from '../components/UserAvatar.vue'
import CountryPhone from '../components/CountryPhone.vue'
import ProfileLocation from '../components/ProfileLocation.vue'
import { useReferences } from '../composables/useReferences'
const { references } = useReferences()
const fields = ref(Object.fromEntries(['first_name', 'last_name', 'phone', 'country_code', 'city_id', 'district_id'].map(k => [k, auth.user?.[k] || ''])))
const currency = computed(() => references.countries.find(c => c.code === fields.value.country_code))
const busy = ref(false), error = ref(null), file = ref(null), preview = ref('')
function country(code) { fields.value.country_code = code; fields.value.city_id = ''; fields.value.district_id = '' }
function photo(event) {
  error.value = null
  const next = event.target.files[0]
  if (!next) return
  if (!['image/jpeg','image/png','image/webp'].includes(next.type) || next.size > 3 * 1024 * 1024) { error.value = new Error('Choisissez une image JPG, PNG ou WebP de 3 Mo maximum.'); return }
  if (preview.value) URL.revokeObjectURL(preview.value)
  file.value = next; preview.value = URL.createObjectURL(next)
}
async function savePhoto() {
  busy.value = true; error.value = null
  try { const form = new FormData(); form.append('avatar', file.value); auth.user = (await post('/me/avatar', form)).data; file.value = null; URL.revokeObjectURL(preview.value); preview.value = ''; notify('Photo de profil enregistrée.') }
  catch(e) { error.value = e } finally { busy.value = false }
}
async function save() {
  busy.value = true; error.value = null
  try { auth.user = (await request('/me/profile', { method: 'PATCH', body: { ...fields.value, city_id: fields.value.city_id || null, district_id: fields.value.district_id || null } })).data; notify('Profil mis à jour.') }
  catch(e) { error.value = e } finally { busy.value = false }
}
async function verify() { try { await post('/auth/email/verification-notification'); notify('Lien de vérification demandé.') } catch(e) { error.value = e } }
onBeforeUnmount(() => { if (preview.value) URL.revokeObjectURL(preview.value) })
</script>
<template><p class="eyebrow">VOS INFORMATIONS</p><h1>Mon profil</h1><p class="page-lead">Un profil à votre image, pour des trajets qui vous ressemblent.</p><section class="content-panel profile-panel"><div class="profile-photo"><UserAvatar :user="auth.user" :src="preview" /><div><label class="text-button">Changer ma photo<input type="file" accept="image/jpeg,image/png,image/webp" @change="photo" /></label><p class="help">JPG, PNG ou WebP · 3 Mo maximum</p><button v-if="file" class="button" :disabled="busy" @click="savePhoto">Enregistrer la photo</button></div></div><FormError :error="error" /><form @submit.prevent="save"><div class="form-pair"><label>Prénom<input v-model="fields.first_name" required autocomplete="given-name" /></label><label>Nom<input v-model="fields.last_name" required autocomplete="family-name" /></label></div><label>Adresse e-mail<input :value="auth.user?.email" type="email" readonly /></label><CountryPhone :country="fields.country_code" @update:country="country" v-model="fields.phone" /><ProfileLocation v-model="fields" /><p class="help" v-if="currency">Devise locale : {{ currency.currency_code }} ({{ currency.currency_symbol }}). Chaque annonce conserve la devise de son pays.</p><button class="button" :disabled="busy">Enregistrer les modifications</button></form><p v-if="auth.user?.demo_mode" class="help">Compte de démonstration</p><div v-if="auth.user?.email_verification_required" class="notice">Vérifiez votre adresse e-mail avant de réserver ou acheter.<div class="action-row"><button class="button" @click="verify">Recevoir le lien de vérification</button><button class="button secondary" @click="restoreAuth(true)">Actualiser</button></div></div></section></template>

<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { login, loginDestination } from '../stores/auth'
import { csrfCookie, post } from '../services/api'
import { useReferences } from '../composables/useReferences'
import { appConfig } from '../stores/appConfig'
import CountryPhone from '../components/CountryPhone.vue'
import ProfileLocation from '../components/ProfileLocation.vue'
import FormError from '../components/FormError.vue'
const router = useRouter(), { references } = useReferences()
const step = ref(1), busy = ref(false), error = ref(null), created = ref(false)
const person = ref({ first_name: '', last_name: '', email: '', country_code: 'CI', phone: '', password: '', password_confirmation: '' })
const shop = ref({ name: '', activity_type: 'both', address: '', country_code: 'CI', city_id: '', district_id: '', timezone: 'Africa/Abidjan' })
const images = ref({ logo: null, cover: null })
const currency = computed(() => references.countries.find(c => c.code === shop.value.country_code)?.currency_code)
const steps = ['Votre compte', 'Votre entreprise', 'Votre identité', 'Confirmation']
function country() { shop.value.city_id = ''; shop.value.district_id = '' }
function file(type, event) { images.value[type] = event.target.files[0] || null }
async function submit() {
  if (step.value < 4) { step.value++; return }
  busy.value = true; error.value = null
  try {
    const data = new FormData()
    Object.entries(person.value).forEach(([k,v]) => data.append(k,v))
    Object.entries(shop.value).forEach(([k,v]) => { if (v !== '') data.append('shop[' + k + ']',v) })
    Object.entries(images.value).forEach(([k,v]) => { if(v) data.append('shop[' + k + ']',v) })
    await csrfCookie(); await post('/auth/register-merchant', data); created.value = true
    await login({ email: person.value.email, password: person.value.password }); person.value.password = ''; person.value.password_confirmation = ''
    await router.replace(loginDestination())
  } catch(e) { error.value = e } finally { busy.value = false }
}
</script>
<template><div class="pro-onboarding container"><aside class="pro-intro"><p class="eyebrow">BOLIDEMARKET PRO</p><h1>Votre savoir-faire.<br />Une nouvelle portée.</h1><p>Donnez une vitrine à vos véhicules et accueillez vos prochains clients.</p><img src="/images/auth-register.webp" alt="Showroom premium, illustration" /><p class="help">Achetez. Louez. Roulez.</p></aside><section class="pro-form"><RouterLink to="/login" class="text-button">Déjà professionnel ? Se connecter →</RouterLink><ol class="pro-steps"><li v-for="(label,i) in steps" :key="label" :aria-current="step === i+1 ? 'step' : undefined"><span>{{ i+1 }}</span>{{ label }}</li></ol><p class="eyebrow">ÉTAPE {{ step }} / 4</p><h2>{{ steps[step - 1] }}</h2><FormError :error="error" /><div v-if="created" class="notice">Votre espace a été créé. <RouterLink to="/pro/login">Connectez-vous pour le retrouver →</RouterLink></div><form v-else @submit.prevent="submit" :aria-busy="busy"><template v-if="step === 1"><div class="form-pair"><label>Prénom<input v-model="person.first_name" required autocomplete="given-name" /></label><label>Nom<input v-model="person.last_name" required autocomplete="family-name" /></label></div><label>Adresse e-mail<input v-model="person.email" type="email" required autocomplete="email" /></label><CountryPhone v-model:country="person.country_code" v-model="person.phone" /><label>Mot de passe<input v-model="person.password" type="password" minlength="12" required autocomplete="new-password" /></label><p class="help">12 caractères minimum, majuscule, minuscule, chiffre et symbole.</p><label>Confirmation du mot de passe<input v-model="person.password_confirmation" type="password" required autocomplete="new-password" /></label></template><template v-if="step === 2"><label>Nom commercial<input v-model="shop.name" required maxlength="150" /></label><label>Type d’activité<select aria-label="Type d’activité" v-model="shop.activity_type"><option value="sale">Vente</option><option value="rental">Location</option><option value="both">Vente &amp; Location</option></select></label><label>Adresse<input v-model="shop.address" required maxlength="255" autocomplete="street-address" /></label><label>Pays de la boutique<select aria-label="Pays de la boutique" v-model="shop.country_code" required @change="country"><option v-for="c in references.countries" :key="c.code" :value="c.code">{{ c.name }}</option></select></label><ProfileLocation v-model="shop" required /><label>Fuseau horaire de la boutique<input v-model="shop.timezone" required placeholder="Ex. Europe/Paris" /></label><p class="notice">Devise de vos annonces : <strong>{{ currency }}</strong> — déterminée par le pays de la boutique.</p></template><template v-if="step === 3"><p>Personnalisez votre vitrine. Vous pouvez aussi continuer sans images.</p><label>Logo de la boutique<input type="file" accept="image/jpeg,image/png,image/webp" @change="file('logo', $event)" /></label><p class="help" v-if="images.logo">{{ images.logo.name }}</p><label>Photo de couverture<input type="file" accept="image/jpeg,image/png,image/webp" @change="file('cover', $event)" /></label><p class="help" v-if="images.cover">{{ images.cover.name }}</p><p class="help">JPG, PNG ou WebP · 3 Mo par image · 4096 × 4096 px maximum.</p></template><template v-if="step === 4"><dl class="record-details"><div><dt>Votre compte</dt><dd>{{ person.first_name }} {{ person.last_name }}<br />{{ person.email }}<br />{{ person.phone }}</dd></div><div><dt>Boutique</dt><dd>{{ shop.name }}<br />{{ shop.address }}</dd></div><div><dt>Activité</dt><dd>{{ {sale: 'Vente', rental: 'Location', both: 'Vente & Location'}[shop.activity_type] }}</dd></div><div><dt>Marché / devise</dt><dd>{{ references.countries.find(c => c.code === shop.country_code)?.name }} · {{ currency }}</dd></div><div><dt>Identité visuelle</dt><dd>{{ images.logo?.name || 'Sans logo' }}<br />{{ images.cover?.name || 'Sans couverture' }}</dd></div></dl><p class="notice">{{ appConfig.demo_mode ? 'En démonstration, votre compte professionnel sera approuvé automatiquement.' : 'Votre compte professionnel sera soumis à validation avant publication.' }}</p></template><div class="action-row"><button v-if="step > 1" type="button" class="button secondary" :disabled="busy" @click="step--">Retour</button><button class="button" :disabled="busy">{{ busy ? 'Création…' : step === 4 ? 'Créer mon espace professionnel' : 'Continuer →' }}</button></div></form><p class="help">Les boutiques, prix et statistiques de démonstration ne constituent pas des offres réelles.</p></section></div></template>

<script setup>
import { computed, inject, ref } from 'vue'
import { config, marketplaceLink } from '../config'
import { landingKey } from '../composables/useLandingData'
import BrandLogo from './BrandLogo.vue'
const { countries, location, selectedLocation } = inject(landingKey)
const country = computed({ get: () => location.value?.params.country_code || '', set: (code) => { selectedLocation.value = code ? `country-${code}` : '' } })
const currency = ref('XOF')
const dialog = ref(null)
const dialogTitle = ref('')
const dialogContent = ref('')
function info(title, content) { dialogTitle.value = title; dialogContent.value = content; dialog.value?.showModal() }
const legal = {
  Confidentialité: 'Cette version est une démonstration locale. La position n’est demandée qu’après votre action et n’est pas conservée par la landing. Les informations de confidentialité définitives seront publiées avant le lancement commercial.',
  Conditions: 'Les véhicules, boutiques, prix, illustrations et statistiques présentés sont des données de démonstration. Cette landing ne permet pas de conclure une vente, une réservation ou un paiement.',
  Cookies: 'Cette landing n’installe pas de cookies publicitaires ou de suivi. Les préférences sélectionnées ne sont pas enregistrées après fermeture de la page.',
  Contact: 'Les coordonnées officielles de BolideMarket seront publiées au lancement. Pour découvrir un professionnel, consultez sa boutique dans le futur catalogue.',
}
</script>
<template><footer class="app-footer"><div class="container"><div class="footer-grid"><div class="footer-brand"><BrandLogo /><p>Achetez. Louez. Roulez.</p></div><nav aria-label="Marketplace"><h3>Marketplace</h3><a :href="marketplaceLink({ listing_type: 'sale' })">Acheter</a><a :href="marketplaceLink({ listing_type: 'rental' })">Louer</a><a href="#recherche">Marques</a><a href="#professionnels">Professionnels</a></nav><nav aria-label="BolideMarket"><h3>BolideMarket</h3><a href="#comment-ca-marche">À propos</a><a href="#comment-ca-marche">Comment ça marche</a><button @click="info('Contact', legal.Contact)">Contact</button></nav><nav aria-label="Professionnels"><h3>Professionnels</h3><a href="#bolidemarket-pro">BolideMarket Pro</a><a :href="config.merchantUrl">Devenir partenaire</a></nav><nav aria-label="Informations légales"><h3>Légal</h3><button v-for="title in ['Confidentialité', 'Conditions', 'Cookies']" :key="title" @click="info(title, legal[title])">{{ title }}</button></nav><div class="footer-preferences"><label>Pays<select v-model="country"><option value="">Tous les pays</option><option v-for="item in countries" :key="item.code" :value="item.code">{{ item.name }}</option></select></label><label>Langue<select aria-label="Langue de la page"><option>Français</option></select></label><label>Devise<select v-model="currency" @change="info('Devise des annonces', 'Les prix restent affichés dans la devise originale de chaque annonce. Aucune conversion n’est effectuée.')"><option value="XOF">FCFA</option><option value="EUR">EUR</option><option value="CAD">CAD</option></select></label></div></div><div class="footer-bottom"><p>© 2026 BolideMarket. Tous droits réservés.</p><p>Boutiques, données, prix et statistiques de démonstration.</p></div><p class="footer-signoff">Des bolides, plus proches de vous.</p></div><dialog ref="dialog" class="info-dialog" aria-labelledby="dialog-title" @click="event => { if (event.target === dialog) dialog.close() }"><h2 id="dialog-title">{{ dialogTitle }}</h2><p>{{ dialogContent }}</p><button class="button button-primary" @click="dialog.close()">Fermer</button></dialog></footer></template>

# BolideMarket Pro — Phase 6A
Dashboard Vue 3 / Vite / Vue Router connecté à l’API Laravel commune. Direction A, logo Triade MBK et palette officielle conservés. Pas de bibliothèque admin ni de nouveau framework graphique.

## Lancer
```powershell
Copy-Item .env.example .env
npm install
npm run dev
```
Aperçu : http://localhost:5175. API locale : http://127.0.0.1:8000 ; utiliser le script backend-api/start-local.ps1 (dossier temporaire d’upload local). Ne pas relancer un deuxième serveur sur un port occupé. Dans cet environnement, Node est fourni par le runtime Codex et npm via ../.tools/npm/bin/npm-cli.js.

Variables : VITE_API_BASE_URL, VITE_MARKETPLACE_URL, VITE_LANDING_URL. Les hôtes localhost/127.0.0.1 sont alignés sur le navigateur pour partager la session Sanctum. En production, configurer des domaines de même racine, CORS et SANCTUM_STATEFUL_DOMAINS côté API ; servir index.html en fallback des routes SPA.

## Parcours
- /login : connexion professionnelle ; les clients sont informés puis redirigés vers la marketplace. Session HttpOnly et CSRF, aucun bearer token en stockage navigateur.
- /dashboard : statistiques réelles du périmètre sélectionné, six mois de ventes livrées, répartition du parc et listes récentes.
- /vehicles, /vehicles/create, /vehicles/:id, /vehicles/:id/edit : filtres serveur, formulaire commun en cinq étapes, brouillon/publication, disponibilité et suppression confirmées.
- /reservations et /reservations/:id : confirmation/refus/annulation/remise/retour selon transitions API.
- /orders et /orders/:id : ventes uniquement, confirmation/livraison/annulation DEMO.
- /rentals : réservations active/completed, aucune seconde logique de location.
- /clients : clients dérivés des réservations et ventes de la boutique ; une réservation et sa commande rental ne comptent qu’une fois.
- /shop : coordonnées, localisation, horaires, logo et couverture. Accès permanent à la boutique publique.
- /profile : profil et avatar existants ; /settings : accès aux réglages effectifs et explications utiles.

Le sélecteur de boutique apparaît pour les comptes ayant plusieurs boutiques ; toutes les listes et statistiques utilisent son identifiant. Le serveur contrôle les memberships. Les agrégats démo et réels sont séparés selon le drapeau de la boutique ; aucun chiffre de maquette n’est utilisé comme donnée.

## Règles conservées
Total du parc = available + rented + sold + other. Le backend n’a pas d’état d’inventaire reserved ni maintenance séparé : les réservations ont leur calendrier et other comprend la maintenance. La remise d’une location et la livraison d’une vente commandent rented/sold ; ces valeurs ne sont pas assignables via le menu rapide. Publication et inventaire sont indépendants.

Les prix sont saisis en unité usuelle et convertis exactement en unités mineures. La devise provient du pays de la boutique, jamais d’un sélecteur libre. Le pays de la boutique ne peut changer si des véhicules (même supprimés), commandes ou réservations existent.

Photos : JPG/JPEG/PNG/WebP statiques, 5 Mo et 6000 px maximum ; 20 par véhicule. Aperçu, suppression, choix de photo principale ; pas de réorganisation arbitraire, l’API ne l’offre pas. Logo/couverture/avatar : 3 Mo et 4096 px. Nettoyage des métadonnées côté serveur. Publication seulement après upload réussi ; si une étape échoue, la même annonce est réutilisée au nouvel essai.

Confirmation DEMO = paiement simulé côté serveur ; annulation ne rembourse pas le paiement, qui reste une trace historique. Aucun chiffre d’affaires affiché qui pourrait confondre ces traces avec un revenu net. Les actions disponibles reflètent l’état et les dates ; l’API revalide toujours les transitions et conflits.

## Vérifier
```text
npm run lint
npm run test
npm run build
```
Tests de guards, montants/devises, chargement, formulaires create/edit, statut, réservations, ventes, boutique et images. Contrôles navigateur et captures dans qa/. Backend : php artisan test sur bolidemarket_test exclusivement. État final et résultats : ../docs/CURRENT_STATUS.md.

## Hors périmètre
Reverb/WebSocket, notifications temps réel, Flutter, PDF/QR, paiements réels, administration et messagerie. Aucun appel Higgsfield. Les scènes de connexion et photographies réutilisent les assets existants ; voir public/images/SOURCES.md.

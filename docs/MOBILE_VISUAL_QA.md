# MOBILE VISUAL QA — BolideMarket

Outil permanent de contrôle des vrais écrans Flutter. Aucun backend requis en aperçu.

## Activation

Depuis `mobile-client/`, après avoir ajouté `../.tools/flutter/bin` au PATH sur ce poste :

```powershell
flutter pub get
flutter run -d chrome --web-port=5176 --dart-define=VISUAL_DEMO=true
```

Chrome 155 / Flutter 3.47.6 sur ce poste : le nouveau format DDC rencontre un timeout DWDS. Le lancement compatible est :

```powershell
flutter run -d chrome --web-port=5176 --dart-define=VISUAL_DEMO=true --no-web-experimental-hot-reload
```

L’option de compatibilité est temporaire et dépréciée par Flutter ; seule la commande de lancement change, pas l’application. Ouvrir http://localhost:5176/#/dev-preview. Au besoin fermer le précédent processus de preview avant de réutiliser le port.

## Contrôle permanent

Avant de valider une phase mobile, parcourir cette galerie et ses vrais écrans. Vérifier les rendus à 390 × 844, puis 360 × 800 et 430 × 932 ; vérifier aussi un parcours invité et un parcours client. Chaque écran dispose d’un petit bouton de galerie. La modale Filtres et les dialogues restent accessibles.

- [ ] Splash et Onboarding
- [ ] Login et Register
- [ ] Home : Djak, Cocody/Abidjan, Acheter/Louer, annonces, professionnels
- [ ] Marketplace, Recherche et Filters
- [ ] Vehicle sale, rental et sold ; CTA sold désactivés
- [ ] Shop et Favoris
- [ ] Account et Profile
- [ ] Reservation : dates → résumé → paiement → confirmation → reçu
- [ ] Purchase : résumé → remise → paiement → confirmation → reçu
- [ ] Demo payment : aucun paiement réel
- [ ] Confirmation et Receipt : coordonnées, devise et total cohérents
- [ ] Retour galerie et quatre destinations de navigation
- [ ] Aucun débordement ; images/fontes officielles

## Données et limites

Repositories injectés à la racine Riverpod ; aucune condition de démo dispersée dans les écrans. Session locale Djak Kouadou / Cocody, Abidjan / CI / XOF ; six véhicules et quatre boutiques. Boutiques, contacts, prix, confirmations et reçus sont fictifs. Dates relatives au jour courant ; le Peugeot est réservé à J+7–J+10. Utiliser J+1–J+4 pour le parcours libre initial. Les données, favoris et opérations sont en mémoire et se réinitialisent au redémarrage ; Guest/Client conserve les opérations locales de cette session.

Le professionnel est simulé en confirmation immédiate, uniquement dans le repository démo. Le mode normal conserve les confirmations serveur. Kia Sportage SOLD utilise le placeholder officiel faute de photographie Kia validée. Les autres photos proviennent des assets officiels existants.

Sur Web, le PDF QA local est exporté par le navigateur ; Partager utilise ce téléchargement comme repli. Android/iOS conservent le stockage privé et le partage natif. Le renderer CanvasKit, les fontes et les photos sont locaux : pas de CDN requis.

## Séparation production

Activation exclusivement `kDebugMode && VISUAL_DEMO && APP_ENV == development`. Profile, release et staging/production n’activent ni galerie, ni overlay, ni bypass. Avec `VISUAL_DEMO=false` : API Laravel, stockage sécurisé, transactions et Reverb existants. Un client Dio bloquant et un transport temps réel inerte empêchent toute requête API/socket du preview.

```powershell
flutter test
flutter test test/preview_routes_test.dart --dart-define=VISUAL_DEMO=true
flutter analyze
flutter build web
# Un export démo doit explicitement être debug :
flutter build web --debug --dart-define=VISUAL_DEMO=true
```

## Android Emulator

```powershell
flutter devices
flutter emulators
flutter emulators --launch <id>
flutter run -d <device-id> --dart-define=VISUAL_DEMO=true
```

Ce poste : SDK et émulateur Android absents ; aperçu Android NOT AVAILABLE. Aucun accord de licence Google déduit ou accepté. La validation visuelle Web ne remplace pas les essais GPS, galerie, stockage et partage sur appareil.

## Preuves

Captures réelles dans `docs/screenshots/mobile/`. Tests de repositories, séparation release et parcours dans `mobile-client/test/visual_demo_test.dart` et `preview_routes_test.dart`. Preuve de tree shaking release dans `mobile-client/qa/visual-release-guard.json`. Journaux dans `.tools/visual-*.log`. Résultats à jour dans CURRENT_STATUS. Aucune Phase 9 engagée.

## Dernière passe — 10 octobre 2026

- pub get et format : PASS ; analyze : PASS, aucun diagnostic.
- Tests : 95 en mode normal + 5 tests de galerie/parcours avec le flag, soit 100 tests distincts réussis. Les six tests de repositories ont aussi été rejoués avec le flag.
- Galerie : auth, marketplace, filtres, véhicule/boutique, compte/profil et historiques exercés aux trois tailles dans les vrais widgets. Paiement → confirmation → reçu testé pour vente et location.
- build web release : PASS, flag true explicitement demandé ; aucune galerie, identité Djak ou libellé DEBUG dans le bundle compilé.
- Chrome : lancement compatible PASS ; navigateur intégré utilisé pour les captures du même rendu Flutter servi sur 5176. Android : NOT AVAILABLE.

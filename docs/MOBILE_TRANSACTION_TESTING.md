# Transactions mobile — Phase 8B

État au 10 octobre 2026 : **PARTIAL**. Fonctionnalités Dart/Flutter implémentées et vérifiées ; APK et essais Android indisponibles faute de SDK. Flutter 3.47.6 stable, Dart 3.13.5. Aucun backend, client Vue, asset officiel ou brief Hero modifié dans ce lot.

## Résultats exécutés

| Vérification | Résultat | Preuve |
|---|---|---|
| Résolution des dépendances | PASS | .tools/mobile8b-pub.log, pubspec.lock |
| Format Dart | PASS | .tools/mobile8b-format.log |
| flutter analyze | PASS, aucun diagnostic | .tools/mobile8b-analyze.log |
| flutter test | 88 passed / 0 failed | .tools/mobile8b-full-tests.log |
| Captures Flutter | 28 nouvelles captures, 360/390/430/768 px, inspection des vues représentatives | mobile-client/test/goldens/8b-*.png |
| Intégration Dart / API Laravel / Reverb locaux | 22 passed / 0 failed | mobile-client/qa/transactions-api.json, .tools/mobile8b-integration.log |
| PDF Laravel authentifié réel | PASS, signature %PDF-, contenu conservé | mobile-client/qa/mobile8b-receipt.pdf |
| Partage PDF | Pont natif simulé PASS ; appareil **non vérifié** | commerce_test.dart |
| APK debug | **NOT AVAILABLE**, No Android SDK found | .tools/mobile8b-android-build.log |
| iOS | Non exécuté, Windows sans Xcode | — |
| Tests backend | Non réexécutés : aucune source backend modifiée | Dernier résultat phase 7 dans CURRENT_STATUS |

Les captures et widgets ne constituent pas un lancement natif sur appareil. L'arrêt/reprise du socket a été testé réellement ; une panne réseau du téléphone et la feuille de partage Android nécessitent un appareil. Les captures 8B utilisent une horloge fixe au 10 octobre 2026 pour rester reproductibles, des snapshots DEMO et les photos officielles existantes.

## Reproduire la suite

Depuis mobile-client, avec Flutter sur le PATH :

```powershell
flutter pub get
dart format --output=none --set-exit-if-changed lib test tool
flutter analyze
flutter test
flutter build apk --debug --no-pub
```

Tests ciblés : commerce_test.dart, commerce_widget_test.dart, reverb_test.dart, commerce_visual_test.dart. Les 54 tests de 8A restent dans la suite ; 34 contrôles ajoutés en 8B. La suite n'appelle aucun serveur.

Couverture : devis serveur ; dates [départ, retour), jours passés, disponibilité paginée, holds expirés, fuseau IANA/DST ; les quatre enums de paiement DEMO ; aucun montant total autoritaire client ; double clic ; même clé/payload après réponse réseau incertaine ; 409/expiration/prix ; achat avec remise personnelle ; lien achat incompatible ; annulation et relecture ; snapshots/reçus/devise ; téléchargement PDF authentifié et refus des faux PDF ; chemins bornés et purge ; partage sans jeton transmis ; refus du partage d'un ancien compte ; protocole Reverb, raw auth Bearer, ping, déduplication, propriétaire/canal exacts, arrière-plan, reconnexion et dispose ; SOLD et confirmation de réservation via REST.

## Vérification réelle locale effectuée

MySQL, API 8000 et Reverb 8080 existants réutilisés, sans doublons ni reset. Le script tool/transaction_smoke.dart emploie les véritables repositories/transport Dart, un client synthétique DEMO et les comptes professionnels DEMO des boutiques concernées. Jetons révoqués à la fin, jamais enregistrés dans les preuves.

Deux unités dédiées ont été créées depuis des annonces DEMO, avec copies de leurs images :
- véhicule location **32**, réservation **10**, confirmée puis annulée ; dates libérées ; reçu historique conservé ;
- véhicule vente **33**, commande **16**, confirmée puis livrée ; SOLD uniquement lors de la remise ;
- reçu location **BM-RCP-2026-01M4HMQKYEQEBPG56N8P4PWXDX**, PDF Laravel réel ; second reçu de vente dans l'historique du client QA.

Contrôles : devis quatre jours, replay idempotent, réservation visible via l'API Pro, événement privé puis statut HTTP confirmé, PDF invité refusé, reçu inchangé après annulation, achat/remise, paiement DEMO, historique, événements OrderCompleted/VehicleStatusChanged, prix relu, dépublication et 404, REST utilisable sans socket, reconnexion au retour. Le véhicule QA location a ensuite été dépublié ; le véhicule QA vente reste vendu. Annonces d'origine intactes. Aucun paiement réel ni remboursement simulé.

La préparation locale est ignorée par Git : .tools/mobile8b-fixture.php et .tools/mobile8b-fixture.json. Le script d'intégration refuse production et hôtes non loopback. Une nouvelle exécution exige de **nouvelles unités dédiées** disponibles ; ne pas réutiliser ni remettre artificiellement en vente les unités déjà vendues. Ne pas lancer sur une base de production.

Commande locale après préparation des fixtures DEMO :

```powershell
dart run --define=API_BASE_URL=http://127.0.0.1:8000/api/v1 tool/transaction_smoke.dart
```

## Essais Android restant à effectuer

Précondition : accord de licence Google demandé précédemment, SDK/émulateur installé ensuite, ou appareil Android connecté à une installation Flutter déjà équipée. Le JDK portable 21 est disponible. Aucun SDK ni licence accepté automatiquement. Ne pas annoncer DONE avant ces essais.

Configurer API_BASE_URL et les dart-defines Reverb suivant le README mobile. Android Emulator utilise 10.0.2.2 ; appareil physique utilise l'IP LAN. Le serveur Reverb doit être accessible depuis l'appareil ; ne pas lancer un second processus sur un port occupé.

| Scénario | Manipulations et résultat attendu | Validation native |
|---|---|---|
| A — location | Client connecté : dates libres → résumé/devis → DEMO → confirmation pending. Pro : nouvelle réservation visible, confirmation. Mobile : message et statut confirmé par REST, reçu disponible. | À faire |
| B — vente | Client : résumé → contact/mode/date/heure → DEMO. Pro confirme puis livre. Mobile : commande livrée, véhicule SOLD, CTA désactivé, reçu présent. | À faire |
| C — calendrier / conflit | Tester passé, retour avant départ, jour occupé, retour adjacent ; concurrence client/Pro après devis. Serveur refuse 409, dates effacées, calendrier relu. | À faire |
| D — reçu | Ouvrir reçu natif puis télécharger PDF ; partager via feuille Android vers un lecteur PDF, exporter/imprimer si cible OS disponible. Refus réseau/401 lisible ; déconnexion et changement de compte nettoient les fichiers. | À faire |
| E — temps réel / cycle de vie | Mobile et Pro ouverts : confirmation, prix, photo, disponibilité, vente, retrait. Mettre en arrière-plan, perdre/retrouver réseau et arrêter/reprendre Reverb ; REST reste utilisable et actualise à la reprise sans doublon. | À faire |

Vérifier également les trois modes de remise (personnelle, personne mandatée, livraison), adresse obligatoire pour livraison, GPS facultatif sur consentement, fuseau boutique, retour/rafraîchissement et doubles taps sur un appareil lent. Aucun chauffeur affecté ni coût de livraison inventé.

## Contrats conservés

API et règles serveur existantes : [API](API.md), [DATABASE](DATABASE.md), [RECEIPTS](RECEIPTS.md), [REALTIME_TESTING](REALTIME_TESTING.md).
- Réservation créée avec quote_id et Idempotency-Key ; devis valable cinq minutes, hold quinze minutes.
- Achat avec handover requis. expected_price_minor est uniquement une garde optimiste, le serveur fige le prix.
- Montants entiers BigInt/units mineures + devise, aucune conversion ni recalcul des reçus.
- Choix MOBILE_MONEY_DEMO, CARD_DEMO, CASH_DEMO, BANK_TRANSFER_DEMO. Paiement confirmé par action Pro autorisée ; aucune collecte carte/OTP.
- Reçus JSON/PDF privés en Bearer, immuables après annulation. Aucun PDF généré localement.
- Protocole Pusher 7 de Reverb existant ; marketplace public, private-user.{id} autorisé par /broadcasting/auth. Aucun statut directement appliqué depuis les signaux.
- Les références de confirmation sont résolues via la liste personnelle avant accès au détail numérique. /me/orders inclut les locations ; le client filtre les ventes et calcule ses compteurs à partir des pages privées.
- Sans clé Reverb ou avec transport indisponible, le mode REST reste opérationnel ; retour premier plan et rafraîchissement réconcilient les données.

Phase 9 — admin global, QA et polish final — recommandée après validation native et sur nouvelle autorisation. Push, Firebase, chat, paiements réels, mobile Pro et soumission aux stores hors périmètre.

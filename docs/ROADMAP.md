# Feuille de route
Chaque phase est un lot vérifiable, pas une autorisation de tout réaliser. Identité et maquettes gelées ; reproduire les [références](references/README.md), sans exploration créative.

| Phase | Livrable ciblé | Critère de sortie |
|---|---|---|
| 1 — Fondation backend | Laravel, environnement local, MySQL, configuration, migrations identité/professionnels, healthcheck, conventions API | Installation documentée, versions figées, migrations aller/retour et seed minimal sur base de test, aucun secret commité |
| 2 — Auth + rôles | Sanctum SPA/mobile, inscription, vérification e-mail, reset, rôles, memberships et Policies | Sessions/CSRF et tokens testés, logout/révocation, admin non assignable, accès croisé refusé |
| 3 — Marketplace API | Référentiels puis catalogue/boutiques/photos, filtres/localisation, favoris, devis, réservations et commandes ; migrations métier restantes | Contrats documentés/OpenAPI, pagination, prix entiers, conflits vente/location et idempotence testés sur MySQL |
| 4 — Landing web | Vue, landing validée responsive, moteur Acheter/Louer relié au catalogue | Comparaison aux visuels, URL de recherche correcte, mentions démo et © 2026, accessibilité essentielle |
| 5 — Marketplace web | Recherche/détail, comparaison, favoris, compte, demande d'achat et réservation | Parcours client complets sur API, prix vente/location distincts, dates/erreurs/états vides et responsive vérifiés |
| 6 — Dashboard Pro | Parc/boutiques/photos, confirmation et suivi, indicateurs, suivi financier ; adaptateur de paiement après décisions nécessaires | Isolation par professionnel, total = statuts, démo 140 cohérente, paiement simulé explicite ; tests sandbox si prestataire choisi |
| 7 — Realtime | Reverb/canaux privés, notifications, mise à jour des vues | Autorisation des canaux, événements après commit, déduplication, reconnexion et relecture HTTP |
| 8 — Mobile | Flutter conforme à l'aperçu, catalogue/compte/favoris/réservations/commandes | API commune, tokens en stockage sécurisé, permissions de localisation facultatives, parcours sur appareils cibles |
| 9 — Reçus PDF | Génération asynchrone, numérotation, snapshots, remboursement, téléchargement privé | Un reçu par encaissement/remboursement, contenu/total/devise vérifiés, accès autorisé, démo clairement marquée |
| 10 — QA et polish | Parcours complets, régression visuelle, responsive, accessibilité, performance, exploitation | Tests métier et concurrence, sauvegarde/restauration, démos isolées, configuration de production et points métier ouverts résolus |

## Prochain lot autorisable
**Phases 1, 2, 3A, 3B, 4, 5A, 5B.1, 5B.2, UX Polish 1, 6A, 6B et 7 réalisées ; prochaine phase recommandée : 8 — application mobile Flutter client**, uniquement sur nouvelle demande. La numérotation de la table initiale ci-dessus est historique : le client a avancé le realtime en 6B et place maintenant les reçus en phase 7. Reverb, les signaux après commit, les canaux privés et la synchronisation des deux SPA sont livrés ; voir CURRENT_STATUS et REALTIME_TESTING pour les preuves. Flutter, paiements réels, chat, push, QR et administration globale restent différés.

Ordre de reprise : lire CURRENT_STATUS et les contrats DATABASE/API du prochain lot, inspecter le backend existant et réutiliser son authentification, ses rôles et ses Policies. Ne pas réinstaller Laravel ni les dépendances déjà verrouillées ; réutiliser la landing Vue et ne pas démarrer les autres clients avant leur phase.

Les transactions réelles attendent les décisions de paiement, pays, conditions de location/caution/annulation et vérification Pro. Le socle et les simulations peuvent avancer sans inventer ces décisions. Aucun planning calendaire artificiel : clore un lot sur ses preuves de fonctionnement, puis mettre à jour [CURRENT_STATUS](CURRENT_STATUS.md).


## Ajustement transversal du 4 octobre 2026
À la demande du client : cohérence des médias Pro, correction du rebond de page, négociation sur opt-in vendeur avec proposition séparée et collecte privée des modalités de remise à l’achat. Contrats et vérifications dans MASTER_SPEC, API, DATABASE et CURRENT_STATUS. Ce lot n’active pas l’organisation des chauffeurs ni les paiements réels et ne change pas la prochaine phase autorisable.

## Phase 7 — livrée le 5 octobre 2026
Reçus vente/location DEMO immuables, PDF local, lecture/téléchargement/impression privés client et Pro. Tests et rendu visuel vérifiés, voir CURRENT_STATUS et RECEIPTS. Phase 8 Flutter client à engager uniquement sur prochaine demande. Aucun QR, justificatif fiscal ou paiement réel.

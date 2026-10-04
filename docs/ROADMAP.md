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
**Phases 1, 2, 3A, 3B, 4, 5A, 5B.1, 5B.2, UX Polish 1 et 6A réalisées ; prochaine phase recommandée : 6B — Laravel Reverb et synchronisation client/Pro**, uniquement sur nouvelle demande. Le lot 6B reprend le périmètre temps réel de la phase 7 initiale ; il n’est pas commencé. La phase 5A couvre le catalogue Vue, le détail, les boutiques et la localisation. La phase 5B.1 livre uniquement le backend favoris/devis/disponibilité/réservations/commandes, les transitions professionnelles et les paiements DEMO : migration exécutée, seed dédié et suite backend 143 tests passants. La phase 5B.2 livre les interfaces client Vue, sessions, favoris et parcours transactionnels DEMO : 45 tests frontend passants et 42 contrôles responsive. Le dashboard Pro 6A est livré avec parc/photos, opérations DEMO, clients dérivés et boutique. Paiements réels, PDF, realtime et Flutter restent différés.

Ordre de reprise : lire CURRENT_STATUS et les contrats DATABASE/API du prochain lot, inspecter le backend existant et réutiliser son authentification, ses rôles et ses Policies. Ne pas réinstaller Laravel ni les dépendances déjà verrouillées ; réutiliser la landing Vue et ne pas démarrer les autres clients avant leur phase.

Les transactions réelles attendent les décisions de paiement, pays, conditions de location/caution/annulation et vérification Pro. Le socle et les simulations peuvent avancer sans inventer ces décisions. Aucun planning calendaire artificiel : clore un lot sur ses preuves de fonctionnement, puis mettre à jour [CURRENT_STATUS](CURRENT_STATUS.md).


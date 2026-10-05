# BolideMarket
**Achetez. Louez. Roulez.**

Marketplace automobile multi-pays pour acheter ou louer auprès de professionnels. Branding et maquettes définitivement validés ; backend Laravel, authentification et catalogue avec recherche/proximité implémentés localement.

## Démarrage rapide
Lire [AGENTS](AGENTS.md), [l'état courant](docs/CURRENT_STATUS.md) et [le cahier des charges](docs/MASTER_SPEC.md). Installation, comptes démo et tests : [README backend](backend-api/README.md). Landing Vue disponible : [lancement](landing-web/README.md). Marketplace publique Vue disponible : [lancement](marketplace-web/README.md). Backend 5B.1 livré : favoris, réservations et achats avec paiements DEMO uniquement. Espace client Vue 5B.2 livré : sessions, favoris et parcours DEMO. UX / PRODUCT POLISH 1 livré : profil éditable et avatar, mode démo, pays/téléphone/devise, refonte compte et auth, inscription professionnelle. Phase 6A livrée : [dashboard professionnel Vue](merchant-dashboard/README.md). Phase 6B livrée : [Reverb et synchronisation client/Pro](docs/REALTIME_TESTING.md). Phase 7 livrée : [reçus, PDF et impression privés](docs/RECEIPTS.md). Prochaine phase après autorisation : 8 — application mobile Flutter client.

## Workspace
| Dossier | Rôle |
|---|---|
| `docs/` | Spécifications et suivi ; documents courts, reliés entre eux |
| `docs/references/` | Copies des visuels validés, origine et empreintes |
| `backend-api/` | API Laravel commune : authentification, catalogue, recherche, favoris et transactions DEMO disponibles |
| `landing-web/` | Landing Vue 3 disponible, API et animations intégrées |
| `marketplace-web/` | Catalogue, recherche, détail et boutiques Vue disponibles (5A) |
| `merchant-dashboard/` | Dashboard professionnel Vue : parc, photos, opérations DEMO, clients et boutique |
| `mobile-client/` | Futur client Flutter |

Landing, marketplace client et dashboard professionnel sont implémentés ; mobile-client reste non commencé. Le backend utilise PHP XAMPP, Laravel 12, Sanctum et MariaDB via le pilote MySQL. Les anciens livrables et `branding logo/` sont préservés.

## Documents
| Document | Source de vérité pour |
|---|---|
| [MASTER_SPEC](docs/MASTER_SPEC.md) | Produit, acteurs, parcours et invariants |
| [BRAND_GUIDELINES](docs/BRAND_GUIDELINES.md) | Identité et usages du logo |
| [DESIGN_SYSTEM](docs/DESIGN_SYSTEM.md) | Règles UI et motion |
| [ARCHITECTURE](docs/ARCHITECTURE.md) | Composants, responsabilités et choix techniques |
| [DATABASE](docs/DATABASE.md) | Modèle logique, relations et états |
| [API](docs/API.md) | Contrat HTTP et autorisations |
| [ROADMAP](docs/ROADMAP.md) | Dix phases et critères de sortie |
| [CURRENT_STATUS](docs/CURRENT_STATUS.md) | Point de reprise compact |
| [Références](docs/references/README.md) | Visuels officiels et traçabilité |

Les choix notés **proposés** constituent une base de travail, pas une validation métier du client. Aucune phase suivante n'est commencée automatiquement.



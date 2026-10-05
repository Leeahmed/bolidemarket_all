# Contrat API principal
Contrat cible. **Implémenté en phases 1–2 :** `/health`, les routes `/auth/*` décrites ci-dessous, CSRF Sanctum et `/up` hors préfixe. Phases 3A et 3B implémentées selon les contrats ci-dessous. Commerce client de démonstration 5B.1 implémenté selon la section dédiée ; profil éditable et onboarding Pro implémentés dans UX / PRODUCT POLISH 1 ; paiements réels futurs. Préfixe `/api/v1`, JSON UTF-8, HTTPS en production. Voir [ARCHITECTURE](ARCHITECTURE.md) pour Sanctum et [DATABASE](DATABASE.md) pour champs/états.

## Phase 3A — contrat effectivement disponible
**27 routes ajoutées**, en complément des 11 routes auth/health. La recherche 3B ci-dessous est disponible ; les modules transactionnels restent futurs.

| Méthode | Route sous `/api/v1` | Comportement |
|---|---|---|
| GET | `/countries`, `/currencies`, `/cities`, `/districts` | Localisations actives ; villes par `country_code`, communes par `city_id` |
| GET | `/brands`, `/models`, `/categories`, `/features` | Référentiels ; modèles par `brand_id` |
| GET | `/vehicles`, `/vehicles/{slug}` | Liste paginée et détail public ; slugs obligatoires au public |
| GET | `/shops`, `/shops/{slug}` | Liste paginée ; détail avec compteur public et 6 véhicules récents maximum |
| GET | `/shops/{slug}/vehicles` | Catalogue public paginé de la boutique |
| GET / POST | `/merchant/shops` | Liste propre / création pour son organisation propriétaire |
| GET / PUT / DELETE | `/merchant/shops/{shop}` | Détail, édition, soft delete ; identifiant numérique |
| GET / POST | `/merchant/vehicles` | Parc propre / création en brouillon |
| GET / PUT / DELETE | `/merchant/vehicles/{vehicle}` | Détail, édition, soft delete ; identifiant numérique |
| PATCH | `/merchant/vehicles/{vehicle}/status` | Publication et disponibilité séparées |
| POST | `/merchant/vehicles/{vehicle}/images` | Multipart `image`, `alt_text` facultatif |
| DELETE | `/merchant/vehicles/{vehicle}/images/{image}` | Retrait ; véhicule parent vérifié |
| PATCH | `/merchant/vehicles/{vehicle}/images/{image}/primary` | Changement de couverture atomique |

Toutes les routes merchant exigent Sanctum + compte actif + rôle merchant ; Policies et appartenances s'appliquent à chaque ressource. Un administrateur ne contourne pas ces routes métier. Lecture publique : véhicule publié, date de publication atteinte, boutique publiée non supprimée, professionnel approuvé avec propriétaire actif, localisations actives. VIN, plaque et données du compte propriétaire sont exclus des réponses publiques.

Pagination : `page`, `per_page` (défaut 20, maximum 100), enveloppes Laravel Resources `data/meta/links`. Recherche et tri de la liste /vehicles : voir phase 3B. L'annuaire /shops conserve son ordre par ID décroissant. Depuis la phase 5A, /shops/{slug}/vehicles utilise les mêmes filtres et tris validés que /vehicles, dans le périmètre de la boutique publique résolue côté serveur ; ordre par publication décroissante puis ID par défaut. Les images/caractéristiques et relations sont chargées en avance.

**Compléments publics 5A :** `/shops` et `/shops/{slug}` ajoutent `sale_vehicles_count` et `rental_vehicles_count` calculés sur les véhicules publics. Ils permettent d'afficher Vente / Location / Vente & Location sans déduire le type depuis une seule page. Un véhicule à double intention entre dans les deux compteurs : leur somme n'est pas le total du parc. L'annuaire ne prend toujours que page/per_page ; recherche boutique et filtres d'annuaire non implémentés. Aucun endpoint transactionnel ajouté.

Création véhicule : `shop_id`, `vehicle_model_id`, `category_id`, `year`, `condition` (new/used), `fuel` (petrol/diesel/hybrid/electric), `transmission` (manual/automatic), `description` et au moins une intention `is_for_sale` / `is_for_rent`. `sale_price_minor` strictement positif si vente ; `rent_daily_minor` si location ; les deux intentions sont autorisées. Entiers en unités mineures, plafond 99 999 999 999 999 ; devise explicite. Localisation/devise/coordonnées héritées de la boutique si omises à la création. Cohérence pays → ville → commune vérifiée ; coordonnées par paire et bornées. Options : title, trim, mileage_km, engine, horsepower, doors, seats, color, vin, license_plate, feature_ids (30 maximum).

PUT conserve les champs éditables omis ; `feature_ids: []` retire les équipements. Transfert de boutique/propriétaire exclu ; slug et référence restent stables. Certification, mise en avant, marqueur démo, dates de publication et identifiants sont gérés par le serveur.

Statut : `publication_status` draft/published/archived ; `inventory_status` available/other modifiables en 3A. Les états rented/sold restent lisibles mais non assignables via PATCH : ils attendent les futurs parcours transactionnels. `other` couvre notamment maintenance ; aucun état de réservation n'est créé. Une publication exige boutique/professionnel autorisés, prix et image principale. Une offre vendue/louée publiée affiche `is_available_now: false` ; `future_availability: null` ne promet aucune disponibilité future.

Images : PNG/JPEG jusqu'à 5 Mio, dimensions ≤ 6000×6000, maximum 20 par véhicule. Noms UUID serveur, suppression des métadonnées textuelles/EXIF, stockage public `storage/app/public/vehicles/`. Position 0 = image principale, unique par contrainte SQL et verrou véhicule. Retrait de la couverture promeut la suivante ; dernière image d'un véhicule publié non supprimable sans dépublication. SVG uploadé refusé ; seuls les placeholders SVG écrits localement par le seed sont utilisés.

Réponses : prix sous `sale_price` / `rental_daily_price`, `amount_minor` chaîne, devise et minor_unit ; tarif location avec `unit: day`. Images avec URL/position/is_primary/is_placeholder, localisation, équipements et boutique. Démo explicitée par `is_demo` et `demo_notice`. Pas d'horaires JSON libres ni d'upload du logo/cover boutique dans cette phase.

## Conventions communes
- Réponse objet : `data` ; liste : `data`, `meta` (page, per_page, total), `links`. Pagination 20 par défaut, plafond 100.
- IDs chaînes, montants chaînes d'entiers + `currency` ISO + `minor_unit`. Exemple prix : `{"amount_minor":"18500000","currency":"XOF","minor_unit":0}` ; location : unité séparée `day`.
- Dates ISO 8601 avec offset à l'entrée, UTC `Z` en sortie ; début inclus/fin exclue. Le fuseau de boutique accompagne les réservations.
- Mutations : liste blanche de champs ; aucun rôle, total calculé, vendeur ou statut de paiement assignable librement.
- Statuts : 200 lecture/action, 201 création, 202 traitement asynchrone, 204 suppression/déconnexion ; 401 non authentifié, 403 non autorisé, 404 absent ou ressource privée étrangère, 409 conflit, 410 devis/hold expiré, 422 validation, 429 débit.
- Erreurs : `error.code`, `error.message`, `error.fields` optionnel, `request_id`. Codes métier : VEHICLE_UNAVAILABLE, PRICE_CHANGED, INVALID_TRANSITION, QUOTE_EXPIRED, IDEMPOTENCY_CONFLICT. Aucun détail SQL.
- `Idempotency-Key` obligatoire pour réservation, commande et initiation de paiement ; conseillé pour actions de transition. Même clé/même corps → même réponse ; corps différent → 409. Rétention minimale proposée 24 h ; unicité métier et références prestataire protègent au-delà.
- Auth requise sur toute donnée privée, e-mail vérifié avant transaction hors mode démo effectif. Permissions Pro contrôlées par appartenance à chaque requête ; IDs opaques ne remplacent pas cette vérification.

## Authentification et compte
| Méthode / route | Entrée / résultat |
|---|---|
| GET `/sanctum/csrf-cookie` (hors préfixe) | Initialisation CSRF navigateur |
| POST `/auth/register` | first_name, last_name, email, country_code, phone (normalisé E.164), password, password_confirmation ; role facultatif limité à customer ; client uniquement, 201, pas de connexion automatique |
| POST `/auth/login` | email, password, device_name facultatif ; session SPA si origine stateful, sinon jeton API ; session régénérée |
| POST `/auth/logout` | Détruit la session SPA ou révoque uniquement le jeton courant ; 204 |
| GET `/auth/me` | Identité authentifiée via session ou Bearer ; endpoint demandé en phase 2 |
| POST `/auth/tokens` | email, password, device_name ; jeton mobile retourné une fois, durée limitée |
| DELETE `/auth/tokens/current` | Révoque le jeton mobile courant |
| POST `/auth/forgot-password`, `/auth/reset-password` | Flux de réinitialisation, réponse non révélatrice de l'existence d'un compte |
| POST `/auth/email/verification-notification` | Renvoi limité, utilisateur connecté |
| GET `/auth/email/verify/{id}/{hash}` | Lien signé expirant, vérification d'e-mail |
| PATCH `/me/profile` | Édition du profil connecté ; e-mail non modifiable (voir UX / PRODUCT POLISH 1) |

Inscription : mots de passe de 12 caractères minimum et 72 octets maximum sans caractère nul, majuscules/minuscules/chiffres/symboles ; téléphone validé par libphonenumber pour le country_code puis normalisé E.164. `name` calculé, rôles sérialisés customer/merchant/admin avec enum PHP CLIENT/MERCHANT/ADMIN. Jeton API sous `data.user/token/token_type/expires_at`, durée 24 h par défaut ; mot de passe jamais retourné. Identifiants invalides : 422 avec erreur email générique. Limites : login 5/minute par IP+e-mail, 30/minute par IP ; inscription et reset 5/minute par IP. Mails en journal local, aucun service externe. Les transactions exigent un e-mail vérifié hors mode démo effectif ; l'accès à `/auth/me` reste possible avant vérification.

## Phase 3B — recherche effectivement disponible
| GET sous `/api/v1` | Fonction |
|---|---|
| `/vehicles` | Recherche paginée, filtres combinables, classement géographique |
| `/nearby/vehicles` | Mêmes filtres, coordonnées obligatoires, rayon 25 km par défaut, distance croissante stricte |
| `/vehicles/filters` | Marques/catégories présentes dans le catalogue public ; valeurs d'enums, intentions, tris disponibles, rayon maximum |
| `/search/suggestions?q=toy` | Suggestions de modèles issues des véhicules publics, `type/label/slug`, 8 maximum, q de 2 à 120 caractères ; 60 appels/minute/IP |

Les trois nouvelles routes portent le total API à 41. Les options de filtre sont globales, pas des compteurs recalculés selon une recherche courante. Une suggestion est une étiquette marque/modèle et le slug d'un véhicule représentatif ; les étiquettes sont dédupliquées après sélection bornée.

| Paramètre de `/vehicles` et `/nearby/vehicles` | Valeurs / comportement |
|---|---|
| `q` | Texte 1–120 caractères ; chaque mot doit apparaître dans au moins un champ public : titre, marque, modèle, finition, description, boutique, ville ou commune. Recherche partielle LIKE liée par paramètres, `%` et `_` littéraux ; collation MySQL existante |
| `listing_type` | `sale` ou `rental` ; un véhicule à double intention répond aux deux |
| `brand`, `model` | Nom exact du référentiel, insensible à la casse selon la collation ; ex. Toyota / RAV4 |
| `category` | Slug : suv, sedan, city, four_by_four, sport, luxury, utility |
| `condition` | new, used |
| `fuel_type` | petrol, diesel, hybrid, electric |
| `transmission` | manual, automatic |
| `year_min`, `year_max` | Entiers 1886–2100, bornes inclusives et ordonnées |
| `min_price`, `max_price` | Entiers en unités mineures, de 0 à 99 999 999 999 999 ; bornes inclusives et ordonnées. Exigent `listing_type` ET `currency` |
| `currency` | Code actif XOF/EUR/CAD dans la démo ; filtre strict, aucune conversion |
| `country_code` | Code ISO à deux lettres actif. `country_id` est un alias acceptant ce même code, jamais un ID numérique ; alias contradictoires → 422 |
| `city_id`, `district_id` | IDs actifs des référentiels ; cohérence pays/ville/commune vérifiée |
| `location_mode` | `filter` par défaut : les IDs de localisation excluent les autres lieux. `rank` : ces mêmes IDs décrivent l'utilisateur et classent tous les lieux sans les exclure |
| `latitude`, `longitude` | Paire numérique obligatoire ensemble, latitude [-90,90], longitude [-180,180]. 0 est valide |
| `radius` | Kilomètres > 0, maximum 500 ; exige des coordonnées et exclut les distances inconnues |
| `status` | Disponibilité publique : available, rented, sold, other. Ce n'est jamais un filtre de publication |
| `is_featured`, `is_certified` | Booléens de query string 0 ou 1, y compris le filtre false |
| `sort` | newest, price_asc, price_desc, year_desc, mileage_asc, distance ; popular réservé, renvoie 422 avec explication |
| `page`, `per_page` | Pagination existante 20 par défaut, maximum 100 ; les liens conservent les paramètres |

**Prix :** sale filtre/trie `sale_price_minor`, rental filtre/trie `rent_daily_minor`. Les tris price_asc/price_desc exigent aussi intention et devise, sinon 422. Les prix restent sous `sale_price` et `rental_daily_price` avec `amount_minor` chaîne, `currency`, `minor_unit`, `unit: day` pour la location. Le paramètre d'entrée `rental` conserve la représentation historique `offer_types: ["rent"]` en sortie. Aucun renommage cassant de la ressource 3A.

**Classement par défaut :** avec `location_mode=rank`, commune identique > ville identique > pays identique > autres pays, puis distance croissante à niveau égal si GPS fourni. Une commune détermine sa ville et son pays ; une ville détermine son pays. Les seules coordonnées donnent un classement par distance, sans inventer un pays par géocodage inversé. Sans GPS, les distances restent nulles ; le classement manuel fonctionne. Sans contexte, annonces récentes. À égalité : published_at décroissant puis ID décroissant. Les tris explicites remplacent la priorité géographique ; `sort=distance` et nearby ordonnent strictement par distance. Nearby accepte uniquement sort=distance si sort est fourni.

**Distance :** Haversine sphérique en km, rayon terrestre 6371,0088 km ; distance indicative à vol d'oiseau, pas routière. Paire du véhicule utilisée en priorité, sinon paire de la boutique. `distance_km` numérique arrondi à 3 décimales ; null si origine ou coordonnées cibles manquantes. Les distances connues précèdent les inconnues au sein d'un même niveau géographique. Le rayon filtre la distance non arrondie, limite incluse. La formule SQL est définie une fois dans une sous-requête ; tri/filtre réutilisent l'alias. Valeurs liées et colonnes de tri en liste blanche. Configuration centralisée dans `config/search.php`.

Exemples (remplacer les IDs par ceux des référentiels, ils ne sont pas fixes) :
```text
/api/v1/vehicles?q=Toyota+RAV4&listing_type=sale
/api/v1/vehicles?listing_type=rental&currency=XOF&min_price=30000&max_price=50000&sort=price_asc
/api/v1/vehicles?brand=Toyota&category=suv&fuel_type=hybrid
/api/v1/vehicles?location_mode=rank&country_code=CI&city_id={abidjan}&district_id={cocody}&latitude=5.3599&longitude=-4.0083
/api/v1/vehicles?location_mode=rank&country_code=FR&city_id={paris}&latitude=48.8566&longitude=2.3522
/api/v1/vehicles?location_mode=rank&city_id={paris}
/api/v1/nearby/vehicles?latitude=5.3599&longitude=-4.0083&radius=20&listing_type=sale
/api/v1/search/suggestions?q=toy
```

Tous ces accès réutilisent le scope public 3A : brouillons, archives, publications futures, suppressions logiques, boutiques suspendues et professionnels non approuvés exclus. Relations chargées en avance, nombre de requêtes constant avec la taille de page. Sans recherche spécialisée ni index spatial, LIKE et Haversine restent adaptés au jeu de simulation ; mesurer les plans et la charge avant un grand catalogue. Les index existants de publication/date, intentions, modèle/marque, catégorie, devise et localisation ont été inspectés ; aucun index redondant ajouté au petit jeu actuel.

**Depuis 5B.1 :** favoris, calendrier et commerce DEMO sont disponibles selon la section dédiée ci-dessous. Comparaisons et transactions réelles restent futures. Les anciennes propositions offer/brand_id/lat/lng/radius_km n'ont pas été implémentées ; utiliser les noms contractuels ci-dessus.
## Parcours client
| Méthode / route | Contrat |
|---|---|
| GET `/me/favorites` | Liste personnelle paginée |
| PUT / DELETE `/me/favorites/{vehicle}` | Ajout/retrait idempotent |
| POST `/rental-quotes` | vehicle_id, starts_at, ends_at ; devis serveur, jours, total, devise et expires_at ; aucune allocation |
| POST `/reservations` | 5B.1 : quote_id OU véhicule + dates, payment_method DEMO, conditions_version optionnelle demo-v1 ; hold pending, prix serveur ; 201 avec expires_at |
| GET `/me/reservations`, `/me/reservations/{id}` | Réservations personnelles |
| POST `/me/reservations/{id}/cancel` | Annulation selon état/conditions, libération du bloc |
| POST `/orders` | 5B.1 : vehicle_id, payment_method DEMO ; expected_price_minor/currency/conditions_version facultatifs ; valeurs attendues comparées, total serveur, pending + hold |
| GET `/me/orders`, `/me/orders/{id}` | Commandes et état financier personnel |
| POST `/me/orders/{id}/cancel` | Annulation admissible ; une commande rental suit aussi sa réservation |
| POST `/me/orders/{id}/reviews` | rating/comment, acheteur, commande fulfilled, un seul avis |
| POST `/me/orders/{id}/payment-intents` | Moyen autorisé ; montant/compte marchand déterminés serveur ; fournisseur à choisir |
| GET `/me/receipts`, `/me/receipts/{id}` | Reçus personnels émis |
| POST `/me/receipts/{id}/download` | Autorisation puis URL privée temporaire ; 202 si PDF encore en génération |
| GET `/me/notifications` | Pagination et filtre unread |
| PATCH `/me/notifications/{id}/read` | Lecture idempotente, uniquement propriétaire |

Le client ne peut confirmer une réservation, démarrer une location ni solder une commande. Devis périmé ou prix changé : retourner le conflit et demander une nouvelle acceptation du prix, jamais ajuster silencieusement.

## Espace professionnel
| Méthode / route | Contrat / autorisation |
|---|---|
| POST `/merchant/onboarding` | Crée profil pending + appartenance owner ; aucun accès public avant approbation |
| GET / PATCH `/merchant/profile` | Organisation active autorisée ; champs légaux sensibles restreints au owner |
| GET / POST `/merchant/shops` | Boutiques de l'organisation |
| GET / PUT / DELETE `/merchant/shops/{id}` | Détail et modification autorisés ; pas de changement libre de propriétaire |
| GET / POST `/merchant/vehicles` | Parc / création sous une boutique autorisée |
| GET / PUT / DELETE `/merchant/vehicles/{id}` | Édition ou archivage, refus si engagements incompatibles |
| PATCH `/merchant/vehicles/{id}/status` | Contrôle organisation, boutique, prix et photos |
| POST `/merchant/vehicles/{id}/images` | Upload multipart contrôlé |
| PATCH / DELETE `/merchant/vehicles/{id}/images/{image}` | Ordre/couverture ou retrait ; relation vérifiée |
| GET / POST `/merchant/vehicles/{id}/blocks` | Calendrier / immobilisation, verrou du véhicule |
| DELETE `/merchant/vehicles/{id}/blocks/{block}` | Libère un bloc maintenance ; pas une réservation via ce raccourci |
| GET `/merchant/reservations`, `/merchant/reservations/{id}` | Scope professionnel ; filtre boutique/statut/dates |
| POST `/merchant/reservations/{id}/{action}` | Actions explicites confirm, reject, start, complete ; routes déclarées, pas un verbe arbitraire |
| GET `/merchant/orders`, `/merchant/orders/{id}` | Commandes de boutiques autorisées |
| POST `/merchant/orders/{id}/{action}` | confirm, fulfil, cancel ; transitions propres à sale/rental |
| GET `/merchant/dashboard` | Agrégats et série de ventes, filtres communs, as_of |
| GET `/merchant/receipts` | Reçus du professionnel, filtres boutique/période/devise |
| POST `/merchant/receipts/{id}/download` | Même contrôle d'accès et URL temporaire que côté client |

Organisation active fournie par contexte autorisé (paramètre merchant_id si plusieurs appartenances) ; ne jamais sélectionner la première organisation d'un utilisateur sans contrôle. Propriétaire et manager gèrent le parc ; les droits financiers sensibles exigent une règle explicite avant activation.

`/merchant/dashboard` : `inventory.total`, `available`, `rented`, `sold`, `other` ; total = somme des 4. `sales_series` précise cumul/période, distinct de l'instantané du parc. Recettes groupées par devise. Le périmètre shop/merchant est commun aux listes ; les filtres temporels s'appliquent à la série commerciale sans transformer le parc courant en fausse photographie historique.

## Administration, paiements et temps réel
- GET `/admin/merchants` et POST `/admin/merchants/{id}/approve` ou `/reject` : administrateur, motif audité.
- PATCH `/admin/reviews/{id}/moderation` : administrateur, statut/motif, aucune réécriture de l'avis.
- POST `/webhooks/payments/{provider}` : fournisseur configuré uniquement, signature/horodatage valides, déduplication, contrôle commande/montant/devise. Pas de session/CSRF navigateur ; authentification par signature du prestataire.
- `/broadcasting/auth` (hors préfixe par défaut) : authentifie les canaux privés, même policy d'appartenance ; aucun canal obtenu par simple connaissance d'un ID.
- Notifications de domaine décrites dans [ARCHITECTURE](ARCHITECTURE.md) ; la réponse HTTP reste exploitable sans WebSocket.

## Vérification future du contrat
Tests prioritaires : accès croisé interdit, rôle admin non assignable, montants XOF exacts, doubles appels idempotents, concurrence vente/location, expiration sans scheduler, transitions invalides, webhook répété, reçus privés et total du parc cohérent. Produire OpenAPI depuis les routes effectivement implémentées en phase 3 ; ce document définit les intentions, pas des endpoints déjà disponibles.




## Phase 5B.1 — contrat effectivement disponible
**PAIEMENTS = DEMO UNIQUEMENT.** 25 routes ajoutées ; 66 routes sous /api/v1 au total. Aucun prestataire, aucune donnée bancaire, aucun débit réel. Mutations commerciales refusées hors local/testing et sur un véhicule ou une boutique non marqués is_demo. Le catalogue public et les favoris ne sont pas soumis à cette restriction d'environnement.

### Authentification et identifiants
Favoris/historique/créations : auth:sanctum + compte actif + rôle customer ou merchant (le professionnel peut utiliser son espace personnel). Créations de devis/réservations/achats : e-mail vérifié requis. Routes professionnelles : rôle merchant, appartenance réelle à la boutique ; e-mail vérifié pour les transitions. Pas de contournement admin. Ressource privée étrangère : 404, listes filtrées avant pagination. IDs numériques en entrée, chaînes JSON en sortie ; références publiques BM-RSV / BM-ORD / BM-PAY uniques.

Intégration navigateur 5B.2 : session Sanctum SPA et CSRF existants conservés. CORS autorise également l'en-tête `Idempotency-Key` sur les origines déjà autorisées ; ce complément permet les créations depuis Vue. Aucun endpoint ni règle métier changé.

### Routes livrées
| Méthode | Route | Contrat |
|---|---|---|
| GET | /me/favorites | Véhicules encore publics, VehicleResource, page/per_page |
| PUT / DELETE | /me/favorites/{vehicle} | ID numérique ; ajout unique, retrait idempotent, uniquement son compte |
| POST | /rental-quotes | vehicle_id + start_date/end_date (YYYY-MM-DD, fuseau boutique) OU starts_at/ends_at (ISO8601 avec secondes et offset/Z) ; prix serveur, devis 5 min, aucune allocation |
| POST | /reservations | quote_id OU vehicle_id + même paire de dates ; payment_method obligatoire ; clé Idempotency-Key obligatoire ; pending + hold 15 min |
| GET | /me/reservations et /me/reservations/{id} | Historique personnel, dates UTC/fuseau, jours, montants, statut, commande/paiement démo éventuels |
| POST | /me/reservations/{id}/cancel | pending, ou confirmed strictement avant starts_at ; libère le bloc, annule la commande liée |
| POST | /orders | vehicle_id, payment_method ; prix/currency attendus facultatifs, jamais des montants faisant autorité ; pending + hold vente sans date de fin, expire après 15 min |
| GET | /me/orders et /me/orders/{id} | Historique personnel, snapshots véhicule/vendeur, montants et paiement démo |
| POST | /me/orders/{id}/cancel | Vente pending/confirmed uniquement ; une commande rental suit l'annulation de sa réservation |
| GET | /vehicles/{slug}/availability | Public ; from/to YYYY-MM-DD facultatifs, fenêtre par défaut maintenant → un an, max un an ; page/per_page (max 100) des intervalles indisponibles |
| GET | /merchant/reservations et /merchant/reservations/{id} | Périmètre des appartenances, filtres shop_id/status, pagination |
| POST | /merchant/reservations/{id}/confirm, /reject, /start, /complete, /cancel | Transitions explicites, autorisation renouvelée sous verrou |
| GET | /merchant/orders et /merchant/orders/{id} | Même isolation et pagination, filtres shop_id/status |
| POST | /merchant/orders/{id}/confirm, /fulfil, /cancel | Uniquement ventes ; location pilotée par sa réservation |

Les autres routes proposées dans les tableaux précédents (reviews, payment-intents, receipts, notifications, blocs maintenance CRUD, etc.) restent futures. Aucun PATCH libre de statut transactionnel ni endpoint de confirmation de paiement client.

### Calculs, dates et idempotence
Bornes [début, fin[ : deux périodes adjacentes sont compatibles. Début >= aujourd'hui dans le fuseau boutique, fin strictement après début ; durée maximale de démonstration 365 jours. Jours facturés = ceil(secondes UTC / 86400), min 1 ; cette convention démo inclut les changements d'heure et reste à valider commercialement. Tarif relu serveur, subtotal = jours × tarif, fees = 0 en démo, total = subtotal, devise figée. Plafond 99 999 999 999 999 unités mineures. Aucun montant client accepté comme source de vérité.

Avec quote_id : propriétaire, expiration, consommation unique, version véhicule, tarif et devise revérifiés. Le flux direct crée le même devis interne puis le consomme dans la transaction. conditions_version, si envoyée, doit être demo-v1. Pas de caution, taxe ou frais inventés.

POST /reservations et /orders : en-tête Idempotency-Key de 8–80 caractères alphanumériques/tiret/underscore, scoped par utilisateur et opération. Même clé et entrée validée : 200 avec la ressource actuelle ; première création : 201. Même clé avec autre contenu : 409 IDEMPOTENCY_CONFLICT. Enregistrement et ressource écrits atomiquement ; conserver une nouvelle clé pour une nouvelle intention après expiration/annulation. Pas de purge automatique dans ce MVP.

### Cycle démonstration
Réservation pending bloque 15 min. Le professionnel confirme avant expiration : bloc durable, commande rental confirmed et paiement paid DEMO atomiques. Le véhicule reste AVAILABLE pour une période future. start n'est autorisé qu'au cours de la période et passe l'inventaire à rented ; complete libère le bloc, clôture réservation/commande et remet available. Les états active/completed/cancelled ne permettent pas l'annulation client. reject est réservé au pending.

Vente pending bloque toute allocation à partir de maintenant. confirm crée le paiement paid DEMO et rend le bloc durable ; fulfil marque commande fulfilled et véhicule sold. Un second achat et toute location concurrente sont refusés. Le client ne peut confirmer ni livrer sa propre commande. Paiement et exécution restent distincts.

Annulation confirmed avant départ et annulation vente confirmed sont permises par la politique **demo-v1 uniquement**. Un paiement démo déjà paid conserve son historique ; aucun remboursement, financier réel ou simulé, n'est créé. Les ressources le précisent. Les opérations validées sont auditées dans commerce_events, sans données bancaires ni payload arbitraire.

### Disponibilité et erreurs
Chaque mutation critique verrouille d'abord la ligne véhicule, puis les allocations, dans une transaction InnoDB avec retries de deadlock. Sale et rental partagent ce verrou. Expiration prise en compte à la lecture et dans les requêtes de disponibilité même sans scheduler ; nettoyage opportuniste sous verrou. Statuts expirés effectifs également utilisés par les filtres Pro. Les modifications/suppressions de véhicules et boutiques avec engagements sont refusées ; une location active dépassant sa fin prévue reste protégée jusqu'à clôture.

availability renvoie uniquement starts_at, ends_at, expires_at pour les intervalles (aucun ID de réservation/client, aucun montant), inventory_status, timezone et is_available_in_window. Celui-ci indique absence de bloc dans la fenêtre et inventaire available ; il ne promet pas une allocation après cette lecture. Confirmation/création revérifient toujours sous verrou.

409 : VEHICLE_UNAVAILABLE, INVALID_TRANSITION, QUOTE_EXPIRED, PRICE_CHANGED, IDEMPOTENCY_CONFLICT, AMOUNT_LIMIT, PAYMENT_REQUIRED. 422 : dates/formats/méthode démo/clé invalides. 403 : mauvais rôle, compte désactivé, e-mail non vérifié ou simulation interdite. Enveloppe error/code/message/fields/request_id existante préservée.

## UX / PRODUCT POLISH 1 — implémenté
| Méthode et route | Contrat |
|---|---|
| GET `/app-config` | Public : `data.demo_mode` effectif, `default_country` (CI en démo, sinon null). Aucun secret. |
| POST `/auth/register` | Ajout obligatoire `country_code` ISO actif. Téléphone national ou international validé pour le pays et normalisé E.164. Même mot de passe sécurisé ; 201 sans connexion automatique. |
| PATCH `/me/profile` | Sanctum + actif + customer/merchant. Prénom, nom, phone, country_code requis ; city_id/district_id facultatifs et cohérents. Email, role, user_id, avatar_path, disabled_at et email_verified_at interdits. Modifie uniquement le compte authentifié ; UserResource. |
| POST `/me/avatar` | Même autorisation, multipart `avatar`, JPG/JPEG/PNG/WebP statique, 3 Mo, dimensions max 4096×4096. Métadonnées supprimées ; clé serveur UUID sous avatars/{user}. Remplacement transactionnel et nettoyage après commit ; UserResource. Limite 20/min. |
| POST `/auth/register-merchant` | Public, même limitation inscription. Champs personne de register + objet/multipart `shop` : name, activity_type sale/rental/both, address, country_code, city_id, district_id facultatif, timezone IANA. `shop.logo`/`shop.cover` optionnels (mêmes limites image). Champs shop en liste blanche, aucune devise imposable, aucun rôle/approbation fourni. 201 UserResource, sans connexion automatique. |

UserResource ajoute country_code, country/city/district (résumés), city_id/district_id, avatar_url, demo_mode, email_verification_required et merchant (organisation propriétaire, approbation, boutiques id/name/country_code/currency_code/status). Ces données restent privées sur auth/me/profile ; aucun chemin de stockage exposé. Le frontend redirige customer vers /account, merchant vers /pro et admin vers /admin ; ces deux dernières destinations sont des écrans d’état, pas de nouveaux dashboards.

`DemoMode::enabled()` exige APP_DEMO_MODE=true **et** environnement local/testing/demo. `User::canUseCommerce()` centralise vérification e-mail réelle ou démo effective pour les requêtes achat/location et actions professionnelles existantes. Aucun changement aux transactions métier, paiements ou contrôles d’appartenance. La production ignore le drapeau démo. Hors démo, envoi et validation du lien e-mail restent actifs ; en démo, aucune notification de vérification à l’inscription.

`CurrencyResolver` impose la devise active de countries.currency_code pour la boutique et ses véhicules. currency_code peut être omis à la création ; une valeur contradictoire retourne 422. Pays véhicule = pays boutique. Changement de pays boutique refusé si elle contient des véhicules, même supprimés, afin de préserver la signification des montants. Le verrou boutique protège la résolution pendant une création véhicule. Aucune conversion.

L’URL frontend `/vehicles?market=all` exprime un choix explicite « Tous les pays » ; `market` n’est pas envoyé à l’API. Le frontend applique sinon country_code du profil (ou CI démo) en l’absence d’une localisation explicite. /vehicles et les autres API conservent leur contrat multi-pays.

Le client HTTP refuse une réponse 2xx au JSON illisible : aucun succès fictif ni effacement de session. En local Windows, start-local.ps1 fournit à PHP un répertoire upload temporaire accessible avant le traitement Laravel.

## Phase 6A — dashboard professionnel effectivement disponible
Tous les endpoints merchant exigent Sanctum + compte actif + rôle merchant. La propriété est résolue via merchant_memberships, jamais déduite d’un identifiant envoyé par le client.

| Méthode | Route | Contrat |
|---|---|---|
| GET | /merchant/dashboard?shop_id={id} | Boutique autorisée obligatoire (404 sinon). Snapshot SQL commun : as_of, shop_id, is_demo, vehicles_total, fleet {available,rented,sold,other}, pending_reservations non expirées, reservations_total, orders_total (ventes), activity (6 mois UTC, ventes fulfilled par fulfilled_at), recent_vehicles/recent_reservations/recent_orders (5 maximum chacun). Aucun total monétaire multidevise. |
| GET | /merchant/clients?shop_id={id}&q=…&page=1 | Pagination 20 ; clients dérivés des réservations + commandes sale. Rental order non recomptée. Champs limités à id, name, email, phone, operations_count, last_activity (date de dernière demande). Aucun profil complet ni secret. Pagination Laravel racine data/total/current_page/last_page. |
| POST | /merchant/shops/{id}/media | Multipart kind=logo ou cover + image JPG/PNG/WebP statique, 3 Mo / 4096 px. Autorisation avant upload, fichier nettoyé et nom UUID serveur ; remplacement transactionnel, ancien fichier supprimé après commit. Retour ShopResource. |

Compléments non cassants aux contrats existants :
- GET /merchant/vehicles : shop_id, q (titre/référence), status (available/rented/sold/other), listing_type (sale/rental), category_id, brand_id, page/per_page. Filtres cumulés avant pagination, memberships imposées.
- GET /merchant/reservations et /merchant/orders : q (référence, véhicule ou client) ; kind sale/rental pour commandes ; rentals=1 pour réserver la vue aux réservations active/completed. Filtres statut et expiration existants conservés.
- Les listes Pro et agrégats ne mélangent pas données démo et réelles : is_demo correspond au drapeau de la boutique. Total du parc hors suppressions logiques, quatre catégories exclusives. Aucune catégorie réservée inventée.
- ReservationResource/OrderResource ajoutent customer {id,name,email,phone} uniquement dans les réponses merchant autorisées, avec relation chargée ; jamais dans les réponses client/publiques.
- VehicleResource expose created_at. POST images accepte aussi WebP statique (même validation, limite 5 Mo / 6000 px).
- ShopResource expose logo_url, cover_url, opening_hours. PUT /merchant/shops/{id} accepte opening_hours : null ou tableau de 7 objets {day:1..7 unique,closed:boolean,opens:HH:mm|null,closes:HH:mm|null}, fermeture après ouverture le même jour pour un jour ouvert ; aucune clé arbitraire.
- Mise à jour de contact/nom/description/horaires permise malgré des transactions ; toute modification de localisation/fuseau/publication/devise garde le verrou commun et les contrôles d’engagements. Pays bloqué après véhicules (soft-deleted inclus), commandes ou réservations.
- Profil Pro réutilise PATCH /me/profile et POST /me/avatar ; aucun endpoint concurrent.

Transitions, paiements DEMO, devises, disponibilité, Policies et verrouillage véhicule des phases précédentes conservés. Pas de temps réel, PDF, paiement réel ou nouvel endpoint de transaction.

## Phase 6B — signaux Reverb
POST /api/v1/broadcasting/auth, authentifié Sanctum + active, corps {socket_id, channel_name}. Sessions SPA : CSRF identique aux autres mutations. Retour 200 signature du protocole ; 401 invité, 403 canal non autorisé. channel_name utilise private-merchant.{merchantId} ou private-user.{userId} ; le préfixe private- est géré par Echo. merchant exige rôle merchant et appartenance ; user exige identité exacte. Aucun endpoint métier remplacé.

Les événements portent {event_id UUID, type, occurred_at UTC, data}. type = nom explicite :
- VehicleCreated, VehicleUpdated, VehicleStatusChanged, VehiclePublished, VehicleUnpublished, VehicleImageUpdated, VehicleAvailabilityChanged.
- ReservationCreated, ReservationConfirmed, ReservationCancelled, ReservationRejected, ReservationStarted, ReservationCompleted, ReservationExpired ; ReservationUpdated réservé à une autre mise à jour autorisée.
- OrderCreated, OrderConfirmed, OrderCancelled, OrderCompleted ; OrderUpdated réservé à une autre mise à jour autorisée. OrderCompleted correspond au statut existant fulfilled.

Payload véhicule : vehicle_id, slug, shop_id, version, changed_fields (noms publics autorisés). Canal merchant propriétaire ; marketplace uniquement si publié/visible, ou retrait d’une ancienne annonce publique. Aucun objet réservation/commande sur marketplace.
Payload privé réservation/commande : id, shop_id, vehicle_id, status, version. Diffusion au merchant propriétaire et au user concerné uniquement. L’API fournit ensuite les détails autorisés. version est celle de la ressource, pas un compteur du transport ; event_id sert à la déduplication.

Diffusion après commit externe ; rollback = aucun message. Messages éphémères, pas de garantie de replay. Une panne Reverb n’annule pas le succès REST ; les vues relisent leurs ressources après reconnexion. Configuration et scénarios : [REALTIME_TESTING](REALTIME_TESTING.md).


## Complément — négociation et remise (4 octobre 2026)
Toutes les routes ci-dessous sont sous `/api/v1`, session active Sanctum, CSRF pour écritures ; opérations commerciales soumises à canUseCommerce. Pro limité aux memberships.

| Méthode | Route | Contrat |
|---|---|---|
| POST | /price-offers | Idempotency-Key requis. Body vehicle_id, amount_minor (entier positif ≤ 99 999 999 999 999 et inférieur au prix public), currency (devise exacte annonce). Retour PriceOfferResource 201, replay 200. |
| GET | /me/price-offers | Propositions du client courant ; vehicle_id facultatif, pagination page/per_page. |
| GET | /me/price-offers/{id} | Proposition du client courant ; accès croisé 404. |
| GET | /merchant/price-offers | Propositions reçues dans les boutiques autorisées ; shop_id facultatif, pagination. |
| POST | /merchant/price-offers/{id}/respond | decision = accepted ou rejected. Pending non expirée uniquement, propriété vérifiée. |

PriceOfferResource : id, vehicle_id, shop_id, snapshots vehicle/shop, status pending/accepted/rejected/expired/consumed, amount_minor/asking_price_minor chaînes entières, currency/minor_unit, expires_at/created_at, is_demo. customer.name uniquement dans la réponse Pro autorisée. Proposition valable 24 h puis, si acceptée, 24 h supplémentaires ; aucune réservation à l’acceptation.

VehicleResource expose negotiation_enabled. Création/édition Pro accepte ce booléen dans les champs éditoriaux existants ; ignoré/remis à false en location seule, défaut false. Le formulaire Pro le présente à l’étape Offre.

**Évolution du contrat POST /orders :** handover obligatoire pour les nouvelles ventes, price_offer_id facultatif. Champs autorisés handover :
- mode : self, proxy ou delivery ;
- scheduled_local : `YYYY-MM-DDTHH:mm`, futur dans le fuseau de la boutique (fourni par le serveur, jamais par le client) ;
- contact_name (120), contact_phone (40 en entrée, normalisé E.164), requis ;
- city (120) et address (500), requis pour delivery ;
- latitude [-90,90] et longitude [-180,180], paire facultative ;
- notes facultatives, maximum 1000.

OrderResource privée ajoute price_offer_id et handover (scheduled_at UTC + timezone, nom/téléphone, adresse/position si livraison, notes). Les anciennes commandes et celles de location peuvent garder null. Le serveur calcule le total depuis l’offre acceptée ; expected_price_minor reste un contrôle, jamais une source de prix. Une proposition consommée ne se réutilise pas après annulation. Le replay de la même demande reste idempotent.

Conflits 409 : NEGOTIATION_DISABLED, OFFER_EXISTS, OFFER_STALE (annonce modifiée avant acceptation), OFFER_UNAVAILABLE ; disponibilité/prix et erreurs 422 usuelles conservés. L’offre ne doit appartenir ni à un autre client ni à un autre véhicule. L’achat reste soumis aux mêmes verrous/disponibilités/paiements DEMO.

Événements privés PriceOfferCreated/Accepted/Rejected/Consumed après commit, canaux merchant.{id} et user.{id}, payload minimal id/shop_id/vehicle_id/status ; aucun contact/GPS public. REST demeure la source de vérité.

Profil Pro : logo/cover de ShopResource réutilisés ; changement photo par POST /merchant/shops/{id}/media kind=logo, couverture par la page boutique kind=cover. POST /me/avatar reste le contrat de l’avatar personnel et le repli pour un compte sans boutique.


## Phase 7 — reçus privés et PDF
Base /api/v1. Auth Sanctum + compte actif, rôle customer/merchant côté me ; rôle merchant + membership côté professionnel.

| Méthode | Route | Résultat |
|---|---|---|
| GET | /me/receipts | Reçus de l’utilisateur, pagination page/per_page, filtre type sale/rental facultatif |
| GET | /me/receipts/{reference} | ReceiptResource historique, sinon 404 |
| GET | /me/receipts/{reference}/pdf | application/pdf, attachment par défaut |
| GET | /merchant/receipts | Reçus des boutiques autorisées, shop_id/type facultatifs, pagination |
| GET | /merchant/receipts/{reference} | Même document, scope professionnel puis Policy |
| GET | /merchant/receipts/{reference}/pdf | Même PDF privé et autorisation |

PDF : disposition=inline ou attachment exclusivement (autre valeur → 422), Content-Disposition avec BolideMarket_{reference}.pdf, Cache-Control private,no-store, nosniff. Limite 30/minute. Aucun endpoint public ou de mutation.

ReceiptResource : reference, type, order_id, shop_id, currency/minor_unit, subtotal_minor/fees_minor/total_minor (chaînes entières), payment_method/payment_status, is_demo, issued_at ISO UTC, buyer/seller/vehicle/transaction (snapshots en liste blanche), demo_notice. Transaction inclut références commande/paiement, paid_at, conditions_version, price_offer_id, timezone ; location ajoute référence/ID réservation, starts_at/ends_at, days, daily_price_minor.

OrderResource et ReservationResource ajoutent receipt_reference nullable quand disponible, pour les CTA confirmation/détail. Reçu émis après paiement DEMO réussi pendant la confirmation professionnelle. Ni pending, ni simple acceptation de proposition de prix ne produisent un reçu. Le reçu conserve le prix négocié réellement payé. Annulation sans remboursement : reçu historique inchangé.

Détails de cycle, compatibilité historique et impression : [RECEIPTS](RECEIPTS.md).

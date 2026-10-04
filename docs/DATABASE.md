# Modèle de données logique
Modèle MVP à traduire en migrations par phases. Socle effectivement migré : users, password_reset_tokens, sessions, merchant_profiles, merchant_memberships, personal_access_tokens et tables Laravel cache/jobs. Bases locales : `bolidemarket` et `bolidemarket_test`, MariaDB XAMPP 10.4.32 via pilote mysql. Les tables de la phase 3A décrites ci-dessous sont également migrées ; les tables de commerce DEMO 5B.1 sont maintenant migrées selon la section dédiée ; remboursements, reçus et événements PSP restent futurs. Les règles produit sont dans [MASTER_SPEC](MASTER_SPEC.md).

## Phase 3A — schéma effectivement ajouté
Deux migrations additives, aucune reconstruction de la base de développement :
- `2026_09_25_180000_create_marketplace_references` : currencies, countries, cities, districts, brands, vehicle_models, categories, features.
- `2026_09_25_180100_create_shops_and_vehicles` : shops, vehicles, vehicle_images, feature_vehicle.

Conventions conservées : `countries.code` CHAR(2) et `currencies.code` CHAR(3) restent les clés primaires documentées ; les autres entités ont un ID numérique. Le client emploie donc `country_code`, pas une seconde colonne country_id. `active` est le nom canonique du drapeau de localisation.

Ajouts aux tables logiques ci-dessous :
- countries : currency_code FK, currency_symbol, phone_code ; currencies : minor_unit et active.
- cities : slug, active, UNIQUE(country_code,slug) ; districts : city_id FK, name, slug, latitude/longitude DECIMAL(10,7) nullable, active, UNIQUE(city_id,slug).
- shops : email/phone publics de contact, district_id nullable, logo_path/cover_path nullable, is_demo et deleted_at. Propriété par merchant_profile → propriétaire utilisateur ; gestion selon memberships. Statut draft/published/suspended conservé, sans booléen is_active concurrent.
- vehicles : reference et slug uniques/stables, trim, condition new/used, engine, horsepower, doors, seats, color, vin, license_plate, country_code/city_id/district_id, is_featured/is_certified/is_demo et published_at. VIN/plaque privés. Prix BIGINT UNSIGNED ; coordonnées DECIMAL(10,7). SoftDeletes.
- features : id, slug UNIQUE, label ; feature_vehicle : PK(vehicle_id,feature_id), FKs. Pas de JSON arbitraire d'équipements.
- vehicle_images : storage_key UNIQUE, alt_text, position, is_placeholder, timestamps ; UNIQUE(vehicle_id,position). `is_primary` est calculé depuis position=0, pas dupliqué en base.

Deux booléens d'offre conservés : vente et location peuvent coexister. Enums dédiés pour intentions, publication, inventaire, condition, carburant, transmission et catégories. Électrique reste une motorisation ; catégories SUV/berline/citadine/4×4/sport/luxe/utilitaire. Brouillon et indisponibilité ne sont pas le même état ; other englobe maintenance, reserved attend le module réservation.

Index : FKs/localisation, slug/reference, shop_id+inventory_status+deleted_at, publication_status+published_at, is_for_sale+is_for_rent, marque/modèle ; contraintes uniques pour références, images et pivots. Réorganisation des images et mutations du véhicule sous transaction avec verrou du véhicule ; suppression logique préserve images et historique. Suppression physique d'une image après commit.

Seed explicite : `php artisan db:seed --class=MarketplaceDemoSeeder`. Ce seed appelle LocationSeeder, CatalogReferenceSeeder et le seed des comptes démo existant. Jeu borné et idempotent : 5 pays, 12 villes, 9 communes d'Abidjan, 3 devises, 18 modèles, 8 équipements, 5 boutiques (4 CI/1 FR), 18 véhicules, 18 placeholders locaux. 17 véhicules publiés, 1 brouillon. Les prix repères RAV4/208/C300/Kangoo sont conservés ; les chiffres du parc fictif de 140 dans les maquettes ne sont pas présentés comme le total de ce jeu de développement.

Le seed marketplace refuse tout environnement autre que local/testing. Il approuve uniquement les profils des comptes `.demo` qu'il cible explicitement pour rendre les boutiques de démonstration consultables ; verified_at reste inchangé. Il ne réinitialise pas les mots de passe ni les véhicules déjà présents. Localisations sans coordonnées fiables : NULL, aucune distance inventée. Photos : SVG originaux locaux « PHOTO À VENIR / DONNÉES DE DÉMONSTRATION », aucun téléchargement externe.

## Enrichissement géographique de démonstration — phase 3B
`php artisan db:seed --class=GeographicDemoSeeder` appelle le seed marketplace puis complète uniquement les données marquées démo. Total : 21 véhicules (20 publiés/1 brouillon), 8 boutiques, 21 placeholders. Ajout de trois boutiques/véhicules à Lyon, Dakar et Bruxelles ; Paris et cinq communes d'Abidjan représentés. Coordonnées approximatives de simulation, non vérifiées et jamais présentées comme des adresses réelles. Le seed ne remplace pas des coordonnées déjà renseignées, ne restaure pas les lignes supprimées et refuse la production. Les référentiels de lieux conservent leurs coordonnées nulles.

Aucune migration 3B : inspection des index SQL effectifs de vehicles et vehicle_models ; index FKs/localisation/devise et publication/date déjà présents. Pas d'index ajouté sans besoin établi sur ce jeu réduit. Recherche LIKE et distance calculée peuvent nécessiter des optimisations après mesure sur un catalogue plus grand. `distance_km` est calculé à la lecture, non stocké. `country_id` est seulement un alias HTTP du code ISO, pas une nouvelle colonne.

## Conventions
MySQL/InnoDB, clés étrangères et index explicites. Identifiants métier `BIGINT UNSIGNED`, exposés en chaînes JSON ; identifiants non secrets. Dates stockées en UTC, fuseau IANA sur la boutique pour affichage et saisie. `created_at`/`updated_at` partout sauf snapshots immuables et tables techniques particulières.

Montants en unités mineures entières `BIGINT`, devise ISO 4217 obligatoire ; exposer les montants en chaînes numériques JSON pour éviter toute perte de précision. `XOF`, exposant 0 : `18500000` = 18 500 000 FCFA. Aucune conversion ou somme entre devises. Prix/montants non négatifs et plafonds métier validés côté serveur. Les enum ci-dessous sont des valeurs contractuelles, indépendantes du choix ENUM SQL ou VARCHAR + contrainte.

## Identité, professionnels, référentiels
| Table | Champs essentiels et contraintes |
|---|---|
| `users` | id, first_name, last_name, email normalisé UNIQUE, password hash, phone, role customer/merchant/admin, email_verified_at, disabled_at, remember_token, country_code/city_id/district_id FK nullable, avatar_path nullable ; name calculé depuis prénom/nom dans l'API, sans colonne dupliquée |
| `merchant_profiles` | id, owner_user_id FK users UNIQUE (un professionnel par propriétaire, MVP), legal_name, display_name, approval_status pending/approved/rejected, verified_at nullable |
| `merchant_memberships` | merchant_id FK, user_id FK, role owner/manager ; UNIQUE(merchant_id,user_id) ; propriétaire cohérent avec owner_user_id |
| `countries` | code CHAR(2) PK, name, active |
| `currencies` | code CHAR(3) PK, minor_unit, active |
| `cities` | id, country_code FK, name, latitude, longitude ; index(country_code,name) |
| `shops` | id, merchant_id FK, slug UNIQUE, name, description, country_code/city_id/currency_code FK, timezone, address, latitude/longitude, status draft/published/suspended |
| `brands` | id, name, slug UNIQUE |
| `vehicle_models` | id, brand_id FK, name ; UNIQUE(brand_id,name) |
| `categories` | id, slug UNIQUE, label ; SUV, berline, citadine, luxe, utilitaire ; électrique reste un carburant/une motorisation, filtrable comme collection |

Une seule devise par véhicule, imposée par le pays de sa boutique via CurrencyResolver, non modifiable arbitrairement ; aucun changement de pays boutique après création de véhicules. Toute validation pays/ville et modèle/marque vérifie leur cohérence. Coordonnées DECIMAL avec bornes géographiques ; deux coordonnées nulles ou deux renseignées.

## Catalogue et disponibilité
| Table | Champs essentiels et contraintes |
|---|---|
| `vehicles` | id, shop_id FK, vehicle_model_id/category_id FK, title, description, year, mileage_km, fuel, transmission, is_for_sale, is_for_rent, sale_price_minor, rent_daily_minor, currency_code FK, publication_status draft/published/archived, inventory_status available/rented/sold/other, latitude/longitude nullable, version, deleted_at |
| `vehicle_images` | id, vehicle_id FK, storage_key UNIQUE, alt_text, position ; UNIQUE(vehicle_id,position), position 0 = couverture |
| `vehicle_blocks` | id, vehicle_id FK, kind hold/rental/sale/maintenance, reservation_id ou order_id nullable FK, starts_at, ends_at nullable, expires_at nullable, released_at nullable, reason |
| `rental_quotes` | id, user_id/vehicle_id FK, starts_at, ends_at, billable_days, daily_price_minor, total_minor, currency_code, vehicle_version, expires_at, consumed_at |

Publication : professionnel approuvé + boutique publiée, au moins une intention d'offre vraie, prix correspondant strictement positif et photo de couverture. Un véhicule représente **une unité physique**, pas un modèle de flotte. Éviter d'effacer son historique : soft delete du catalogue ; suppressions en cascade interdites vers transactions.

`inventory_status` est une partition du parc à l'instant courant ; elle ne remplace pas le calendrier. `rented` provient du démarrage d'une location, `sold` de la livraison d'une vente, retour terminé → available sauf immobilisation. `other` comprend immobilisé/indisponible ; le statut de publication est indépendant. L'API n'autorise pas de PATCH arbitraire vers rented/sold.

Un bloc hold appartient à exactement une réservation ou commande ; rental à une réservation ; sale à une commande ; maintenance n'a aucun de ces liens. Contraintes CHECK et service métier cohérents. La réservation location et sa commande ne créent pas deux blocs pour une même allocation. Bloc vente sans fin ; location avec fin obligatoire. Un bloc released ou une retenue expirée ne bloque plus.

## Transactions commerciales
| Table | Champs essentiels et contraintes |
|---|---|
| `reservations` | id, user_id/vehicle_id/quote_id FK, quote_id UNIQUE, starts_at, ends_at, shop_timezone snapshot, status, expires_at, daily_price_minor, billable_days, total_minor, currency_code, conditions_version, version |
| `orders` | id, user_id/vehicle_id/shop_id FK, reservation_id FK nullable UNIQUE, kind sale/rental, status, expires_at nullable, total_minor, currency_code, vehicle_snapshot JSON, seller_snapshot JSON, conditions_version, confirmed_at, fulfilled_at, cancellation_reason, version |
| `payments` | id, order_id FK, provider, provider_reference nullable, method, amount_minor, currency_code, status initiated/pending/paid/failed/cancelled, paid_at ; UNIQUE(provider,provider_reference) |
| `payment_refunds` | id, payment_id FK, provider_reference nullable UNIQUE par prestataire, amount_minor, status pending/succeeded/failed, reason, completed_at |
| `payment_events` | id, provider, external_event_id, payload_hash, processing_status, processed_at ; UNIQUE(provider,external_event_id) |
| `receipts` | id, payment_id ou refund_id FK, kind payment/refund, number UNIQUE, amount_minor, currency_code, buyer_snapshot/seller_snapshot JSON, storage_key, checksum, issued_at ; une source exactement, UNIQUE sur chaque FK source |

L'ordre rental est créé à confirmation de la réservation, avec ses prix figés. L'ordre sale est créé à la demande d'achat. L'historique commercial est conservé ; politique de durée/anonymisation à déterminer avant lancement. Les reçus sont immuables : un remboursement a son propre reçu, aucun écrasement de l'encaissement initial.

États autorisés :
- Réservation : pending → confirmed/rejected/cancelled/expired ; confirmed → active/cancelled ; active → completed. États finaux non réouvrables.
- Commande : pending → confirmed/cancelled ; confirmed → fulfilled/cancelled selon conditions. Une location active ne s'annule pas comme une réservation ; elle se termine. Expiration d'une vente pending → cancelled avec motif expired.
- Confirmation location crée une commande confirmed ; fin de location → commande fulfilled ; annulation avant départ → commande cancelled. Mise à jour atomique des deux ressources.
- Paiement : initiated → pending/paid/failed/cancelled ; pending → paid/failed/cancelled. Un événement tardif contradictoire exige rapprochement, pas une nouvelle allocation automatique. Remboursement séparé ; somme des remboursements réussis ≤ montant payé, même devise.
- Solde commande = encaissements paid − remboursements succeeded ; exécution et paiement restent distincts. Pas de confirmation de paiement via un PATCH client.

## Verrouillage et calcul des disponibilités
Chaque création, confirmation, démarrage, clôture, annulation ou blocage verrouille **la ligne véhicule** avec `SELECT ... FOR UPDATE`, dans une transaction. Le verrou parent sérialise aussi le cas où aucun bloc n'existe encore ; une simple recherche de chevauchement sans verrou commun est insuffisante. [MySQL : locking reads](https://dev.mysql.com/doc/refman/8.4/en/innodb-locking-reads.html).

Après acquisition : relire véhicule, allocations non libérées et retenues non expirées, vérifier l'état attendu et le devis, puis écrire réservation/commande/bloc ensemble. Chevauchement [start,end[ : `existing.start < requested.end` ET (`existing.end IS NULL` OU `existing.end > requested.start`). Pour vente : intervalle dès maintenant sans fin ; refuser les engagements locatifs présents/futurs incompatibles. Confirmation transforme le hold en bloc durable. Un devis seul ne retient jamais un véhicule.

Durées proposées : devis 5 minutes, retenue pending 15 minutes, configurables et visibles au client ; confirmation impossible après expiration. Les requêtes ignorent déjà les holds expirés, même avant passage du scheduler. Double clic/retry : clé d'idempotence persistée dans la même transaction ; erreur de concurrence explicite, pas de double réservation. Les paiements arrivant après expiration ne réservent rien automatiquement : rapprochement/remboursement à traiter.

Index minimum : vehicles(shop_id,inventory_status,deleted_at), vehicles(publication_status,category_id), models(brand_id), blocks(vehicle_id,starts_at,ends_at), reservations(vehicle_id,status,starts_at), reservations(user_id,created_at), orders(shop_id,status,created_at), payments(order_id,status). Vérifier les plans de requêtes avant d'ajouter des index de recherche géographique.

## Relation client et tables de support
| Table | Champs essentiels et contraintes |
|---|---|
| `favorites` | user_id/vehicle_id FK, created_at ; UNIQUE(user_id,vehicle_id) |
| `reviews` | id, user_id/order_id/shop_id FK, order_id UNIQUE, rating 1–5, comment, moderation_status pending/published/rejected ; auteur = acheteur d'une commande fulfilled |
| `notifications` | id, user_id FK, type, data JSON minimal, read_at ; index(user_id,read_at,created_at) |
| `idempotency_keys` | id, user_id FK, route, key, request_hash, response_status/body, expires_at ; UNIQUE(user_id,route,key) |
| `audit_logs` | id, actor_user_id nullable FK, merchant_id nullable FK, action, target_type/id, redacted_changes JSON, request_id, created_at ; append-only |

Tables Laravel complémentaires selon configuration : password_reset_tokens, sessions, personal_access_tokens, jobs, failed_jobs et cache. Elles sont créées dans la phase où leur mécanisme est activé, pas simulées comme existantes.

## Démonstration et indicateurs
Seed de démonstration isolé en environnement dédié, flag `is_demo` sur organisations/données importées si coexistence nécessaire ; exclusion systématique des agrégats réels. Phase 2 : trois comptes `admin/client/merchant@bolidemarket.demo`, mot de passe local `password` demandé par le client, haché ; seed refusé hors local/testing et sans remise à zéro de comptes existants. Aucun mot de passe fixe utilisable en production.

Agrégats Pro calculés sur le même périmètre shop/merchant et les mêmes données non supprimées : total = available + rented + sold + other. Fournir `as_of` commun ; liste et compteur partagent filtres et requête de base. La liste paginée n'est pas le total. Historique ventes = commandes sale fulfilled, regroupées par fulfilled_at ; le graphique démo cumule Jan–Juin et atteint 84. Seed complet ou fixture explicitement nommée ; ne pas faire croire que trois lignes constituent un parc de 140. Valeurs figées dans [MASTER_SPEC](MASTER_SPEC.md).



## Phase 5B.1 — schéma effectivement ajouté
Migration additive `2026_09_27_010000_create_demo_commerce`, exécutée sur bolidemarket ; tests exclusivement sur bolidemarket_test. Aucune table existante reconstruite. Huit tables nouvelles : favorites, rental_quotes, reservations, orders, vehicle_blocks, payments, idempotency_keys, commerce_events.

- favorites : ID technique, FK utilisateur/véhicule, created_at, UNIQUE(user_id,vehicle_id). Seule table de cette phase à suppression FK en cascade ; historique commercial restrictOnDelete.
- rental_quotes : dates UTC, fuseau boutique, montant/jours/devise/minor_unit, version véhicule, expires_at/consumed_at. Aucun bloc à la création d'un devis.
- reservations : référence unique, shop_id et quote_id unique, dates/fuseau, jours/prix/total, subtotal_minor/fees_minor, minor_unit, payment_method_demo, conditions_version, version, is_demo, snapshots véhicule/vendeur. Statuts documentés conservés.
- orders : un seul véhicule, pas de order_items. kind sale/rental ; réservation nullable unique pour sa commande ; statuts pending/confirmed/fulfilled/cancelled. Snapshots, dates de confirmation/livraison, expiration et motif d'annulation ; mêmes montants/flags démo. Pas de statut paid sur une commande : paid appartient au paiement.
- vehicle_blocks : verrou parent véhicule obligatoire, FK réservation OU commande, UNIQUE sur chaque FK, index véhicule/début/fin. CHECK propriétaire selon kind, CHECK fin strictement après début ; sale sans fin. Un seul bloc pour réservation et commande rental. Holds expirés ou libérés ignorés sans scheduler.
- payments : FK order_id UNIQUE (un paiement démo par commande dans cette phase), référence unique, provider=demo, méthode parmi CASH_DEMO/MOBILE_MONEY_DEMO/CARD_DEMO/BANK_TRANSFER_DEMO, montant/devise/minor_unit, statut paid à simulation réussie, is_demo et paid_at. Aucun événement PSP, token bancaire ou metadata sensible. La contrainte un paiement/commande est propre au workflow de simulation ; les paiements multiples réels demanderont une migration ultérieure.
- idempotency_keys : UNIQUE(user_id,scope,key), hash du payload validé, resource_id et timestamps ; insérée/verrouillée dans la transaction de création. Pas de secret, pas de réponse privée stockée en clair.
- commerce_events : journal interne append-only de l'acteur, type/ID de ressource, action, is_demo et date ; aucune API publique d'écriture. Pas de table history supplémentaire.

Index dédiés : réservations véhicule/statut/début, utilisateur/date et boutique/statut/date ; commandes utilisateur/date et boutique/statut/date ; blocs véhicule/début/fin ; uniques de références/FKs paiements et idempotence. Les autres index sont ceux des FKs, sans doublons statut isolés inutiles.

Durées démo retenues : devis 5 min, hold 15 min, location max 365 jours ; config/commerce.php. Montants entiers max 99 999 999 999 999, frais zéro pour cette simulation. Annulation confirmed avant départ ; commandes déjà payées démo conservent le paiement comme trace, sans remboursement dans cette phase. États périmés reflétés en lecture et nettoyés opportunément sous verrou. Aucun scheduler nécessaire à l'exclusion des holds périmés.

Seed `ClientCommerceDemoSeeder` : réutilise client et professionnels démo existants ; crée deux unités explicitement dédiées (copie locale de leur placeholder existant), deux favoris, une réservation future confirmed, une commande rental confirmed + une vente fulfilled et deux paiements paid DEMO. Idempotent, refusé hors local/testing, ne remet pas à zéro les offres originales. Le catalogue local passe de 21 à 23 véhicules, dont 22 publiés, sans modifier les 21 originaux.
## Migration UX / PRODUCT POLISH 1
`2026_09_28_020000_add_profile_and_onboarding` ajoute users.country_code nullable (FK vers countries.code), city_id/district_id nullable (FK restrict) et avatar_path nullable ; aucune seconde table pays ni country_id numérique. shops.activity_type = sale/rental/both, défaut both. Les anciennes lignes restent compatibles ; pas de remise à zéro.

Référentiel complété : États-Unis (+1, USD, $), New York et Los Angeles ; six pays actifs CI/FR/US/CA/SN/BE, quatre devises XOF/EUR/USD/CAD. Libphonenumber fournit les métadonnées téléphoniques ; countries reste la source des marchés proposés. Téléphones nouveaux/modifiés en E.164.

Onboarding Pro : une transaction écrit users(role=merchant), merchant_profiles, merchant_memberships(role=owner), shops. Rollback de toutes les lignes et des nouveaux médias en cas d’échec. Approbation et publication uniquement décidées serveur. Les fichiers d’identité sont stockés sur public ; aucune photo distante téléchargée.

`PolishDemoSeeder` (démo effective uniquement) complète les références et répare uniquement les comptes @bolidemarket.demo : email_verified_at manquant et pays déduit du téléphone si reconnu. DemoAccountsSeeder et MarketplaceDemoSeeder vérifient aussi leurs identités déjà existantes. Aucun compte réel n’est transformé en donnée de démo.

Devise de la boutique et du véhicule dérivée du pays, jamais modifiée arbitrairement. Modification du pays d’une boutique avec véhicules refusée. Les snapshots de prix, réservations, commandes et paiements existants restent intacts.

## Phase 6A — données professionnelles
Migration additive 2026_10_04_000000_add_shop_opening_hours : shops.opening_hours JSON nullable, validé en sept jours ISO (1 lundi à 7 dimanche), fermeture ou une plage HH:mm le même jour. Aucun nouveau modèle client ni nouvelle table transactionnelle. Les champs logo_path/cover_path existants sont réutilisés.

Les clients professionnels sont une projection de reservations et orders(kind=sale) par shop_id autorisé, regroupée par user_id ; nombre d’interactions et dernière date de demande, sans double comptage des commandes rental. Agrégats et listes Pro filtrent is_demo selon la boutique. Le dashboard calcule ses compteurs/listes dans une transaction de lecture cohérente ; le graphique compte les ventes fulfilled des six mois UTC glissants par mois, sans extrapoler les chiffres de la maquette.

Statuts et snapshots financiers inchangés. Les coordonnées éditoriales/horaires de boutique n’altèrent pas les snapshots transactionnels ; les changements sensibles restent contrôlés sous verrous véhicule puis boutique. Tout changement de pays après véhicule (y compris supprimé), commande ou réservation est interdit.

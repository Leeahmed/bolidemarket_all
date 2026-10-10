# Architecture cible
Statut : fondation backend, authentification, catalogue et transactions DEMO 5B.1 implémentés. Environnement local vérifié : PHP XAMPP 8.2.12, Composer local 2.10.3, Laravel 12.69.2, Sanctum 4.3.3, MariaDB XAMPP 10.4.32 avec pilote `mysql`. Aucun changement global PHP/XAMPP ; dépendances figées dans composer.lock. Landing Vue 3 livrée en phase 4 et marketplace publique en phase 5A ; espace client transactionnel Vue livré en 5B.2 avec sessions Sanctum SPA existantes. Dashboard Pro livré en 6A ; client Flutter 8A implémenté, validation native en attente du SDK Android.

## Commerce DEMO — phase 5B.1 livrée
`Services/Commerce` centralise AvailabilityService, ReservationService, OrderService, DemoPaymentService, IdempotencyService et CommerceAudit. Contrôleurs, Form Requests, enums, Policies et Resources réutilisent Sanctum, les comptes et les memberships existants. Les historiques privés sont limités au client ou au professionnel propriétaire ; le calendrier public n'expose aucune identité client.

Création et transitions prennent une transaction SQL et le même verrou de ligne véhicule, avant les enregistrements de réservation/commande/blocage. Les modifications du catalogue et des boutiques respectent aussi les allocations engagées. Les réservations futures ne passent pas le véhicule en `rented` avant remise ; une location active en retard continue d'interdire sa réaffectation. Les blocs utilisent des intervalles semi-ouverts et les retenues expirées sont ignorées même sans scheduler ; leur état est régularisé à la prochaine opération concernée.

Les créations exigent `Idempotency-Key`, unique par utilisateur et opération, avec empreinte des données validées. Prix, devise, jours et instantanés sont calculés côté serveur. La confirmation professionnelle écrit atomiquement commande, paiement DEMO et blocage. L'audit interne est transactionnel, sans diffusion temps réel. Le paiement ne fait aucun appel externe et refuse les environnements autres que local/testing ; les véhicules, boutiques et commandes concernés doivent être explicitement démo. Un paiement démo déjà payé reste dans l'historique après annulation ; aucun remboursement n'est simulé.

Une migration additive crée les huit tables documentées dans DATABASE ; un seed dédié ajoute deux véhicules sans réinitialiser les exemples existants. Les réglages de démonstration sont dans `config/commerce.php`. Paiements réels, PDF, notifications et realtime sont hors de ce lot.

## Composants
| Dossier | Responsabilité |
|---|---|
| `backend-api/` | Laravel, API JSON versionnée, authentification, métier, autorisations, fichiers, jobs et événements |
| `landing-web/` | Vue, vitrine conforme aux références ; prérendu/SSR proposé pour indexation, contenu public uniquement |
| `marketplace-web/` | Vue, catalogue, compte et transactions client |
| `merchant-dashboard/` | Vue, gestion du professionnel et de ses boutiques |
| `mobile-client/` | Flutter, mêmes contrats API, stockage sécurisé des jetons |
| MySQL | Source de vérité partagée ; aucune connexion directe depuis les clients |
| Stockage / workers | Images publiques validées, reçus privés, traitement asynchrone |
| Realtime | Laravel Reverb proposé en phase 7, sous réserve de l'hébergement retenu |

Monorepo, trois applications Vue autonomes et un client Flutter, **une seule API métier**. Pas de microservices au démarrage. Les clients partagent le contrat HTTP et les tokens graphiques, sans inventer un package partagé avant besoin réel.

## MVC et organisation Laravel
- Routes `/api/v1` → contrôleurs fins → Form Requests (validation) et Policies (autorisation).
- Actions métier explicites : publier, réserver, confirmer, démarrer/terminer une location, vendre, enregistrer un paiement.
- Modèles Eloquent + MySQL pour les données ; transactions dans les actions, pas dispersées dans les contrôleurs.
- API Resources pour les représentations JSON ; les vues interactives sont rendues par Vue/Flutter. MVC ne signifie pas dupliquer le métier dans les clients.
- Modules logiques : Identity, Merchants, Catalog, Booking, Commerce, Billing, Notifications. Organisation en namespaces/dossiers au sein du monolithe ; aucun générateur de modules requis.
- Jobs idempotents pour images, PDF et notifications ; tous les changements cohérents sont commités avant émission des événements.

## Recherche catalogue — phase 3B
`VehicleSearchRequest` valide les filtres, paires GPS et dépendances prix/intention/devise. `LocationContext` regroupe l'origine et déduit ville/pays depuis une commune ; aucune déduction GPS par géocodage externe. `VehicleSearchService` centralise texte, filtres, proximité Haversine et classement générique ; les contrôleurs restent fins. Les scopes publics existants sont partagés avec facettes et suggestions. Distance définie dans une sous-requête SQL puis réutilisée par alias, valeurs liées, tri en liste blanche, relations chargées en avance. Rayon et limite de suggestions dans `config/search.php`. Contrats et limites de montée en charge : [API](API.md).

## Authentification et cloisonnement
Sanctum installé : sessions/cookies + CSRF pour les clients Vue propriétaires ; jetons révocables de 24 h par défaut pour les clients API/Flutter. `/auth/login` utilise une session si la requête est stateful, sinon un jeton ; `/auth/tokens` explicite le mode mobile. L'authentification SPA par cookies suppose un même domaine racine API/web, éventuellement plusieurs sous-domaines. CORS local limité à localhost/127.0.0.1:5173–5175 ; Secure activable pour HTTPS, désactivé en local HTTP. Ne pas conserver les jetons web dans localStorage. [Documentation Sanctum](https://laravel.com/docs/12.x/sanctum).

L'inscription crée un client. Le statut professionnel passe par onboarding ; le rôle administrateur n'est jamais fourni par le client. Une appartenance `merchant_memberships` autorise chaque ressource Pro, jusqu'aux photos et reçus. Le rôle global seul ne donne pas accès à une boutique. Policies systématiques sur listes, détails, mutations, téléchargements et canaux privés. [Autorisation Laravel](https://laravel.com/docs/13.x/authorization).

Validation serveur, limitation de débit sur authentification/recherche sensible, vérification de l'e-mail avant transaction, réinitialisation des mots de passe, révocation à la déconnexion. Secrets exclusivement dans l'environnement ; `.env.example` futur sans valeurs réelles.

## Flux critiques
1. Le client envoie une intention et une clé d'idempotence ; jamais un prix faisant autorité.
2. L'API autorise, ouvre une transaction, verrouille le véhicule, vérifie devis/prix/allocations et écrit l'état.
3. Le commit rend l'opération visible ; événement métier et notifications partent ensuite.
4. Le client affiche la réponse, puis relit l'API si une notification ou une reconnexion le nécessite.

Paiement derrière un adaptateur de prestataire, simulation séparée. Un retour navigateur « succès » ne prouve pas un encaissement. Webhook signé, dédupliqué, montant/devise/référence vérifiés ; enregistrement manuel seulement selon une politique Pro explicite et auditée. Aucun prestataire choisi dans cette phase.

## Temps réel
Canaux privés `user.{id}` et `merchant.{id}`, autorisés côté serveur. Événements versionnés : `reservation.updated`, `order.updated`, `vehicle.updated`, `notification.created`. Charge utile minimale : `event_id`, `resource_id`, `resource_version`, `occurred_at` ; pas de données de paiement personnelles. Aucun canal public de transactions.

API REST pour les écritures ; WebSocket pour signaler les changements. En cas de déconnexion : reconnexion progressive, déduplication puis relecture HTTP ; fonctionnement dégradé possible par actualisation. Jobs et événements déclenchés après commit, retries journalisés. [Broadcasting Laravel](https://laravel.com/docs/13.x/broadcasting).

## Fichiers, exploitation et versions
- Images : MIME réel, taille/dimensions bornées, nom serveur, variantes optimisées, suppression des métadonnées privées ; pas de chemin local fourni par le client.
- Reçus : stockage privé, autorisation à chaque demande, URL de téléchargement temporaire ; snapshots immuables.
- Queue Laravel ; pilote database possible au début, Redis seulement si utile. Scheduler pour expirations/nettoyage ; la disponibilité ne dépend pas de la ponctualité de ce scheduler.
- Environnements local/test/staging/production isolés ; démos uniquement par seed explicite et jamais mélangées aux métriques réelles.
- Logs structurés avec identifiant de requête, sans secrets ni corps de paiement sensibles ; erreurs et jobs échoués suivis. Sauvegarde MySQL/fichiers et essai de restauration avant production.
- Laravel 12 retenu pour PHP 8.2 existant ; support sécurité jusqu'au 24 février 2027 selon les [notes officielles](https://laravel.com/docs/12.x/releases). MariaDB XAMPP local remplace la proposition MySQL 8.4 pour cette phase uniquement ; moteur de production encore à choisir. Installation et versions : [README backend](../backend-api/README.md).
- CI future : migrations sur MySQL de test, tests ciblés, analyse/lint puis builds des applications concernées. Les scénarios de concurrence doivent utiliser MySQL, pas uniquement SQLite.

Voir [DATABASE](DATABASE.md), [API](API.md) et [ROADMAP](ROADMAP.md) pour les contrats et critères de sortie.


## Landing web — phase 4
Vue 3 + Vite, page unique à ancres sans routeur, composants Composition API. Configuration API et destinations centralisée ; services de lecture publics et composable partagé pour localisation/chargement. GSAP/ScrollTrigger importés dynamiquement avec nettoyage et réduction de mouvement. Assets locaux issus des références validées, fontes locales. Construction SPA statique ; SSR/prérendu encore différé. Tests Vitest et contrôles navigateur documentés dans [README landing](../landing-web/README.md).

## Marketplace web — phase 5A
Vue 3/Vite/Vue Router, JavaScript et Composition API ; pages catalogue, véhicule, annuaire et boutique chargées à la demande. Filtres/pagination/localisation dans la query string, y compris lors de la redirection des liens racine de la landing. Aucun changement landing. Composables pour références publiques et lectures annulables ; services HTTP séparés par ressource ; pas de Pinia nécessaire pour ce périmètre public.

L'API demeure source des résultats, distances et prix. Le catalogue boutique partage VehicleSearchRequest/VehicleSearchService, avec shop_id interne imposé après résolution du slug public, jamais un identifiant fourni librement par le navigateur. Présentation d'intentions depuis des compteurs publics dédiés. Aucune mutation transactionnelle, aucun nouveau modèle ni migration. Cartographie illustrative, SSR et workflows client différés. Installation et limites : [README marketplace](../marketplace-web/README.md).


## Dashboard Pro — phase 6A
merchant-dashboard est une SPA Vue 3/Vite/Router autonome, Composition API sans Pinia ni bibliothèque admin. Services HTTP centralisés par domaine, store réactif de session et boutique sélectionnée, chargements avec états erreur/vide/réessai. Police/assets officiels locaux réutilisés. Graphiques SVG/CSS à partir des seules données API, aucun moteur graphique additionnel.

Sessions Sanctum HttpOnly/CSRF communes ; normalisation localhost/127.0.0.1 pour les liens et cookies locaux. Guards merchant, refus explicite des clients et redirection marketplace ; 401 relance le login, 403 donne un message contrôlé. Catalogue/create/edit partage le formulaire ; images envoyées séquentiellement après création et avant publication, identifiant conservé pour reprise sur erreur.

Backend : MerchantDashboardController pour lecture agrégée/clients dérivés, filtres ajoutés aux contrôleurs existants, MerchantShopMediaController réutilise ProfileImages/RasterMetadata. Aucun changement aux services de transitions commerciales ; règles métier et autorisations restent côté API. Voir API/DATABASE et merchant-dashboard/README.md pour les contrats et commandes.

## Reverb — phase 6B
Reverb transporte les signaux métier entre l’API commune, la marketplace et le dashboard Pro. Les observers Vehicle/VehicleImage/Shop et Reservation/Order appellent RealtimePublisher ; celui-ci n’émet qu’après le commit externe via DB::afterCommit. Les callbacks sont abandonnés au rollback. Les transitions et verrous existants restent la seule autorité métier ; les mises à jour d’ordres liés et d’expiration utilisent maintenant les modèles pour passer par ces observers.

Les événements nommés sont synchrones (ShouldBroadcastNow), sans worker de queue. Une panne de transport est capturée et journalisée sans données sensibles, avec timeout HTTP borné ; elle ne transforme pas une écriture SQL validée en erreur REST. Les messages ne sont pas un journal durable.

Canal public marketplace : uniquement signaux de véhicules effectivement publics, avec tombstone minimal à la dépublication d’une annonce précédemment visible. Canaux privés merchant.{id} et user.{id} : autorisation Sanctum selon membership/identité et compte actif. Les payloads sont construits en liste blanche ; aucun modèle Eloquent complet ni paiement transmis. Origines locales explicites, aucun événement client accepté.

web-shared/realtime.js centralise transport, abonnements, déduplication et changement d’identité ; chaque SPA fournit Echo/Pusher, son client HTTP et son environnement. Les composables assurent le nettoyage par page et regroupent les rafraîchissements. Les ressources concernées sont relues par REST, avec réconciliation à chaque reconnexion. Configuration, commandes et preuves : [REALTIME_TESTING](REALTIME_TESTING.md).


## Phase 7 — reçus et rendu local
ReceiptService appelé par DemoPaymentService dans la transaction métier existante ; aucune réécriture des transitions achat/location. Receipt/ReceiptType/ReceiptPolicy, contrôleur de lecture fin et ReceiptResource. Données figées dès la demande puis copiées au reçu, sans relecture des profils lors du rendu.

ReceiptPdfService sépare rendu Blade/DomPDF du métier ; génération synchrone à la demande dans ce lot, sans cache/queue/service externe. Données échappées, assets locaux, accès distant/PHP/JS du moteur désactivés. Le PDF n’est jamais stocké publiquement.

Composants Vue partagés web-shared/ReceiptDocument, ReceiptActions et ReceiptsBrowser ; services HTTP et auth existants injectés par chaque SPA. Vue dédupliqué dans Vite. Listes/détails/confirmation utilisent REST, les événements commerciaux existants déclenchent les relectures. Contrats : [RECEIPTS](RECEIPTS.md).

## Mobile client — phase 8A
Flutter stable 3.47.6/Dart 3.13.5, Riverpod 2.6, Dio unique et GoRouter ; dépendances verrouillées. Organisation par features, repositories fins, controllers et AsyncValue assemblés dans core/providers, widgets partagés. Thème central, Inter/Sora locales, assets raster officiels ; aucun nouveau logo ni nouvelle photographie.

Auth Bearer sur le contrat Sanctum existant, restauration /auth/me et token flutter_secure_storage, onboarding seul dans SharedPreferences. Interceptor invalide uniquement un 401 du jeton courant ; erreurs réseau/JSON et réponses de sessions anciennes ne détruisent pas une nouvelle session. Routes privées gardées ; inscription client, E.164 via métadonnées téléphoniques et marchés API ; mode démo/vérification décidés serveur.

Recherche et home utilisent le même contexte de localisation : choix manuel/GPS explicite, sinon profil puis app-config. Filtres/prix/tris selon API ; budgets majeurs vers minor units BigInt, aucune conversion. Pagination avec exclusion des doubles chargements, états et retry. Favoris CRUD et profil/avatar réutilisent leurs endpoints. Résumé compte en lecture seule ; aucune mutation commerciale, PDF, Reverb, push ou Pro mobile en 8A.

Android/iOS com.bolidemarket.client ; Android minimum Flutter (24 sur ce SDK), iOS 15. Icones et splash Carbon générés depuis les assets validés. Permission localisation au premier plan sur clic, galerie seule. HTTP de développement autorisé uniquement dans Android debug / iOS Debug ; production APP_ENV exige une URL HTTPS. Backup Android désactivé pour ne pas exporter le stockage du jeton.

Installation, configuration réseau emulator/LAN, tests, limitations natives : [README mobile](../mobile-client/README.md). Pas de changement au backend ni aux contrats API/DB ; 8B reste séparée.

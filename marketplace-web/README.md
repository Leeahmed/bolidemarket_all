# BolideMarket — marketplace web, phases 5A et 5B.2

Vue 3.5.43, Vite 8.3.1 et Vue Router 4.6.4 ; Composition API, JavaScript, CSS existant, Sora/Inter locaux. Stores réactifs légers pour auth, favoris et notifications ; aucune nouvelle dépendance. Identité et assets officiels repris sans modification de la landing.

## Installation et lancement

Depuis ce dossier, avec Node >=22.12 (Node 24.19 testé) :

```powershell
npm install
Copy-Item .env.example .env # seulement si .env n'existe pas
npm run dev
```

Sur le poste actuel, si npm n'est pas dans PATH, remplacer `npm` par `node ../.tools/npm/bin/npm-cli.js`. Node se trouve dans `C:\Users\HP\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe`. Le serveur écoute sur **http://127.0.0.1:5174**. Garder le processus en fonctionnement pendant la consultation.

Variables publiques, centralisées dans `src/config.js` :

```dotenv
VITE_API_BASE_URL=http://127.0.0.1:8000/api/v1
VITE_LANDING_URL=http://localhost:5173
VITE_MARKETPLACE_URL=http://localhost:5174
VITE_MERCHANT_URL=http://localhost:5175
```

Copier et adapter `.env` avant le build. Aucune clé secrète dans les variables VITE. Production : URL API HTTPS et réécriture des routes inconnues vers `index.html` pour les accès directs Vue Router. SPA sans SSR/prérendu pour cette phase.

## Backend

Démarrer MySQL/MariaDB XAMPP puis, dans `backend-api` :

```powershell
C:\xampp\php\php.exe artisan serve --host=127.0.0.1 --port=8000
```

Réutiliser la base et les données de démonstration existantes, avec la migration commerce 5B.1 déjà exécutée. Ne pas relancer migrations ou seed pour démarrer l'interface. CORS autorise localhost/127.0.0.1:5174 et, depuis 5B.2, l'en-tête Idempotency-Key nécessaire aux créations. Garder un seul serveur API sur le port 8000.

## Pages et navigation

- `/` redirige vers `/vehicles` **en conservant les paramètres** des liens Hero/CTA existants.
- `/vehicles` : recherche à 400 ms, filtres, prix/devise, tris, pagination de 12 véhicules. Sidebar desktop, drawer natif sur mobile, URL partageable et retour navigateur.
- `/vehicles/:slug` : galerie/lightbox, prix par intention, caractéristiques, description texte, équipements, vendeur, localisation illustrative, offres similaires par catégorie.
- `/shops` : annuaire public paginé simple. L'API d'annuaire n'offre pas encore la recherche et les filtres boutique : aucun faux filtre client n'est présenté.
- `/shops/:slug` : présentation, coordonnées publiques hors démo, parc paginé filtré côté serveur par Tous/À vendre/À louer/Disponibles. Initiales si le backend ne fournit pas de logo ; aucune note inventée.
- Route inconnue ou annonce absente : état 404 et retour au catalogue.

Depuis 5B.2, Favoris, Compte, Acheter et Réserver sont opérationnels via l'API. Le contact de démonstration reste préparatoire.

## Espace client — phase 5B.2

- `/login`, `/register` : connexion et inscription CLIENT avec les champs et validations réels de l'API ; composition automobile/Ivory, formulaire mobile.
- `/forgot-password`, `/reset-password?token=…&email=…` : workflow de réinitialisation existant. FRONTEND_URL côté Laravel doit pointer vers la marketplace. En local, MAIL_MAILER=log : les liens sont dans le journal Laravel, aucun e-mail externe. Ne jamais partager ce journal contenant des liens privés.
- `/account`, `/account/profile`, `/account/favorites` : vue d'ensemble avec totaux réels, profil en lecture seule, renvoi du lien de vérification et favoris persistants. Aucun endpoint d'édition de profil inventé.
- `/account/reservations`, `/account/orders` : historiques paginés ; les commandes incluent ventes et locations et les distinguent. Détails sous `/:id`, conformément aux identifiants des endpoints backend ; les références métier restent affichées.
- `/vehicles/:slug/reserve`, `/vehicles/:slug/buy` : dates ou véhicule, résumé, mode de paiement DEMO et envoi.
- `/reservation-confirmation/:id`, `/order-confirmation/:id` : confirmation de création relue via l'API, y compris après actualisation. Une demande créée n'est pas présentée comme confirmée par le professionnel.

### Sessions et HTTP

Sanctum SPA existant : `/sanctum/csrf-cookie`, cookies HttpOnly de session, credentials inclus, X-XSRF-TOKEN sur mutations. Aucun Bearer token dans localStorage/sessionStorage. `stores/auth.js` restaure l'identité avec auth/me ; guards, retour vers l'URL interne demandée et déconnexion centralisés. 401 invalide l'identité et redirige les pages privées ; erreurs réseau, 403/404/409/419/422/429/5xx affichées sans détails techniques. Les champs 422 restent visibles sans effacer la saisie.

Utiliser le même hostname pour SPA/API. Pour les deux hôtes locaux autorisés, config.js aligne automatiquement localhost/127.0.0.1 sur celui du navigateur. En production : HTTPS, origines Sanctum/CORS explicites et domaine de cookies adapté ; le mécanisme backend reste inchangé.

### Favoris et transactions

Favoris : GET paginé, PUT/DELETE des routes documentées ; état partagé entre cartes, fiche et compte. Aucun changement visuel définitif avant succès serveur, actions verrouillées, feedback discret. Le store est vidé au changement de compte. Toutes les pages API sont lues pour afficher correctement les cœurs ; la grille du compte est paginée par 12.

Disponibilité : toutes les pages d'intervalles sont chargées, dates civiles interprétées dans le fuseau de la boutique, intersection [début, fin[ et retours adjacents autorisés. Calendrier au clavier par boutons natifs, dates passées/indisponibles désactivées. Le backend revalide toujours et fournit le devis utilisé. Un conflit recharge les disponibilités ; devis expiré ou prix modifié impose une nouvelle vérification. SOLD désactive l'achat.

PAIEMENTS DEMO UNIQUEMENT : quatre enums du backend, aucun numéro de carte/CVV. Le serveur crée d'abord une demande pending avec retenue de 15 minutes ; le professionnel confirme et simule le paiement. Annulation de réservation avec dialogue natif, seulement selon les états/dates documentés puis validation serveur. Un paiement DEMO payé reste dans l'historique après annulation, sans remboursement simulé.

Double clic bloqué et Idempotency-Key conservée avec le même payload en cas de résultat réseau incertain. La tentative minimale (IDs, dates, devis/prix déjà publics, mode DEMO et clé), séparée par utilisateur/véhicule/type, est dans sessionStorage pour survivre à un rechargement ; aucun mot de passe, token ou donnée bancaire. Après succès elle est supprimée. Les prix attendus de vente détectent un changement ; ils ne font jamais autorité.

## Services et règles d'affichage

`services/api.js` centralise lectures et mutations, cookies/CSRF, query string, délai réseau et erreurs. `vehicleService`, `shopService`, `locationService` et `commerceService` portent les contrats. `useResource` annule les requêtes remplacées et ignore les anciennes réponses. Référentiels pays/villes/communes, marques/modèles, catégories et devises via l'API.

Localisation uniquement sur action explicite : accepter GPS ajoute latitude/longitude et tri distance à l'URL. Refuser laisse les sélecteurs manuels accessibles. Le choix manuel propose restriction au lieu ou classement prioritaire (`location_mode=rank`). Les valeurs 0 sont valides ; aucune localisation GPS inventée ou sauvegardée automatiquement. L'URL partageable peut contenir les coordonnées choisies. `distance_km` affichée telle que calculée par l'API ; pas de Haversine client. Le détail API ne calcule pas de distance : si null, elle n'est pas affichée. Carte illustrative, aucun SDK cartographique.

Prix API en unités mineures, devise obligatoire pour comparaison. Saisie en unités usuelles, conversion exacte par BigInt avant envoi. XOF sans décimales, EUR/CAD avec leurs décimales, aucune conversion entre devises. Le filtre et le tri prix exigent intention + devise. `rental` en entrée, `rent` dans les offer_types historiques de sortie.

Vrais médias API prioritaires. Illustrations issues des maquettes uniquement sur véhicules explicitement démo et modèles correspondants, puis placeholder ; aucune photographie créée ni substituée à un autre modèle. Plusieurs véhicules du seed n'ont pas encore de photographie. Les badges démo restent visibles. Voir `public/images/SOURCES.md` ; licences locales dans `public/licenses`.

## Vérifications

```powershell
npm run test
npm run lint
npm run build
```

Vitest : **45 tests** couvrant le catalogue initial et l'espace client : sessions, guards, redirection sûre, erreurs, favoris, calendriers/fuseaux, collisions/adjacence, devis et expiration, réservation/achat, idempotence, SOLD et annulation. Pool `forks` retenu sur ce poste Windows. Build Vite avec pages chargées à la demande ; ESLint.

QA navigateur dans `qa/` : catalogue, détail et boutique aux largeurs 1920, 1440, 1366, 1024, 768 et 390 ; captures desktop/mobile et rapport de parcours. Le script local de vérification est `.tools/verify-marketplace.cjs` à la racine du workspace.

QA 5B.2 : `qa/5b2-browser-report.json` et `qa/5b2-registration-report.json` ; **42 contrôles responsive passants** (7 pages × 6 largeurs), aucune erreur JavaScript. Parcours testés sur l'API locale : inscription CLIENT, session restaurée, favoris persistants, devis/réservation, achat, confirmations, annulation, SOLD et déconnexion. Demandes de contrôle annulées après vérification ; compte QA explicitement démo créé pour l'inscription. Captures 1440/390 et scripts locaux `.tools/verify-phase5b2*.cjs`.

Backend 5B.2 : correction minimale de CORS pour Idempotency-Key et test preflight associé ; **143 tests passants, 709 assertions**, Pint PASS. Pas de migration supplémentaire ni changement métier.

Prochaine phase après validation : **6 — BolideMarket Pro, dashboard professionnel Vue**. PDF, QR, realtime, Flutter, chat et paiements réels restent hors périmètre.

## UX / PRODUCT POLISH 1
API avec migration profil et APP_DEMO_MODE=true en local requise. L’app charge /app-config : elle n’infère pas la démo depuis le domaine. Inscription/profil utilisent libphonenumber-js/max (métadonnées complètes) et les pays du référentiel API. Le pays de catalogue par défaut est celui du compte ou CI en démo ; market=all conserve le choix international. Pas de FX.

Routes : /pro/register (quatre étapes), /pro/login (login partagé), /pro et /admin (états d’accès, dashboards futurs). Avatar multipart avec preview local ; session HttpOnly conservée, aucun bearer persisté. Images Login/Register originales documentées dans public/images/SOURCES.md. Le logo et les autres assets officiels restent inchangés.

QA reproductible : .tools/verify-polish.cjs depuis la racine, Playwright Edge et API/Vite locaux. Crée uniquement des comptes @bolidemarket.demo. Captures/rapport dans marketplace-web/qa/polish-*.

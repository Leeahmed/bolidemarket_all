# BolideMarket — Administration globale (Phase 9A)

Vue 3 / Vite / Vue Router, API Laravel commune. Application distincte de BolideMarket Pro. Identité officielle réutilisée ; aucun paiement réel ni création publique d’ADMIN.

## Installation et aperçu

```powershell
cd admin-dashboard
npm install
Copy-Item .env.example .env # seulement si absent
npm run dev
```

Ouvrir http://localhost:5177/login. API : port 8000. Utiliser le même hostname pour navigateur/API ; la configuration normalise localhost/127.0.0.1 en local. Variables publiques : VITE_API_BASE_URL, VITE_MARKETPLACE_URL, VITE_MERCHANT_URL, VITE_ADMIN_URL, dans .env.example. Ne placer aucun secret dans une variable VITE_*.

Backend local existant :

```powershell
cd backend-api
php artisan migrate
php artisan db:seed --class=DemoAccountsSeeder
php artisan config:clear
php artisan serve --host=127.0.0.1 --port=8000
```

Pas de migrate:fresh, ni de réinitialisation des données. Ajouter les origines exactes : localhost:5177 et 127.0.0.1:5177 à SANCTUM_STATEFUL_DOMAINS ; http://localhost:5177 et http://127.0.0.1:5177 à CORS_ALLOWED_ORIGINS. Aucun wildcard ; session HttpOnly et CSRF existants. Ces valeurs sont incluses dans .env.example.

Sur ce workspace, PHP : C:\xampp\php\php.exe ; npm portable : `node ../.tools/npm/bin/npm-cli.js` depuis admin-dashboard si npm absent du PATH.

## Connexion locale DEMO seulement

Compte existant validé par DemoAccountsSeeder : **admin@bolidemarket.demo**, mot de passe local **password**, e-mail vérifié. Seed interdit hors local/testing, aucun rôle ou mot de passe existant écrasé. Ne jamais utiliser ces identifiants en production. Le frontend ne stocke aucun jeton dans localStorage.

CLIENT/MERCHANT : écran 403 puis redirection vers marketplace/Pro. Invité : login. Le serveur exige auth:sanctum + compte actif + role:admin sur toutes les routes /admin, y compris recherche, rapports et PDF. La protection serveur ne repose pas sur le guard Vue.

## Fonctionnalités

Dashboard et rapports : un appel agrégé ; KPI actuels, partition du parc, séries UTC 7/30/90/365 jours, géographie et encaissements DEMO séparés par devise. Périmètre demo/real/all explicite. Demo par défaut lorsque le mode serveur est actif, sinon real. Les comptes de simulation sont identifiés par le domaine @bolidemarket.demo ; les autres ressources par is_demo. Aucun graphique factice.

Listes paginées serveur et filtres URL : utilisateurs, professionnels, boutiques, véhicules, réservations, ventes, paiements DEMO, reçus et journal administratif. Recherche globale limitée à cinq résultats par famille, puis navigation vers la liste. Sélecteurs ville/boutique/professionnel recherchables côté serveur, 25 suggestions maximum ; marques/catégories/pays issus des référentiels.

Actions explicites avec motif requis : suspendre/réactiver utilisateur et boutique, approuver/suspendre/réactiver professionnel, dépublier/republier/suspendre/marquer une annonce. Suspension personnelle refusée au serveur. Suspension utilisateur révoque sessions et tokens. Aucun formulaire ne modifie rôle, propriété, mot de passe, montants ou devise historique. Aucune suppression.

Boutique/professionnel/compte propriétaire suspendu : annonces invisibles et nouvelles opérations interdites par les scopes métier communs. Suspension d’annonce et dépublication administrative persistent jusqu’à republication ADMIN autorisée ; Pro ne peut les annuler. Vérification n’efface pas une suspension. Republication exige boutique/professionnel actifs, prix et photo. Les décisions ne changent pas les engagements ni l’état vendu/loué. Retours/annulations existants restent régis par les services métier ; historique conservé.

Reçus immuables, rendus par les composants partagés ; PDF et impression authentifiés via le moteur existant. Aucune mutation même ADMIN. Modération en transaction avec verrou véhicule commun, audit minimal immuable ; signaux Reverb existants après commit. Aucun nouveau canal Admin, actualisation REST suffisante.

## Vérification

```powershell
npm run build
npm run test
npm run lint
cd ../backend-api
php artisan test
php vendor/bin/pint --test
```

Tests backend exclusivement sur bolidemarket_test. Résultats, responsive et captures : qa/ et docs/CURRENT_STATUS.md. QA locale optionnelle : `php artisan db:seed --class=AdminQaSeeder`, ajoute uniquement un professionnel fictif pending, une boutique QA et un RAV4 brouillon avec photographie officielle. Ne remplace aucune donnée ni décision existante ; interdit hors local/testing. Ce compte n’est pas ADMIN. Les opérations du contrôle restent auditées.

DA, landing, marketplace, Pro et mobile Visual Demo Mode préservés. Phase 9B non commencée.

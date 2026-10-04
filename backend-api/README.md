# BolideMarket API — phases 1, 2, 3A, 3B et 5B.1

API Laravel exécutable localement : authentification, catalogue, boutiques, recherche, proximité, favoris, réservations et achats disponibles. **PAIEMENTS = DEMO UNIQUEMENT.** Contrats dans [API.md](../docs/API.md). Interfaces transactionnelles Vue, Flutter et paiements réels restent futurs.

## Commerce DEMO — phase 5B.1
Depuis `backend-api/`, après le socle et les seeds catalogue existants :

```powershell
php artisan migrate
php artisan db:seed --class=ClientCommerceDemoSeeder
php artisan test
php vendor/bin/pint --test
```

Le seed réutilise le client démo et les professionnels approuvés existants ; il ajoute deux véhicules dédiés, deux favoris, une réservation confirmée et deux commandes avec paiements DEMO. Il est idempotent, limité à local/testing et préserve le catalogue précédent. Sur une installation neuve, exécuter d'abord les seeds catalogue et géographique décrits plus bas.

25 routes ajoutées : favoris `/me/favorites`, devis `/rental-quotes`, créations `/reservations` et `/orders`, historiques `/me/reservations` et `/me/orders`, calendrier public `/vehicles/{slug}/availability`, listes et transitions `/merchant/reservations` et `/merchant/orders`. Payloads, méthodes exactes, autorisations et erreurs : section phase 5B.1 de [API.md](../docs/API.md). Créations de réservation/achat : en-tête `Idempotency-Key` obligatoire. Méthodes autorisées : CASH_DEMO, MOBILE_MONEY_DEMO, CARD_DEMO, BANK_TRANSFER_DEMO ; aucune donnée bancaire ni API externe.

La confirmation est professionnelle ; une vente passe à SOLD à la livraison (`fulfil`). Une location future laisse le véhicule AVAILABLE jusqu'à sa remise. Calcul des prix et disponibilité sont côté serveur sous transaction et verrou véhicule. Les durées de devis/retenue sont dans `config/commerce.php`. Suite complète vérifiée : **143 passed / 0 failed, 705 assertions** sur la base de test séparée ; Pint PASS. Aucun client Vue modifié pour cette phase.

## Environnement vérifié
- PHP XAMPP 8.2.12, Laravel 12.69.2, Sanctum 4.3.3, Composer 2.10.3 ; versions PHP verrouillées par les contraintes et dépendances dans composer.lock.
- XAMPP fournit ici **MariaDB 10.4.32**, utilisé avec le pilote Laravel `mysql` ; ce n'est pas MySQL 8.4. Aucun remplacement global effectué.
- Extensions : ctype, curl, dom, fileinfo, filter, hash, mbstring, openssl, pcre, PDO/pdo_mysql, session, tokenizer, xml. Zip chargé uniquement pour Composer via `-d extension=zip` sur ce poste.
- Laravel 12 accepte PHP 8.2 et reçoit des correctifs de sécurité jusqu'au 24 février 2027 : [support officiel](https://laravel.com/docs/12.x/releases). Réévaluer les versions avant production.

## Installation locale PowerShell
Se placer dans `backend-api/`. Démarrer MySQL depuis XAMPP. Sur ce poste, PHP et Composer ne sont pas dans le PATH ; préparation temporaire du terminal, sans modification globale :

```powershell
$env:PATH = 'C:\xampp\php;' + $env:PATH
$env:COMPOSER_HOME = Join-Path (Split-Path $PWD) '.tools/composer-home'
$env:COMPOSER_CACHE_DIR = Join-Path (Split-Path $PWD) '.tools/composer-cache'
$BolideComposer = (Resolve-Path '../.tools/composer.phar').Path
function composer { php -d extension=zip $BolideComposer @args }
php -v
composer --version
composer install
if (-not (Test-Path .env)) {
    Copy-Item .env.example .env
    php artisan key:generate
}
```

Composer local se trouve dans `.tools/` à la racine, exclu de Git, installé depuis l'[installeur officiel vérifié par SHA-384](https://getcomposer.org/doc/faqs/how-to-install-composer-programmatically.md). Sur un autre poste, utiliser Composer déjà installé ou installer sa version locale officielle. Ne pas régénérer une APP_KEY existante.

`.env.example` prévoit `mysql`, `127.0.0.1:3306`, base `bolidemarket`, utilisateur `root`, mot de passe vide uniquement pour la configuration locale XAMPP. Renseigner un éventuel mot de passe réel seulement dans `.env` ignoré. Création non destructive si nécessaire :

```powershell
& C:\xampp\mysql\bin\mysql.exe -h 127.0.0.1 -u root -e 'CREATE DATABASE IF NOT EXISTS bolidemarket CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
php artisan migrate
php artisan db:seed
php artisan serve --host=127.0.0.1 --port=8000
```

Base URL : **http://127.0.0.1:8000/api/v1**. Vérifications : `/api/v1/health` et `/up`. Ne jamais lancer `migrate:fresh` sur la base de développement contenant des données.

## Comptes de démonstration
| Rôle | E-mail | Mot de passe local |
|---|---|---|
| ADMIN | admin@bolidemarket.demo | password |
| CLIENT | client@bolidemarket.demo | password |
| MERCHANT | merchant@bolidemarket.demo | password |

Seed idempotent, sans réinitialiser un mot de passe existant ; interdit hors environnements local/testing, même avec `--force`. Ces comptes et ce mot de passe faible sont exclusivement destinés à la démonstration locale, jamais à la production. Le professionnel démo reste pending. L'inscription publique applique une règle de mot de passe forte différente.

## Contrat disponible
Toutes les routes suivantes sont sous `/api/v1` :

| Méthode | Route | Fonction |
|---|---|---|
| POST | `/auth/register` | Client uniquement, 201, sans connexion automatique |
| POST | `/auth/login` | Session pour SPA stateful ; jeton Bearer pour appel API sans contexte SPA |
| GET | `/auth/me` | Utilisateur authentifié, 401 sinon |
| POST | `/auth/logout` | Révoque le jeton courant ou invalide la session, 204 |
| POST | `/auth/tokens` | Jeton mobile explicite |
| DELETE | `/auth/tokens/current` | Révocation du jeton courant |
| POST | `/auth/forgot-password` | Réponse identique compte présent/absent |
| POST | `/auth/reset-password` | Jeton de réinitialisation, révoque sessions et jetons existants |
| POST | `/auth/email/verification-notification` | Renvoi à l'utilisateur connecté |
| GET | `/auth/email/verify/{id}/{hash}` | Lien signé expirant + authentification du propriétaire |

Inscription : `first_name`, `last_name`, `email`, `phone` international E.164 (ex. +2250700000001), `password`, `password_confirmation`. Mot de passe de 12 caractères minimum et 72 octets maximum, sans caractère nul, avec majuscule/minuscule/chiffre/symbole. `role` facultatif, seule valeur acceptée `customer` ; les enums PHP nomment ce rôle `UserRole::CLIENT`. Les rôles `merchant`/`admin` ne sont pas assignables publiquement. `name` est calculé depuis prénom/nom ; il n'est pas stocké une seconde fois.

Connexion API : `email`, `password`, `device_name` facultatif. Réponse `data.user`, `data.token`, `data.token_type`, `data.expires_at` ; transmettre ensuite `Authorization: Bearer <token>`. Jeton affiché une seule fois, hashé en base, durée par défaut 24 h, configurable via SANCTUM_EXPIRATION. Succès sous `data`, erreurs sous `error.code/message/fields` + `request_id`. Aucun password/remember_token retourné.

SPA propriétaire : appeler d'abord `/sanctum/csrf-cookie`, envoyer cookies et `X-XSRF-TOKEN` avec les requêtes modifiant l'état. Origines autorisées : localhost/127.0.0.1, ports 5173–5175. Utiliser le même hostname pour API et SPA (ne pas mélanger localhost et 127.0.0.1). Les cookies restent HttpOnly, SameSite=Lax ; Secure=false seulement en local HTTP, à passer à true en HTTPS. Aucune origine `*` avec credentials.

E-mails : `MAIL_MAILER=log` ; liens uniquement dans `storage/logs/laravel.log`, aucun service externe. `FRONTEND_URL` prépare la future page de reset, non construite dans cette phase. Le token peut être envoyé directement à l'API pour les essais. Pour vérifier un e-mail mobile, appeler le lien signé avec le token d'authentification du même utilisateur.

## Organisation et sécurité
Controllers fins, Form Requests, actions Auth, UserResource, enums, middleware `auth:sanctum`, `active` et `role:merchant,admin`. Le rôle global ne donne pas la propriété d'un professionnel : MerchantProfilePolicy vérifie l'appartenance et réserve l'édition au owner. L'action interne CreateMerchantProfile crée profil pending + appartenance owner dans une transaction ; aucune route publique d'onboarding encore exposée.

## Tests MySQL isolés
Créer la base de test une fois :

```powershell
& C:\xampp\mysql\bin\mysql.exe -h 127.0.0.1 -u root -e 'CREATE DATABASE IF NOT EXISTS bolidemarket_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
php artisan test
php vendor/bin/pint --test
```

`phpunit.xml` force la base locale **bolidemarket_test**, réservée exclusivement aux tests. Le TestCase refuse toute autre base avant RefreshDatabase. La suite peut reconstruire cette base et exécute chaque scénario en transaction ; ne jamais y stocker des données à conserver. Adapter les identifiants de test localement si XAMPP est protégé, sans commiter de secret. La base `bolidemarket` n'est pas utilisée par les tests.

Les tests couvrent inscription/validation/hash, anti-escalade, sessions/CSRF, tokens/expiration/logout, comptes désactivés, limitation de débit, CORS, reset/vérification e-mail, isolation Pro et seed local. Le bilan vérifié est dans [CURRENT_STATUS](../docs/CURRENT_STATUS.md).


## Démonstration catalogue et proximité
Seed explicite : `php artisan db:seed --class=GeographicDemoSeeder`. Jeu local de 21 véhicules et 8 boutiques ; coordonnées et prix de démonstration. Refusé en production. [Bilan et tests](../docs/CURRENT_STATUS.md).


## UX / PRODUCT POLISH 1
Après installation des dépendances : php artisan migrate ; en environnement local avec APP_DEMO_MODE=true, php artisan db:seed --class=PolishDemoSeeder (sans reset).
La variable est sans effet en production ; config/demo.php et DemoMode centralisent la règle. phpunit.xml force false par défaut pour tester les protections, les tests démo l’activent explicitement.
Téléphones : giggsey/libphonenumber-for-php ; pays et devise : référentiel existant complété, CurrencyResolver. Profil et onboarding : contrats dans docs/API.md. Avatar et logo/cover sont des fichiers raster statiques sur le disque public, sans GD requis.

### Démarrage local Windows et uploads
Exécuter `./start-local.ps1` depuis backend-api (PHP XAMPP par défaut, paramètre -Php disponible), après arrêt de l’ancien serveur sur le port 8000. Ce lanceur ouvre PHP en arrière-plan avec upload_tmp_dir sous storage/app/upload-tmp, sans modifier le php.ini global. Il évite les notices PHP de repli vers un dossier temporaire système qui corrompaient les réponses JSON des uploads sur ce poste. Logs et PID : storage/logs/local-server*. Aucun doublon de serveur ne doit être lancé.

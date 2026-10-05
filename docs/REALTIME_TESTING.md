# Temps réel local — phase 6B

Laravel Reverb 1.12, Broadcasting Laravel, Echo 2.5 et pusher-js 8.6 (protocole uniquement, aucun service SaaS). L’API reste la source de vérité. Aucun paiement réel.

## Configuration et lancement

Les dépendances sont verrouillées : `composer install` dans backend-api, `npm ci` dans chacune des deux SPA. Sur ce poste, utiliser PHP XAMPP et les runtimes locaux décrits dans les README.

Dans backend-api/.env, conserver les clés locales existantes. Pour une nouvelle installation, générer une clé publique et un secret aléatoires, puis renseigner :
- BROADCAST_CONNECTION=reverb ; REVERB_APP_ID identifiant local ;
- REVERB_APP_KEY et REVERB_APP_SECRET (secret exclusivement backend) ;
- REVERB_HOST=127.0.0.1, REVERB_PORT=8080, REVERB_SCHEME=http ;
- REVERB_SERVER_HOST=127.0.0.1, REVERB_SERVER_PORT=8080 ;
- REVERB_ALLOWED_ORIGINS=localhost,127.0.0.1.

Dans marketplace-web/.env et merchant-dashboard/.env : VITE_REVERB_APP_KEY égale la clé publique backend ; VITE_REVERB_HOST=127.0.0.1 ; VITE_REVERB_PORT=8080 ; VITE_REVERB_SCHEME=http. Jamais de secret dans VITE_*. Redémarrer Vite après modification. Les .env sont ignorés par Git ; les .env.example n’incluent aucune clé.

Démarrer MySQL, puis, dans des terminaux distincts :
1. backend-api : `./start-local.ps1` — API 8000, répertoire temporaire upload préservé.
2. backend-api : `php artisan reverb:start --host=127.0.0.1 --port=8080`.
3. marketplace-web : `npm run dev` — 5174.
4. merchant-dashboard : `npm run dev` — 5175.
5. landing-web, si nécessaire : `npm run dev` — 5173.

Sur Windows, le PHP de ce poste est C:/xampp/php/php.exe. Vérifier qu’un port n’a pas déjà de serveur avant de lancer un second processus. Aucun queue:work ni Redis nécessaire : diffusion synchrone après commit SQL. Reverb et l’API sont deux processus distincts. En local HTTP, Echo utilise ws ; en production HTTPS, configurer wss et les origines réelles.

## Quatre scénarios, deux sessions séparées

Employer deux profils/contextes de navigateur pour isoler les sessions Sanctum : un professionnel propriétaire et un client. Utiliser un véhicule DEMO dédié à ces contrôles, avec photo, offre vente + location et statut disponible.

| Scénario | Manipulation | Résultat attendu sans actualiser la page cliente |
|---|---|---|
| Prix | Client sur la fiche RAV4 ; Pro modifie le prix puis enregistre | Nouveau prix sur la fiche et sur une carte visible |
| Réservation | Pro sur le dashboard ; client choisit des dates disponibles et confirme une réservation DEMO | Toast « Nouvelle réservation reçue. », compteur et liste récente actualisés |
| Confirmation | Client reste sur sa confirmation ; Pro confirme la réservation | Statut Confirmée et toast « Votre réservation a été confirmée. » |
| Vente | Annuler la location future pour libérer le véhicule ; client crée un achat DEMO ; Pro confirme puis livre | Client sur la fiche : Vendu, CTA désactivé et « Ce véhicule vient d’être vendu. » |

Le statut SOLD est produit par la livraison de la commande, jamais par un changement manuel d’inventaire. Les écritures REST continuent de contrôler les conflits et les verrous.

## Résilience et sécurité

- Publication : un bandeau propose d’actualiser le catalogue ; aucune insertion perturbant pagination ou tri. Dépublication : carte retirée, fiche indisponible avec message explicite.
- Prix/image/statut : relecture REST du véhicule visible, avec protection contre les réponses anciennes. Si une modification affecte un filtre actif, le serveur recalcule la page filtrée. Après reconnexion, relecture REST des vues actives.
- Arrêter Reverb : navigation, lecture et écritures REST doivent continuer à réussir. Relancer Reverb : reconnexion et réconciliation sans F5. Aucun toast répété de déconnexion.
- POST /api/v1/broadcasting/auth emploie Sanctum/CSRF. Un autre merchant, un autre user, un client sur un canal merchant, un invité et un compte désactivé doivent être refusés. Le rôle merchant seul ne suffit pas : membership contrôlée.
- Les événements publics ne contiennent que des identifiants de véhicule/boutique, slug, version et noms de champs modifiés. Ni identité client, ni paiement, ni e-mail/téléphone, ni secret. Les événements initiés directement par les clients WebSocket sont désactivés.
- Déconnexion du compte/changement d’organisation : ancien socket fermé. Les listeners de page et temporisations sont supprimés au démontage. Déduplication bornée par event_id.

Les événements sont éphémères : aucun replay ni garantie de livraison durable dans cette démo. Un échec de diffusion est journalisé sans contenu métier et ne fait pas échouer une transaction déjà validée. La reconnexion et le bouton d’actualisation récupèrent l’état REST.

## Vérifications

- backend-api : `php artisan test` (garde-fou bolidemarket_test), `vendor/bin/pint --test --dirty`.
- marketplace-web et merchant-dashboard : `npm run lint`, `npm test`, `npm run build`.
- RealtimeTest vérifie commit externe réel, rollback imbriqué, panne de diffusion, images, dépublication, réservation/vente et isolation des comptes.
- Tests Vue : mises à jour ciblées, nouveau bandeau, SOLD, retrait, confirmation, KPI Pro, déduplication, reconnexion et nettoyage.
- Preuves réelles du 4 octobre 2026 : marketplace-web/qa/realtime.json, realtime-outage.json, realtime-sold.png ; merchant-dashboard/qa/realtime-order.png. Les quatre scénarios, la publication et les refus d’accès ont été constatés dans deux sessions Edge distinctes. L’arrêt/redémarrage réel de Reverb a aussi été contrôlé : lecture/création/modification/suppression REST réussies pendant la panne, puis réconciliation.
- Le véhicule QA 25 reste vendu avec sa commande DEMO 7 ; la réservation QA 6 a été annulée. Le brouillon QA du test de panne a été supprimé logiquement. Aucun véhicule de référence modifié.

Documentation officielle : [Reverb](https://laravel.com/docs/12.x/reverb), [Broadcasting](https://laravel.com/docs/12.x/broadcasting).

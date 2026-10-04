# BolideMarket — landing Vue 3

Phase 4 : landing responsive de 16 sections, conforme à la direction validée. Vue 3.5.43, Vite 8.3.1, GSAP 3.15.0 ; versions exactes dans package.json et package-lock.json. Composition API, composants dédiés, aucun routeur nécessaire pour cette page à ancres.

## Lancement

Node 24 LTS recommandé (24.19.0 testé), npm disponible dans le terminal :

```powershell
cd landing-web
npm install
Copy-Item .env.example .env # seulement si .env n'existe pas
npm run dev
```

Ouvrir http://127.0.0.1:5173. Sur le poste Codex actuel, npm a été installé localement dans ../.tools/npm, sans installation globale : remplacer `npm` par `node ../.tools/npm/bin/npm-cli.js` si nécessaire. Cache npm local dans ../.tools/npm-cache.

Configuration centralisée dans src/config.js :

```dotenv
VITE_API_BASE_URL=http://127.0.0.1:8000/api/v1
VITE_MARKETPLACE_URL=http://localhost:5174
VITE_MERCHANT_URL=http://localhost:5175
VITE_HERO_VIDEO_URL=/videos/hero-abidjan.mp4
```

Les deux applications de destination ne sont pas encore développées : les CTA construisent déjà leurs URL et paramètres. Adapter ces valeurs au déploiement, avec des URL HTTPS en production.

## Backend requis

Démarrer MariaDB/MySQL XAMPP puis, depuis backend-api :

```powershell
$env:PATH = 'C:\xampp\php;' + $env:PATH
php artisan serve --host=127.0.0.1 --port=8000
```

Le jeu local existe déjà. Sur un environnement vierge préparé selon le README backend, seed explicite facultatif : `php artisan db:seed --class=GeographicDemoSeeder`. Ne pas reconstruire une base existante. CORS autorise localhost/127.0.0.1:5173 ; conserver les origines configurées côté Laravel.

- Véhicules : GET /vehicles, per_page=4, status=available, contexte manuel location_mode=rank ; coordonnées seulement après permission navigateur explicite.
- Référentiels : GET /vehicles/filters, /countries, /cities, /districts. IDs issus de l'API, jamais codés en dur.
- Boutiques : GET /shops, première page de 100 maximum, priorité manuelle commune/ville/pays côté présentation puis 4 résultats. L'API n'offre pas encore de recherche géographique des boutiques : ce classement ne couvre pas les pages suivantes et aucune distance boutique n'est inventée.
- Le Hero prépare listing_type=sale/rental, brand, category et contexte de lieu pour la future marketplace. Les prix conservent leur devise originale ; le sélecteur du footer explique cette limite.

Loading, error avec réessai, empty et success couverts. Annulation des requêtes véhicules remplacées, délai borné, protection contre les réponses obsolètes. Pas d'authentification, favoris, réservation ou paiement dans cette application.

## Visuels et motion

Sources dans ../docs/references, préservées sans modification. Les assets WebP sont des extractions de zones des maquettes, sans génération ni nouveau logo. Provenance : public/images/SOURCES.md. Le mot-symbole est une image officielle, jamais une imitation typographique. Sora et Inter sont servis localement ; licences OFL conservées dans public/licenses.

Les photos réelles de l'API priment. Pour les seuls véhicules/boutiques marqués is_demo, les illustrations disponibles sont reprises des maquettes et signalées comme telles. Les modèles sans illustration conservent le placeholder backend. Aucun prix, stock, avis ou véhicule n'est créé côté frontend. Les favoris sont des icônes décoratives, sans promesse de sauvegarde.

Vidéo officielle temporaire : public/videos/hero-abidjan.mp4, active par défaut et configurable via VITE_HERO_VIDEO_URL. Autoplay, muted, loop et playsinline conservés, sans bouton lecture/pause, avec overlay sombre et poster public/images/hero-auto-poster.webp. Le poster reste visible en cas de chargement impossible. En prefers-reduced-motion, aucune vidéo montée ni téléchargée ; un changement de préférence est pris en compte pendant la session. Aucune nouvelle génération autorisée actuellement ; docs/HERO_VIDEO_BRIEF.md est conservé intégralement pour plus tard, aucun crédit Higgsfield consommé par cette intégration.

GSAP/ScrollTrigger chargés à la demande : apparition du Hero, des cartes et sections, statistiques, mockups ; parallaxe de 12 px maximum. MatchMedia et animations nettoyés au démontage. Aucune animation ou vidéo imposée en réduction de mouvement. Les contenus restent lisibles avant le chargement de GSAP.

Carte temporaire illustrative d'Abidjan, labels géographiques et liens issus du catalogue ; aucun fournisseur externe. Mockups Pro/mobile purement marketing. Boutons stores et informations légales/contact expliquent l'état de démonstration dans la page.

## Vérifications et production

```powershell
npm run build
npm run test
npm run lint
npm run preview
```

Build statique dans dist/. Déployer ce dossier ; aucune publication effectuée dans cette phase. SPA client, pas de SSR/prérendu ajouté. Vitest utilise un worker thread pour éviter les limitations de sous-processus rencontrées sur ce poste Windows.

11 tests : Home, API véhicules, erreurs/réessai, skeleton/empty, contexte manuel, menu/clavier/stores, paramètres HeroSearch, cartes vente/location, stock professionnel, formatage XOF/EUR.

Contrôle navigateur Edge headless avec API réelle : 1920×1080, 1440×900, 1366×768, 1024×900, 768×1024 et 390×844. Vérification absence de débordement, images cassées et erreurs JS ; navigation Acheter/Louer, erreur API simulée, réduction de mouvement et animations. Captures et rapport dans qa/. Script de vérification local : ../.tools/phase4-qa.cjs, avec Playwright fourni par le runtime Codex ; aucune dépendance navigateur imposée à l'application.

Limites acceptées : vidéo temporaire existante, sources raster parfois petites, photos du catalogue encore partiellement placeholders, destinations marketplace/Pro à venir. Prochaine phase : marketplace web Vue 3 après autorisation.


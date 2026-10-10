# BolideMarket — client Flutter, phases 8A et 8B

Foundation, auth, découverte, catalogue, boutiques, favoris et profil sur l’API Laravel existante. Direction A, logo Triade et palette officiels conservés. Phase 8B : réservation avec disponibilité/devis serveur, achat avec coordonnées de remise, paiements DEMO, historiques, reçus/PDF et Reverb intégrés. Validation native Android encore indisponible ; push et paiements réels hors périmètre.

## Environnement
Vérifié avec **Flutter 3.47.6 stable / Dart 3.13.5**, dépendances figées par pubspec.lock. SDK local de ce workspace : ../.tools/flutter (ignoré par Git). Sur une autre machine : [installation officielle Flutter](https://docs.flutter.dev/install/manual), puis Android SDK/JDK et un émulateur ou appareil. Android minimum 24 (maximum entre Flutter minimum et 23), SDK cible/compilation provenant de Flutter. iOS minimum 15 ; compilation iOS sur macOS/Xcode seulement. Identifiant Android/iOS : com.bolidemarket.client, nom BolideMarket.

Depuis mobile-client, sur ce poste PowerShell :

```powershell
$mobileFlutterBin = (Resolve-Path '../.tools/flutter/bin').Path
$env:Path = "$mobileFlutterBin;$env:Path"
flutter pub get
dart format .
flutter analyze
flutter test
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
flutter build apk --debug --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
```

Alternative directe : & ../.tools/flutter/bin/flutter.bat analyze. Ne pas lancer deux commandes Flutter qui téléchargent le SDK simultanément.

Démarrer MySQL puis l’API existante avec ../backend-api/start-local.ps1. Aucun seed, migration ou reset supplémentaire nécessaire.

## URL et environnements
AppConfig centralise APP_ENV et API_BASE_URL :
- Android Emulator : http://10.0.2.2:8000/api/v1, défaut de développement.
- Appareil physique : http://IP_LAN_DU_PC:8000/api/v1, même réseau local, API accessible sur cette interface.
- Simulateur iOS sur Mac : http://127.0.0.1:8000/api/v1 si l’API tourne sur ce Mac, sinon IP LAN.
- Production future : APP_ENV=production et API_BASE_URL=https://votre-api/api/v1 ; HTTPS obligatoire, aucune clé embarquée.

Pour un appareil physique, arrêter le serveur 8000 existant avant de relancer l’API sur le LAN. Depuis mobile-client, dans un terminal dédié :

```powershell
$apiProject = (Resolve-Path '../backend-api').Path
Push-Location "$apiProject/public"
& 'C:/xampp/php/php.exe' -d "upload_tmp_dir=$apiProject/storage/app/upload-tmp" -S 0.0.0.0:8000 "$apiProject/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"
Pop-Location
```

Autoriser le port sur votre réseau privé si nécessaire. Remplacer ensuite IP_LAN_DU_PC par l’adresse de ce PC :

```powershell
flutter run --dart-define=API_BASE_URL=http://IP_LAN_DU_PC:8000/api/v1
```

Les URLs de médias loopback renvoyées par Laravel sont réécrites vers l’hôte API uniquement en développement. HTTP local autorisé dans le manifest Android **debug seulement** et dans Info-Debug.plist iOS **Debug seulement**. Release conserve ATS et la politique réseau normale.

## Organisation
- core : configuration, un seul Dio, erreurs utilisateur, modèles utilisés, prix/budget entiers BigInt, thème et composition Riverpod/GoRouter.
- features : auth, splash, onboarding, home, marketplace, vehicle, shop, account, profile, location. Repositories fins, controllers Riverpod/AsyncValue ; pas de couches use-case artificielles.
- shared : logo raster officiel, photos mises en cache, cartes, grilles, placeholders et états.
- Token Bearer en flutter_secure_storage ; SharedPreferences contient uniquement onboarding.seen. Session restaurée avec /auth/me, 401 courant invalidant ; panne réseau, JSON invalide et 401 ancien n’effacent pas une nouvelle session.
- Inscription client sans champ rôle et sans connexion automatique. Téléphone normalisé E.164 par phone_numbers_parser ; marchés/indicatifs issus du backend. Mode démo et vérification e-mail viennent du serveur.
- Origine explicite prioritaire ; sinon profil puis default_country de /app-config. Classement rank, distances serveur, choix Tous les pays. Budget saisi en unités majeures converti en entiers selon le référentiel devise ; aucune conversion FX.
- Recherche debounce 400 ms ; compteurs filtrés serveur ; pagination protégée et retry conservant la page en cas d’échec.
- Galerie PageView/zoom, image Hero depuis la carte, navigation native, favori animé et splash bref ; réduction de mouvement respectée pour ces animations.
- Compte : compteurs et dernières opérations privés, réservations à venir, achats, reçus, favoris et suggestions ; accès aux listes et détails.
- Photos de profil : galerie seule, aperçu avant upload, pas de caméra ni permission de stockage Android générale.
- Géolocalisation uniquement sur clic expliqué, premier plan, choix manuel possible après refus.

## Vérifications et limites
Les tests utilisent des repositories simulés ; aucun serveur Laravel nécessaire pour flutter test. Tests unitaires sur montants, budget, session/401/réseau, téléphone, filtres et pagination ; widgets auth/home/catalogue/cartes/SOLD/boutique/favoris/profil/compte ; parcours onboarding/login/retour et refus GPS. Captures Flutter avec fontes et images chargées : test/goldens, 360/390/430/768 px et vues auth/compte. Les fixtures visuelles restent des données de test.

JDK Temurin 21.0.12.1 portable installé dans ../.tools/mobile-android/java, archive officielle Adoptium vérifiée par SHA-256. Le poste Windows n’a pas encore de SDK Android/émulateur ni Xcode. Installation du SDK en attente de l’accord client sur la [licence Google](https://developer.android.com/studio#terms). La commande build APK a réellement été tentée et indique No Android SDK found : **APK NOT AVAILABLE**. Aucun APK ni compilation iOS revendiqués. Stockage sécurisé natif, sélecteur de photos et permission GPS physique doivent être vérifiés sur appareil une fois cet outillage disponible.

Après installation autorisée du SDK dans ../.tools/mobile-android/sdk, tool/android.ps1 configure Java/Android/Gradle pour le processus courant uniquement : ./tool/android.ps1 -Action doctor ; ./tool/android.ps1 -Action devices ; ./tool/android.ps1 -Action build ; ./tool/android.ps1 -Action run -DeviceId IDENTIFIANT. ApiBaseUrl est configurable pour émulateur ou appareil LAN. Ce script n’accepte aucune licence et n’installe aucun SDK.

Contrôle d’intégration distinct, local DEMO uniquement, crée une identité synthétique dédiée, met à jour son profil/avatar et ajoute/retire son favori, puis révoque le jeton ; zéro transaction :

```powershell
dart run -DAPI_BASE_URL=http://127.0.0.1:8000/api/v1 tool/api_smoke.dart
```

Résultat vérifié : **13 contrôles PASS**, qa/api-smoke.json. Ne pas exécuter ce script contre une production. État final et nombre de tests : ../docs/CURRENT_STATUS.md.

Régénération des ressources natives à partir des assets validés : dart run flutter_launcher_icons, puis dart run flutter_native_splash:create. Après modification des paramètres splash, actualiser la copie iOS Info-Debug.plist en conservant son exception HTTP réservée au Debug. Android/iOS ne contiennent plus l’icône Flutter.


## Transactions et Reverb — 8B
Les routes protégées /vehicle/:slug/reserve et /vehicle/:slug/buy remplacent les écrans préparatoires. Historique, confirmation et reçus natifs sont accessibles depuis le compte. Retrait personnel, personne mandatée ou demande de livraison : contact et rendez-vous obligatoires, adresse requise pour livraison, GPS facultatif. Réservation/vente toujours en attente de confirmation professionnelle ; paiement DEMO et statut métier affichés séparément.

Nouvelles dépendances verrouillées : share_plus 12.0.2 pour la feuille de partage, path_provider 2.1.6 pour les documents privés de l'application, timezone 0.11.1 pour les fuseaux boutiques, web_socket_channel 3.0.3 pour le protocole Reverb existant, clock 1.1.3 pour les dates testables. Aucun SDK de paiement ni service push. Partage/lecture/impression via les cibles système proposées par l'appareil ; aucun PDF créé par Flutter.

Le téléchargement reçoit le PDF Laravel en Bearer, vérifie son type et sa signature, puis l'enregistre dans bolidemarket-receipts du répertoire documents de l'application. Pas de permission générale de stockage. Fichiers purgés à la déconnexion/changement d'identité ; partage d'un fichier d'une ancienne session refusé. Ne pas utiliser un lien PDF public contenant un token.

Reverb est facultatif pour REST. Lire uniquement REVERB_APP_KEY (clé publique) depuis la configuration locale ; ne jamais embarquer REVERB_APP_SECRET. Souscription privée autorisée par l'API Bearer ; canal marketplace pour véhicules, private-user.{id} pour opérations. Signaux dédupliqués et regroupés avant relecture HTTP ; reconnexion, arrêt arrière-plan et fermeture au changement d'identité. Les notifications sont locales dans l'application ; aucune notification push.

Exemple Android Emulator :

```powershell
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1 --dart-define=REVERB_APP_KEY=CLE_PUBLIQUE_LOCALE --dart-define=REVERB_HOST=10.0.2.2 --dart-define=REVERB_PORT=8080
```

Appareil physique : remplacer les deux hôtes par l'IP LAN du PC et rendre les serveurs existants accessibles sur cette interface. REVERB_ORIGIN vaut http://localhost pour les origines locales autorisées ; ajuster avec la configuration du serveur. Production future : API HTTPS, REVERB_TLS=true, hôte/port WSS et origine autorisée. Aucune clé privée dans les dart-defines.

Le helper tool/android.ps1 accepte maintenant ReverbAppKey, ReverbHost, ReverbPort et le switch ReverbTls, en plus de ApiBaseUrl/DeviceId. Exemple après disponibilité du SDK : ./tool/android.ps1 -Action run -ReverbAppKey CLE_PUBLIQUE_LOCALE -ReverbHost 10.0.2.2. Sans clé publique, le transport reste désactivé et REST fonctionne.

État vérifié : flutter analyze PASS, 88 tests PASS, 22 contrôles API/Reverb réels PASS. APK **NOT AVAILABLE** (SDK absent), partage Android et parcours sur appareil non vérifiés. Plan, preuves et cinq scénarios natifs restants : [MOBILE_TRANSACTION_TESTING](../docs/MOBILE_TRANSACTION_TESTING.md). Captures 8B en français : test/goldens/8b-*.png (horloge fixe, photos officielles DEMO). Aucun changement aux trois clients Vue, backend, logo ou Hero.

## VISUAL QA

Contrôle permanent des vrais écrans et composants, sans Laravel, uniquement en debug/development. La galerie `/dev-preview` permet Guest / Client connecté et l’accès aux étapes de réservation/achat/reçu ; un petit bouton ramène à la galerie depuis chaque écran. Données fictives en mémoire, aucune transaction réelle. `VISUAL_DEMO=false` conserve les repositories API et le comportement existants ; release/profile ne peuvent pas activer le bypass.

```powershell
flutter run -d chrome --web-port=5176 --dart-define=VISUAL_DEMO=true
# Compatibilité vérifiée sur ce poste (Flutter 3.47.6 / Chrome 155, timeout du nouveau DDC) :
flutter run -d chrome --web-port=5176 --dart-define=VISUAL_DEMO=true --no-web-experimental-hot-reload

flutter devices
flutter emulators
flutter emulators --launch <id>
flutter run -d <device-id> --dart-define=VISUAL_DEMO=true

flutter test test/preview_routes_test.dart --dart-define=VISUAL_DEMO=true
flutter build web
# Pour un export de QA local (release désactive toujours la démo) :
flutter build web --debug --dart-define=VISUAL_DEMO=true
```

Sur ce poste, appeler Flutter via `& ../.tools/flutter/bin/flutter.bat` si le PATH n’est pas configuré. Aperçu : http://localhost:5176/#/dev-preview. Le Web utilise le téléchargement navigateur comme repli PDF ; les services Android/iOS existants sont conservés. SDK/émulateur Android actuellement indisponibles.

Avant de clore toute phase mobile, parcourir la [checklist VISUAL QA](../docs/MOBILE_VISUAL_QA.md) et contrôler 390 × 844, 360 × 800 et 430 × 932. Captures réelles : [docs/screenshots/mobile](../docs/screenshots/mobile/).

# Design system — traduction des maquettes validées
Autorité visuelle : [références](references/README.md). Mesures ci-dessous = conventions d'implémentation proposées, à ajuster optiquement pour reproduire ces visuels, sans nouveau design.

## Fondations
Palette : [BRAND_GUIDELINES](BRAND_GUIDELINES.md), aucune duplication de couleurs alternatives.
Display : Sora 600/700 ; interface : Inter 400/500/600/700, avec fichiers de licence conservés. Ces familles guident les textes UI ; elles ne remplacent pas le wordmark fourni.

| Style | Desktop taille/interligne | Mobile |
|---|---|---|
| H1 | 64/68 px, 700 | 36/40 px |
| H2 | 40/46 px, 700 | 28/34 px |
| H3 | 24/30 px, 600 | 22/28 px |
| Body | 16/24 px | 16/24 px |
| Specs / boutons | 14/20 px | 14/20 px |
| Caption | 12/18 px | 12/18 px |
| Prix | 24/30 px, 700 | 20/26 px |

Grille desktop cible 1440×900 : 12 colonnes, marges 72 px, gouttières 24 px. Espacements : 4, 8, 12, 16, 24, 32, 48, 64, 96 px. Sections : 80–96 px de respiration comme point de départ.
Radius : 6 px pour boutons/champs/cartes ; pastille pour badges ; conserver les exceptions visibles sur la référence. Ombre discrète sur clair (décalage 4 px, flou 16 px, Carbon à 6 %), quasi absente sur sombre. Icônes linéaires homogènes 20–24 px.

## Composants
| Composant | Règle |
|---|---|
| CTA principal | Fond Orange, texte Carbon ; hauteur 48–56 px ; un objectif principal par groupe |
| Secondaire | Contour et texte adaptés au fond ; Espace professionnel secondaire dans le header |
| Input | Label visible, état focus contrasté, message d'erreur textuel, valeur conservée après erreur |
| Recherche hero | **Offre Acheter/Louer → Marque → Type de véhicule → Localisation → Rechercher** |
| Carte véhicule | Photo, favori, badge **À vendre/À louer**, modèle, specs, prix/unité, localisation/distance, professionnel |
| Prix | Entier formaté avec espaces ; **18 500 000 FCFA** ou **45 000 FCFA / jour** ; devise explicite |
| Disponibilité | Distincte de l'intention de vente/location ; jamais remplacer À louer par un simple Disponible |
| Badge | Ivory/Graphite, texte lisible, point Orange si utile ; pas d'information donnée par la couleur seule |
| Boutique | Cover, identité propre au professionnel, localisation, note, stock, vente/location, lien boutique |
| Pro | Même marque, densité plus forte, tables alignées, statut textuel ; chiffres selon MASTER_SPEC |
| Navigation | Transparente au hero, Carbon légèrement opaque au scroll ; ne pas masquer le focus |
| État vide/chargement | Même géométrie, texte utile et action de reprise ; aucun prix ou stock fictif en production |

Le budget location signifie prix/jour ; dates dans le parcours de location. L'ajout fonctionnel de dates ne justifie pas de redessiner le hero validé.
Favoris accessibles au clavier ; zones interactives d'au moins 44 px ; photos avec alternatives pertinentes. Vérifier les contrastes dans les écrans réels, en particulier liens orange sur Ivory : adapter le traitement/underline, pas la palette.
Ne pas afficher une note fictive dans une boutique réelle ; séparer « aucun avis » d'une note nulle.

## Responsive et hiérarchie
1920 : contenu centré, photos étendues. 1366 : marges 40–48 px, titre réduit sans changer l'ordre. Tablette : 2 colonnes. Mobile : 1 colonne ou carousel lisible, recherche empilée, menu compact. Pas de mise à l'échelle globale d'une capture.

## Motion
Hero : vidéo muette en boucle automatique, image de remplacement, sans bouton lecture/pause (décision client du 26 septembre 2026) ; titre 600 ms, décalage 90 ms par ligne ; CTA 300 ms ; recherche 450 ms/16 px.
Cartes : 320 ms/12 px, décalage 50 ms ; parallaxe éditoriale 8–12 px max ; pins 100 ms d'écart ; compteurs 800 ms ; Pro 500 ms/20 px ; téléphone flottant 3–4 px sur 6 s ; hover/focus 150 ms.
Une apparition par section, aucun scroll détourné. En réduction de mouvements : contenu direct, image fixe, sans parallaxe ni compteurs animés.

## Contrôle visuel
Comparer aux images aux dimensions cibles et vérifier hero, ordre des champs, typographie, prix de location, diversité des véhicules, logo et mentions de démonstration. Les sources raster ne garantissent pas les petits textes : les règles métier documentées font foi.

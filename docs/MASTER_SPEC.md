# Cahier des charges produit
Statut : identité et maquettes validées ; comportements fonctionnels ci-dessous proposés pour le MVP, sauf mentions « validé ».

## Vision et périmètre
BolideMarket rapproche clients et professionnels automobiles dans plusieurs pays : trouver, comparer, acheter ou louer un véhicule et explorer la boutique du professionnel. Premium accessible, international ; Abidjan est le marché de démonstration, pas une restriction de la marque.

**Validé :** nom, slogan, Direction A, palette, logo Triade, landing et aperçus associés, sélecteur Acheter/Louer, badges À vendre/À louer, prix de location par jour, gamme diversifiée.
**MVP proposé :** catalogue multi-professionnels, comptes, favoris, comparaison, demande d'achat, réservation de location, gestion Pro, notifications, suivi des paiements et reçus.
**Différé :** financement, assurance, enchères, orchestration et tarification automatique de livraison, panier multi-vendeurs, conversion automatique des devises, chat et suivi GPS en direct. Aucun paiement réel avant choix du prestataire et des règles commerciales.

## Modules
| Module | Périmètre |
|---|---|
| Landing | Les 16 sections validées : navigation, hero, recherche, avantages, offres, achat/location, catégories, boutiques, carte, étapes, Pro, mobile, international, chiffres, CTA et footer |
| Marketplace | Recherche, filtres, tri de proximité, détail véhicule, favoris, comparaison jusqu'à 3 offres, boutique publique |
| Client | Profil, demandes d'achat, réservations, commandes, notifications et reçus personnels |
| Pro | Profil professionnel, boutiques, parc, photos, prix, publication, disponibilité, réservations, ventes et indicateurs |
| Administration | Vérification des professionnels, modération, supervision ; pas de nouveau design d'admin dans cette phase |
| Mobile | Même API et règles, recherche et parcours client ; aperçu validé utilisé comme référence |
| Services | Géolocalisation, notifications et temps réel, paiements, reçus PDF |

## Rôles proposés
- Visiteur : consulte les offres et boutiques publiées ; localisation manuelle possible.
- Client authentifié : favoris, commandes/réservations, avis après transaction terminée, reçus.
- Professionnel : peut également acheter comme client ; gère uniquement les commerces auxquels il appartient.
- Membre Pro : rôle `owner` ou `manager` limité à un professionnel ; transferts de propriété hors MVP.
- Administrateur : vérifie/modère ; toute intervention sensible est auditée. Ce rôle n'est jamais attribuable par inscription publique.
Un professionnel en attente peut compléter son dossier ; seule une organisation approuvée peut publier.

## Achat — proposition MVP
Une commande concerne un véhicule et un professionnel. Le serveur fige modèle, vendeur, prix, devise et conditions au moment de la demande. Une retenue courte évite deux confirmations simultanées ; le professionnel confirme ou annule. La livraison effective termine la vente et marque le véhicule vendu. Paiement et exécution sont deux états distincts : une commande confirmée n'est pas une preuve de paiement. Voir [les états](DATABASE.md).

## Location — proposition MVP
Dates de début/fin obligatoires, timezone de la boutique, intervalle [début, fin[. Prix journalier entier et devis serveur ; minimum 1 jour, arrondi supérieur par période de 24 h comme règle initiale **à confirmer avant activation commerciale**. Les retenues expirables, réservations confirmées/actives et indisponibilités bloquent les dates correspondantes. Réservation et vente d'un même véhicule ne peuvent se contredire ; verrou transactionnel commun.

Le professionnel confirme, remet le véhicule puis clôture son retour. Caution, annulation, pénalités, fiscalité et remboursement : décisions ouvertes ; aucun montant arbitraire codé en production.

## Marketplace et géolocalisation
- Filtres : offre sale/rent, marque, modèle, type, budget, année, carburant, transmission, ville, rayon ; dates en location.
- Sans coordonnées : sélection manuelle de ville ; ne pas inventer une distance personnelle.
- Phase 3B validée pour implémentation : contexte manuel (`location_mode=rank`) → même commune, même ville, même pays, puis autres pays ; distance croissante à niveau égal si coordonnées fournies. Coordonnées seules ou tri distance explicite → distance croissante. Égalités : publication récente puis ID. Distance géographique indicative, jamais un temps routier ; aucun pays prioritaire codé en dur.
- Localisation de référence : celle du véhicule, héritée de la boutique si non renseignée. Une seule origine par requête.
- Seuls véhicules publiés d'un professionnel approuvé sont publics ; dates et blocages filtrent les locations.
- Chaque annonce affiche l'intention, le prix avec devise/unité, la localisation et le professionnel.
- Pays, langue et devise sont indépendants du logo ; interface initiale française, traduction préparée.

## Temps réel, paiements, reçus
- Événements : changement d'offre/disponibilité, réservation, commande et notification ; pas de position GPS continue.
- L'API décide, le temps réel avertit. Après reconnexion, relire l'état et dédupliquer les événements.
- Paiements reliés à une commande ; simulations explicitement isolées. Confirmation signée/idempotente côté serveur.
- Reçu lié à un encaissement confirmé, accessible au client et au professionnel concernés ; numéro unique et données figées. Un reçu de démonstration porte une mention claire. Ce document n'est pas automatiquement une facture fiscale.

## Invariants et acceptation
1. Aucune lecture/écriture privée entre professionnels ou clients sans autorisation.
2. Une unité physique de véhicule ; jamais deux allocations incompatibles pour cette unité.
3. Prix et totaux calculés côté serveur ; montant entier + devise ; aucune addition multidevise.
4. Dashboard, KPI, graphiques et exports utilisent le même filtre, périmètre et instant de référence.
5. Total du parc non supprimé = disponibles + loués + vendus + autres ; catégories exclusives.
6. Avis uniquement après expérience terminée ; « vérifié » seulement si vérification effective.
7. Références visuelles inchangées ; réutilisation directe du logo, pas de génération de nouvelles variantes.
8. Toute donnée factice est marquée et exclue des métriques réelles.

## Démonstration figée
Parc Pro : **140 = 16 disponibles + 28 loués + 84 vendus + 12 autres**.
Ventes cumulées Jan–Juin : **12, 24, 39, 52, 68, 84** ; la liste visible contient 3 exemples sur 140.
Offres repères : RAV4 à vendre 18 500 000 FCFA ; Peugeot 208 à louer 45 000 FCFA/jour ; C300 à vendre 28 900 000 FCFA ; Kangoo à louer 35 000 FCFA/jour ; électrique bleue dans l'aperçu mobile.
+1 200 véhicules / +80 professionnels / 5 pays : chiffres marketing de démonstration uniquement, distincts du parc Pro.
Copyright des maquettes : © 2026 BolideMarket.

## Décisions ouvertes
À trancher au dernier moment utile, sans bloquer la documentation : versions exactes et domaines (phase 1), prestataire de cartes, processus de vérification Pro, conditions de réservation, paiements/cautions et pays de lancement réel (avant transactions réelles), règles locales des documents et conservation (avant reçus de production).

## Règles de simulation appliquées — phase 5B.1 / demo-v1
Ces conventions permettent uniquement les essais locaux demandés ; elles ne fixent pas les conditions commerciales de production. Les paiements sont exclusivement DEMO, sans prestataire ni donnée bancaire, sur véhicules et boutiques marqués démo. Le serveur refuse la simulation hors local/testing.

- Location : intervalle [début, fin[, dates civiles interprétées dans le fuseau de la boutique ou instants ISO explicites ; départ au plus tôt aujourd'hui dans ce fuseau. Arrondi supérieur par période de 24 heures, minimum 1 jour, maximum 365 jours ; frais fixés à zéro pour la démonstration. Montants entiers et devise figés côté serveur.
- Devis valable 5 minutes et retenue PENDING de 15 minutes, configurables. Une retenue expirée ne bloque plus. La confirmation professionnelle rend le blocage durable et enregistre un paiement DEMO réussi dans la même transaction.
- Réservation PENDING annulable ; CONFIRMED annulable avant le départ ; ACTIVE, COMPLETED et autres états finaux non annulables. Remise pendant la période seulement, puis retour explicite ; une réservation future ne modifie pas le statut courant du véhicule.
- Achat : PENDING → CONFIRMED → FULFILLED ; annulation possible avant livraison. SOLD uniquement à la livraison. Une confirmation bloque toute allocation incompatible. États de paiement distincts de ceux de la commande.
- Annulation après confirmation : allocation libérée, commande annulée, paiement DEMO déjà payé conservé comme historique. Aucun remboursement financier ou simulé dans ce lot ; cette limite doit rester explicite dans les futures interfaces.
- Identité client, prix, jours, devise, propriété professionnelle et collisions vérifiés côté API. Créations idempotentes et verrou commun véhicule ; aucune décision de disponibilité uniquement côté client.

Les contrats exacts et limites livrées sont dans API et DATABASE. PDF, temps réel, paiements réels et interfaces client Vue restent hors 5B.1.

## UX / PRODUCT POLISH 1 — règles validées (28 septembre 2026)
- Le pays du profil client est un code ISO relié au référentiel existant. Le téléphone est validé pour ce pays et enregistré en E.164 ; ville/commune facultatives et cohérentes.
- La devise officielle de chaque annonce est celle du pays de sa boutique : CI/SN → XOF, FR/BE → EUR, US → USD, CA → CAD. Pas de choix arbitraire par véhicule ni conversion FX. Le pays d’une boutique ayant déjà des véhicules ne change pas.
- Le catalogue privilégie par défaut le pays du profil ; CI par défaut en démonstration. Un choix explicite de pays, « Tous les pays » ou une position GPS reste prioritaire. Un véhicule étranger garde son prix officiel.
- Inscription Pro distincte en quatre étapes : personne, boutique, visuels facultatifs, résumé. Création atomique utilisateur merchant + organisation + membre propriétaire + boutique ; jamais admin. Hors démo : pending et boutique draft ; en démo effective : approved/published.
- APP_DEMO_MODE ne peut lever la vérification e-mail que pour local/testing/demo. La production conserve la vérification même si le drapeau est activé. Les comptes seedés restent vérifiés.
- Le compte client dispose d’un accès Marketplace permanent, d’un profil éditable avec avatar et d’une déconnexion serveur. Logo, palette et direction A inchangés. Le dashboard professionnel complet reste une phase ultérieure.

## Phase 6A — décisions d’implémentation
Le dashboard Pro Vue est branché sur l’API commune, avec sélection de boutique, gestion du parc/photos/publication, opérations DEMO, clients dérivés, boutique/horaires et profil. La maquette définit la composition et l’identité ; les chiffres affichés proviennent de la base, pas du parc fictif de 140. Le graphique représente les ventes livrées par mois sur six mois UTC.

Le parc conserve la partition available/rented/sold/other ; les réservations futures sont suivies séparément et maintenance reste incluse dans other. Aucune commande manuelle ne peut forcer vendu/loué. Les prix sont inférés du pays boutique, sans conversion. Les photos supportent JPG/PNG/WebP ; seule la photo principale est réorganisable via le contrat actuel. Les horaires ont une plage par jour. Les clients sont uniquement ceux ayant une réservation ou une vente dans la boutique, sans double compte de la commande rental.

Les liens Pro/marketplace partagent la session locale et permettent toujours de revenir à l’annonce ou boutique publique. Reverb et la synchronisation client/Pro constituent le prochain lot 6B, seulement sur nouvelle autorisation.


## Complément validé — identité Pro, négociation et remise (4 octobre 2026)
- L’espace Pro utilise le logo et la couverture de la boutique active, fournis à l’inscription. Modifier cette photo dans le profil Pro met à jour le même logo public ; les coordonnées personnelles restent celles du compte.
- Les trois clients web désactivent le rebond vertical aux limites de page. Direction A, logo, palette, composition et Hero sont conservés.
- Le professionnel peut activer la négociation sur une annonce de vente (ou vente + location), désactivée par défaut. Le client propose un prix positif inférieur au prix public, dans sa devise. **Parcours choisi par le client : proposition séparée → acceptation/refus du vendeur → achat au prix accepté.**
- Une proposition pending dure 24 h ; après acceptation, l’achat est possible pendant 24 h. Une seule proposition pending/accepted non expirée par client et véhicule. Accepter ne réserve pas le véhicule, ne crée ni vente ni paiement. Le serveur vérifie disponibilité, identité et version de l’annonce à l’achat ; une proposition utilisée ne peut pas être réutilisée, même après annulation.
- Avant tout nouvel achat, le client choisit retrait personnel, retrait par personne mandatée ou demande de livraison par chauffeur. Nom/téléphone du contact et date/heure approximative sont requis. Livraison : ville et adresse/repère requis, position GPS facultative (permission uniquement sur clic). Les données sont privées et visibles dans le détail de vente du client et du professionnel autorisé.
- Le rendez-vous est interprété dans le fuseau de la boutique, validé futur et conservé en UTC. Il reste à confirmer avec le professionnel. Ce lot collecte une demande de livraison : aucune affectation de chauffeur, aucun suivi GPS continu, aucune tarification ou promesse de livraison automatique. Paiements DEMO et états existants inchangés.


## Phase 7 — reçus de démonstration (5 octobre 2026)
Reçu de vente ou location après paiement DEMO réussi à la confirmation professionnelle. Une transaction payée → un reçu, données historiques immuables, aucune conversion. Consultation HTML, téléchargement PDF serveur et ouverture pour impression privés côté client et Pro. Contenu DEMO explicite, sans facture fiscale, TVA inventée, QR ni page publique. Annulation sans remboursement conserve le document historique. Le détail et les confirmations montrent les actions seulement lorsqu’un reçu existe.

## Phase 8A — client mobile (5 octobre 2026)
Onboarding bref, inscription client, connexion/restauration/déconnexion, exploration automobile, choix de localisation, filtres/tris, véhicule, boutique, favoris et profil/avatar sur les endpoints existants. Données/prix/statuts serveur, aucune conversion. Compte inclut ses résumés en lecture seule et conserve un accès permanent au marché. Acheter/Réserver et les routes historiques transactionnelles sont préparés avec message explicite ; aucune transaction mobile, paiement, reçu ou temps réel n’est activé dans ce lot. Validation native à compléter sur appareil avant le lot 8B.

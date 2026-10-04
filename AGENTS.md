# BolideMarket — instructions permanentes

## Avant d'agir
1. Lire `docs/CURRENT_STATUS.md`, puis `README.md` et les sections pertinentes de `docs/MASTER_SPEC.md`.
2. Lire uniquement les documents utiles à la tâche : architecture pour le socle ; DATABASE/API pour le backend ; BRAND_GUIDELINES/DESIGN_SYSTEM et les images concernées pour l'UI.
3. Inspecter les fichiers et changements existants avant toute modification. Ne pas recréer un module déjà présent.
4. Respecter la portée de la demande active. Les documents de planification n'autorisent pas à exécuter toutes les phases.

## Autorité et identité
- Les instructions explicites du client priment. Les décisions produit validées sont dans MASTER_SPEC ; les références visuelles officielles sont dans `docs/references/`.
- Direction A — Performance Premium, logo Triade MBK, nom BolideMarket, slogan « Achetez. Louez. Roulez. » et palette sont figés.
- Ne pas redessiner le logo, régénérer les maquettes, changer la DA ou proposer des variantes sans demande explicite.
- Réutiliser les fichiers officiels directement. Les anciens dossiers `livrables-*` et les directions B/C sont des archives, pas des alternatives.
- Les valeurs métier documentées priment sur les petits textes approximatifs des images ; préserver leur composition.

## Travail et économie de contexte
- Travailler directement dans les fichiers ; ne pas coller leurs contenus complets dans le chat.
- Lire de façon ciblée, utiliser les liens entre documents, éviter les relectures intégrales et la duplication de règles.
- Ne pas installer de framework, outil ou dépendance pour une tâche seulement documentaire.
- Réutiliser les composants et règles métier ; aucune règle de disponibilité, paiement ou autorisation uniquement côté client.
- Réponses courtes : résultat, vérification, prochaine étape ou blocage concret.
- Ne jamais afficher de secrets ni créer de données réelles à partir des données de démonstration.

## Cohérence technique
- Une API Laravel, MySQL ; Vue pour les trois clients web, Flutter pour le mobile.
- Toute ressource professionnelle est autorisée selon l'appartenance au professionnel ; un identifiant fourni par le client ne prouve pas la propriété.
- Montants entiers en unités mineures avec devise explicite ; aucune somme entre devises.
- Création/confirmation de réservations et ventes : transactions et verrou du véhicule commun, suivant DATABASE.
- Un paiement n'est confirmé que par une preuve serveur ou un enregistrement manuel autorisé et audité.
- Tout événement temps réel part après validation transactionnelle ; l'API reste la source de vérité.

## Fin de tâche
- Exécuter les vérifications pertinentes ; ne pas inventer des résultats de tests.
- Mettre à jour `docs/CURRENT_STATUS.md` : fait, en cours, prochain pas, tests, blocages.
- Si un contrat change, mettre à jour son document de référence (MASTER_SPEC, DATABASE, API ou ARCHITECTURE), puis ROADMAP si nécessaire.
- Ne pas déclarer une phase terminée sur la seule présence d'un dossier vide.

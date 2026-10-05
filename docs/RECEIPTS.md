# Phase 7 — reçus de démonstration

## Cycle et instantanés
Un paiement DEMO `paid` crée son reçu dans la transaction de confirmation professionnelle (vente et location). Pas de reçu à la simple demande pending. `ReceiptService` verrouille le paiement ; UNIQUE payment_id et order_id interdisent les doublons. Référence BM-RCP-année-ULID. Type enum SALE/RENTAL ; la commande rental relie déjà sa réservation, aucune relation polymorphique supplémentaire.

À la création d’une commande/réservation : buyer_snapshot en liste blanche, enrichissement des snapshots vendeur/véhicule existants. À l’émission : copie de ces instantanés + prix, devise, paiement et période figés. Les profils actuels ne sont jamais relus pour dessiner le document. Une annulation ultérieure conserve le reçu de la simulation payée ; aucun avoir/remboursement dans ce lot.

Modèle Receipt : mise à jour et suppression Eloquent interdites ; aucune API de modification/suppression. Index et clés étrangères protègent les références. Les accès SQL administratifs restent de la responsabilité de l’exploitation.

Historique avant phase 7 : aucun backfill automatique à partir des profils actuels. Si un paiement historique est émis explicitement par le service, les champs acheteur absents portent « coordonnées historiques non enregistrées » ; aucune fausse reconstitution. Le seed du lot crée quatre opérations dédiées avec snapshots complets.

## PDF et impression
Laravel DomPDF 3.1.2 / Dompdf 3.1.6, génération locale à la demande, A4 portrait, DejaVu Sans embarquée. Assets distants, PHP et JavaScript PDF désactivés. Logo horizontal officiel local exporté en JPEG pour éviter de dépendre de GD au rendu ; original inchangé. Aucune photo véhicule distante ni chemin fourni par l’utilisateur.

Les deux SPA partagent le document HTML et les actions dans web-shared. Télécharger utilise l’endpoint backend en attachment ; Imprimer ouvre le même PDF inline dans un onglet, puis l’utilisateur lance l’impression du navigateur. Pas de PDF JavaScript, pas d’iframe comme seule interface, pas d’envoi automatique vers une imprimante.

Chaque document indique REÇU DE DÉMONSTRATION, aucun paiement réel et aucune facture fiscale. Pas de TVA/RCCM fictifs, QR ou vérification publique.

## Accès
Routes et contrats : [API](API.md). Tous les accès sont authentifiés ; client limité à son user_id, Pro à ses memberships et boutique filtrée. Policy pour détail/PDF, mêmes scopes pour listes. PDF privé, no-store, aucun cache public ni URL statique utilisateur. Limite 30 générations/minute.

Les événements existants de confirmation Order/Reservation suffisent à relire les listes et détails ; aucun PDF broadcasté.

## Démonstration et QA
`php artisan migrate`, puis `php artisan db:seed --class=ReceiptDemoSeeder` après les seeds catalogue/clients : deux ventes (XOF/EUR) et deux locations XOF, idempotent, local/testing uniquement. Les véhicules dédiés portent BM-RECEIPT-DEMO ; aucune réinitialisation.

Exemples exportés sous output/pdf : receipt-sale-xof.pdf, receipt-rental-xof.pdf, receipt-sale-eur.pdf. Contrôle visuel Poppler : une page A4 par document, logo, marges, totaux et mentions lisibles. Glyphes FCFA/€/$/CA$ et é/à/è/ç contrôlés avec fixtures sans mutation DB.
Preuve navigateur : marketplace-web/qa/receipts.json ; captures client desktop/mobile et Pro. Deux sessions réelles, liste/détail/download/inline et CTA confirmation testés.

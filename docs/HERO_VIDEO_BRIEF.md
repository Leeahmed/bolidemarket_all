# Vidéo Hero — brief de production

Statut au 26 septembre 2026 : nouvelle vidéo demandée ; génération non lancée, solde insuffisant. Ce document prépare la production, il ne constitue pas une vidéo livrée.

## Sources et périmètre

- Brief utilisateur joint et clarification : créer une nouvelle vidéo conforme au brief.
- `branding logo/direction A.png` : repère de composition seulement. Ne pas l'animer, la recréer ou la fournir comme première image au générateur.
- `branding logo/logo.png` : identité inchangée, aucun logo dans la vidéo.
- `landing-web/public/videos/hero-abidjan.mp4` : fichier existant conservé ; ne pas l'écraser avant contrôle de la nouvelle vidéo.
- Aucun changement de l'interface ou des contrats métier.

## Livraison cible

Une vidéo de 10 secondes, 16:9, au moins 1920 × 1080, 24 ou 30 images/seconde, muette. Animation 3D semi-réaliste publicitaire premium ; une histoire continue. Aucun texte, logo, interface, watermark, marque automobile ou plaque lisible.

## Composition dérivée de la référence

Ces coordonnées normalisées guident la composition ; elles ne modifient pas la maquette.

- Gauche x=0–45 % : zone sombre, peu détaillée, sans personnage ni véhicule principal, pour titre, description et CTA.
- Centre droit x=52–94 %, y=22–70 % : personnage, portière et SUV restent lisibles dans cette zone.
- Haut y=0–10 % : pas d'information narrative indispensable sous la navigation.
- Bas y=75–100 % : chaussée et reflets calmes ; pas d'action indispensable sous la recherche et les avantages.
- Préserver ces zones pendant tout le mouvement, pas uniquement à la première image. Contrôler ensuite le recadrage réel du Hero sur desktop et mobile.

## Prompt de génération

Create a 10-second, horizontal 16:9 luxury automotive commercial designed as a website hero BACKGROUND. Premium semi-realistic 3D animation, almost filmed realism with a restrained artistic CGI signature. Physically coherent lighting, natural skin, believable textile, metallic paint and automotive glass. Contemporary Abidjan-inspired West African metropolis at blue hour, clean credible glass-and-concrete semi-open car showroom directly adjoining a broad modern avenue. A few elegantly parked vehicles, discreet tropical planting, palms and contemporary buildings. Warm architectural lighting balanced with cool evening light. Carbon #0B0D0F, graphite #25282D and ivory #F5F3EE, with only restrained warm accents compatible with #E85D2A.

COMPOSITION IS CRITICAL THROUGHOUT: keep the left 45 percent quiet, dark and low-detail for future white website text. Keep the entire main action in the center-right, approximately x=52–94 percent, y=22–70 percent. Leave the upper 10 percent and lower 25 percent without essential action for future navigation and search overlays. Render ONLY the cinematic background: no actual typography, interface or logo. Maintain a wide composition; do not fill the frame with a close-up.

One consistent adult Black African woman, approximately 28 years old: elegant, confident and natural, dark hair in a neat low bun, ivory tailored blouse, graphite tailored trousers and understated dark shoes. Same face, hair, clothes and body proportions throughout. One consistent unbranded graphite metallic premium crossover: restrained contemporary design, five-spoke wheels, dark leather interior, no recognizable production model, no visible badges. Maintain exact bodywork, wheels, paint and interior continuity.

0–2 seconds: a gentle stabilized lateral dolly reveals the woman already a few steps from the driver's door on the visible side of the parked SUV. She walks naturally toward it, never looking at the camera. Both remain on the right side of the frame.

2–4.5 seconds: she naturally opens the driver's door, sits fully inside and closes the door. Show a coherent physical action, anatomically correct hands, realistic articulation, no teleportation, no body passing through the vehicle. The camera keeps a graceful medium-wide distance, letting the door partly occlude the entry naturally.

4.5–6.5 seconds: only after the door is closed, the same SUV starts smoothly and rolls out through the open showroom driveway onto the directly adjacent avenue. Show enough of the driveway to understand the departure. Safe low speed, no abrupt acceleration.

6.5–9.7 seconds: the stabilized camera gently accompanies the same SUV on the modern avenue, with very light traffic, subtle headlights and controlled blue-hour reflections. Keep the vehicle on the right, keep the left calm. Smooth restrained parallax and modest depth of field, no rapid pans or artificial zooms. The woman remains the driver.

LOOP: use a nearby dark architectural foreground column as a motivated occlusion at the loop boundary. Begin with the trailing edge of an identical softly defocused dark column clearing the lens and end with a matching column moving across the lens in the same direction and at the same speed. The first and last frames should match in darkness, texture and motion so the reset of the story is concealed. Keep this occlusion brief, not a long black screen. Apart from this boundary concealment, preserve the feeling of one continuous shot; no montage or flashy transitions.

No audio, dialogue, captions, text, slogans, logos, watermarks, readable license plates, real car branding, crowd, speeding, dangerous driving or accident. No childish cartoon, Pixar-like style, anime, video-game look, plastic skin, generic futuristic city, European generic setting or stereotyped African imagery. No face changes, morphing car parts, duplicate limbs, malformed fingers, sliding feet, floating tires, vehicle body deformation or direction reversals.

## Préflight et blocage

Outils Higgsfield interrogés le 26 septembre 2026, sans soumission de génération :

- Solde de l'espace privé : **10 crédits**.
- Seedance 2.5, `mode=t2v`, 10 s, 16:9, 1080p, audio désactivé : **120 crédits** estimés.
- Kling 3.0, `mode=pro`, `sound=off`, 10 s, 16:9 : **17,5 crédits** estimés. La fiche du modèle ne garantit pas explicitement les dimensions de sortie : les contrôler après génération.
- MiniMax H3, 10 s, 16:9 : **20 crédits** estimés ; résolution automatiquement ajustée à **2K** par le service.
- Les allocations gratuites signalées concernent Genjutsu/Viral en basse résolution, pas la nouvelle génération demandée en 1080p.

Option de reprise : Seedance pour le paramétrage explicite en 1080p, ou MiniMax H3 à 20 crédits en 2K. Refaire l'estimation et vérifier le solde avant toute soumission. Aucun achat de crédits effectué.

## Contrôle après génération

1. Vérifier durée, résolution, cadence, absence de piste audio et décodage complet.
2. Visionner toute la séquence : entrée dans le véhicule, fermeture de porte avant départ, identité constante, anatomie, roues, décor et absence d'inscriptions.
3. Contrôler chaque seconde sous les zones d'interface et visionner au moins trois boucles ; un raccord demandé dans le prompt n'est pas un raccord validé.
4. Préparer le MP4 H.264 web avec démarrage rapide et un poster issu du résultat approuvé ; conserver le master et l'ancien fichier séparément.
5. Vérifier dans le Hero réel la lisibilité, le recadrage, la pause et le mode de réduction des mouvements avant remplacement.

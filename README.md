# Vitrine+ — version premium

## Lancer
```bash
npm install
npm run dev
```

## Build production
```bash
npm run build
```

Le dossier `dist/` est la version à publier sur l'hébergement IONOS.

## Important avant mise en ligne
1. Remplacer les informations de la page `Mentions légales`.
2. Vérifier que `contact@vitrineplus.fr` est bien la boîte de réception souhaitée.
3. Tester `contact.php` et `audit.php` sur IONOS.
4. Ajouter les vrais projets et témoignages.
5. Ajouter les images/vidéos définitives si souhaité.
6. Vérifier le consentement cookies/RGPD si des outils analytics ou marketing sont ajoutés.
7. Vérifier les tarifs et conditions commerciales avant publication.

## Publication IONOS
Après `npm run build`, envoyer le contenu de `dist/` dans le dossier web racine du domaine. Le fichier `.htaccess` permet à React Router de fonctionner sur les routes internes.

## Campagne Black Friday 2026
- Activation automatique côté affichage : du 19/11/2026 à 00:00 au 30/11/2026 à 23:59, heure de Paris.
- Désactivation automatique à partir du 01/12/2026 à 00:00, heure de Paris.
- Remise de 40 % sur START, GROW et SCALE uniquement.
- V+ Care et les abonnements récurrents restent exclus.
- Les bannières d'accueil et Solutions ainsi que les prix remisés utilisent `src/lib/blackFridayPromo.ts`.
- Le site ne réalise pas de paiement en ligne dans cette intégration : les prix affichés doivent être confirmés dans le devis avant acceptation.

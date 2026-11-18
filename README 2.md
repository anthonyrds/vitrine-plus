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
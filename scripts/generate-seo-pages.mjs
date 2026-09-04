import fs from "node:fs";
import path from "node:path";

const dist = path.resolve("dist");

const SITE_URL = "https://vitrineplus.fr";

const pages = {
  "/": {
    title:
      "Vitrine+ — Création de sites internet & visibilité",
    description:
      "Vitrine+ crée des sites internet professionnels et accompagne les entreprises en SEO, visibilité et conversion.",
    h1:
      "Votre entreprise. En mieux.",
    intro:
      "Nous créons des présences digitales qui donnent envie de vous choisir et qui transforment votre site en véritable outil commercial.",
  },

  "/creation-site-internet": {
    title:
      "Création de site internet pour entreprise | Vitrine+",
    description:
      "Vitrine+ crée des sites internet professionnels, rapides et pensés pour le SEO, l'expérience utilisateur et la conversion.",
    h1:
      "Création de site internet professionnel",
    intro:
      "Vitrine+ conçoit des sites internet professionnels qui associent stratégie, design, performance, référencement et conversion.",
    sections: [
      "Création et refonte de site internet",
      "Un site pensé pour convertir",
      "Une base SEO solide",
      "Un site rapide et adapté au mobile",
    ],
  },

  "/services": {
    title:
      "Services — Création de sites, SEO & accompagnement | Vitrine+",
    description:
      "Découvrez les services Vitrine+ : création de sites internet, SEO, acquisition, identité digitale, réseaux sociaux et accompagnement technique.",
    h1:
      "Des services digitaux pensés pour votre entreprise",
    intro:
      "Vitrine+ réunit création web, SEO, visibilité, acquisition et accompagnement pour construire une présence digitale cohérente.",
  },

  "/services/web": {
    title:
      "Conception & refonte web — UX, performance & SEO | Vitrine+",
    description:
      "Vitrine+ conçoit et refond des expériences web sur mesure : UX/UI, développement, performance, SEO technique et conversion.",
    h1:
      "Conception et refonte web",
    intro:
      "Nous concevons des expériences web rapides, premium et pensées autour de l'utilisateur, du référencement et de la conversion.",
    sections: [
      "UX et direction artistique",
      "Développement web moderne",
      "Performance et responsive",
      "SEO technique et conversion",
    ],
  },

  "/services/seo": {
    title:
      "SEO & référencement naturel — Gagnez en visibilité | Vitrine+",
    description:
      "Vitrine+ améliore votre visibilité dans Google grâce au SEO technique, au référencement local, aux contenus et à l'optimisation continue.",
    h1:
      "SEO et référencement naturel",
    intro:
      "Nous travaillons la structure, la technique, les contenus et les signaux de visibilité pour aider votre entreprise à être mieux trouvée.",
    sections: [
      "Audit SEO",
      "SEO technique",
      "Contenus et sémantique",
      "Référencement local",
    ],
  },

  "/services/maintenance": {
    title:
      "Maintenance de site internet — V+ Care | Vitrine+",
    description:
      "V+ Care assure la maintenance, la sécurité, les sauvegardes et l'évolution de votre site internet après sa mise en ligne.",
    h1:
      "Maintenance de site internet",
    intro:
      "Votre site reste suivi, sécurisé et évolutif après sa mise en ligne grâce à l'accompagnement V+ Care.",
  },

  "/services/ia": {
    title:
      "IA & automatisation — Gagnez du temps | Vitrine+",
    description:
      "Vitrine+ accompagne les entreprises dans l'intégration de l'intelligence artificielle et l'automatisation des processus pour gagner du temps.",
    h1:
      "IA et automatisation",
    intro:
      "Nous identifions les usages où l'intelligence artificielle et l'automatisation peuvent réellement simplifier votre activité.",
  },

  "/realisations": {
    title:
      "Réalisations — Projets web & identité digitale | Vitrine+",
    description:
      "Découvrez les projets conceptuels de Vitrine+ : création de sites internet, webdesign, identité visuelle, SEO et expériences digitales.",
    h1:
      "Réalisations digitales",
    intro:
      "Découvrez notre approche à travers des projets web et des directions créatives conçus pour servir une identité et un objectif commercial.",
  },

  "/solutions": {
    title:
      "Tarifs & solutions — Création de site internet | Vitrine+",
    description:
      "Découvrez les solutions START, GROW et SCALE de Vitrine+ pour créer, développer et faire évoluer votre présence digitale.",
    h1:
      "Une solution adaptée à votre ambition",
    intro:
      "Des solutions pensées pour lancer, développer ou structurer votre présence digitale.",
  },

  "/a-propos": {
    title:
      "À propos de Vitrine+ — Agence digitale indépendante",
    description:
      "Vitrine+ est une agence digitale indépendante qui transforme les présences en ligne en outils commerciaux : stratégie, web, SEO et conversion.",
    h1:
      "Une agence digitale indépendante",
    intro:
      "Vitrine+ accompagne les entreprises avec une approche mêlant stratégie, création, technologie, SEO et conversion.",
  },

  "/audit": {
    title:
      "Audit digital gratuit — Analyse complète de votre site | Vitrine+",
    description:
      "Analysez gratuitement votre site internet : SEO, structure, mobile, contenu, performance et partage social.",
    h1:
      "Audit digital gratuit",
    intro:
      "Analysez les principaux facteurs qui influencent la visibilité, la crédibilité et la conversion de votre site internet.",
    sections: [
      "SEO",
      "Structure",
      "Mobile",
      "Contenu",
      "Performance",
      "Réseaux sociaux",
      "Conversion",
    ],
  },

  "/rendez-vous": {
    title:
      "Prendre rendez-vous téléphonique — Vitrine+",
    description:
      "Choisissez directement une date et une heure pour être rappelé par Vitrine+ au sujet de votre projet digital.",
    h1:
      "Prendre rendez-vous",
    intro:
      "Choisissez un créneau pour échanger directement sur votre entreprise, votre site et vos objectifs digitaux.",
  },

  "/contact": {
    title:
      "Contact — Parlons de votre projet | Vitrine+",
    description:
      "Vous avez un projet web, une refonte, un besoin SEO ou une idée à transformer en solution ? Contactez Vitrine+.",
    h1:
      "Parlons de votre projet",
    intro:
      "Un projet web, une refonte, une problématique de visibilité ou une idée à transformer en solution ? Écrivez-nous.",
  },
};

function escapeHtml(value) {
  return value
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

const source = fs.readFileSync(
  path.join(dist, "index.html"),
  "utf8"
);

for (const [route, page] of Object.entries(pages)) {
  const directory =
    route === "/"
      ? dist
      : path.join(dist, route.slice(1));

  fs.mkdirSync(directory, {
    recursive: true,
  });

  const canonical =
    `${SITE_URL}${route === "/" ? "/" : route}`;

  const sections =
    page.sections ?? [
      "Stratégie digitale",
      "Expérience utilisateur",
      "Visibilité et référencement",
      "Conversion",
    ];

  const staticBody = `
    <header>
      <nav aria-label="Navigation principale">
        <a href="/">Vitrine+</a>
        <a href="/services">Services</a>
        <a href="/creation-site-internet">
          Création de site internet
        </a>
        <a href="/services/seo">SEO</a>
        <a href="/realisations">Réalisations</a>
        <a href="/a-propos">À propos</a>
        <a href="/contact">Contact</a>
      </nav>
    </header>

    <main>
      <article>

        <h1>
          ${escapeHtml(page.h1)}
        </h1>

        <p>
          ${escapeHtml(page.intro)}
        </p>

        <img
          src="/og-image.svg"
          alt="Vitrine+ — agence digitale"
          width="1200"
          height="630"
        />

        ${sections
          .map(
            (section) => `
              <section>
                <h2>
                  ${escapeHtml(section)}
                </h2>

                <p>
                  Vitrine+ construit une présence digitale
                  claire, utile et cohérente avec les objectifs
                  de votre entreprise. Chaque projet associe
                  stratégie, expérience utilisateur, performance,
                  visibilité et conversion afin de créer un site
                  réellement utile à votre activité.
                </p>
              </section>
            `
          )
          .join("\n")}

        <section>
          <h2>
            Une présence digitale pensée pour votre entreprise
          </h2>

          <p>
            Votre site internet doit permettre à vos visiteurs
            de comprendre rapidement votre activité, vos services
            et votre valeur. Il doit également inspirer confiance,
            faciliter la prise de contact et accompagner vos
            objectifs commerciaux. C'est pourquoi Vitrine+
            travaille la structure des pages, la hiérarchie des
            contenus, l'expérience mobile, les performances
            techniques et les éléments de conversion dès la
            conception.
          </p>

          <p>
            Le référencement naturel fait également partie des
            fondations du projet. Une structure HTML cohérente,
            des titres pertinents, des contenus compréhensibles,
            des métadonnées correctement définies et un site
            techniquement accessible permettent aux moteurs de
            recherche de mieux comprendre votre activité.
          </p>

          <p>
            Au-delà du référencement, nous cherchons surtout à
            construire une expérience utile pour vos futurs
            clients. Le design doit servir votre positionnement,
            les informations doivent être faciles à trouver et
            les parcours doivent naturellement conduire vers une
            action : demander un devis, prendre rendez-vous,
            contacter votre entreprise ou découvrir vos services.
          </p>
        </section>

        <section>
          <h2>
            Un accompagnement qui va au-delà de la mise en ligne
          </h2>

          <p>
            Un site internet évolue avec une entreprise. Vitrine+
            peut donc intervenir sur la maintenance, la sécurité,
            les évolutions, le SEO, les contenus et les optimisations
            nécessaires au fil du temps. L'objectif est de conserver
            une présence digitale cohérente avec votre activité et
            vos ambitions.
          </p>
        </section>

        <p>
          <a href="/audit">
            Obtenir mon audit digital gratuit
          </a>
        </p>

        <p>
          <a href="/rendez-vous">
            Prendre rendez-vous
          </a>
        </p>

        <p>
          <a href="/contact">
            Contacter Vitrine+
          </a>
        </p>

        <p>
          <a href="mailto:contact@vitrineplus.fr">
            contact@vitrineplus.fr
          </a>
        </p>

      </article>
    </main>

    <footer>
      <a href="/">
        Vitrine+
      </a>

      <a href="/contact">
        Contact
      </a>

      <a href="/mentions-legales">
        Mentions légales
      </a>
    </footer>
  `;

  const html = source
    .replace(
      /<title>[\s\S]*?<\/title>/i,
      `<title>${escapeHtml(page.title)}</title>`
    )

    .replace(
      /<meta name="description"[^>]*>/i,
      `<meta name="description" content="${escapeHtml(
        page.description
      )}" />`
    )

    .replace(
      /<meta name="robots"[^>]*>/i,
      `<meta name="robots" content="index, follow" />`
    )

    .replace(
      /<link rel="canonical"[^>]*>/i,
      `<link rel="canonical" href="${canonical}" />`
    )

    .replace(
      /<meta property="og:title"[^>]*>/i,
      `<meta property="og:title" content="${escapeHtml(
        page.title
      )}" />`
    )

    .replace(
      /<meta property="og:description"[^>]*>/i,
      `<meta property="og:description" content="${escapeHtml(
        page.description
      )}" />`
    )

    .replace(
      /<meta property="og:url"[^>]*>/i,
      `<meta property="og:url" content="${canonical}" />`
    )

    .replace(
      /<meta property="og:image"[^>]*>/i,
      `<meta property="og:image" content="${SITE_URL}/og-image.svg" />`
    )

    .replace(
      /<meta name="twitter:title"[^>]*>/i,
      `<meta name="twitter:title" content="${escapeHtml(
        page.title
      )}" />`
    )

    .replace(
      /<meta name="twitter:description"[^>]*>/i,
      `<meta name="twitter:description" content="${escapeHtml(
        page.description
      )}" />`
    )

    .replace(
      /<meta name="twitter:image"[^>]*>/i,
      `<meta name="twitter:image" content="${SITE_URL}/og-image.svg" />`
    )

    .replace(
      /<div id="root">[\s\S]*?<\/div>/i,
      `<div id="root">${staticBody}</div>`
    );

  fs.writeFileSync(
    path.join(directory, "index.html"),
    html
  );
}

console.log(
  `SEO statique généré pour ${Object.keys(pages).length} routes.`
);
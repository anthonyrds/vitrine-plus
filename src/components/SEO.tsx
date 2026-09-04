import { useEffect } from "react";

type FAQItem = [string, string];

type ServiceSchema = {
  name: string;
  description: string;
};

type SEOProps = {
  title: string;
  description: string;
  canonical?: string;
  image?: string;
  type?: "website" | "article";
  noindex?: boolean;
  service?: ServiceSchema;
  faqs?: FAQItem[];
};

const SITE_URL = "https://vitrineplus.fr";
const DEFAULT_IMAGE = `${SITE_URL}/og-image.svg`;

const ORGANIZATION_ID = `${SITE_URL}/#organization`;
const WEBSITE_ID = `${SITE_URL}/#website`;

const SERVICE_MAP: Record<
  string,
  {
    name: string;
    description: string;
  }
> = {
  "/creation-site-internet": {
    name: "Création de site internet",
    description:
      "Création et refonte de sites internet professionnels pensés pour l’expérience utilisateur, le référencement naturel et la conversion.",
  },

  "/services/web": {
    name: "Conception et refonte web",
    description:
      "Conception et refonte de sites web avec une attention particulière portée au design, à l’expérience utilisateur, aux performances, au SEO et à la conversion.",
  },

  "/services/seo": {
    name: "SEO et référencement naturel",
    description:
      "Accompagnement en référencement naturel pour améliorer la visibilité d’un site internet dans les moteurs de recherche et développer une acquisition durable.",
  },

  "/services/maintenance": {
    name: "Maintenance de site internet",
    description:
      "Maintenance, sécurité, sauvegardes, surveillance et évolution de sites internet après leur mise en ligne.",
  },

  "/services/ia": {
    name: "IA et automatisation",
    description:
      "Solutions d’intelligence artificielle et d’automatisation pour améliorer les processus et les usages numériques des entreprises.",
  },

  "/audit": {
    name: "Audit digital",
    description:
      "Audit digital gratuit permettant d’identifier les principaux points d’amélioration d’un site internet en matière de SEO, structure, contenu, performance, visibilité et conversion.",
  },

  "/solutions": {
    name: "Solutions digitales",
    description:
      "Solutions digitales pour créer, développer et faire évoluer la présence en ligne des entreprises.",
  },
};

const BREADCRUMBS: Record<
  string,
  {
    name: string;
    parent?: {
      name: string;
      url: string;
    };
  }
> = {
  "/creation-site-internet": {
    name: "Création de site internet",
  },

  "/services": {
    name: "Services",
  },

  "/services/web": {
    name: "Conception & refonte web",
    parent: {
      name: "Services",
      url: `${SITE_URL}/services`,
    },
  },

  "/services/seo": {
    name: "SEO & référencement naturel",
    parent: {
      name: "Services",
      url: `${SITE_URL}/services`,
    },
  },

  "/services/maintenance": {
    name: "Maintenance de site internet",
    parent: {
      name: "Services",
      url: `${SITE_URL}/services`,
    },
  },

  "/services/ia": {
    name: "IA & automatisation",
    parent: {
      name: "Services",
      url: `${SITE_URL}/services`,
    },
  },

  "/realisations": {
    name: "Réalisations",
  },

  "/solutions": {
    name: "Solutions",
  },

  "/a-propos": {
    name: "À propos",
  },

  "/audit": {
    name: "Audit digital gratuit",
  },

  "/rendez-vous": {
    name: "Prendre rendez-vous",
  },

  "/contact": {
    name: "Contact",
  },
};

function absoluteUrl(value: string): string {
  if (/^https?:\/\//i.test(value)) {
    return value;
  }

  return `${SITE_URL}${value.startsWith("/") ? value : `/${value}`}`;
}

function normalizePathname(pathname: string): string {
  if (!pathname || pathname === "/") {
    return "/";
  }

  const cleanPath = pathname
    .split("?")[0]
    .split("#")[0];

  return cleanPath.replace(/\/+$/, "") || "/";
}

function normalizeCanonical(value?: string): string {
  const pathname = normalizePathname(
    window.location.pathname
  );

  if (!value) {
    return `${SITE_URL}${pathname}`;
  }

  return (
    absoluteUrl(value).replace(/\/+$/, "") ||
    SITE_URL
  );
}

function upsertMeta(
  selector: string,
  attribute: "name" | "property",
  attributeValue: string,
  content: string
) {
  let element =
    document.head.querySelector<HTMLMetaElement>(
      selector
    );

  if (!element) {
    element = document.createElement("meta");

    element.setAttribute(
      attribute,
      attributeValue
    );

    document.head.appendChild(element);
  }

  element.setAttribute("content", content);
}

function upsertCanonical(url: string) {
  let link =
    document.head.querySelector<HTMLLinkElement>(
      'link[rel="canonical"]'
    );

  if (!link) {
    link = document.createElement("link");

    link.setAttribute("rel", "canonical");

    document.head.appendChild(link);
  }

  link.setAttribute("href", url);
}

function upsertJsonLd(
  id: string,
  data: Record<string, unknown>
) {
  let script =
    document.head.querySelector<HTMLScriptElement>(
      `script[data-seo-id="${id}"]`
    );

  if (!script) {
    script = document.createElement("script");

    script.type = "application/ld+json";

    script.setAttribute(
      "data-seo-id",
      id
    );

    document.head.appendChild(script);
  }

  script.textContent = JSON.stringify(data);
}

function removeJsonLd(id: string) {
  document.head
    .querySelector(
      `script[data-seo-id="${id}"]`
    )
    ?.remove();
}

export default function SEO({
  title,
  description,
  canonical,
  image,
  type = "website",
  noindex = false,
  service,
  faqs,
}: SEOProps) {
  useEffect(() => {
    const pathname = normalizePathname(
      window.location.pathname
    );

    const canonicalUrl =
      normalizeCanonical(canonical);

    const imageUrl = absoluteUrl(
      image || DEFAULT_IMAGE
    );

    const detectedService =
      service || SERVICE_MAP[pathname];

    document.documentElement.lang = "fr";

    document.title = title;

    /*
    |--------------------------------------------------------------------------
    | META DESCRIPTION
    |--------------------------------------------------------------------------
    */

    upsertMeta(
      'meta[name="description"]',
      "name",
      "description",
      description
    );

    /*
    |--------------------------------------------------------------------------
    | ROBOTS
    |--------------------------------------------------------------------------
    */

    upsertMeta(
      'meta[name="robots"]',
      "name",
      "robots",
      noindex
        ? "noindex, nofollow"
        : "index, follow"
    );

    /*
    |--------------------------------------------------------------------------
    | AUTHOR
    |--------------------------------------------------------------------------
    */

    upsertMeta(
      'meta[name="author"]',
      "name",
      "author",
      "Vitrine+"
    );

    /*
    |--------------------------------------------------------------------------
    | OPEN GRAPH
    |--------------------------------------------------------------------------
    */

    upsertMeta(
      'meta[property="og:title"]',
      "property",
      "og:title",
      title
    );

    upsertMeta(
      'meta[property="og:description"]',
      "property",
      "og:description",
      description
    );

    upsertMeta(
      'meta[property="og:type"]',
      "property",
      "og:type",
      type
    );

    upsertMeta(
      'meta[property="og:url"]',
      "property",
      "og:url",
      canonicalUrl
    );

    upsertMeta(
      'meta[property="og:image"]',
      "property",
      "og:image",
      imageUrl
    );

    upsertMeta(
      'meta[property="og:locale"]',
      "property",
      "og:locale",
      "fr_FR"
    );

    upsertMeta(
      'meta[property="og:site_name"]',
      "property",
      "og:site_name",
      "Vitrine+"
    );

    /*
    |--------------------------------------------------------------------------
    | TWITTER / X
    |--------------------------------------------------------------------------
    */

    upsertMeta(
      'meta[name="twitter:card"]',
      "name",
      "twitter:card",
      "summary_large_image"
    );

    upsertMeta(
      'meta[name="twitter:title"]',
      "name",
      "twitter:title",
      title
    );

    upsertMeta(
      'meta[name="twitter:description"]',
      "name",
      "twitter:description",
      description
    );

    upsertMeta(
      'meta[name="twitter:image"]',
      "name",
      "twitter:image",
      imageUrl
    );

    upsertMeta(
      'meta[name="twitter:url"]',
      "name",
      "twitter:url",
      canonicalUrl
    );

    /*
    |--------------------------------------------------------------------------
    | CANONICAL
    |--------------------------------------------------------------------------
    */

    upsertCanonical(canonicalUrl);

    /*
    |--------------------------------------------------------------------------
    | ORGANIZATION
    |--------------------------------------------------------------------------
    */

    upsertJsonLd("organization", {
      "@context": "https://schema.org",
      "@type": "Organization",
      "@id": ORGANIZATION_ID,
      name: "Vitrine+",
      url: SITE_URL,
      logo: `${SITE_URL}/favicon.svg`,
      image: imageUrl,
      description:
        "Agence digitale française spécialisée dans la création de sites internet, le SEO, la visibilité et la conversion.",
      email: "contact@vitrineplus.fr",
      areaServed: {
        "@type": "Country",
        name: "France",
      },
      contactPoint: {
        "@type": "ContactPoint",
        contactType: "customer service",
        email: "contact@vitrineplus.fr",
        availableLanguage: "French",
      },
    });

    /*
    |--------------------------------------------------------------------------
    | WEBSITE
    |--------------------------------------------------------------------------
    */

    upsertJsonLd("website", {
      "@context": "https://schema.org",
      "@type": "WebSite",
      "@id": WEBSITE_ID,
      name: "Vitrine+",
      url: SITE_URL,
      description:
        "Création de sites internet professionnels, SEO, visibilité et conversion pour les entreprises.",
      inLanguage: "fr-FR",
      publisher: {
        "@id": ORGANIZATION_ID,
      },
    });

    /*
    |--------------------------------------------------------------------------
    | WEBPAGE
    |--------------------------------------------------------------------------
    */

    const webpage: Record<string, unknown> = {
      "@context": "https://schema.org",
      "@type": "WebPage",
      "@id": `${canonicalUrl}#webpage`,
      url: canonicalUrl,
      name: title,
      description,
      inLanguage: "fr-FR",
      isPartOf: {
        "@id": WEBSITE_ID,
      },
      about: {
        "@id": ORGANIZATION_ID,
      },
      publisher: {
        "@id": ORGANIZATION_ID,
      },
    };

    if (BREADCRUMBS[pathname]) {
      webpage.breadcrumb = {
        "@id": `${canonicalUrl}#breadcrumb`,
      };
    }

    if (detectedService) {
      webpage.mainEntity = {
        "@id": `${canonicalUrl}#service`,
      };
    }

    if (faqs?.length) {
      webpage.mainEntity = {
        "@id": `${canonicalUrl}#faq`,
      };
    }

    upsertJsonLd("webpage", webpage);

    /*
    |--------------------------------------------------------------------------
    | BREADCRUMB
    |--------------------------------------------------------------------------
    */

    const breadcrumb =
      BREADCRUMBS[pathname];

    if (breadcrumb) {
      const breadcrumbItems: Record<
        string,
        unknown
      >[] = [
        {
          "@type": "ListItem",
          position: 1,
          name: "Accueil",
          item: `${SITE_URL}/`,
        },
      ];

      let position = 2;

      if (breadcrumb.parent) {
        breadcrumbItems.push({
          "@type": "ListItem",
          position,
          name: breadcrumb.parent.name,
          item: breadcrumb.parent.url,
        });

        position++;
      }

      breadcrumbItems.push({
        "@type": "ListItem",
        position,
        name: breadcrumb.name,
        item: canonicalUrl,
      });

      upsertJsonLd("breadcrumb", {
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        "@id": `${canonicalUrl}#breadcrumb`,
        itemListElement: breadcrumbItems,
      });
    } else {
      removeJsonLd("breadcrumb");
    }

    /*
    |--------------------------------------------------------------------------
    | SERVICE
    |--------------------------------------------------------------------------
    */

    if (detectedService) {
      upsertJsonLd("service", {
        "@context": "https://schema.org",
        "@type": "Service",
        "@id": `${canonicalUrl}#service`,
        name: detectedService.name,
        description:
          detectedService.description,
        url: canonicalUrl,
        provider: {
          "@id": ORGANIZATION_ID,
        },
        areaServed: {
          "@type": "Country",
          name: "France",
        },
      });
    } else {
      removeJsonLd("service");
    }

    /*
    |--------------------------------------------------------------------------
    | FAQ PAGE
    |--------------------------------------------------------------------------
    */

    if (faqs?.length) {
      upsertJsonLd("faq", {
        "@context": "https://schema.org",
        "@type": "FAQPage",
        "@id": `${canonicalUrl}#faq`,
        url: canonicalUrl,
        mainEntity: faqs.map(
          ([question, answer]) => ({
            "@type": "Question",
            name: question,
            acceptedAnswer: {
              "@type": "Answer",
              text: answer,
            },
          })
        ),
      });
    } else {
      removeJsonLd("faq");
    }

    /*
    |--------------------------------------------------------------------------
    | CLEANUP
    |--------------------------------------------------------------------------
    */

    return () => {
      // Le composant SEO est réutilisé entre les routes.
      // Les éléments sont volontairement conservés et
      // remplacés lors du prochain rendu.
    };
  }, [
    title,
    description,
    canonical,
    image,
    type,
    noindex,
    service,
    faqs,
  ]);

  return null;
}
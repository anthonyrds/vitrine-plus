import { useEffect } from "react";

type SEOProps = {
  title: string;
  description: string;
  canonical?: string;
  image?: string;
  type?: "website" | "article";
  noindex?: boolean;
};

const SITE_URL = "https://vitrineplus.fr";

export default function SEO({
  title,
  description,
  canonical,
  image,
  type = "website",
  noindex = false,
}: SEOProps) {
  const canonicalUrl = canonical
    ? `${SITE_URL}${canonical.startsWith("/") ? canonical : `/${canonical}`}`
    : window.location.href.split("#")[0];

  useEffect(() => {
    document.title = title;

    const setMeta = (
      selector: string,
      attribute: "name" | "property",
      value: string
    ) => {
      let element =
        document.head.querySelector<HTMLMetaElement>(selector);

      if (!element) {
        element = document.createElement("meta");
        element.setAttribute(attribute, value);
        document.head.appendChild(element);
      }

      element.setAttribute("content", value);
    };

    const setCanonical = (url: string) => {
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
    };

    setMeta(
      'meta[name="description"]',
      "name",
      description
    );

    setMeta(
      'meta[name="robots"]',
      "name",
      noindex
        ? "noindex, nofollow"
        : "index, follow"
    );

    setMeta(
      'meta[property="og:title"]',
      "property",
      title
    );

    setMeta(
      'meta[property="og:description"]',
      "property",
      description
    );

    setMeta(
      'meta[property="og:type"]',
      "property",
      type
    );

    setMeta(
      'meta[property="og:url"]',
      "property",
      canonicalUrl
    );

    if (image) {
      setMeta(
        'meta[property="og:image"]',
        "property",
        image
      );

      setMeta(
        'meta[name="twitter:image"]',
        "name",
        image
      );
    } else {
      document.head
        .querySelector('meta[property="og:image"]')
        ?.remove();

      document.head
        .querySelector('meta[name="twitter:image"]')
        ?.remove();
    }

    setMeta(
      'meta[property="og:locale"]',
      "property",
      "fr_FR"
    );

    setMeta(
      'meta[property="og:site_name"]',
      "property",
      "Vitrine+"
    );

    setMeta(
      'meta[name="twitter:card"]',
      "name",
      "summary_large_image"
    );

    setMeta(
      'meta[name="twitter:title"]',
      "name",
      title
    );

    setMeta(
      'meta[name="twitter:description"]',
      "name",
      description
    );

    setCanonical(canonicalUrl);

    const upsertJsonLd = (
      id: string,
      data: object
    ) => {
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

      script.textContent =
        JSON.stringify(data);
    };

    upsertJsonLd("organization", {
      "@context": "https://schema.org",
      "@type": "Organization",
      name: "Vitrine+",
      url: SITE_URL,
      logo: `${SITE_URL}/favicon.svg`,
      description:
        "Agence digitale française spécialisée dans la création de sites internet, le SEO et la conversion.",
      email: "contact@vitrineplus.fr",
    });

    upsertJsonLd("website", {
      "@context": "https://schema.org",
      "@type": "WebSite",
      name: "Vitrine+",
      url: SITE_URL,
      inLanguage: "fr-FR",
    });
  }, [
    title,
    description,
    canonicalUrl,
    image,
    type,
    noindex,
  ]);

  return null;
}

import {
  ArrowRight,
  Check,
  FileSearch,
  Gauge,
  MapPin,
  Search,
  TrendingUp,
} from "lucide-react";

import { Link } from "react-router-dom";

import PageHero from "../components/PageHero";
import CTA from "../components/CTA";
import SEOHead from "../components/SEO";
import SectionLabel from "../components/SectionLabel";

const pillars = [
  {
    icon: Search,
    title: "SEO technique",
    text: "Nous travaillons la structure technique du site, son indexation, sa hiérarchie, ses métadonnées, ses performances et les éléments qui permettent aux moteurs de recherche de comprendre vos pages.",
  },
  {
    icon: MapPin,
    title: "Référencement local",
    text: "Pour les entreprises qui travaillent sur une zone géographique précise, nous renforçons les signaux qui permettent d'améliorer la visibilité sur les recherches locales.",
  },
  {
    icon: TrendingUp,
    title: "Croissance organique",
    text: "Nous identifions les opportunités de visibilité, suivons les évolutions et faisons évoluer les pages en fonction des recherches réellement pertinentes pour votre activité.",
  },
];

const foundations = [
  "Une architecture de pages claire",
  "Une hiérarchie H1, H2 et H3 cohérente",
  "Des titres et descriptions travaillés",
  "Un maillage interne logique",
  "Des contenus réellement utiles",
  "Une expérience mobile soignée",
  "Des performances techniques surveillées",
  "Une structure compréhensible par les moteurs de recherche",
  "Des données structurées lorsque pertinentes",
  "Un suivi des opportunités de visibilité",
];

const process = [
  {
    number: "01",
    title: "Analyser",
    text: "Nous identifions les problèmes techniques, les contenus existants, les opportunités et les recherches pertinentes pour votre entreprise.",
  },
  {
    number: "02",
    title: "Prioriser",
    text: "Toutes les optimisations ne se valent pas. Nous concentrons les efforts sur les actions susceptibles d'avoir le plus d'impact.",
  },
  {
    number: "03",
    title: "Optimiser",
    text: "Nous améliorons la structure, les contenus, les pages stratégiques et les fondations techniques du site.",
  },
  {
    number: "04",
    title: "Mesurer",
    text: "Le référencement naturel se construit dans le temps. Les données permettent d'identifier ce qui progresse et ce qui doit encore être travaillé.",
  },
];

const faqs = [
  {
    question: "Qu'est-ce que le référencement naturel ?",
    answer:
      "Le référencement naturel, ou SEO, regroupe les techniques qui permettent à un site internet d'être mieux compris et mieux positionné dans les résultats organiques des moteurs de recherche. Il concerne notamment la technique, la structure, les contenus, la popularité et l'expérience utilisateur.",
  },
  {
    question: "Pourquoi le SEO est-il important pour une entreprise ?",
    answer:
      "Une bonne visibilité organique permet d'être présent lorsque des prospects recherchent vos produits ou services. Contrairement à une présence uniquement publicitaire, le référencement naturel permet de construire progressivement une source de visibilité durable.",
  },
  {
    question: "Le SEO est-il inclus dans la création d'un site internet ?",
    answer:
      "Les fondamentaux SEO doivent être intégrés dès la création du site : architecture, balises, hiérarchie des contenus, responsive design, performances, maillage interne et indexabilité. Un accompagnement SEO plus approfondi peut ensuite être mis en place selon les objectifs.",
  },
  {
    question: "Combien de temps faut-il pour obtenir des résultats SEO ?",
    answer:
      "Le référencement naturel demande du temps. Les résultats dépendent notamment de votre secteur, de la concurrence, de l'état initial du site, de la qualité des contenus et de l'autorité du domaine. L'approche consiste à travailler progressivement les leviers les plus importants et à mesurer leur évolution.",
  },
  {
    question: "Faut-il publier beaucoup de contenu pour être bien référencé ?",
    answer:
      "Non. La quantité ne suffit pas. Il vaut mieux produire des contenus utiles, pertinents et réellement liés aux recherches de votre cible. La structure du site et la qualité des pages existantes sont également essentielles.",
  },
  {
    question: "Le référencement local concerne-t-il mon entreprise ?",
    answer:
      "Si vos clients recherchent vos services dans une zone géographique donnée, le référencement local peut être particulièrement important. Il concerne notamment votre présence Google, vos informations locales et la cohérence des signaux associés à votre entreprise.",
  },
  {
    question: "Pouvez-vous améliorer le SEO d'un site existant ?",
    answer:
      "Oui. Une optimisation SEO peut être réalisée sur un site existant. L'analyse permet d'identifier les problèmes techniques, les pages à améliorer, les contenus manquants et les opportunités de visibilité.",
  },
];

export default function SEO() {
  return (
    <>
      <SEOHead
        title="SEO & référencement naturel — Gagnez en visibilité | Vitrine+"
        description="Vitrine+ améliore votre visibilité dans Google grâce au SEO technique, au référencement local, aux contenus et à l'optimisation continue."
        canonical="/services/seo"
      />

      <PageHero
        eyebrow="SEO & référencement naturel"
        title={
          <>
            Être visible.
            <br />
            <span className="text-black/30">Au bon moment.</span>
          </>
        }
        text="Nous travaillons votre visibilité organique pour que votre entreprise puisse être trouvée par les personnes qui recherchent réellement vos produits ou services."
      />

      {/* INTRODUCTION */}

      <section className="px-6 pb-24 lg:px-8 lg:pb-36">
        <div className="mx-auto grid max-w-7xl gap-12 lg:grid-cols-[.85fr_1.15fr] lg:items-end">
          <div>
            <SectionLabel>
              Référencement naturel
            </SectionLabel>

            <h2 className="display mt-6 text-4xl font-extrabold leading-[1.02] sm:text-6xl">
              Le SEO ne consiste pas
              <br />
              <span className="text-black/30">
                à remplir une page de mots-clés.
              </span>
            </h2>
          </div>

          <div className="space-y-5 text-lg leading-8 text-black/55">
            <p>
              Le référencement naturel consiste avant tout à aider les moteurs
              de recherche à comprendre votre entreprise, vos services et la
              valeur de vos pages, tout en offrant une expérience réellement
              utile aux visiteurs.
            </p>

            <p>
              Chez Vitrine+, nous travaillons donc le SEO comme une composante
              de votre présence digitale : structure du site, contenus,
              expérience mobile, performances, maillage interne et visibilité
              locale doivent fonctionner ensemble.
            </p>

            <Link
              to="/audit"
              className="inline-flex items-center gap-2 rounded-full bg-[#080808] px-5 py-3 text-sm font-bold !text-white transition hover:bg-[#c8a45d] hover:!text-black"
            >
              Faire mon audit gratuit
              <ArrowRight size={16} />
            </Link>
          </div>
        </div>
      </section>

      {/* 3 PILIERS */}

      <section className="bg-[#f5f5f2] px-6 py-24 lg:px-8 lg:py-36">
        <div className="mx-auto max-w-7xl">
          <SectionLabel>
            Les piliers de notre approche
          </SectionLabel>

          <div className="mt-8 max-w-3xl">
            <h2 className="display text-4xl font-extrabold leading-[1.02] sm:text-6xl">
              Une visibilité construite
              <br />
              <span className="text-black/30">
                sur plusieurs leviers.
              </span>
            </h2>
          </div>

          <div className="mt-14 grid gap-5 md:grid-cols-3">
            {pillars.map(
              ({ icon: Icon, title, text }) => (
                <article
                  key={title}
                  className="rounded-[2rem] border border-black/10 bg-white p-8 sm:p-10"
                >
                  <Icon
                    size={28}
                    className="text-[#c8a45d]"
                  />

                  <h3 className="display mt-12 text-2xl font-extrabold">
                    {title}
                  </h3>

                  <p className="mt-5 leading-7 text-black/50">
                    {text}
                  </p>
                </article>
              )
            )}
          </div>
        </div>
      </section>

      {/* POURQUOI SEO */}

      <section className="px-6 py-24 lg:px-8 lg:py-36">
        <div className="mx-auto grid max-w-7xl gap-14 lg:grid-cols-[.8fr_1.2fr]">
          <div>
            <SectionLabel>
              Pourquoi travailler votre référencement ?
            </SectionLabel>

            <h2 className="display mt-6 text-4xl font-extrabold leading-[1.02] sm:text-6xl">
              Être trouvé
              <br />
              <span className="text-black/30">
                avant d'être choisi.
              </span>
            </h2>
          </div>

          <div className="space-y-6 text-lg leading-8 text-black/55">
            <p>
              Vos futurs clients utilisent déjà les moteurs de recherche pour
              trouver des entreprises, comparer des solutions et répondre à
              leurs questions. Si votre entreprise n'apparaît pas au moment où
              cette recherche est effectuée, une partie de ces opportunités
              revient naturellement à vos concurrents.
            </p>

            <p>
              Le SEO permet de construire une présence sur ces recherches
              pertinentes. Il ne s'agit pas de chercher à apparaître partout,
              mais d'être visible là où votre cible a réellement une intention.
            </p>

            <p>
              Cette logique rejoint directement notre approche de la création
              de site internet : une page doit être compréhensible pour les
              visiteurs, utile pour eux et correctement structurée pour les
              moteurs de recherche.
            </p>

            <Link
              to="/creation-site-internet"
              className="inline-flex items-center gap-2 text-sm font-bold"
            >
              Découvrir notre création de sites
              <ArrowRight size={16} />
            </Link>
          </div>
        </div>
      </section>

      {/* FONDATIONS */}

      <section className="bg-[#080808] px-6 py-24 text-white lg:px-8 lg:py-36">
        <div className="mx-auto grid max-w-7xl gap-14 lg:grid-cols-[.8fr_1.2fr]">
          <div>
            <SectionLabel>
              Les fondamentaux
            </SectionLabel>

            <h2 className="display mt-6 text-4xl font-extrabold leading-[1.02] sm:text-6xl">
              Les détails qui
              <br />
              <span className="text-white/30">
                font la différence.
              </span>
            </h2>

            <p className="mt-7 max-w-lg text-lg leading-8 text-white/45">
              Une stratégie SEO solide repose sur de nombreuses optimisations
              complémentaires. Elles doivent rester cohérentes avec votre
              entreprise et vos objectifs commerciaux.
            </p>
          </div>

          <div className="grid gap-3 sm:grid-cols-2">
            {foundations.map((item) => (
              <div
                key={item}
                className="flex items-start gap-3 border-t border-white/10 py-5 text-white/65"
              >
                <Check
                  size={18}
                  className="mt-1 shrink-0 text-[#c8a45d]"
                />

                <span>{item}</span>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* AUDIT */}

      <section className="px-6 py-24 lg:px-8 lg:py-36">
        <div className="mx-auto max-w-7xl rounded-[2.5rem] bg-[#f5f5f2] p-8 sm:p-12 lg:p-16">
          <div className="grid gap-12 lg:grid-cols-[.9fr_1.1fr] lg:items-center">
            <div>
              <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-[#080808] text-[#c8a45d]">
                <FileSearch size={25} />
              </div>

              <h2 className="display mt-8 text-4xl font-extrabold leading-[1.02] sm:text-5xl">
                Vous ne savez pas
                <br />
                <span className="text-black/30">
                  ce qui bloque votre site ?
                </span>
              </h2>
            </div>

            <div>
              <p className="text-lg leading-8 text-black/55">
                Avant de commencer une stratégie SEO, il est souvent plus
                pertinent de comprendre la situation actuelle. Notre audit
                analyse notamment le SEO, la structure, le mobile, le contenu,
                les performances, les réseaux sociaux et les éléments de
                conversion.
              </p>

              <Link
                to="/audit"
                className="mt-8 inline-flex items-center gap-2 rounded-full bg-[#080808] px-6 py-3.5 text-sm font-bold !text-white transition hover:bg-[#c8a45d] hover:!text-black"
              >
                Analyser mon site gratuitement
                <ArrowRight size={16} />
              </Link>
            </div>
          </div>
        </div>
      </section>

      {/* PROCESS */}

      <section className="bg-[#f5f5f2] px-6 py-24 lg:px-8 lg:py-36">
        <div className="mx-auto max-w-7xl">
          <SectionLabel>
            Notre méthode SEO
          </SectionLabel>

          <div className="mt-8 max-w-3xl">
            <h2 className="display text-4xl font-extrabold leading-[1.02] sm:text-6xl">
              Comprendre.
              <br />
              <span className="text-black/30">
                Optimiser. Mesurer.
              </span>
            </h2>
          </div>

          <div className="mt-14">
            {process.map((item) => (
              <article
                key={item.number}
                className="grid gap-5 border-t border-black/10 py-8 lg:grid-cols-[80px_220px_1fr] lg:items-start"
              >
                <span className="text-xs font-bold tracking-[.2em] text-[#c8a45d]">
                  {item.number}
                </span>

                <h3 className="display text-2xl font-extrabold">
                  {item.title}
                </h3>

                <p className="max-w-2xl leading-7 text-black/50">
                  {item.text}
                </p>
              </article>
            ))}
          </div>
        </div>
      </section>

      {/* SEO + CREATION WEB */}

      <section className="px-6 py-24 lg:px-8 lg:py-36">
        <div className="mx-auto grid max-w-7xl gap-12 lg:grid-cols-2">
          <div>
            <SectionLabel>
              SEO & création de site internet
            </SectionLabel>

            <h2 className="display mt-6 text-4xl font-extrabold leading-[1.02] sm:text-6xl">
              Le meilleur moment
              <br />
              <span className="text-black/30">
                pour penser au SEO ?
              </span>
            </h2>
          </div>

          <div className="space-y-6 text-lg leading-8 text-black/55">
            <p>
              Avant même la mise en ligne. Lorsque nous créons un site
              internet, nous pouvons intégrer dès le départ une architecture
              adaptée au référencement naturel.
            </p>

            <p>
              Cela permet d'éviter de construire un site puis de devoir
              corriger après coup une structure qui n'était pas pensée pour
              les utilisateurs ou les moteurs de recherche.
            </p>

            <p>
              Si votre site existe déjà, une refonte peut également être
              l'occasion de remettre à plat sa structure, ses contenus et ses
              performances.
            </p>

            <Link
              to="/services/web"
              className="inline-flex items-center gap-2 text-sm font-bold"
            >
              Découvrir notre expertise web
              <ArrowRight size={16} />
            </Link>
          </div>
        </div>
      </section>

      {/* FAQ */}

      <section className="bg-[#080808] px-6 py-24 text-white lg:px-8 lg:py-32">
        <div className="mx-auto max-w-5xl">
          <SectionLabel>
            Questions fréquentes
          </SectionLabel>

          <h2 className="display mt-6 text-4xl font-extrabold sm:text-6xl">
            Le référencement naturel,
            <br />
            <span className="text-white/30">
              en clair.
            </span>
          </h2>

          <div className="mt-12 divide-y divide-white/10 border-y border-white/10">
            {faqs.map(({ question, answer }) => (
              <details
                key={question}
                className="group py-6"
              >
                <summary className="flex cursor-pointer list-none items-center justify-between gap-6 text-lg font-bold marker:hidden">
                  {question}

                  <span className="text-2xl font-light text-[#c8a45d] transition-transform group-open:rotate-45">
                    +
                  </span>
                </summary>

                <p className="max-w-3xl pt-5 text-sm leading-7 text-white/45">
                  {answer}
                </p>
              </details>
            ))}
          </div>
        </div>
      </section>

      {/* CTA */}

      <CTA
        title="Votre visibilité mérite mieux qu'une intuition."
        text="Commencez par comprendre ce qui fonctionne déjà sur votre site et les leviers qui peuvent réellement améliorer votre visibilité."
      />
    </>
  );
}
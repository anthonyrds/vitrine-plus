import {
  ArrowRight,
  Check,
  Gauge,
  Layout,
  MonitorSmartphone,
  Search,
  Target,
  Zap,
} from "lucide-react";

import { Link } from "react-router-dom";

import PageHero from "../components/PageHero";
import CTA from "../components/CTA";
import SEO from "../components/SEO";
import SectionLabel from "../components/SectionLabel";

const expertise = [
  {
    icon: Layout,
    title: "UX & architecture",
    text: "Nous organisons les contenus et les parcours pour que vos visiteurs trouvent rapidement l'information qui les intéresse.",
  },
  {
    icon: MonitorSmartphone,
    title: "Responsive design",
    text: "L'expérience est pensée pour les ordinateurs, tablettes et smartphones, avec une interface adaptée à chaque écran.",
  },
  {
    icon: Gauge,
    title: "Performance",
    text: "Nous limitons les éléments inutiles et travaillons la structure technique pour proposer une expérience rapide et fluide.",
  },
  {
    icon: Search,
    title: "SEO technique",
    text: "La structure du site, les balises, le maillage et les fondations techniques sont pensés pour faciliter sa compréhension par les moteurs de recherche.",
  },
  {
    icon: Target,
    title: "Conversion",
    text: "Les pages sont construites autour d'objectifs précis : informer, rassurer, convaincre et faciliter la prise de contact.",
  },
  {
    icon: Zap,
    title: "Technologie moderne",
    text: "Nous utilisons des technologies adaptées au projet afin de construire des expériences web modernes et évolutives.",
  },
];

const projects = [
  {
    number: "01",
    title: "Création",
    text: "Construire un nouveau site lorsque votre entreprise a besoin d'une présence digitale entièrement repensée.",
    link: "/creation-site-internet",
    linkText: "Voir la création de sites",
  },
  {
    number: "02",
    title: "Refonte",
    text: "Repenser un site existant lorsque son design, sa structure, ses performances ou son parcours ne répondent plus à vos objectifs.",
    link: "/creation-site-internet",
    linkText: "Découvrir notre approche",
  },
  {
    number: "03",
    title: "Optimisation",
    text: "Faire évoluer un site existant pour améliorer son expérience, sa performance, sa visibilité ou sa capacité à convertir.",
    link: "/audit",
    linkText: "Auditer votre site",
  },
];

const process = [
  [
    "01",
    "Comprendre",
    "Nous analysons votre activité, votre cible, votre positionnement et vos objectifs avant de concevoir l'expérience.",
  ],
  [
    "02",
    "Structurer",
    "Nous définissons l'architecture, les contenus, la navigation et les parcours qui permettront à vos visiteurs de comprendre et d'agir.",
  ],
  [
    "03",
    "Concevoir",
    "Nous créons une direction artistique cohérente avec votre identité et votre positionnement.",
  ],
  [
    "04",
    "Développer",
    "Nous transformons cette direction en une expérience web responsive, performante et évolutive.",
  ],
  [
    "05",
    "Optimiser",
    "Nous travaillons les fondamentaux techniques, le SEO, les performances et les points de conversion.",
  ],
  [
    "06",
    "Faire évoluer",
    "Une fois en ligne, votre site peut continuer à évoluer selon vos besoins et ceux de votre entreprise.",
  ],
];

const faqs = [
  [
    "Quelle est la différence entre création et refonte de site internet ?",
    "La création consiste à concevoir un nouveau site à partir des objectifs de votre entreprise. La refonte part d'un site existant afin d'améliorer son design, sa structure, son expérience utilisateur, ses performances ou son référencement.",
  ],
  [
    "Pourquoi travailler l'UX d'un site internet ?",
    "Une bonne expérience utilisateur permet aux visiteurs de comprendre plus rapidement votre offre et de trouver les informations importantes. Elle facilite également les actions que vous souhaitez générer : contact, demande de devis, rendez-vous ou achat.",
  ],
  [
    "Est-ce que votre conception web prend en compte le SEO ?",
    "Oui. La structure des pages, la hiérarchie des titres, les liens internes, les métadonnées, les performances et l'expérience mobile font partie des éléments pris en compte dans la conception.",
  ],
  [
    "Les sites sont-ils adaptés aux smartphones ?",
    "Oui. Le responsive design fait partie des fondamentaux du projet. L'expérience doit rester claire et confortable quel que soit l'appareil utilisé.",
  ],
  [
    "Pouvez-vous améliorer les performances d'un site existant ?",
    "Oui. Un audit permet d'identifier les éléments qui ralentissent ou compliquent l'expérience. Nous pouvons ensuite travailler sur la structure, les ressources, le code et les éléments d'interface concernés.",
  ],
  [
    "Pouvez-vous refaire un site sans changer toute son identité ?",
    "Oui. Une refonte peut conserver certains éléments de votre identité tout en modernisant la structure, le parcours utilisateur, le contenu et l'interface.",
  ],
  [
    "Pourquoi un site doit-il être pensé pour la conversion ?",
    "Parce qu'un site professionnel doit contribuer à vos objectifs. Une visite doit pouvoir naturellement mener vers une action : demander des informations, prendre rendez-vous, demander un devis ou vous contacter.",
  ],
];

export default function Web() {
  return (
    <>
      <SEO
        title="Conception & refonte web — UX, performance & SEO | Vitrine+"
        description="Vitrine+ conçoit et refond des expériences web sur mesure : UX/UI, développement, responsive design, performance, SEO technique et conversion."
        canonical="/services/web"
      />

      <PageHero
        eyebrow="Conception & refonte web"
        title={
          <>
            Votre site ne doit pas seulement être beau.
            <br />
            <span className="text-black/30">
              Il doit travailler.
            </span>
          </>
        }
        text="Nous concevons des expériences web rapides, claires et premium, pensées autour de l'utilisateur, du référencement naturel et de vos objectifs commerciaux."
      />

      <section className="px-6 pb-24 lg:px-8 lg:pb-36">
        <div className="mx-auto grid max-w-7xl gap-12 lg:grid-cols-[.85fr_1.15fr] lg:items-end">
          <div>
            <SectionLabel>
              Conception d'expérience web
            </SectionLabel>

            <h2 className="display mt-6 text-4xl font-extrabold leading-[1.02] sm:text-6xl">
              Une interface peut être
              <br />
              <span className="text-black/30">
                belle et efficace.
              </span>
            </h2>
          </div>

          <div className="space-y-5 text-lg leading-8 text-black/55">
            <p>
              Un site web performant commence par une bonne compréhension de
              l'utilisateur. Il doit permettre de trouver rapidement
              l'information, comprendre l'offre et savoir quelle action
              effectuer ensuite.
            </p>

            <p>
              C'est pourquoi notre approche associe UX, direction artistique,
              développement, performance, SEO technique et conversion au sein
              d'une même expérience.
            </p>

            <Link
              to="/creation-site-internet"
              className="inline-flex items-center gap-2 rounded-full bg-[#080808] px-5 py-3 text-sm font-bold !text-white transition hover:bg-[#c8a45d] hover:!text-black"
            >
              Découvrir la création de sites
              <ArrowRight size={16} />
            </Link>
          </div>
        </div>
      </section>

      <section className="bg-[#080808] px-6 py-24 text-white lg:px-8 lg:py-36">
        <div className="mx-auto max-w-7xl">
          <SectionLabel>
            Notre expertise web
          </SectionLabel>

          <h2 className="display mt-7 max-w-4xl text-4xl font-extrabold leading-[1.02] sm:text-6xl">
            Chaque détail participe
            <br />
            <span className="text-white/30">
              à l'expérience.
            </span>
          </h2>

          <div className="mt-14 grid gap-px overflow-hidden rounded-[2rem] bg-white/10 md:grid-cols-2 lg:grid-cols-3">
            {expertise.map(({ icon: Icon, title, text }) => (
              <article
                key={title}
                className="bg-[#080808] p-8 sm:p-10"
              >
                <Icon
                  size={26}
                  className="text-[#c8a45d]"
                />

                <h3 className="display mt-12 text-2xl font-extrabold">
                  {title}
                </h3>

                <p className="mt-4 text-sm leading-7 text-white/45">
                  {text}
                </p>
              </article>
            ))}
          </div>
        </div>
      </section>

      <section className="px-6 py-24 lg:px-8 lg:py-36">
        <div className="mx-auto max-w-7xl">
          <SectionLabel>
            Création, refonte ou optimisation
          </SectionLabel>

          <div className="mt-8 max-w-3xl">
            <h2 className="display text-4xl font-extrabold leading-[1.02] sm:text-6xl">
              Le bon projet
              <br />
              <span className="text-black/30">
                au bon moment.
              </span>
            </h2>
          </div>

          <div className="mt-14 grid gap-5 lg:grid-cols-3">
            {projects.map((project) => (
              <article
                key={project.number}
                className="rounded-[2rem] border border-black/10 p-8 sm:p-10"
              >
                <span className="text-xs font-bold tracking-[.2em] text-[#c8a45d]">
                  {project.number}
                </span>

                <h3 className="display mt-12 text-3xl font-extrabold">
                  {project.title}
                </h3>

                <p className="mt-4 leading-7 text-black/50">
                  {project.text}
                </p>

                <Link
                  to={project.link}
                  className="mt-8 inline-flex items-center gap-2 text-sm font-bold"
                >
                  {project.linkText}
                  <ArrowRight size={15} />
                </Link>
              </article>
            ))}
          </div>
        </div>
      </section>

      <section className="bg-[#f5f5f2] px-6 py-24 lg:px-8 lg:py-36">
        <div className="mx-auto grid max-w-7xl gap-14 lg:grid-cols-[.8fr_1.2fr]">
          <div>
            <SectionLabel>
              Notre méthode
            </SectionLabel>

            <h2 className="display mt-6 text-4xl font-extrabold leading-[1.02] sm:text-6xl">
              Une conception
              <br />
              <span className="text-black/30">
                qui commence avant le design.
              </span>
            </h2>

            <p className="mt-7 max-w-lg text-lg leading-8 text-black/50">
              Le design n'est que l'une des étapes. Une expérience web
              efficace repose d'abord sur une stratégie, une architecture et
              des contenus correctement organisés.
            </p>
          </div>

          <div>
            {process.map(([number, title, text]) => (
              <article
                key={number}
                className="grid gap-4 border-t border-black/10 py-7 sm:grid-cols-[70px_180px_1fr]"
              >
                <span className="text-xs font-bold tracking-[.2em] text-[#c8a45d]">
                  {number}
                </span>

                <h3 className="display text-2xl font-extrabold">
                  {title}
                </h3>

                <p className="leading-7 text-black/50">
                  {text}
                </p>
              </article>
            ))}
          </div>
        </div>
      </section>

      <section className="px-6 py-24 lg:px-8 lg:py-36">
        <div className="mx-auto grid max-w-7xl gap-12 lg:grid-cols-2">
          <div>
            <SectionLabel>
              Web & référencement naturel
            </SectionLabel>

            <h2 className="display mt-6 text-4xl font-extrabold leading-[1.02] sm:text-6xl">
              Un site performant
              <br />
              <span className="text-black/30">
                doit aussi être compréhensible.
              </span>
            </h2>
          </div>

          <div className="space-y-6 text-lg leading-8 text-black/55">
            <p>
              La conception technique d'un site influence directement son
              expérience et sa capacité à être correctement interprété par
              les moteurs de recherche.
            </p>

            <p>
              Une architecture cohérente, des contenus bien hiérarchisés, un
              affichage mobile adapté, des temps de chargement maîtrisés et
              un maillage interne logique constituent des fondations utiles
              aussi bien pour vos visiteurs que pour votre référencement.
            </p>

            <Link
              to="/services/seo"
              className="inline-flex items-center gap-2 text-sm font-bold"
            >
              Découvrir notre approche SEO
              <ArrowRight size={16} />
            </Link>
          </div>
        </div>
      </section>

      <section className="bg-[#080808] px-6 py-24 text-white lg:px-8 lg:py-32">
        <div className="mx-auto max-w-5xl">
          <SectionLabel>
            Questions fréquentes
          </SectionLabel>

          <h2 className="display mt-6 text-4xl font-extrabold sm:text-6xl">
            Avant de refaire
            <br />
            <span className="text-white/30">
              votre site.
            </span>
          </h2>

          <div className="mt-12 divide-y divide-white/10 border-y border-white/10">
            {faqs.map(([question, answer]) => (
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

      <CTA
        title="Votre site peut faire davantage."
        text="Parlons de votre site actuel, de ce qui fonctionne déjà et de ce qui pourrait réellement être amélioré."
      />
    </>
  );
}
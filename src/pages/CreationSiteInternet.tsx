import {
  ArrowRight,
  Check,
  Gauge,
  Layout,
  Search,
  Smartphone,
  Target,
  Zap,
} from "lucide-react";

import { Link } from "react-router-dom";

import PageHero from "../components/PageHero";
import CTA from "../components/CTA";
import SEO from "../components/SEO";
import SectionLabel from "../components/SectionLabel";

const standards = [
  {
    icon: Layout,
    title: "Une identité sur mesure",
    text: "Votre site doit ressembler à votre entreprise, pas à un modèle utilisé par tout le monde.",
  },
  {
    icon: Target,
    title: "Un objectif clair",
    text: "Chaque page possède une fonction : présenter, rassurer, convaincre ou faire passer à l'action.",
  },
  {
    icon: Smartphone,
    title: "Pensé pour tous les écrans",
    text: "Une expérience cohérente sur ordinateur, tablette et mobile.",
  },
  {
    icon: Search,
    title: "Une base SEO solide",
    text: "Structure sémantique, métadonnées, performances et fondations techniques pensées dès la conception.",
  },
  {
    icon: Gauge,
    title: "Rapide et performant",
    text: "Nous limitons les éléments inutiles pour conserver une expérience fluide et agréable.",
  },
  {
    icon: Zap,
    title: "Conçu pour évoluer",
    text: "Votre site doit pouvoir accompagner votre entreprise plutôt que devenir rapidement obsolète.",
  },
];

const websiteTypes = [
  {
    title: "Site vitrine",
    text: "Présenter votre entreprise, vos services et votre savoir-faire avec une image professionnelle.",
  },
  {
    title: "Refonte",
    text: "Transformer un site vieillissant en une présence plus claire, moderne et efficace.",
  },
  {
    title: "Landing page",
    text: "Créer une page dédiée à une offre, une campagne ou un objectif commercial précis.",
  },
];

const faqs = [
  [
    "Combien coûte la création d'un site internet ?",
    "Chez Vitrine+, les solutions de création commencent à 790 €. Le tarif dépend du niveau de personnalisation, du contenu, des fonctionnalités et de l'accompagnement nécessaires.",
  ],
  [
    "Combien de temps faut-il pour créer un site ?",
    "Le délai dépend du périmètre du projet, des contenus et des validations. Un projet simple peut être lancé en quelques semaines.",
  ],
  [
    "Est-ce que le site sera adapté au mobile ?",
    "Oui. Chaque projet est pensé pour offrir une expérience adaptée aux smartphones, tablettes et ordinateurs.",
  ],
  [
    "Le référencement est-il inclus ?",
    "Une base SEO technique est intégrée à la conception. Un accompagnement SEO plus poussé peut ensuite être mis en place selon vos objectifs.",
  ],
  [
    "Puis-je conserver mon nom de domaine actuel ?",
    "Oui. Une création ou une refonte peut généralement être réalisée en conservant votre domaine existant.",
  ],
  [
    "Est-ce que vous pouvez refaire mon site actuel ?",
    "Oui. Une refonte permet de repartir de l'existant tout en conservant ce qui fonctionne et en corrigeant ce qui freine votre présence en ligne.",
  ],
  [
    "Que se passe-t-il après la mise en ligne ?",
    "Vous pouvez continuer à faire évoluer votre site avec Vitrine+. Notre accompagnement V+ Care permet notamment d'assurer sa maintenance et son suivi.",
  ],
  [
    "Puis-je prendre rendez-vous avant de commencer ?",
    "Oui. Vous pouvez réserver directement un créneau avec Vitrine+ afin de parler de votre entreprise, de votre site actuel et de vos objectifs.",
  ],
];

export default function CreationSiteInternet() {
  return (
    <>
      <SEO
        title="Création de site internet pour entreprise | Vitrine+"
        description="Vitrine+ crée des sites internet professionnels, rapides et pensés pour le SEO, l'expérience utilisateur et la conversion. Création ou refonte de site."
        canonical="/creation-site-internet"
      />

      <PageHero
        eyebrow="Création de site internet"
        title={
          <>
            Votre site doit faire
            <br />
            <span className="text-black/30">plus que vous présenter.</span>
          </>
        }
        text="Vitrine+ conçoit des sites internet professionnels qui associent stratégie, design, performance, référencement et conversion pour donner à votre entreprise une présence digitale à la hauteur de son ambition."
      />

      <section className="px-6 pb-24 lg:px-8 lg:pb-36">
        <div className="mx-auto grid max-w-7xl gap-12 lg:grid-cols-[1.1fr_.9fr] lg:items-end">
          <div>
            <SectionLabel>
              Création & refonte
            </SectionLabel>

            <h2 className="display mt-6 max-w-4xl text-4xl font-extrabold leading-[1.02] sm:text-6xl">
              Un site peut être beau.
              <br />
              <span className="text-black/30">
                Il doit surtout être utile.
              </span>
            </h2>
          </div>

          <div>
            <p className="text-lg leading-8 text-black/55">
              Votre site est souvent le premier contact entre votre entreprise
              et un futur client. Il doit immédiatement expliquer qui vous êtes,
              ce que vous proposez et pourquoi vous choisir.
            </p>

            <Link
              to="/audit"
              className="mt-8 inline-flex items-center gap-2 rounded-full bg-[#080808] px-5 py-3 text-sm font-bold !text-white transition hover:bg-[#c8a45d] hover:!text-black"
            >
              Auditer mon site
              <ArrowRight size={16} />
            </Link>
          </div>
        </div>
      </section>

      <section className="bg-[#080808] px-6 py-24 text-white lg:px-8 lg:py-36">
        <div className="mx-auto max-w-7xl">
          <SectionLabel>
            Notre standard
          </SectionLabel>

          <div className="mt-8 max-w-4xl">
            <h2 className="display text-4xl font-extrabold leading-[1.02] sm:text-6xl">
              Nous ne construisons pas simplement des pages.
            </h2>

            <p className="mt-7 max-w-2xl text-lg leading-8 text-white/50">
              Nous construisons l'expérience qui se trouve entre votre
              entreprise et vos futurs clients.
            </p>
          </div>

          <div className="mt-16 grid gap-px overflow-hidden rounded-[2rem] bg-white/10 md:grid-cols-2 lg:grid-cols-3">
            {standards.map(({ icon: Icon, title, text }) => (
              <article
                key={title}
                className="bg-[#080808] p-8 sm:p-10"
              >
                <Icon
                  size={25}
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
            Ce que nous créons
          </SectionLabel>

          <div className="mt-10 grid gap-5 md:grid-cols-3">
            {websiteTypes.map((item, index) => (
              <article
                key={item.title}
                className="group rounded-[2rem] border border-black/10 p-8 transition hover:-translate-y-1 hover:border-[#c8a45d]"
              >
                <span className="text-xs font-bold tracking-[.2em] text-[#c8a45d]">
                  0{index + 1}
                </span>

                <h3 className="display mt-10 text-3xl font-extrabold">
                  {item.title}
                </h3>

                <p className="mt-4 leading-7 text-black/50">
                  {item.text}
                </p>

                <div className="mt-10 flex items-center gap-2 text-sm font-bold">
                  En savoir plus
                  <ArrowRight
                    size={15}
                    className="transition-transform group-hover:translate-x-1"
                  />
                </div>
              </article>
            ))}
          </div>
        </div>
      </section>

      <section className="bg-[#f5f5f2] px-6 py-24 lg:px-8 lg:py-36">
        <div className="mx-auto grid max-w-7xl gap-14 lg:grid-cols-[.75fr_1.25fr]">
          <div>
            <SectionLabel>
              De la première idée à la mise en ligne
            </SectionLabel>

            <h2 className="display mt-6 text-4xl font-extrabold leading-[1.02] sm:text-6xl">
              Un projet cadré.
              <br />
              <span className="text-black/30">
                Une direction claire.
              </span>
            </h2>
          </div>

          <div className="grid gap-0">
            {[
              [
                "01",
                "Analyser",
                "Nous comprenons votre entreprise, votre cible et votre situation actuelle.",
              ],
              [
                "02",
                "Structurer",
                "Nous organisons le contenu et le parcours pour que l'information soit immédiatement compréhensible.",
              ],
              [
                "03",
                "Concevoir",
                "Nous créons une direction visuelle cohérente avec votre positionnement.",
              ],
              [
                "04",
                "Développer",
                "Nous construisons une expérience responsive, performante et techniquement propre.",
              ],
              [
                "05",
                "Optimiser",
                "Nous travaillons les fondamentaux SEO, la performance et les points de conversion.",
              ],
              [
                "06",
                "Lancer",
                "Votre site est mis en ligne et peut ensuite continuer à évoluer avec votre entreprise.",
              ],
            ].map(([number, title, text]) => (
              <div
                key={number}
                className="grid gap-4 border-t border-black/10 py-7 sm:grid-cols-[70px_180px_1fr] sm:items-start"
              >
                <span className="text-xs font-bold tracking-[.2em] text-[#c8a45d]">
                  {number}
                </span>

                <h3 className="display text-2xl font-extrabold">
                  {title}
                </h3>

                <p className="max-w-xl leading-7 text-black/50">
                  {text}
                </p>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section className="px-6 py-24 lg:px-8 lg:py-36">
        <div className="mx-auto max-w-7xl">
          <div className="grid gap-12 lg:grid-cols-[.8fr_1.2fr]">
            <div>
              <SectionLabel>
                Ce que vous obtenez
              </SectionLabel>

              <h2 className="display mt-6 text-4xl font-extrabold sm:text-6xl">
                Un site pensé autour de votre entreprise.
              </h2>
            </div>

            <div className="grid gap-3">
              {[
                "Une identité digitale cohérente",
                "Une navigation claire",
                "Une expérience mobile soignée",
                "Une structure pensée pour le référencement",
                "Des appels à l'action visibles",
                "Une base technique performante",
                "Un site évolutif",
                "Un accompagnement après la mise en ligne",
              ].map((item) => (
                <div
                  key={item}
                  className="flex items-center gap-3 border-t border-black/10 py-4 text-black/65"
                >
                  <Check
                    size={18}
                    className="shrink-0 text-[#c8a45d]"
                  />
                  {item}
                </div>
              ))}
            </div>
          </div>
        </div>
      </section>

      <section className="bg-[#080808] px-6 py-24 text-white lg:px-8 lg:py-32">
        <div className="mx-auto max-w-5xl">
          <SectionLabel>
            Questions fréquentes
          </SectionLabel>

          <h2 className="display mt-6 text-4xl font-extrabold sm:text-6xl">
            Avant de commencer,
            <br />
            <span className="text-white/30">
              vous voulez probablement savoir.
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
        title="Votre entreprise mérite un site à sa hauteur."
        text="Parlons de votre projet, de votre site actuel et de ce que vous souhaitez obtenir. Vous pouvez également commencer par un audit gratuit."
      />
    </>
  );
}

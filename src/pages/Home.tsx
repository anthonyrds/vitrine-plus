import {
  ArrowRight,
  Check,
  ChevronDown,
  CircleCheck,
  Search,
  Sparkles,
} from "lucide-react";

import { Link } from "react-router-dom";

import Button from "../components/Button";
import CTA from "../components/CTA";
import SEO from "../components/SEO";
import SectionLabel from "../components/SectionLabel";
import ServiceCard from "../components/ServiceCard";

const faqs = [
  [
    "Pourquoi commencer par un audit ?",
    "Parce qu’un bon projet commence par les bons problèmes. L’audit permet d’identifier les priorités avant d’investir dans des changements.",
  ],
  [
    "Combien coûte un site Vitrine+ ?",
    "Nos solutions démarrent à 790 €. Le prix dépend du niveau de personnalisation, du contenu, des fonctionnalités et de l’accompagnement souhaité.",
  ],
  [
    "Combien de temps faut-il ?",
    "Un site simple peut être lancé en quelques semaines. Le calendrier exact est défini après cadrage selon le périmètre du projet.",
  ],
  [
    "Puis-je prendre rendez-vous directement ?",
    "Oui. Choisissez directement une date et une heure. Nous vous appellerons au numéro indiqué.",
  ],
  [
    "Travaillez-vous partout en France ?",
    "Oui. Les échanges et le suivi peuvent être réalisés à distance, avec le même niveau d’accompagnement.",
  ],
];

const method = [
  ["01", "Révéler", "Audit et compréhension de l’existant."],
  ["02", "Positionner", "Clarifier votre offre, votre message et votre cible."],
  ["03", "Concevoir", "UX, direction artistique et parcours."],
  ["04", "Construire", "Développement, SEO technique et performance."],
  ["05", "Convertir", "CTA, formulaires et parcours commerciaux."],
  ["06", "Accélérer", "SEO, contenu, automatisation et amélioration continue."],
];

export default function Home() {
  return (
    <>
      <SEO
        title="Vitrine+ — Agence digitale | Votre entreprise. En mieux."
        description="Vitrine+ transforme votre présence digitale en véritable outil commercial : stratégie, création web, SEO, acquisition, IA et automatisation."
        canonical="/"
      />

      <section className="relative min-h-[82vh] overflow-hidden bg-[#080808] px-6 pb-14 pt-32 text-white lg:px-8 lg:pt-40">
        <div className="absolute -right-40 top-0 h-[520px] w-[520px] rounded-full bg-[#c8a45d]/10 blur-[110px]" />

        <div className="relative mx-auto flex max-w-7xl flex-col justify-between">
          <div className="max-w-6xl">
            <SectionLabel>
              Agence digitale indépendante
            </SectionLabel>

            <h1 className="display mt-7 max-w-6xl text-[15vw] font-extrabold leading-[.9] tracking-[-.055em] sm:text-8xl lg:text-[112px]">
              Votre entreprise.
              <span className="block text-white/35">
                En mieux.
              </span>
            </h1>

            <p className="mt-9 max-w-2xl text-lg leading-8 text-white/55 sm:text-xl">
              Nous créons des présences digitales qui donnent
              envie de vous choisir — et qui transforment votre
              site en véritable outil commercial.
            </p>

            <div className="mt-8 flex flex-col gap-3 sm:flex-row">
              <Button to="/audit">
                Obtenir mon audit gratuit
              </Button>

              <Button
                to="/rendez-vous"
                dark={false}
              >
                Prendre rendez-vous
              </Button>
            </div>
          </div>

          <div className="mt-16 grid gap-5 border-t border-white/10 pt-6 sm:grid-cols-3">
            {[
              ["01", "Positionnement & stratégie"],
              ["02", "Design & technologie"],
              ["03", "Visibilité & conversion"],
            ].map(([number, text]) => (
              <div key={number}>
                <p className="text-xs uppercase tracking-[.2em] text-white/30">
                  {number}
                </p>

                <p className="mt-2 text-sm font-semibold text-white/65">
                  {text}
                </p>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section className="px-6 py-14 lg:px-8 lg:py-20">
        <div className="mx-auto max-w-7xl">
          <div className="grid overflow-hidden rounded-[2rem] bg-[#f5f5f2] lg:grid-cols-[1.05fr_.95fr]">
            <div className="p-7 sm:p-10 lg:p-14">
              <SectionLabel>
                Audit digital gratuit
              </SectionLabel>

              <h2 className="display mt-5 max-w-2xl text-4xl font-extrabold leading-[1] sm:text-6xl">
                Et si votre site vous faisait perdre des clients ?
              </h2>

              <p className="mt-6 max-w-xl text-base leading-7 text-black/55 sm:text-lg">
                Analysez votre domaine et découvrez votre niveau
                en SEO, structure, mobile, contenu, performance,
                partage social et conversion.
              </p>

              <Button
                to="/audit"
                className="mt-8"
              >
                Analyser mon site gratuitement
              </Button>

              <div className="mt-7 flex flex-wrap gap-x-6 gap-y-3 text-xs font-semibold text-black/40">
                {[
                  "Multi-pages",
                  "Résultat rapide",
                  "100 % gratuit",
                ].map((item) => (
                  <span
                    key={item}
                    className="flex items-center gap-2"
                  >
                    <Check
                      size={14}
                      className="text-[#c8a45d]"
                    />

                    {item}
                  </span>
                ))}
              </div>
            </div>

            <div className="relative bg-[#080808] p-7 text-white sm:p-10 lg:p-12">
              <div className="relative">
                <p className="text-xs font-bold uppercase tracking-[.25em] text-white/35">
                  Exemple de résultat
                </p>

                <div className="mt-4 flex items-baseline gap-3">
                  <span className="display text-8xl font-extrabold leading-none tracking-[-.07em]">
                    72
                  </span>

                  <span className="text-2xl font-semibold text-white/65">
                    /100
                  </span>
                </div>

                <div className="mt-5 h-1.5 rounded-full bg-white/10">
                  <div className="h-full w-[72%] rounded-full bg-[#c8a45d]" />
                </div>

                <div className="mt-8 border-t border-white/10 pt-7">
                  <p className="text-xs font-bold uppercase tracking-[.2em] text-white/35">
                    3 priorités
                  </p>

                  <div className="mt-5 grid gap-4">
                    {[
                      "Clarifier vos titres et descriptions.",
                      "Renforcer les parcours de conversion.",
                      "Améliorer les signaux techniques prioritaires.",
                    ].map((item, index) => (
                      <div
                        key={item}
                        className="flex gap-3"
                      >
                        <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white/10 text-[10px] font-extrabold text-[#c8a45d]">
                          0{index + 1}
                        </span>

                        <p className="text-sm leading-5 text-white/60">
                          {item}
                        </p>
                      </div>
                    ))}
                  </div>
                </div>

                <div className="mt-8 rounded-2xl border border-[#c8a45d]/20 bg-[#c8a45d]/[.06] p-5">
                  <p className="text-xs font-bold uppercase tracking-[.2em] text-[#c8a45d]">
                    Ce que Vitrine+ peut faire
                  </p>

                  <p className="mt-3 text-sm leading-6 text-white/65">
                    Transformer les constats en plan d’action :
                    visibilité, crédibilité, expérience et conversion.
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section className="px-6 py-16 lg:px-8 lg:py-24">
        <div className="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[.8fr_1.2fr]">
          <SectionLabel>
            Notre conviction
          </SectionLabel>

          <div>
            <h2 className="display text-4xl font-extrabold leading-[1.02] sm:text-6xl">
              Un site n’est pas une brochure.
              <span className="block text-black/30">
                C’est un outil commercial.
              </span>
            </h2>

            <p className="mt-7 max-w-2xl text-lg leading-8 text-black/55">
              Nous réunissons stratégie, design, technologie,
              SEO et conversion autour d’un même objectif :
              rendre votre entreprise plus claire, plus crédible
              et plus facile à choisir.
            </p>

            <Link
              to="/a-propos"
              className="mt-7 inline-flex items-center gap-2 text-sm font-bold"
            >
              Découvrir notre approche
              <ArrowRight size={16} />
            </Link>
          </div>
        </div>
      </section>

      <section className="bg-[#f5f5f2] px-6 py-16 lg:px-8 lg:py-24">
        <div className="mx-auto max-w-7xl">
          <div className="flex flex-col justify-between gap-7 md:flex-row md:items-end">
            <div>
              <SectionLabel>
                Nos expertises
              </SectionLabel>

              <h2 className="display mt-5 max-w-3xl text-5xl font-extrabold leading-[.98] sm:text-7xl">
                Tout ce qu’il faut pour construire une présence forte.
              </h2>
            </div>

            <Button to="/services">
              Voir les services
            </Button>
          </div>

          <div className="mt-10 grid gap-px overflow-hidden rounded-3xl bg-black/10 sm:grid-cols-2 lg:grid-cols-3">
            <ServiceCard
              number="01"
              title="Création web"
              text="Des sites rapides, premium et conçus autour de la conversion."
              to="/services/web"
            />

            <ServiceCard
              number="02"
              title="SEO & visibilité"
              text="Être visible lorsque vos futurs clients recherchent votre activité."
              to="/services/seo"
            />

            <ServiceCard
              number="03"
              title="Acquisition"
              text="Des parcours et outils qui transforment les visiteurs en prospects."
              to="/audit"
            />

            <ServiceCard
              number="04"
              title="Identité digitale"
              text="Une image cohérente qui rend votre entreprise immédiatement crédible."
              to="/contact"
            />

            <ServiceCard
              number="05"
              title="Réseaux sociaux"
              text="Une présence sociale régulière, stratégique et reconnaissable."
              to="/contact"
            />

            <ServiceCard
              number="06"
              title="IA & automatisation"
              text="Des processus plus intelligents pour gagner du temps et de la capacité."
              to="/services/ia"
            />
          </div>
        </div>
      </section>

      <section className="px-6 py-16 lg:px-8 lg:py-24">
        <div className="mx-auto max-w-7xl">
          <SectionLabel>
            La méthode V+
          </SectionLabel>

          <h2 className="display mt-5 max-w-4xl text-5xl font-extrabold leading-[.98] sm:text-7xl">
            Du problème au résultat.
          </h2>

          <div className="mt-12 grid gap-px overflow-hidden rounded-3xl bg-black/10 sm:grid-cols-2 lg:grid-cols-3">
            {method.map(([number, title, text]) => (
              <div
                key={number}
                className="bg-white p-7 sm:p-9"
              >
                <span className="text-xs font-bold tracking-[.2em] text-[#c8a45d]">
                  {number}
                </span>

                <h3 className="display mt-8 text-2xl font-extrabold">
                  {title}
                </h3>

                <p className="mt-3 text-sm leading-6 text-black/50">
                  {text}
                </p>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section className="bg-[#080808] px-6 py-16 text-white lg:px-8 lg:py-24">
        <div className="mx-auto max-w-7xl">
          <SectionLabel>
            Réalisations
          </SectionLabel>

          <h2 className="display mt-5 max-w-4xl text-5xl font-extrabold leading-[.98] sm:text-7xl">
            Des expériences pensées pour être choisies.
          </h2>

          <div className="mt-12 grid gap-5 md:grid-cols-3">
            {[
              [
                "Ligne 7",
                "Concept — identité & site",
                "Une vitrine premium pensée pour une marque locale.",
              ],
              [
                "Atelier Noma",
                "Concept — conversion",
                "Une expérience éditoriale conçue pour générer des demandes.",
              ],
              [
                "Maison Alba",
                "Concept — stratégie digitale",
                "Une présence digitale cohérente de la marque au parcours client.",
              ],
            ].map(([title, subtitle, text]) => (
              <Link
                to="/realisations"
                key={title}
                className="group rounded-[2rem] border border-white/10 bg-white/[.03] p-7 transition hover:-translate-y-1 hover:border-[#c8a45d]/40"
              >
                <p className="text-xs uppercase tracking-[.2em] text-[#c8a45d]">
                  {subtitle}
                </p>

                <h3 className="display mt-10 text-3xl font-extrabold">
                  {title}
                </h3>

                <p className="mt-3 text-sm leading-6 text-white/45">
                  {text}
                </p>

                <span className="mt-8 inline-flex items-center gap-2 text-sm font-bold">
                  Découvrir
                  <ArrowRight
                    size={15}
                    className="transition group-hover:translate-x-1"
                  />
                </span>
              </Link>
            ))}
          </div>

          <p className="mt-6 text-xs text-white/30">
            Les projets présentés ici sont des projets conceptuels.
            Les résultats clients réels seront publiés avec accord
            et données vérifiables.
          </p>
        </div>
      </section>

      <section className="px-6 py-16 lg:px-8 lg:py-24">
        <div className="mx-auto max-w-7xl">
          <SectionLabel>
            Pourquoi Vitrine+
          </SectionLabel>

          <h2 className="display mt-5 max-w-4xl text-5xl font-extrabold leading-[.98] sm:text-7xl">
            Pas une prestation isolée.
            <span className="block text-black/30">
              Un système cohérent.
            </span>
          </h2>

          <div className="mt-12 grid gap-5 md:grid-cols-2 lg:grid-cols-4">
            {[
              [
                CircleCheck,
                "Une vision globale",
                "Web, visibilité, conversion et automatisation ne sont pas traités séparément.",
              ],
              [
                Search,
                "Des décisions utiles",
                "Nous partons des objectifs et des problèmes avant de choisir les outils.",
              ],
              [
                Sparkles,
                "Une exigence premium",
                "Une expérience soignée sans sacrifier la clarté ni la performance.",
              ],
              [
                Check,
                "Un interlocuteur unique",
                "Un échange simple, direct et adapté à la réalité de votre entreprise.",
              ],
            ].map(([Icon, title, text]) => (
              <div
                key={title as string}
                className="rounded-3xl border border-black/10 p-7"
              >
                <Icon
                  size={25}
                  className="text-[#c8a45d]"
                />

                <h3 className="display mt-9 text-2xl font-extrabold">
                  {title as string}
                </h3>

                <p className="mt-3 text-sm leading-6 text-black/50">
                  {text as string}
                </p>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section className="bg-[#f5f5f2] px-6 py-16 lg:px-8 lg:py-24">
        <div className="mx-auto max-w-4xl">
          <SectionLabel>
            Questions fréquentes
          </SectionLabel>

          <h2 className="display mt-5 text-5xl font-extrabold sm:text-7xl">
            Avant de commencer.
          </h2>

          <div className="mt-10 divide-y divide-black/10">
            {faqs.map(([question, answer]) => (
              <details
                key={question}
                className="group py-6"
              >
                <summary className="flex cursor-pointer list-none items-center justify-between gap-5 text-lg font-bold">
                  <span>{question}</span>

                  <ChevronDown
                    size={19}
                    className="transition group-open:rotate-180"
                  />
                </summary>

                <p className="mt-4 max-w-3xl text-sm leading-7 text-black/50">
                  {answer}
                </p>
              </details>
            ))}
          </div>
        </div>
      </section>

      <CTA
        title="Votre prochain outil commercial commence ici."
        text="Commencez par un audit gratuit ou choisissez directement une date pour que nous vous appelions et parlions de votre projet."
      />
    </>
  );
}
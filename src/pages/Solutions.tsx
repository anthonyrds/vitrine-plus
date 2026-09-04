import {
  Check,
  HelpCircle,
} from "lucide-react";

import { Link } from "react-router-dom";

import PageHero from "../components/PageHero";
import CTA from "../components/CTA";
import SEO from "../components/SEO";
import SectionLabel from "../components/SectionLabel";

const plans = [
  {
    name: "START",
    price: "790 €",
    target: "Pour professionnaliser votre présence.",
    result: "Être clair, crédible et correctement présent en ligne.",
    items: [
      "Site vitrine jusqu’à 5 pages",
      "Design responsive",
      "Formulaire de contact",
      "SEO technique de base",
      "Mise en ligne",
    ],
  },
  {
    name: "GROW",
    price: "1 990 €",
    target: "Pour faire du digital un levier d’acquisition.",
    result: "Transformer votre site en outil commercial.",
    featured: true,
    items: [
      "Tout START",
      "Design entièrement personnalisé",
      "SEO avancé",
      "Optimisation du parcours de conversion",
      "Suivi des conversions",
      "1 mois d’accompagnement",
    ],
  },
  {
    name: "SCALE",
    price: "3 990 €",
    target: "Pour construire un écosystème digital avancé.",
    result: "Connecter visibilité, contenu, automatisation et acquisition.",
    items: [
      "Tout GROW",
      "Architecture web avancée",
      "Stratégie de contenu",
      "Automatisations",
      "Intégrations CRM / IA",
      "Suivi stratégique",
    ],
  },
];

const carePlans = [
  [
    "CARE",
    "49 €/mois",
    "Maintenance technique, sécurité, sauvegardes, surveillance et petites interventions.",
  ],
  [
    "GROWTH",
    "149 €/mois",
    "Maintenance + SEO + visibilité locale + optimisations régulières pour faire progresser votre présence.",
  ],
  [
    "PERFORMANCE",
    "299 €/mois+",
    "Accompagnement global : SEO, contenu, acquisition, évolutions et suivi stratégique.",
  ],
];

const faqs: [string, string][] = [
  [
    "Quelle solution Vitrine+ choisir ?",
    "START convient aux entreprises qui souhaitent professionnaliser leur présence en ligne. GROW s’adresse aux entreprises qui veulent transformer leur site en véritable outil commercial. SCALE correspond aux projets digitaux plus avancés combinant visibilité, contenu, automatisation et acquisition.",
  ],
  [
    "Combien coûte la création d’un site internet avec Vitrine+ ?",
    "Les solutions Vitrine+ démarrent à 790 €. Le prix exact dépend du périmètre, du niveau de personnalisation, des fonctionnalités et de l’accompagnement nécessaires.",
  ],
  [
    "Le SEO est-il inclus dans les solutions ?",
    "Une base SEO technique est incluse dans START. GROW propose un SEO plus avancé et SCALE intègre une approche plus globale de la visibilité, du contenu et de l’acquisition.",
  ],
  [
    "Que comprend V+ Care ?",
    "V+ Care permet de poursuivre l’accompagnement après la mise en ligne avec différents niveaux de maintenance, sécurité, sauvegardes, surveillance, SEO, visibilité et évolutions.",
  ],
  [
    "Puis-je faire évoluer mon site après sa création ?",
    "Oui. Les sites peuvent évoluer avec votre entreprise. Selon votre formule et vos besoins, nous pouvons ajouter des pages, fonctionnalités, optimisations ou nouveaux parcours.",
  ],
  [
    "Travaillez-vous partout en France ?",
    "Oui. Les échanges, le cadrage et le suivi peuvent être réalisés à distance avec les entreprises partout en France.",
  ],
];

export default function Solutions() {
  return (
    <>
      <SEO
        title="Tarifs & solutions — Création de site internet | Vitrine+"
        description="Découvrez les solutions START, GROW et SCALE de Vitrine+ pour créer un site internet professionnel, développer votre visibilité et faire évoluer votre présence digitale."
        canonical="/solutions"
        service={{
          name: "Solutions digitales Vitrine+",
          description:
            "Solutions de création de site internet, SEO, conversion, automatisation et accompagnement digital pour les entreprises.",
        }}
        faqs={faqs}
      />

      <PageHero
        eyebrow="Solutions"
        title={
          <>
            Une offre simple.
            <br />
            <span className="text-black/30">
              Une ambition adaptable.
            </span>
          </>
        }
        text="Choisissez le niveau d’accompagnement qui correspond à votre étape. Le périmètre est ensuite ajusté à votre entreprise."
      />

      <section
        aria-labelledby="solutions-principales"
        className="px-6 pb-28 lg:px-8 lg:pb-40"
      >
        <div className="mx-auto max-w-7xl">
          <div className="mb-10 max-w-3xl">
            <SectionLabel>
              Création de site internet
            </SectionLabel>

            <h2
              id="solutions-principales"
              className="display mt-5 text-4xl font-extrabold sm:text-6xl"
            >
              Trois niveaux pour construire une présence digitale solide.
            </h2>

            <p className="mt-6 text-lg leading-8 text-black/55">
              De la création d’un site vitrine professionnel à un écosystème
              digital plus avancé, nos solutions s’adaptent à vos objectifs,
              votre activité et votre niveau de maturité digitale.
            </p>
          </div>

          <div className="grid gap-5 lg:grid-cols-3">
            {plans.map((plan) => (
              <article
                key={plan.name}
                aria-label={`Solution ${plan.name}`}
                className={`rounded-[2rem] p-8 sm:p-10 ${
                  plan.featured
                    ? "bg-[#080808] text-white"
                    : "border border-black/10"
                }`}
              >
                {plan.featured && (
                  <span className="rounded-full bg-[#c8a45d] px-3 py-1 text-[10px] font-extrabold uppercase tracking-[.15em] text-black">
                    La plus choisie
                  </span>
                )}

                <p className="mt-6 text-xs font-bold tracking-[.2em] text-[#c8a45d]">
                  {plan.name}
                </p>

                <h3 className="display mt-6 text-5xl font-extrabold">
                  {plan.price}
                </h3>

                <p
                  className={`mt-4 font-semibold ${
                    plan.featured
                      ? "text-white/75"
                      : "text-black/70"
                  }`}
                >
                  {plan.target}
                </p>

                <div
                  className={`mt-4 rounded-2xl p-4 text-sm leading-6 ${
                    plan.featured
                      ? "bg-white/[.05] text-white/60"
                      : "bg-[#f5f5f2] text-black/55"
                  }`}
                >
                  <strong
                    className={
                      plan.featured
                        ? "text-white"
                        : "text-black"
                    }
                  >
                    Objectif :{" "}
                  </strong>

                  {plan.result}
                </div>

                <ul className="mt-8 grid gap-3">
                  {plan.items.map((item) => (
                    <li
                      key={item}
                      className={`flex gap-2 text-sm ${
                        plan.featured
                          ? "text-white/70"
                          : "text-black/60"
                      }`}
                    >
                      <Check
                        aria-hidden="true"
                        className="mt-0.5 shrink-0 text-[#c8a45d]"
                        size={16}
                      />

                      <span>{item}</span>
                    </li>
                  ))}
                </ul>

                <Link
                  to="/rendez-vous"
                  className={`mt-10 inline-flex rounded-full px-5 py-3 text-sm font-bold transition ${
                    plan.featured
                      ? "bg-white !text-black hover:bg-[#c8a45d]"
                      : "bg-black !text-white hover:bg-[#c8a45d] hover:!text-black"
                  }`}
                >
                  En parler
                </Link>
              </article>
            ))}
          </div>

          <p className="mt-6 flex max-w-4xl items-start gap-2 text-xs leading-5 text-black/40">
            <HelpCircle
              aria-hidden="true"
              size={15}
              className="mt-0.5 shrink-0"
            />

            <span>
              Les prix sont indicatifs. Le périmètre exact et le calendrier
              sont confirmés après échange et cadrage du projet.
            </span>
          </p>
        </div>
      </section>

      <section
        aria-labelledby="v-care"
        className="bg-[#f5f5f2] px-6 py-24 lg:px-8 lg:py-32"
      >
        <div className="mx-auto max-w-7xl">
          <SectionLabel>
            V+ Care
          </SectionLabel>

          <h2
            id="v-care"
            className="display mt-5 max-w-4xl text-4xl font-extrabold sm:text-6xl"
          >
            Maintenance et accompagnement de votre site internet.
          </h2>

          <p className="mt-6 max-w-3xl text-lg leading-8 text-black/55">
            Un site professionnel doit rester sécurisé, stable et à jour.
            Choisissez le niveau de suivi adapté à votre entreprise et gardez
            un interlocuteur unique lorsque votre site doit évoluer.
          </p>

          <div className="mt-12 grid gap-5 md:grid-cols-3">
            {carePlans.map(([name, price, description]) => (
              <article
                key={name}
                className="rounded-3xl bg-white p-8"
              >
                <p className="text-xs font-bold tracking-[.2em] text-[#c8a45d]">
                  {name}
                </p>

                <h3 className="display mt-6 text-3xl font-extrabold">
                  {price}
                </h3>

                <p className="mt-3 leading-7 text-black/50">
                  {description}
                </p>
              </article>
            ))}
          </div>

          <Link
            to="/services/maintenance"
            className="mt-8 inline-flex rounded-full bg-[#080808] px-5 py-3 text-sm font-bold !text-white transition hover:bg-[#c8a45d] hover:!text-black"
          >
            Voir le détail de V+ Care
          </Link>
        </div>
      </section>

      <section
        aria-labelledby="solutions-faq"
        className="px-6 py-24 lg:px-8 lg:py-32"
      >
        <div className="mx-auto max-w-5xl">
          <SectionLabel>
            Questions fréquentes
          </SectionLabel>

          <h2
            id="solutions-faq"
            className="display mt-5 max-w-4xl text-4xl font-extrabold sm:text-6xl"
          >
            Tout savoir avant de choisir votre solution.
          </h2>

          <div className="mt-12 divide-y divide-black/10 border-y border-black/10">
            {faqs.map(([question, answer]) => (
              <details
                key={question}
                className="group py-6"
              >
                <summary className="flex cursor-pointer list-none items-center justify-between gap-6 text-left text-lg font-bold">
                  <span>{question}</span>

                  <span
                    aria-hidden="true"
                    className="shrink-0 text-2xl font-light text-[#c8a45d] transition-transform group-open:rotate-45"
                  >
                    +
                  </span>
                </summary>

                <p className="max-w-3xl pt-4 pr-10 text-base leading-7 text-black/55">
                  {answer}
                </p>
              </details>
            ))}
          </div>
        </div>
      </section>

      <section className="px-6 pb-24 lg:px-8 lg:pb-32">
        <div className="mx-auto max-w-5xl">
          <div className="rounded-[2rem] border border-black/10 bg-[#f5f5f2] p-8 sm:p-12">
            <SectionLabel>
              Aller plus loin
            </SectionLabel>

            <h2 className="display mt-5 max-w-3xl text-3xl font-extrabold sm:text-5xl">
              Une solution n’a de valeur que si elle répond réellement à
              votre problème.
            </h2>

            <p className="mt-6 max-w-3xl text-lg leading-8 text-black/55">
              Si vous ne savez pas encore quelle solution correspond à votre
              entreprise, commencez par un échange ou par notre audit digital
              gratuit.
            </p>

            <div className="mt-8 flex flex-col gap-3 sm:flex-row">
              <Link
                to="/audit"
                className="inline-flex items-center justify-center rounded-full bg-[#080808] px-6 py-3.5 text-sm font-bold !text-white transition hover:bg-[#c8a45d] hover:!text-black"
              >
                Faire l’audit gratuit
              </Link>

              <Link
                to="/creation-site-internet"
                className="inline-flex items-center justify-center rounded-full border border-black/10 bg-white px-6 py-3.5 text-sm font-bold text-black transition hover:border-black"
              >
                Découvrir la création de site
              </Link>
            </div>
          </div>
        </div>
      </section>

      <CTA
        title="Vous ne savez pas quelle solution choisir ?"
        text="C’est justement le rôle du premier échange : comprendre votre situation et vous orienter vers le bon niveau de projet."
      />
    </>
  );
}
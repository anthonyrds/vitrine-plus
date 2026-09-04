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

export default function Solutions() {
  return (
    <>
      <SEO
        title="Tarifs & solutions — Création de site internet | Vitrine+"
        description="Découvrez les solutions START, GROW et SCALE de Vitrine+ pour créer, développer et faire évoluer votre présence digitale."
        canonical="/solutions"
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

      <section className="px-6 pb-28 lg:px-8 lg:pb-40">
        <div className="mx-auto grid max-w-7xl gap-5 lg:grid-cols-3">
          {plans.map((plan) => (
            <article
              key={plan.name}
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

              <h2 className="display mt-6 text-5xl font-extrabold">
                {plan.price}
              </h2>

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

              <div className="mt-8 grid gap-3">
                {plan.items.map((item) => (
                  <div
                    key={item}
                    className={`flex gap-2 text-sm ${
                      plan.featured
                        ? "text-white/70"
                        : "text-black/60"
                    }`}
                  >
                    <Check
                      className="mt-0.5 shrink-0 text-[#c8a45d]"
                      size={16}
                    />

                    {item}
                  </div>
                ))}
              </div>

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

        <p className="mx-auto mt-6 flex max-w-7xl items-start gap-2 text-xs leading-5 text-black/40">
          <HelpCircle
            size={15}
            className="mt-0.5 shrink-0"
          />

          Les prix sont indicatifs. Le périmètre exact et le
          calendrier sont confirmés après échange et cadrage
          du projet.
        </p>
      </section>

      <section className="bg-[#f5f5f2] px-6 py-24 lg:px-8 lg:py-32">
        <div className="mx-auto max-w-7xl">
          <SectionLabel>
            V+ Care
          </SectionLabel>

          <h2 className="display mt-5 max-w-4xl text-4xl font-extrabold sm:text-6xl">
            Votre site ne s’arrête pas à sa mise en ligne.
          </h2>

          <p className="mt-6 max-w-3xl text-lg leading-8 text-black/55">
            Un site professionnel doit rester sécurisé, stable et à jour.
            Choisissez le niveau de suivi adapté à votre entreprise et gardez
            un interlocuteur unique lorsque votre site doit évoluer.
          </p>

          <div className="mt-12 grid gap-5 md:grid-cols-3">
            {[
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
            ].map(([name, price, description]) => (
              <div
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
              </div>
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

      <CTA
        title="Vous ne savez pas quelle solution choisir ?"
        text="C’est justement le rôle du premier échange : comprendre votre situation et vous orienter vers le bon niveau de projet."
      />
    </>
  );
}
import {
  Activity,
  ArrowRight,
  Check,
  Database,
  ShieldCheck,
  Wrench,
} from "lucide-react";

import { Link } from "react-router-dom";

import PageHero from "../components/PageHero";
import CTA from "../components/CTA";
import SEO from "../components/SEO";
import SectionLabel from "../components/SectionLabel";

const services = [
  [
    ShieldCheck,
    "Sécurité",
    "Surveillance et entretien des éléments techniques essentiels de votre site.",
  ],
  [
    Database,
    "Sauvegardes",
    "Des sauvegardes régulières pour limiter les risques en cas de problème technique.",
  ],
  [
    Wrench,
    "Maintenance",
    "Mises à jour, corrections et petites interventions pour garder un site fonctionnel.",
  ],
  [
    Activity,
    "Surveillance",
    "Nous restons attentifs aux problèmes techniques qui peuvent affecter votre présence en ligne.",
  ],
];

const plans = [
  {
    name: "CARE",
    price: "49 €/mois",
    text: "L’essentiel pour garder votre site entretenu et sécurisé.",
    items: [
      "Maintenance technique",
      "Mises à jour",
      "Sauvegardes",
      "Surveillance",
      "Petites interventions",
    ],
  },
  {
    name: "GROWTH",
    price: "149 €/mois",
    text: "Pour faire évoluer votre visibilité en même temps que votre site.",
    featured: true,
    items: [
      "Tout CARE",
      "SEO & visibilité locale",
      "Optimisations régulières",
      "Suivi des priorités",
      "Accompagnement continu",
    ],
  },
  {
    name: "PERFORMANCE",
    price: "299 €/mois+",
    text: "Pour les entreprises qui veulent un accompagnement digital plus complet.",
    items: [
      "Tout GROWTH",
      "SEO & contenu",
      "Évolutions du site",
      "Acquisition",
      "Suivi stratégique",
    ],
  },
];

export default function Maintenance() {
  return (
    <>
      <SEO
        title="Maintenance de site internet — V+ Care | Vitrine+"
        description="V+ Care assure la maintenance, la sécurité, les sauvegardes et l’évolution de votre site internet après sa mise en ligne."
        canonical="/services/maintenance"
      />

      <PageHero
        eyebrow="V+ Care — Maintenance"
        title={
          <>
            Votre site ne s’arrête pas
            <br />
            <span className="text-black/30">
              à sa mise en ligne.
            </span>
          </>
        }
        text="Un site professionnel doit rester sécurisé, stable, à jour et capable d’évoluer avec votre entreprise. V+ Care assure le suivi dont vous avez besoin après la livraison."
      />

      <section className="px-6 pb-24 lg:px-8 lg:pb-36">
        <div className="mx-auto max-w-7xl">
          <SectionLabel>
            Ce que nous surveillons
          </SectionLabel>

          <div className="mt-10 grid gap-5 md:grid-cols-2 lg:grid-cols-4">
            {services.map(([Icon, title, text]) => (
              <article
                key={title as string}
                className="rounded-3xl border border-black/10 p-7"
              >
                <Icon
                  size={27}
                  className="text-[#c8a45d]"
                />

                <h2 className="display mt-10 text-2xl font-extrabold">
                  {title as string}
                </h2>

                <p className="mt-3 text-sm leading-7 text-black/50">
                  {text as string}
                </p>
              </article>
            ))}
          </div>
        </div>
      </section>

      <section className="bg-[#080808] px-6 py-24 text-white lg:px-8 lg:py-32">
        <div className="mx-auto max-w-7xl">
          <SectionLabel>
            Choisissez votre niveau de suivi
          </SectionLabel>

          <h2 className="display mt-5 max-w-4xl text-4xl font-extrabold sm:text-6xl">
            De la tranquillité à l’accompagnement continu.
          </h2>

          <div className="mt-12 grid gap-5 lg:grid-cols-3">
            {plans.map((plan) => (
              <article
                key={plan.name}
                className={`rounded-[2rem] p-8 sm:p-10 ${
                  plan.featured
                    ? "bg-white text-black"
                    : "border border-white/10 bg-white/[.03]"
                }`}
              >
                {plan.featured && (
                  <span className="rounded-full bg-[#c8a45d] px-3 py-1 text-[10px] font-extrabold uppercase tracking-[.15em] text-black">
                    Recommandé
                  </span>
                )}

                <p
                  className={`mt-6 text-xs font-bold tracking-[.2em] ${
                    plan.featured
                      ? "text-[#8e7136]"
                      : "text-[#c8a45d]"
                  }`}
                >
                  {plan.name}
                </p>

                <h3 className="display mt-6 text-4xl font-extrabold">
                  {plan.price}
                </h3>

                <p
                  className={`mt-4 leading-7 ${
                    plan.featured
                      ? "text-black/55"
                      : "text-white/55"
                  }`}
                >
                  {plan.text}
                </p>

                <div className="mt-8 grid gap-3">
                  {plan.items.map((item) => (
                    <div
                      key={item}
                      className={`flex gap-2 text-sm ${
                        plan.featured
                          ? "text-black/65"
                          : "text-white/65"
                      }`}
                    >
                      <Check
                        size={16}
                        className="mt-0.5 shrink-0 text-[#c8a45d]"
                      />
                      {item}
                    </div>
                  ))}
                </div>

                <Link
                  to="/rendez-vous"
                  className={`mt-10 inline-flex items-center gap-2 rounded-full px-5 py-3 text-sm font-bold ${
                    plan.featured
                      ? "bg-black !text-white"
                      : "bg-white !text-black"
                  }`}
                >
                  En parler
                  <ArrowRight size={15} />
                </Link>
              </article>
            ))}
          </div>

          <p className="mt-7 max-w-3xl text-xs leading-6 text-white/35">
            Les niveaux d’accompagnement et les interventions incluses
            sont précisés dans le devis selon la technologie, l’hébergement
            et les besoins du site.
          </p>
        </div>
      </section>

      <section className="px-6 py-24 lg:px-8 lg:py-32">
        <div className="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[.8fr_1.2fr]">
          <SectionLabel>
            Pourquoi la maintenance compte
          </SectionLabel>

          <div>
            <h2 className="display text-4xl font-extrabold sm:text-6xl">
              Parce qu’un site professionnel est un actif.
            </h2>

            <p className="mt-7 max-w-2xl text-lg leading-8 text-black/55">
              Une mise en ligne n’est pas une fin. Les technologies évoluent,
              les dépendances doivent être mises à jour et votre entreprise
              peut avoir besoin de nouvelles pages ou fonctionnalités.
              V+ Care vous permet de garder un site fiable sans devoir gérer
              seul sa maintenance.
            </p>
          </div>
        </div>
      </section>

      <CTA
        title="Votre site est déjà en ligne ?"
        text="Parlons de son entretien, de ses évolutions et du niveau de suivi réellement utile à votre entreprise."
      />
    </>
  );
}

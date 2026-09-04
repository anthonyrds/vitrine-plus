import {
  Activity,
  ArrowRight,
  Check,
  Database,
  Gauge,
  Search,
  ShieldCheck,
  TrendingUp,
  Wrench,
} from "lucide-react";

import { Link } from "react-router-dom";

import PageHero from "../components/PageHero";
import CTA from "../components/CTA";
import SEO from "../components/SEO";
import SectionLabel from "../components/SectionLabel";

const services = [
  {
    icon: ShieldCheck,
    title: "Sécurité",
    text: "Surveillance et entretien des éléments techniques essentiels de votre site afin de limiter les risques liés aux problèmes techniques.",
  },
  {
    icon: Database,
    title: "Sauvegardes",
    text: "Des sauvegardes régulières pour disposer d'un point de restauration en cas de problème ou d'incident.",
  },
  {
    icon: Wrench,
    title: "Mises à jour",
    text: "Mises à jour, corrections et petites interventions pour conserver un site fonctionnel et entretenu.",
  },
  {
    icon: Activity,
    title: "Surveillance",
    text: "Nous restons attentifs aux problèmes techniques susceptibles d'affecter votre site ou votre présence en ligne.",
  },
  {
    icon: Gauge,
    title: "Performance",
    text: "Le suivi permet d'identifier certains problèmes susceptibles de dégrader l'expérience utilisateur ou les performances.",
  },
  {
    icon: TrendingUp,
    title: "Évolution",
    text: "Votre site peut évoluer avec votre entreprise grâce à des interventions et améliorations adaptées à vos besoins.",
  },
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

const faqs = [
  {
    question: "Pourquoi faire maintenir son site internet ?",
    answer:
      "Un site internet repose sur une technologie qui évolue. Les mises à jour, la sécurité, les sauvegardes et certaines corrections permettent de limiter les risques et de conserver une présence digitale fonctionnelle.",
  },
  {
    question: "La maintenance comprend-elle les mises à jour ?",
    answer:
      "Oui. Les mises à jour techniques font partie du suivi prévu dans les offres de maintenance, dans les limites définies selon la technologie et les besoins du site.",
  },
  {
    question: "Pourquoi les sauvegardes sont-elles importantes ?",
    answer:
      "Une sauvegarde permet de disposer d'une copie du site ou de certaines données afin de pouvoir réagir plus facilement en cas de problème technique.",
  },
  {
    question: "Pouvez-vous maintenir un site que vous n'avez pas créé ?",
    answer:
      "Cela peut être envisagé après analyse de la technologie utilisée, de l'hébergement, de l'accès au site et de son état technique.",
  },
  {
    question: "La maintenance améliore-t-elle le référencement naturel ?",
    answer:
      "La maintenance et le SEO sont deux sujets différents. Toutefois, conserver un site techniquement sain, accessible, sécurisé et performant contribue à préserver une bonne base technique pour le référencement.",
  },
  {
    question: "Puis-je faire évoluer mon site pendant le contrat ?",
    answer:
      "Oui. Les besoins d'évolution peuvent être étudiés selon votre offre et la nature de l'intervention souhaitée.",
  },
  {
    question: "Quelle offre de maintenance choisir ?",
    answer:
      "Cela dépend du niveau de suivi nécessaire. CARE couvre l'entretien essentiel, GROWTH ajoute un accompagnement autour de la visibilité et PERFORMANCE vise un accompagnement digital plus complet.",
  },
];

export default function Maintenance() {
  return (
    <>
      <SEO
        title="Maintenance de site internet — V+ Care | Vitrine+"
        description="V+ Care assure la maintenance, la sécurité, les sauvegardes, les mises à jour et l'évolution de votre site internet après sa mise en ligne."
        canonical="/services/maintenance"
      />

      <PageHero
        eyebrow="V+ Care — Maintenance de site internet"
        title={
          <>
            Votre site ne s'arrête pas
            <br />
            <span className="text-black/30">
              à sa mise en ligne.
            </span>
          </>
        }
        text="Un site professionnel doit rester sécurisé, stable, à jour et capable d'évoluer avec votre entreprise. V+ Care assure le suivi dont vous avez besoin après la livraison."
      />

      <section className="px-6 pb-24 lg:px-8 lg:pb-36">
        <div className="mx-auto max-w-7xl">
          <SectionLabel>
            Maintenance de site internet
          </SectionLabel>

          <div className="mt-8 grid gap-12 lg:grid-cols-[.85fr_1.15fr]">
            <h2 className="display text-4xl font-extrabold leading-[1.02] sm:text-6xl">
              Un site entretenu
              <br />
              <span className="text-black/30">
                reste un site utile.
              </span>
            </h2>

            <div className="space-y-5 text-lg leading-8 text-black/55">
              <p>
                La mise en ligne d'un site internet n'est pas la dernière
                étape. Les technologies évoluent, les dépendances doivent être
                mises à jour, les sauvegardes doivent être maintenues et votre
                entreprise peut avoir besoin de nouvelles évolutions.
              </p>

              <p>
                V+ Care permet de déléguer une partie de cette maintenance afin
                que votre site reste fonctionnel, sécurisé et cohérent avec
                votre activité.
              </p>
            </div>
          </div>
        </div>
      </section>

      <section className="bg-[#f5f5f2] px-6 py-24 lg:px-8 lg:py-36">
        <div className="mx-auto max-w-7xl">
          <SectionLabel>
            Ce que nous surveillons
          </SectionLabel>

          <h2 className="display mt-7 max-w-4xl text-4xl font-extrabold leading-[1.02] sm:text-6xl">
            Les fondamentaux d'un site
            <br />
            <span className="text-black/30">
              professionnel.
            </span>
          </h2>

          <div className="mt-14 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            {services.map(({ icon: Icon, title, text }) => (
              <article
                key={title}
                className="rounded-[2rem] border border-black/10 bg-white p-8 sm:p-10"
              >
                <Icon
                  size={27}
                  className="text-[#c8a45d]"
                />

                <h3 className="display mt-12 text-2xl font-extrabold">
                  {title}
                </h3>

                <p className="mt-4 leading-7 text-black/50">
                  {text}
                </p>
              </article>
            ))}
          </div>
        </div>
      </section>

      <section className="bg-[#080808] px-6 py-24 text-white lg:px-8 lg:py-32">
        <div className="mx-auto max-w-7xl">
          <SectionLabel>
            Nos niveaux de suivi
          </SectionLabel>

          <h2 className="display mt-5 max-w-4xl text-4xl font-extrabold sm:text-6xl">
            De la tranquillité
            <br />
            <span className="text-white/30">
              à l'accompagnement continu.
            </span>
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
            Les niveaux d'accompagnement et les interventions incluses sont
            précisés dans le devis selon la technologie, l'hébergement et les
            besoins du site.
          </p>
        </div>
      </section>

      <section className="px-6 py-24 lg:px-8 lg:py-36">
        <div className="mx-auto grid max-w-7xl gap-14 lg:grid-cols-[.8fr_1.2fr]">
          <div>
            <SectionLabel>
              Maintenance & évolution
            </SectionLabel>

            <h2 className="display mt-6 text-4xl font-extrabold leading-[1.02] sm:text-6xl">
              Entretenir.
              <br />
              <span className="text-black/30">
                Puis améliorer.
              </span>
            </h2>
          </div>

          <div className="space-y-6 text-lg leading-8 text-black/55">
            <p>
              La maintenance ne consiste pas seulement à corriger les
              problèmes. Un site peut évoluer avec votre entreprise : nouvelle
              offre, nouvelle page, amélioration du parcours, optimisation des
              performances ou travail sur la visibilité.
            </p>

            <p>
              Pour les entreprises qui souhaitent aller plus loin, les offres
              GROWTH et PERFORMANCE associent maintenance et accompagnement
              autour du SEO, du contenu, de l'acquisition et de l'évolution du
              site.
            </p>

            <div className="flex flex-wrap gap-3 pt-2">
              <Link
                to="/services/seo"
                className="inline-flex items-center gap-2 text-sm font-bold"
              >
                Découvrir le SEO
                <ArrowRight size={15} />
              </Link>

              <Link
                to="/creation-site-internet"
                className="inline-flex items-center gap-2 text-sm font-bold"
              >
                Création de site
                <ArrowRight size={15} />
              </Link>
            </div>
          </div>
        </div>
      </section>

      <section className="bg-[#f5f5f2] px-6 py-24 lg:px-8 lg:py-32">
        <div className="mx-auto max-w-5xl">
          <SectionLabel>
            Questions fréquentes
          </SectionLabel>

          <h2 className="display mt-6 text-4xl font-extrabold sm:text-6xl">
            La maintenance,
            <br />
            <span className="text-black/30">
              en clair.
            </span>
          </h2>

          <div className="mt-12 divide-y divide-black/10 border-y border-black/10">
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

                <p className="max-w-3xl pt-5 text-sm leading-7 text-black/50">
                  {answer}
                </p>
              </details>
            ))}
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
import { ArrowRight, Check } from "lucide-react";
import { Link } from "react-router-dom";
import Button from "../components/Button";
import CTA from "../components/CTA";
import SEO from "../components/SEO";
import SectionLabel from "../components/SectionLabel";
import ServiceCard from "../components/ServiceCard";

export default function Home() {
  return (
    <>
      <SEO
        title="Vitrine+ — Agence digitale | Votre entreprise. En mieux."
        description="Vitrine+ accompagne les entreprises dans leur transformation digitale : création web, SEO, acquisition, identité, automatisation et IA."
        canonical="/"
      />

      {/* HERO */}
      <section className="relative min-h-[78vh] overflow-hidden bg-[#080808] px-6 pb-14 pt-32 text-white lg:min-h-[82vh] lg:px-8 lg:pt-40">
        <div className="absolute -right-40 top-0 h-[500px] w-[500px] rounded-full bg-[#c8a45d]/10 blur-[110px]" />
        <div className="absolute left-1/3 top-1/2 h-64 w-64 rounded-full bg-white/[0.025] blur-[90px]" />

        <div className="relative mx-auto flex max-w-7xl flex-col justify-between">
          <div className="max-w-6xl">
            <SectionLabel>Agence digitale indépendante</SectionLabel>

            <h1 className="display mt-7 max-w-6xl text-[15vw] font-extrabold leading-[0.92] tracking-[-0.045em] sm:text-8xl lg:text-[112px]">
              Votre entreprise.
              <span className="block text-white/35">En mieux.</span>
            </h1>

            <div className="mt-9 flex max-w-3xl flex-col gap-7 sm:flex-row sm:items-end">
              <p className="max-w-xl text-lg leading-8 text-white/55">
                Vitrine+ conçoit des expériences digitales qui renforcent
                votre image, votre visibilité et votre capacité à générer des
                clients.
              </p>

              <Button to="/audit">
                Obtenir mon audit gratuit
              </Button>
            </div>
          </div>

          <div className="mt-16 border-t border-white/10 pt-5 text-xs font-bold uppercase tracking-[.2em] text-white/25">
            Web · SEO · Acquisition · IA
          </div>
        </div>
      </section>

      {/* AUDIT TEASER — JUSTE SOUS LE HERO */}
      <section className="px-6 py-14 lg:px-8 lg:py-20">
        <div className="mx-auto max-w-7xl">
          <div className="overflow-hidden rounded-[2rem] bg-[#f5f5f2]">
            <div className="grid lg:grid-cols-[1.05fr_.95fr]">
              <div className="p-7 sm:p-10 lg:p-14">
                <SectionLabel>Audit digital gratuit</SectionLabel>

                <h2 className="display mt-5 max-w-2xl text-4xl font-extrabold leading-[1] sm:text-6xl">
                  Et si votre site vous faisait perdre des clients ?
                </h2>

                <p className="mt-6 max-w-xl text-base leading-7 text-black/55 sm:text-lg">
                  Entrez simplement l’adresse de votre site. Nous analysons
                  automatiquement l’ensemble de ses pages et identifions les
                  points qui peuvent freiner votre visibilité, votre crédibilité
                  et votre conversion.
                </p>

                <Button to="/audit">
                Analyser mon site gratuitement
              </Button>

                <div className="mt-7 flex flex-wrap gap-x-6 gap-y-3 text-xs font-semibold text-black/40">
                  <span className="flex items-center gap-2">
                    <Check size={14} className="text-[#c8a45d]" />
                    Analyse multi-pages
                  </span>
                  <span className="flex items-center gap-2">
                    <Check size={14} className="text-[#c8a45d]" />
                    Résultat en quelques secondes
                  </span>
                  <span className="flex items-center gap-2">
                    <Check size={14} className="text-[#c8a45d]" />
                    100 % gratuit
                  </span>
                </div>
              </div>

              {/* EXEMPLE DE RÉSULTAT */}
<div className="relative overflow-hidden bg-[#080808] p-7 text-white sm:p-10 lg:p-12">
  <div className="absolute -right-24 -top-24 h-72 w-72 rounded-full bg-[#c8a45d]/10 blur-[90px]" />

  <div className="relative">
    <p className="text-xs font-bold uppercase tracking-[.25em] text-white/35">
      Exemple de résultat
    </p>

    <div className="mt-4 flex items-baseline gap-3">
      <span className="display text-7xl font-extrabold leading-none tracking-[-.07em] sm:text-8xl">
        72
      </span>

      <span className="text-2xl font-semibold text-white/65">
        /100
      </span>
    </div>

    <div className="mt-5 h-1.5 w-full overflow-hidden rounded-full bg-white/10">
      <div className="h-full w-[72%] rounded-full bg-[#c8a45d]" />
    </div>

    <p className="mt-4 text-xs text-white/40">
      Score indicatif de présence digitale
    </p>

    <div className="mt-8 border-t border-white/10 pt-7">
      <p className="text-xs font-bold uppercase tracking-[.2em] text-white/35">
        3 conseils
      </p>

      <div className="mt-5 space-y-4">
        {[
          "Optimiser vos titres et descriptions pour améliorer votre visibilité sur Google.",
          "Améliorer la vitesse de chargement de vos pages.",
          "Renforcer vos appels à l’action pour transformer davantage de visiteurs en clients.",
        ].map((tip, index) => (
          <div key={tip} className="flex gap-3">
            <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white/10 text-[10px] font-extrabold text-[#c8a45d]">
              0{index + 1}
            </span>

            <p className="text-sm leading-5 text-white/60">
              {tip}
            </p>
          </div>
        ))}
      </div>
    </div>

    <div className="mt-8 rounded-2xl border border-[#c8a45d]/20 bg-[#c8a45d]/[0.06] p-5">
      <p className="text-xs font-bold uppercase tracking-[.2em] text-[#c8a45d]">
        Les + que Vitrine+ peut vous apporter
      </p>

      <p className="mt-3 text-sm leading-6 text-white/65">
        Un site plus performant, une meilleure visibilité et un parcours
        pensé pour convertir vos visiteurs en clients.
      </p>
    </div>
  </div>
</div>
            </div>
          </div>
        </div>
      </section>

      {/* CONVICTION */}
      <section className="px-6 py-16 lg:px-8 lg:py-24">
        <div className="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[.8fr_1.2fr]">
          <div>
            <SectionLabel>Notre conviction</SectionLabel>
          </div>

          <div>
            <h2 className="display text-4xl font-extrabold leading-[1.02] sm:text-6xl">
              Être présent en ligne ne suffit plus.
              <span className="block text-black/30">Il faut être choisi.</span>
            </h2>

            <p className="mt-7 max-w-2xl text-lg leading-8 text-black/55">
              Un site performant n’est pas une brochure. C’est un point de
              contact commercial. Nous pensons chaque détail autour d’une
              question : comment transformer l’attention en confiance, puis la
              confiance en action ?
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

      {/* SERVICES */}
      <section
        id="services"
        className="bg-[#f5f5f2] px-6 py-16 lg:px-8 lg:py-24"
      >
        <div className="mx-auto max-w-7xl">
          <div className="flex flex-col justify-between gap-7 md:flex-row md:items-end">
            <div>
              <SectionLabel>Nos expertises</SectionLabel>

              <h2 className="display mt-5 max-w-3xl text-5xl font-extrabold leading-[.98] sm:text-7xl">
                Tout ce qu’il faut pour construire une présence forte.
              </h2>
            </div>

            <Button to="/services">Voir tous les services</Button>
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
              to="/services"
            />

            <ServiceCard
              number="04"
              title="Identité digitale"
              text="Une image cohérente qui rend votre entreprise immédiatement crédible."
              to="/services"
            />

            <ServiceCard
              number="05"
              title="Réseaux sociaux"
              text="Une présence sociale régulière, stratégique et reconnaissable."
              to="/services"
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

      {/* SOLUTIONS */}
      <section className="px-6 py-16 lg:px-8 lg:py-24">
        <div className="mx-auto max-w-7xl">
          <div className="flex items-end justify-between gap-8">
            <div>
              <SectionLabel>Solutions</SectionLabel>

              <h2 className="display mt-5 text-5xl font-extrabold sm:text-7xl">
                Choisissez votre niveau d’ambition.
              </h2>
            </div>

            <Link
              to="/solutions"
              className="hidden text-sm font-bold md:block"
            >
              Voir les solutions →
            </Link>
          </div>

          <div className="mt-10 grid gap-5 lg:grid-cols-3">
            {[
              [
                "START",
                "À partir de 790 €",
                "Une présence professionnelle, claire et solide.",
              ],
              [
                "GROW",
                "À partir de 1 990 €",
                "Un système digital pensé pour développer votre activité.",
              ],
              [
                "SCALE",
                "À partir de 3 990 €",
                "Un écosystème avancé pour les entreprises ambitieuses.",
              ],
            ].map(([name, price, desc], i) => (
              <div
                key={name}
                className={`rounded-[2rem] p-8 sm:p-10 ${
                  i === 1
                    ? "bg-[#080808] text-white"
                    : "border border-black/10 bg-white"
                }`}
              >
                {i === 1 && (
                  <span className="rounded-full bg-[#c8a45d] px-3 py-1 text-[10px] font-extrabold uppercase tracking-[.15em] text-black">
                    Recommandée
                  </span>
                )}

                <p className="mt-6 text-xs font-bold uppercase tracking-[.2em] text-[#c8a45d]">
                  {name}
                </p>

                <h3 className="display mt-7 text-3xl font-extrabold">
                  {price}
                </h3>

                <p
                  className={`mt-4 leading-7 ${
                    i === 1 ? "text-white/55" : "text-black/50"
                  }`}
                >
                  {desc}
                </p>

                <div
                  className={`mt-7 grid gap-3 text-sm ${
                    i === 1 ? "text-white/75" : "text-black/65"
                  }`}
                >
                  {[
                    "Design sur mesure",
                    "Mobile & performance",
                    "SEO technique",
                    "Accompagnement",
                  ].map((x) => (
                    <span key={x} className="flex items-center gap-2">
                      <Check size={16} className="text-[#c8a45d]" />
                      {x}
                    </span>
                  ))}
                </div>

                <Link
                  to="/solutions"
                  className="mt-9 inline-flex items-center gap-2 text-sm font-bold"
                >
                  Découvrir
                  <ArrowRight size={16} />
                </Link>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* CTA FINAL */}
      <CTA
        title="Prêt à faire passer votre présence digitale au niveau supérieur ?"
        text="Commencez par un audit gratuit. Nous identifions les opportunités les plus importantes pour votre entreprise, sans engagement."
      />
    </>
  );
}
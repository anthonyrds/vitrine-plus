import { ArrowDown, ArrowRight, Check, Quote } from "lucide-react";
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

      <section className="noise relative min-h-[92vh] overflow-hidden bg-[#080808] px-6 pb-16 pt-36 text-white lg:min-h-screen lg:px-8 lg:pt-44">
        <div className="absolute -right-32 top-10 h-[620px] w-[620px] rounded-full bg-[#c8a45d]/15 blur-[130px]" />
        <div className="absolute left-1/3 top-1/2 h-72 w-72 rounded-full bg-white/5 blur-[100px]" />

        <div className="relative mx-auto flex max-w-7xl flex-col justify-between">
          <div className="max-w-6xl">
            <SectionLabel>Agence digitale indépendante</SectionLabel>

            <h1 className="display mt-7 max-w-6xl text-[15vw] font-extrabold leading-[0.94] tracking-[-0.045em] sm:text-8xl sm:leading-[0.92] lg:text-[112px] lg:leading-[0.9]">
              Votre entreprise.
              <span className="block text-white/35">En mieux.</span>
            </h1>

            <div className="mt-10 flex max-w-3xl flex-col gap-8 sm:flex-row sm:items-end">
              <p className="text-lg leading-8 text-white/55">
                Vitrine+ conçoit des expériences digitales qui renforcent votre
                image, votre visibilité et votre capacité à générer des clients.
              </p>

              <Button to="/audit" dark={false}>
                Obtenir mon audit gratuit
              </Button>
            </div>
          </div>

          <div className="mt-24 flex items-center justify-between border-t border-white/10 pt-5 text-xs font-bold uppercase tracking-[.2em] text-white/30">
            <span>Web · SEO · Acquisition · IA</span>
            <ArrowDown className="float" size={18} />
          </div>
        </div>
      </section>

      <section className="px-6 py-24 lg:px-8 lg:py-36">
        <div className="mx-auto grid max-w-7xl gap-12 lg:grid-cols-[.8fr_1.2fr]">
          <div>
            <SectionLabel>Notre conviction</SectionLabel>
          </div>

          <div>
            <h2 className="display text-4xl font-extrabold leading-[1.02] sm:text-6xl">
              Être présent en ligne ne suffit plus.
              <span className="block text-black/30">Il faut être choisi.</span>
            </h2>

            <p className="mt-8 max-w-2xl text-lg leading-8 text-black/55">
              Un site performant n’est pas une brochure. C’est un point de
              contact commercial. Nous pensons chaque détail autour d’une
              question : comment transformer l’attention en confiance, puis la
              confiance en action ?
            </p>

            <Link
              to="/a-propos"
              className="mt-8 inline-flex items-center gap-2 text-sm font-bold"
            >
              Découvrir notre approche
              <ArrowRight size={16} />
            </Link>
          </div>
        </div>
      </section>

      <section
        id="services"
        className="bg-[#f5f5f2] px-6 py-24 lg:px-8 lg:py-36"
      >
        <div className="mx-auto max-w-7xl">
          <div className="flex flex-col justify-between gap-8 md:flex-row md:items-end">
            <div>
              <SectionLabel>Nos expertises</SectionLabel>

              <h2 className="display mt-5 max-w-3xl text-5xl font-extrabold leading-[.98] sm:text-7xl">
                Tout ce qu’il faut pour construire une présence forte.
              </h2>
            </div>

            <Button to="/services">Voir tous les services</Button>
          </div>

          <div className="mt-14 grid gap-px overflow-hidden rounded-3xl bg-black/10 sm:grid-cols-2 lg:grid-cols-3">
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

      <section className="overflow-hidden bg-[#080808] py-5 text-white">
        <div className="marquee flex w-max gap-12 text-sm font-bold uppercase tracking-[.2em] text-white/30">
          <span>Vitrine+</span>
          <span>•</span>
          <span>Web</span>
          <span>•</span>
          <span>SEO</span>
          <span>•</span>
          <span>Stratégie</span>
          <span>•</span>
          <span>IA</span>
          <span>•</span>

          <span>Vitrine+</span>
          <span>•</span>
          <span>Web</span>
          <span>•</span>
          <span>SEO</span>
          <span>•</span>
          <span>Stratégie</span>
          <span>•</span>
          <span>IA</span>
        </div>
      </section>

      <section className="px-6 py-24 lg:px-8 lg:py-36">
        <div className="mx-auto max-w-7xl">
          <div className="flex flex-col justify-between gap-8 md:flex-row md:items-end">
            <div>
              <SectionLabel>Notre méthode</SectionLabel>

              <h2 className="display mt-5 max-w-3xl text-5xl font-extrabold sm:text-7xl">
                Stratégie. Design. Technologie. Résultats.
              </h2>
            </div>

            <p className="max-w-md text-black/50">
              Une approche simple : comprendre votre activité, construire une
              expérience forte et mesurer ce qui compte.
            </p>
          </div>

          <div className="mt-16 grid gap-10 border-t border-black/10 pt-10 md:grid-cols-4">
            {[
              ["01", "Comprendre", "Votre activité, vos clients et vos objectifs."],
              ["02", "Structurer", "Le positionnement, le parcours et les contenus."],
              ["03", "Construire", "Le design, la technologie et les intégrations."],
              ["04", "Optimiser", "Les performances, la visibilité et la conversion."],
            ].map(([n, t, d]) => (
              <div key={n}>
                <span className="text-xs font-bold tracking-[.2em] text-[#c8a45d]">
                  {n}
                </span>

                <h3 className="display mt-8 text-2xl font-extrabold">
                  {t}
                </h3>

                <p className="mt-3 leading-7 text-black/50">{d}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section className="bg-[#f5f5f2] px-6 py-24 lg:px-8 lg:py-36">
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

          <div className="mt-14 grid gap-5 lg:grid-cols-3">
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
                    ? "bg-[#080808] text-white glow"
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

                <h3 className="display mt-8 text-3xl font-extrabold">
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
                  className={`mt-8 grid gap-3 text-sm ${
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
                  className="mt-10 inline-flex items-center gap-2 text-sm font-bold"
                >
                  Découvrir
                  <ArrowRight size={16} />
                </Link>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section className="px-6 py-24 lg:px-8 lg:py-36">
        <div className="mx-auto max-w-7xl rounded-[2rem] border border-black/10 p-8 sm:p-12 lg:p-20">
          <div className="grid gap-12 lg:grid-cols-[1fr_.7fr] lg:items-end">
            <div>
              <Quote className="text-[#c8a45d]" size={40} />

              <blockquote className="display mt-8 text-4xl font-extrabold leading-tight sm:text-6xl">
                “Votre image digitale doit être à la hauteur de la qualité de
                votre entreprise.”
              </blockquote>
            </div>

            <div className="lg:text-right">
              <p className="text-sm font-bold">Vitrine+</p>

              <p className="mt-1 text-sm text-black/40">
                Une ambition nationale, construite projet après projet.
              </p>
            </div>
          </div>
        </div>
      </section>

      <CTA
        title="Prêt à faire passer votre présence digitale au niveau supérieur ?"
        text="Commencez par un audit gratuit. Nous identifions les opportunités les plus importantes pour votre entreprise, sans engagement."
      />
    </>
  );
}
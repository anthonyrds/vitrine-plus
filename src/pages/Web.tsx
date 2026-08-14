import { Check } from "lucide-react";
import PageHero from "../components/PageHero";
import CTA from "../components/CTA";
import SEO from "../components/SEO";
import SectionLabel from "../components/SectionLabel";

export default function Web() {
  return (
    <>
      <SEO
        title="Création de sites web — Design, UX & performance | Vitrine+"
        description="Vitrine+ conçoit des sites web rapides, premium et optimisés pour l'expérience utilisateur, le référencement naturel et la conversion."
        canonical="/services/web"
      />

      <PageHero
        eyebrow="Création web"
        title={
          <>
            Votre site ne doit pas seulement être beau.
            <br />
            <span className="text-black/30">Il doit travailler.</span>
          </>
        }
        text="Nous concevons des sites rapides, premium et pensés dès le départ autour de l'expérience utilisateur, du référencement et de la conversion."
      />

      <section className="bg-[#080808] px-6 py-24 text-white lg:px-8 lg:py-36">
        <div className="mx-auto grid max-w-7xl gap-16 lg:grid-cols-2">
          <div>
            <SectionLabel>Ce que nous construisons</SectionLabel>

            <h2 className="display mt-6 text-5xl font-extrabold sm:text-7xl">
              Une expérience qui donne envie de vous choisir.
            </h2>
          </div>

          <div className="grid gap-4">
            {[
              "Direction artistique sur mesure",
              "UX/UI et parcours de conversion",
              "Développement React moderne",
              "Responsive mobile, tablette et desktop",
              "SEO technique et structure sémantique",
              "Performance, sécurité et maintenance",
            ].map((x) => (
              <div
                key={x}
                className="flex gap-3 border-t border-white/10 py-5 text-white/70"
              >
                <Check
                  className="shrink-0 text-[#c8a45d]"
                  size={20}
                />
                {x}
              </div>
            ))}
          </div>
        </div>
      </section>

      <section className="px-6 py-24 lg:px-8 lg:py-36">
        <div className="mx-auto max-w-7xl">
          <SectionLabel>Notre standard</SectionLabel>

          <div className="mt-8 grid gap-10 md:grid-cols-3">
            {[
              [
                "01",
                "Rapidité",
                "Chaque seconde compte. Nous optimisons les assets, le code et l'expérience.",
              ],
              [
                "02",
                "Clarté",
                "Une hiérarchie visuelle qui permet de comprendre et d'agir immédiatement.",
              ],
              [
                "03",
                "Conversion",
                "Chaque page a un objectif commercial clair : rassurer, convaincre ou convertir.",
              ],
            ].map(([n, t, d]) => (
              <div key={n}>
                <span className="text-xs font-bold tracking-[.2em] text-[#c8a45d]">
                  {n}
                </span>

                <h3 className="display mt-7 text-3xl font-extrabold">
                  {t}
                </h3>

                <p className="mt-3 leading-7 text-black/50">{d}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      <CTA title="Construisons votre prochain site." />
    </>
  );
}
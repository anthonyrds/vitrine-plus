import { Search, TrendingUp, MapPin } from "lucide-react";
import PageHero from "../components/PageHero";
import CTA from "../components/CTA";
import SEOHead from "../components/SEO";
import SectionLabel from "../components/SectionLabel";

export default function SEO() {
  return (
    <>
      <SEOHead
        title="SEO & référencement naturel — Gagnez en visibilité | Vitrine+"
        description="Vitrine+ améliore votre visibilité dans Google grâce au SEO technique, au référencement local, aux contenus et à l'optimisation continue."
        canonical="/services/seo"
      />

      <PageHero
        eyebrow="SEO & visibilité"
        title={
          <>
            Être visible.
            <br />
            <span className="text-black/30">Au bon moment.</span>
          </>
        }
        text="Nous travaillons votre visibilité organique pour que votre entreprise puisse être trouvée par les personnes qui recherchent réellement vos services."
      />

      <section className="px-6 pb-24 lg:px-8 lg:pb-36">
        <div className="mx-auto grid max-w-7xl gap-5 md:grid-cols-3">
          {[
            [
              Search,
              "SEO technique",
              "Structure, indexation, performance, contenus et données structurées.",
            ],
            [
              MapPin,
              "SEO local",
              "Google Business Profile, visibilité locale et pages géographiques pertinentes.",
            ],
            [
              TrendingUp,
              "Croissance",
              "Suivi des positions, opportunités de mots-clés et optimisation continue.",
            ],
          ].map(([Icon, t, d]) => (
            <div
              key={t as string}
              className="rounded-3xl border border-black/10 p-8"
            >
              <Icon className="text-[#c8a45d]" size={30} />

              <h3 className="display mt-12 text-2xl font-extrabold">
                {t as string}
              </h3>

              <p className="mt-3 leading-7 text-black/50">
                {d as string}
              </p>
            </div>
          ))}
        </div>
      </section>

      <CTA
        title="Mesurons votre visibilité."
        text="Commencez par un audit de votre présence actuelle et identifions les leviers prioritaires."
      />
    </>
  );
}
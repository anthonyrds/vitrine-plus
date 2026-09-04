import { Link } from "react-router-dom";

import PageHero from "../components/PageHero";
import ServiceCard from "../components/ServiceCard";
import CTA from "../components/CTA";
import SEO from "../components/SEO";
import SectionLabel from "../components/SectionLabel";

export default function Services() {
  return (
    <>
      <SEO
        title="Services digitaux — Web, SEO, visibilité & automatisation | Vitrine+"
        description="Découvrez les services Vitrine+ : création et refonte de sites internet, SEO, acquisition, identité digitale, réseaux sociaux, maintenance et automatisation."
        canonical="/services"
      />

      <PageHero
        eyebrow="Nos expertises"
        title={
          <>
            Un écosystème digital.
            <br />
            <span className="text-black/30">Une seule direction.</span>
          </>
        }
        text="Vitrine+ réunit stratégie, design, technologie et acquisition pour construire une présence digitale cohérente et performante."
      />

      <section className="px-6 pb-28 lg:px-8 lg:pb-40">
        <div className="mx-auto max-w-7xl">
          <div className="mb-12 max-w-4xl">
            <SectionLabel>Nos services</SectionLabel>

            <h2 className="display mt-5 max-w-3xl text-5xl font-extrabold leading-[.98] sm:text-6xl">
              Chaque levier doit servir
              <span className="text-black/30"> le même objectif.</span>
            </h2>

            <p className="mt-7 max-w-2xl text-lg leading-8 text-black/55">
              Un site internet, le référencement naturel, l’identité visuelle
              ou l’automatisation ne doivent pas fonctionner séparément.
              Vitrine+ construit une stratégie cohérente autour de votre
              entreprise et de vos objectifs commerciaux.
            </p>
          </div>

          <div className="grid gap-px overflow-hidden rounded-3xl bg-black/10 sm:grid-cols-2 lg:grid-cols-3">
            <ServiceCard
              number="01"
              title="Création web"
              text="Sites vitrines, plateformes et expériences web pensées pour la conversion."
              to="/creation-site-internet"
            />

            <ServiceCard
              number="02"
              title="SEO & visibilité"
              text="Référencement naturel, visibilité locale et optimisation technique."
              to="/services/seo"
            />

            <ServiceCard
              number="03"
              title="Acquisition"
              text="Parcours, landing pages, formulaires et stratégie de génération de prospects."
              to="/audit"
            />

            <ServiceCard
              number="04"
              title="Identité digitale"
              text="Identité visuelle, direction artistique et cohérence de marque."
              to="/contact"
            />

            <ServiceCard
              number="05"
              title="Réseaux sociaux"
              text="Stratégie éditoriale, contenus et présence sociale."
              to="/contact"
            />

            <ServiceCard
              number="06"
              title="IA & automatisation"
              text="Solutions intelligentes pour simplifier certains processus et gagner du temps."
              to="/services/ia"
            />
          </div>
        </div>
      </section>

      <section className="bg-[#f5f5f2] px-6 py-24 lg:px-8 lg:py-32">
        <div className="mx-auto grid max-w-7xl gap-12 lg:grid-cols-2 lg:items-center">
          <div>
            <SectionLabel>Par où commencer ?</SectionLabel>

            <h2 className="display mt-5 max-w-3xl text-5xl font-extrabold leading-[.98] sm:text-6xl">
              Commencez par comprendre ce qui bloque.
            </h2>
          </div>

          <div>
            <p className="max-w-2xl text-lg leading-8 text-black/55">
              Avant de choisir une prestation, notre audit digital permet
              d'identifier les principaux freins de votre présence en ligne :
              SEO, structure, contenu, performance, visibilité et conversion.
            </p>

            <Link
              to="/audit"
              className="mt-7 inline-flex rounded-full bg-[#080808] px-5 py-3 text-sm font-bold !text-white transition hover:bg-[#c8a45d] hover:!text-[#080808]"
            >
              Réaliser mon audit gratuit
            </Link>
          </div>
        </div>
      </section>

      <CTA />
    </>
  );
}
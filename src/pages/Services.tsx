import { Link } from "react-router-dom";
import PageHero from "../components/PageHero";
import ServiceCard from "../components/ServiceCard";
import CTA from "../components/CTA";
import SEO from "../components/SEO";

export default function Services() {
  return (
    <>
      <SEO
        title="Services — Création de sites, SEO & accompagnement | Vitrine+"
        description="Découvrez les services Vitrine+ : création de sites internet, SEO, acquisition, identité digitale, réseaux sociaux et accompagnement technique."
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
        <div className="mx-auto grid max-w-7xl gap-px overflow-hidden rounded-3xl bg-black/10 sm:grid-cols-2 lg:grid-cols-3">
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
            text="Automatiser les tâches répétitives et connecter les outils de l'entreprise."
            to="/services/ia"
          />
        </div>
      </section>

      <section className="bg-[#f5f5f2] px-6 py-20 lg:px-8 lg:py-28">
        <div className="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[.8fr_1.2fr] lg:items-end">
          <div>
            <p className="text-xs font-bold uppercase tracking-[.2em] text-[#c8a45d]">
              Après la mise en ligne
            </p>

            <h2 className="display mt-5 max-w-3xl text-5xl font-extrabold leading-[.98] sm:text-6xl">
              Votre site mérite un suivi.
            </h2>
          </div>

          <div>
            <p className="max-w-2xl text-lg leading-8 text-black/55">
              Avec V+ Care, votre site ne reste pas figé après sa livraison.
              Nous assurons son suivi technique et pouvons intervenir pour
              préserver sa sécurité, sa stabilité et son évolution.
            </p>

            <Link
              to="/services/maintenance"
              className="mt-7 inline-flex items-center rounded-full bg-[#080808] px-5 py-3 text-sm font-bold !text-white transition hover:bg-[#c8a45d] hover:!text-[#080808]"
            >
              Découvrir V+ Care
            </Link>
          </div>
        </div>
      </section>

      <CTA />
    </>
  );
}
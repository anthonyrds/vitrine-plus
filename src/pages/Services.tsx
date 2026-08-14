import PageHero from "../components/PageHero";
import ServiceCard from "../components/ServiceCard";
import CTA from "../components/CTA";
import SEO from "../components/SEO";

export default function Services() {
  return (
    <>
      <SEO
        title="Services digitaux — Web, SEO, IA & acquisition | Vitrine+"
        description="Découvrez les services Vitrine+ : création web, SEO, acquisition, identité digitale, réseaux sociaux, IA et automatisation."
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
            to="/services/web"
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

      <CTA />
    </>
  );
}
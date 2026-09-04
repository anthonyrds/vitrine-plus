import PageHero from "../components/PageHero";
import LeadForm from "../components/LeadForm";
import SectionLabel from "../components/SectionLabel";
import SEO from "../components/SEO";

export default function Contact() {
  return (
    <>
      <SEO
        title="Contact Vitrine+ — Création de site, SEO & projet digital"
        description="Contactez Vitrine+ pour parler de votre projet de création ou refonte de site internet, de SEO, de visibilité ou de transformation digitale."
        canonical="/contact"
      />

      <PageHero
        eyebrow="Contact"
        title={
          <>
            Parlons de ce que
            <br />
            <span className="text-black/30">
              vous voulez construire.
            </span>
          </>
        }
        text="Un projet web, une refonte, une problématique de visibilité ou une idée à transformer en solution ? Écrivez-nous."
      />

      <section className="px-6 pb-28 lg:px-8 lg:pb-40">
        <div className="mx-auto grid max-w-7xl gap-12 lg:grid-cols-[.6fr_1.4fr]">
          <div>
            <SectionLabel>Vitrine+</SectionLabel>

            <h2 className="display mt-6 text-4xl font-extrabold leading-[1.05] sm:text-6xl">
              Un premier échange suffit pour commencer.
            </h2>

            <div className="mt-8 grid gap-4 text-sm leading-7 text-black/55">
              <p>✦ Réponse à votre demande</p>
              <p>✦ Analyse de vos objectifs</p>
              <p>✦ Proposition adaptée</p>
              <p>✦ Aucun engagement pour l'échange initial</p>
            </div>

            <div className="mt-10">
              <p className="text-sm font-bold text-black">
                Vous préférez être rappelé ?
              </p>

              <p className="mt-2 max-w-sm text-sm leading-6 text-black/50">
                Vous pouvez également réserver directement un créneau avec
                Vitrine+ afin de présenter votre entreprise et votre projet.
              </p>

              <a
                href="/rendez-vous"
                className="mt-5 inline-flex rounded-full border border-black/10 px-5 py-3 text-sm font-bold transition hover:bg-black/5"
              >
                Prendre rendez-vous
              </a>
            </div>
          </div>

          <div className="rounded-[2rem] border border-black/10 bg-[#f5f5f2] p-7 sm:p-10">
            <LeadForm />
          </div>
        </div>
      </section>
    </>
  );
}
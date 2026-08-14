import PageHero from "../components/PageHero";
import LeadForm from "../components/LeadForm";
import SectionLabel from "../components/SectionLabel";
import SEO from "../components/SEO";

export default function Contact() {
  return <>
  <SEO
  title="Contact — Parlons de votre projet | Vitrine+"
  description="Vous avez un projet web, une refonte, un besoin SEO ou une idée à transformer en solution ? Contactez Vitrine+."
  canonical="/contact"
/>
    <PageHero eyebrow="Contact" title={<>Parlons de ce que<br/><span className="text-black/30">vous voulez construire.</span></>} text="Un projet web, une refonte, une problématique de visibilité ou une idée à transformer en solution ? Écrivez-nous." />
    <section className="px-6 pb-28 lg:px-8 lg:pb-40"><div className="mx-auto grid max-w-7xl gap-12 lg:grid-cols-[.6fr_1.4fr]">
      <div><SectionLabel>Vitrine+</SectionLabel><h2 className="display mt-6 text-4xl font-extrabold">Un premier échange suffit pour commencer.</h2><div className="mt-8 grid gap-4 text-sm text-black/55"><p>✦ Réponse à votre demande</p><p>✦ Analyse de vos objectifs</p><p>✦ Proposition adaptée</p><p>✦ Aucun engagement pour l'échange initial</p></div></div>
      <div className="rounded-[2rem] border border-black/10 bg-[#f5f5f2] p-7 sm:p-10"><LeadForm/></div>
    </div></section>
  </>;
}
import PageHero from "../components/PageHero";
import LeadForm from "../components/LeadForm";
import SectionLabel from "../components/SectionLabel";
import SEO from "../components/SEO";

export default function Audit() {
  return <>
  <SEO
  title="Audit digital gratuit — Analysez votre présence en ligne | Vitrine+"
  description="Obtenez un audit digital gratuit de votre site, SEO, visibilité Google, réseaux sociaux et parcours de conversion avec Vitrine+."
  canonical="/audit"
/>
    <PageHero eyebrow="Audit digital gratuit" title={<>Découvrez ce qui freine<br/><span className="text-black/30">votre présence digitale.</span></>} text="Nous analysons les principaux points de contact de votre entreprise en ligne et vous indiquons les opportunités prioritaires." />
    <section className="px-6 pb-28 lg:px-8 lg:pb-40">
      <div className="mx-auto grid max-w-7xl gap-12 lg:grid-cols-[.65fr_1.35fr]">
        <div><SectionLabel>Ce que nous regardons</SectionLabel><div className="mt-8 grid gap-5">{["Site internet & expérience mobile","Référencement & visibilité Google","Fiche Google Business","Réseaux sociaux","Parcours de conversion","Cohérence de votre image"].map((x,i)=><div key={x} className="flex gap-4 border-b border-black/10 pb-4 text-sm font-semibold"><span className="text-[#c8a45d]">0{i+1}</span>{x}</div>)}</div></div>
        <div className="rounded-[2rem] border border-black/10 bg-[#f5f5f2] p-7 sm:p-10"><LeadForm audit/></div>
      </div>
    </section>
  </>;
}
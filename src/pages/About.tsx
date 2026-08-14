import { Target, Eye, Compass } from "lucide-react";
import PageHero from "../components/PageHero";
import CTA from "../components/CTA";
import SEO from "../components/SEO";

export default function About() {
  return <>
  <SEO
  title="À propos de Vitrine+ — Agence digitale indépendante"
  description="Découvrez Vitrine+, agence digitale indépendante dédiée au web, au SEO, à l'identité digitale, à l'acquisition et à l'automatisation."
  canonical="/a-propos"
/>
    <PageHero eyebrow="À propos" title={<>Construire une entreprise digitale<br/><span className="text-black/30">à la hauteur de ses ambitions.</span></>} text="Vitrine+ est née d'une conviction simple : une petite entreprise peut avoir une présence digitale aussi exigeante qu'une grande marque." />
    <section className="bg-[#080808] px-6 py-24 text-white lg:px-8 lg:py-36"><div className="mx-auto max-w-7xl grid gap-5 md:grid-cols-3">
      {[[Target,"Exigence","Chaque détail compte. Nous cherchons la qualité plutôt que la quantité."],[Eye,"Vision","Construire progressivement une entreprise digitale capable de servir des acteurs partout en France."],[Compass,"Précision","Une stratégie claire, une exécution soignée et des décisions guidées par les objectifs."]].map(([I,t,d])=><div key={t as string} className="rounded-3xl border border-white/10 p-8"><I className="text-[#c8a45d]" size={30}/><h3 className="display mt-14 text-3xl font-extrabold">{t as string}</h3><p className="mt-3 leading-7 text-white/50">{d as string}</p></div>)}
    </div></section>
    <section className="px-6 py-24 lg:px-8 lg:py-36"><div className="mx-auto max-w-4xl"><p className="text-2xl font-semibold leading-9 sm:text-4xl sm:leading-[1.35]">« Nous ne voulons pas seulement créer des présences digitales. Nous voulons créer des actifs qui renforcent les entreprises sur le long terme. »</p></div></section>
    <CTA title="Construisons la suite." />
  </>;
}
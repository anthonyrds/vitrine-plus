import { Check } from "lucide-react";
import { Link } from "react-router-dom";
import PageHero from "../components/PageHero";
import CTA from "../components/CTA";
import SEO from "../components/SEO";

const plans = [
  { name:"START", price:"790 €", desc:"Pour poser des bases professionnelles.", items:["Site vitrine jusqu'à 5 pages","Design responsive","Formulaire de contact","SEO technique de base","Mise en ligne"] },
  { name:"GROW", price:"1 990 €", desc:"Pour transformer le digital en outil d'acquisition.", featured:true, items:["Tout START","Design entièrement personnalisé","SEO avancé","Optimisation Google Business","Suivi des conversions","1 mois d'accompagnement"] },
  { name:"SCALE", price:"3 990 €", desc:"Pour les entreprises qui veulent un écosystème avancé.", items:["Tout GROW","Architecture web avancée","Stratégie de contenu","Automatisations","Intégrations CRM / IA","Suivi stratégique"] }
];

export default function Solutions() {
  return <>
  <SEO
  title="Solutions digitales — Start, Grow & Scale | Vitrine+"
  description="Découvrez les solutions digitales Vitrine+ : Start, Grow et Scale pour construire une présence professionnelle, développer votre acquisition et faire évoluer votre entreprise."
  canonical="/solutions"
/>
    <PageHero eyebrow="Solutions" title={<>Une offre simple.<br/><span className="text-black/30">Une ambition adaptable.</span></>} text="Des bases solides pour démarrer, un système d'acquisition pour grandir, une architecture avancée pour passer à l'échelle." />
    <section className="px-6 pb-28 lg:px-8 lg:pb-40">
      <div className="mx-auto grid max-w-7xl gap-5 lg:grid-cols-3">
        {plans.map(p=><article key={p.name} className={`rounded-[2rem] p-8 sm:p-10 ${p.featured?"bg-[#080808] text-white glow":"border border-black/10"}`}>
          {p.featured && <span className="rounded-full bg-[#c8a45d] px-3 py-1 text-[10px] font-extrabold uppercase tracking-[.15em] text-black">Recommandée</span>}
          <p className="mt-6 text-xs font-bold tracking-[.2em] text-[#c8a45d]">{p.name}</p>
          <h2 className="display mt-7 text-4xl font-extrabold">{p.price}</h2>
          <p className={`mt-3 leading-7 ${p.featured?"text-white/50":"text-black/50"}`}>{p.desc}</p>
          <div className="mt-8 grid gap-3">{p.items.map(x=><div key={x} className={`flex gap-2 text-sm ${p.featured?"text-white/70":"text-black/60"}`}><Check className="mt-0.5 shrink-0 text-[#c8a45d]" size={16}/>{x}</div>)}</div>
          <Link
  to="/contact"
  className={`mt-10 inline-flex rounded-full px-5 py-3 text-sm font-bold transition ${
    p.featured
      ? "bg-white !text-black hover:bg-[#c8a45d]"
      : "bg-black !text-white hover:bg-[#c8a45d] hover:!text-black"
  }`}
>
  Choisir cette solution
</Link>
        </article>)}
      </div>
    </section>
    <section className="bg-[#f5f5f2] px-6 py-24 lg:px-8 lg:py-32"><div className="mx-auto max-w-7xl"><h2 className="display text-4xl font-extrabold sm:text-6xl">Accompagnement mensuel.</h2><p className="mt-5 max-w-2xl text-lg leading-8 text-black/50">Après le lancement, nous pouvons continuer à faire évoluer votre présence digitale.</p><div className="mt-12 grid gap-5 md:grid-cols-3">{[["CARE","49 €/mois","Maintenance, sécurité, sauvegardes et petites interventions."],["GROWTH","149 €/mois","Maintenance + SEO + Google + optimisation continue."],["PERFORMANCE","299 €/mois+","SEO, contenu, acquisition, automatisation et stratégie."]].map(x=><div key={x[0]} className="rounded-3xl bg-white p-8"><p className="text-xs font-bold tracking-[.2em] text-[#c8a45d]">{x[0]}</p><h3 className="display mt-6 text-3xl font-extrabold">{x[1]}</h3><p className="mt-3 leading-7 text-black/50">{x[2]}</p></div>)}</div></div></section>
    <CTA />
  </>;
}
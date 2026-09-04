import {
  Compass,
  Eye,
  Target,
} from "lucide-react";

import { Link } from "react-router-dom";

import PageHero from "../components/PageHero";
import CTA from "../components/CTA";
import SEO from "../components/SEO";
import SectionLabel from "../components/SectionLabel";

const principles = [
  [
    Target,
    "Exigence",
    "Chaque détail doit avoir une raison. Nous cherchons la qualité sans ajouter de complexité inutile.",
  ],
  [
    Eye,
    "Vision",
    "Votre présence digitale doit pouvoir évoluer avec votre entreprise, pas être reconstruite à chaque étape.",
  ],
  [
    Compass,
    "Précision",
    "Nous préférons une stratégie claire, des priorités assumées et une exécution réellement utile.",
  ],
];

const method = [
  ["01", "Révéler", "Audit et diagnostic."],
  ["02", "Positionner", "Offre, cible et message."],
  ["03", "Concevoir", "UX et direction artistique."],
  ["04", "Construire", "Développement et SEO technique."],
  ["05", "Convertir", "Parcours, CTA et formulaires."],
  ["06", "Accélérer", "SEO, contenu et automatisation."],
];

export default function About() {
  return (
    <>
      <SEO
        title="À propos de Vitrine+ — Agence digitale indépendante"
        description="Vitrine+ est une agence digitale indépendante qui transforme les présences en ligne en outils commerciaux grâce à la stratégie, au web, au SEO et à la conversion."
        canonical="/a-propos"
      />

      <PageHero
        eyebrow="À propos"
        title={
          <>
            Construire une présence digitale
            <br />
            <span className="text-black/30">
              à la hauteur de votre entreprise.
            </span>
          </>
        }
        text="Vitrine+ part d’une idée simple : une petite entreprise peut avoir une présence digitale aussi exigeante qu’une grande marque — et surtout aussi utile commercialement."
      />

      <section className="bg-[#080808] px-6 py-24 text-white lg:px-8 lg:py-36">
        <div className="mx-auto grid max-w-7xl gap-5 md:grid-cols-3">
          {principles.map(([Icon, title, text]) => (
            <article
              key={title as string}
              className="rounded-3xl border border-white/10 p-8"
            >
              <Icon
                className="text-[#c8a45d]"
                size={30}
              />

              <h2 className="display mt-14 text-3xl font-extrabold">
                {title as string}
              </h2>

              <p className="mt-3 leading-7 text-white/50">
                {text as string}
              </p>
            </article>
          ))}
        </div>
      </section>

      <section className="px-6 py-24 lg:px-8 lg:py-36">
        <div className="mx-auto max-w-5xl">
          <SectionLabel>
            Notre rôle
          </SectionLabel>

          <h2 className="display mt-6 text-4xl font-extrabold leading-[1.05] sm:text-6xl">
            Nous ne voulons pas seulement créer des sites.
            <span className="block text-black/30">
              Nous voulons construire des outils qui servent votre entreprise.
            </span>
          </h2>

          <p className="mt-8 max-w-3xl text-lg leading-8 text-black/55">
            Cela signifie commencer par vos objectifs, comprendre ce qui
            bloque aujourd’hui, puis choisir la bonne combinaison de stratégie,
            design, technologie, visibilité et automatisation.
          </p>

          <div className="mt-8 flex flex-wrap gap-3">
            <Link
              to="/creation-site-internet"
              className="rounded-full bg-[#080808] px-5 py-3 text-sm font-bold !text-white transition hover:bg-[#c8a45d] hover:!text-[#080808]"
            >
              Découvrir la création de site
            </Link>

            <Link
              to="/audit"
              className="rounded-full border border-black/10 px-5 py-3 text-sm font-bold transition hover:bg-black/5"
            >
              Faire un audit gratuit
            </Link>
          </div>
        </div>
      </section>

      <section className="bg-[#f5f5f2] px-6 py-24 lg:px-8 lg:py-32">
        <div className="mx-auto max-w-7xl">
          <SectionLabel>
            La méthode V+
          </SectionLabel>

          <h2 className="display mt-5 max-w-4xl text-5xl font-extrabold sm:text-7xl">
            Révéler. Positionner. Concevoir. Construire. Convertir. Accélérer.
          </h2>

          <div className="mt-12 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            {method.map(([number, title, text]) => (
              <article
                key={number}
                className="rounded-3xl border border-black/10 bg-white p-7"
              >
                <span className="text-xs font-bold tracking-[.2em] text-[#b08a45]">
                  {number}
                </span>

                <h3 className="display mt-7 text-2xl font-extrabold">
                  {title}
                </h3>

                <p className="mt-3 text-sm leading-7 text-black/50">
                  {text}
                </p>
              </article>
            ))}
          </div>
        </div>
      </section>

      <section className="px-6 py-24 lg:px-8 lg:py-36">
        <div className="mx-auto grid max-w-7xl gap-12 lg:grid-cols-2">
          <div>
            <SectionLabel>
              Une approche globale
            </SectionLabel>

            <h2 className="display mt-6 text-4xl font-extrabold leading-[1.02] sm:text-6xl">
              Du premier diagnostic
              <span className="block text-black/30">
                jusqu’à l’accélération.
              </span>
            </h2>
          </div>

          <div className="space-y-6 text-lg leading-8 text-black/55">
            <p>
              Une présence digitale efficace ne repose pas uniquement sur un
              beau site. Elle doit être compréhensible, visible, rapide et
              orientée vers l’action.
            </p>

            <p>
              C’est pourquoi Vitrine+ travaille sur l’ensemble du parcours :
              positionnement, expérience utilisateur, conception web,
              référencement naturel, conversion et évolution du site.
            </p>

            <div className="flex flex-wrap gap-3 pt-2">
              <Link
                to="/services"
                className="rounded-full border border-black/10 px-5 py-3 text-sm font-bold transition hover:bg-black/5"
              >
                Voir nos services
              </Link>

              <Link
                to="/solutions"
                className="rounded-full border border-black/10 px-5 py-3 text-sm font-bold transition hover:bg-black/5"
              >
                Découvrir nos solutions
              </Link>

              <Link
                to="/rendez-vous"
                className="rounded-full bg-[#080808] px-5 py-3 text-sm font-bold !text-white transition hover:bg-[#c8a45d] hover:!text-[#080808]"
              >
                Prendre rendez-vous
              </Link>
            </div>
          </div>
        </div>
      </section>

      <CTA />
    </>
  );
}
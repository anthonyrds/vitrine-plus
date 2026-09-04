import { Bot, Workflow, ShieldCheck } from "lucide-react";
import { Link } from "react-router-dom";

import PageHero from "../components/PageHero";
import CTA from "../components/CTA";
import SEO from "../components/SEO";
import SectionLabel from "../components/SectionLabel";

const applications = [
  {
    icon: Bot,
    title: "Assistants IA",
    text: "Répondre, qualifier, synthétiser et aider vos équipes dans les tâches qui consomment du temps.",
  },
  {
    icon: Workflow,
    title: "Automatisations",
    text: "Faire circuler automatiquement les informations entre vos outils et réduire les tâches répétitives.",
  },
  {
    icon: ShieldCheck,
    title: "Cadre & contrôle",
    text: "Construire des automatisations utiles, traçables et maîtrisées, adaptées à votre fonctionnement.",
  },
];

export default function AI() {
  return (
    <>
      <SEO
        title="IA & automatisation pour entreprise — Solutions intelligentes | Vitrine+"
        description="Vitrine+ accompagne les entreprises dans l'intégration de l'intelligence artificielle et l'automatisation des processus pour gagner du temps et améliorer leur efficacité."
        canonical="/services/ia"
      />

      <PageHero
        eyebrow="IA & automatisation"
        title={
          <>
            Moins de tâches répétitives.
            <br />
            <span className="text-black/30">Plus de capacité.</span>
          </>
        }
        text="Nous identifions les processus qui peuvent être simplifiés, automatisés ou augmentés par l'intelligence artificielle."
      />

      <section className="px-6 py-24 lg:px-8 lg:py-36">
        <div className="mx-auto max-w-5xl">
          <SectionLabel>Intelligence artificielle</SectionLabel>

          <h2 className="display mt-6 max-w-4xl text-4xl font-extrabold leading-[1.02] sm:text-6xl">
            L’IA doit résoudre un problème.
            <span className="block text-black/30">
              Pas simplement ajouter une technologie.
            </span>
          </h2>

          <p className="mt-8 max-w-3xl text-lg leading-8 text-black/55">
            L’intelligence artificielle peut aider une entreprise à gagner du
            temps, traiter plus rapidement certaines informations et
            automatiser des tâches répétitives. Mais une bonne solution
            commence toujours par l’usage et les objectifs de l’entreprise.
          </p>

          <div className="mt-8 flex flex-wrap gap-3">
            <Link
              to="/audit"
              className="rounded-full bg-[#080808] px-5 py-3 text-sm font-bold !text-white transition hover:bg-[#c8a45d] hover:!text-[#080808]"
            >
              Faire un audit gratuit
            </Link>

            <Link
              to="/rendez-vous"
              className="rounded-full border border-black/10 px-5 py-3 text-sm font-bold transition hover:bg-black/5"
            >
              Parler de mon projet
            </Link>
          </div>
        </div>
      </section>

      <section className="bg-[#080808] px-6 py-24 text-white lg:px-8 lg:py-36">
        <div className="mx-auto max-w-7xl">
          <SectionLabel>Applications</SectionLabel>

          <h2 className="display mt-6 max-w-4xl text-4xl font-extrabold leading-[1.02] sm:text-6xl">
            Des usages concrets.
            <span className="block text-white/30">
              Une technologie au service du quotidien.
            </span>
          </h2>

          <div className="mt-12 grid gap-5 md:grid-cols-3">
            {applications.map(({ icon: Icon, title, text }) => (
              <article
                key={title}
                className="rounded-3xl border border-white/10 p-8"
              >
                <Icon className="text-[#c8a45d]" size={30} />

                <h3 className="display mt-12 text-2xl font-extrabold">
                  {title}
                </h3>

                <p className="mt-3 leading-7 text-white/50">
                  {text}
                </p>
              </article>
            ))}
          </div>
        </div>
      </section>

      <section className="px-6 py-24 lg:px-8 lg:py-36">
        <div className="mx-auto grid max-w-7xl gap-12 lg:grid-cols-2 lg:items-start">
          <div>
            <SectionLabel>Notre approche</SectionLabel>

            <h2 className="display mt-6 text-4xl font-extrabold leading-[1.02] sm:text-6xl">
              Commencer par le processus.
              <span className="block text-black/30">
                Choisir ensuite la technologie.
              </span>
            </h2>
          </div>

          <div className="space-y-6 text-lg leading-8 text-black/55">
            <p>
              Avant de parler d’outils d’intelligence artificielle, nous
              cherchons les tâches qui peuvent réellement être améliorées.
            </p>

            <p>
              Cela peut concerner la qualification de demandes, la génération
              de contenus, la synthèse d’informations, le traitement de
              données ou encore la circulation d’informations entre plusieurs
              outils.
            </p>

            <p>
              L’objectif n’est pas de remplacer votre fonctionnement par une
              solution complexe, mais de construire un système plus simple,
              plus rapide et plus efficace.
            </p>

            <div className="flex flex-wrap gap-3 pt-2">
              <Link
                to="/services"
                className="rounded-full border border-black/10 px-5 py-3 text-sm font-bold transition hover:bg-black/5"
              >
                Voir tous nos services
              </Link>

              <Link
                to="/services/web"
                className="rounded-full border border-black/10 px-5 py-3 text-sm font-bold transition hover:bg-black/5"
              >
                Découvrir notre expertise web
              </Link>
            </div>
          </div>
        </div>
      </section>

      <div className="mt-4">
        <CTA title="Identifions ce que l'IA peut réellement vous faire gagner." />
      </div>
    </>
  );
}
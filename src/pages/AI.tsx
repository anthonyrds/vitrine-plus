import { Bot, Workflow, ShieldCheck } from "lucide-react";
import PageHero from "../components/PageHero";
import CTA from "../components/CTA";
import SEO from "../components/SEO";
import SectionLabel from "../components/SectionLabel";

export default function AI() {
  return (
    <>
      <SEO
        title="IA & automatisation — Gagnez du temps | Vitrine+"
        description="Vitrine+ accompagne les entreprises dans l'intégration de l'intelligence artificielle et l'automatisation des processus pour gagner du temps."
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

      <section className="bg-[#080808] px-6 py-24 text-white lg:px-8 lg:py-36">
        <div className="mx-auto max-w-7xl">
          <SectionLabel>Applications</SectionLabel>

          <div className="mt-12 grid gap-5 md:grid-cols-3">
            {[
              [
                Bot,
                "Assistants IA",
                "Répondre, qualifier, synthétiser et aider vos équipes.",
              ],
              [
                Workflow,
                "Automatisations",
                "Faire circuler automatiquement les informations entre vos outils.",
              ],
              [
                ShieldCheck,
                "Cadre & contrôle",
                "Construire des automatisations utiles, traçables et maîtrisées.",
              ],
            ].map(([Icon, t, d]) => (
              <div
                key={t as string}
                className="rounded-3xl border border-white/10 p-8"
              >
                <Icon className="text-[#c8a45d]" size={30} />

                <h3 className="display mt-12 text-2xl font-extrabold">
                  {t as string}
                </h3>

                <p className="mt-3 leading-7 text-white/50">
                  {d as string}
                </p>
              </div>
            ))}
          </div>
        </div>
      </section>

      <div className="mt-24">
        <CTA title="Identifions ce que l'IA peut réellement vous faire gagner." />
      </div>
    </>
  );
}
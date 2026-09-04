// src/pages/Realisations.tsx

import React, { useState } from "react";
import PageHero from "../components/PageHero";
import SEO from "../components/SEO";


type Project = {
  id: number;
  category: string;
  title: string;
  subtitle: string;
  description: string;
  problem: string;
  solution: string;
  result: string;
  services: string[];
  visual: string;
};

const projects: Project[] = [
  {
    id: 1,
    category: "SITE VITRINE",
    title: "Maison Alba",
    subtitle: "Une présence digitale élégante pour une maison indépendante.",
    description:
      "Maison Alba est un projet conceptuel imaginé pour une marque artisanale souhaitant présenter son univers, ses prestations et son savoir-faire avec une image plus premium.",
    problem:
      "Une communication principalement basée sur les réseaux sociaux et une identité digitale peu structurée.",
    solution:
      "Vitrine+ imagine une identité digitale cohérente, une interface épurée et un parcours pensé pour transformer les visiteurs en prises de contact.",
    result:
      "Une présence en ligne plus professionnelle, plus claire et plus cohérente avec le positionnement haut de gamme de la marque.",
    services: ["Site vitrine", "Webdesign", "Responsive", "SEO"],
    visual: "MAISON ALBA",
  },
  {
    id: 2,
    category: "RESTAURATION",
    title: "Le Comptoir des Saveurs",
    subtitle: "Un site pensé autour de l'expérience client.",
    description:
      "Projet conceptuel réalisé autour d'un restaurant indépendant souhaitant moderniser son image et rendre les informations essentielles accessibles rapidement.",
    problem:
      "Une présence digitale vieillissante et une difficulté pour les visiteurs à trouver rapidement la carte, les horaires et les informations pratiques.",
    solution:
      "Une interface moderne centrée sur les contenus essentiels : carte, concept, horaires, réservation et contact.",
    result:
      "Une expérience mobile plus fluide et une image de marque plus actuelle.",
    services: ["Site vitrine", "UX/UI", "Mobile", "Direction artistique"],
    visual: "LE COMPTOIR",
  },
  {
    id: 3,
    category: "IDENTITÉ VISUELLE",
    title: "Atelier Nova",
    subtitle: "Construire une identité reconnaissable dès le premier regard.",
    description:
      "Atelier Nova est un projet conceptuel développé pour illustrer l'accompagnement de Vitrine+ dans la création d'une identité visuelle complète.",
    problem:
      "Une marque avec une offre intéressante mais sans véritable univers graphique permettant de la différencier.",
    solution:
      "Création d'un territoire visuel cohérent : logo, typographies, couleurs, éléments graphiques et déclinaisons digitales.",
    result:
      "Une identité plus forte et immédiatement identifiable sur les différents supports de communication.",
    services: ["Logo", "Identité visuelle", "Charte graphique", "Direction artistique"],
    visual: "ATELIER NOVA",
  },
  {
    id: 4,
    category: "SITE PREMIUM",
    title: "Maison Élégance",
    subtitle: "Donner à une activité premium une présence à sa hauteur.",
    description:
      "Projet conceptuel imaginé pour une entreprise positionnée sur un marché premium et souhaitant faire évoluer son image digitale.",
    problem:
      "Un site trop classique qui ne reflétait ni la qualité des prestations ni le positionnement de l'entreprise.",
    solution:
      "Vitrine+ imagine une expérience immersive, minimaliste et élégante, avec une hiérarchie de contenu conçue pour valoriser chaque prestation.",
    result:
      "Une identité digitale plus premium et une présentation des services beaucoup plus impactante.",
    services: ["Webdesign", "UX/UI", "Site vitrine", "SEO"],
    visual: "MAISON ÉLÉGANCE",
  },
  {
    id: 5,
    category: "COMMERCE",
    title: "L'Atelier du Café",
    subtitle: "Mettre le savoir-faire au centre de l'expérience digitale.",
    description:
      "Projet conceptuel développé pour une enseigne spécialisée souhaitant présenter son univers, ses produits et son savoir-faire.",
    problem:
      "Une communication digitale dispersée entre plusieurs supports et une difficulté à transmettre l'identité de l'enseigne.",
    solution:
      "Création d'un univers digital chaleureux mettant en avant les produits, l'histoire de la marque et son savoir-faire.",
    result:
      "Une présence digitale plus cohérente et une meilleure mise en valeur de l'univers de l'enseigne.",
    services: ["Webdesign", "Branding", "Site vitrine", "Contenu"],
    visual: "L'ATELIER DU CAFÉ",
  },
  {
    id: 6,
    category: "ÉVÉNEMENTIEL",
    title: "Maison Céleste",
    subtitle: "Une identité digitale conçue pour créer de l'émotion.",
    description:
      "Maison Céleste est un projet conceptuel imaginé autour d'une activité événementielle haut de gamme.",
    problem:
      "Une communication principalement basée sur des publications sociales, sans véritable espace centralisant l'univers de la marque.",
    solution:
      "Création d'un site immersif présentant les prestations, les réalisations et l'univers de la marque dans une expérience élégante.",
    result:
      "Une image plus professionnelle et un parcours permettant aux futurs clients de découvrir facilement l'offre.",
    services: ["Site vitrine", "Branding", "Webdesign", "Mobile"],
    visual: "MAISON CÉLESTE",
  },
];

export default function Realisations() {
  const [selectedProject, setSelectedProject] = useState<Project | null>(null);

  return (
    <main className="min-h-screen bg-white text-[#171717]">
      <SEO
  title="Réalisations — Projets web & identité digitale | Vitrine+"
  description="Découvrez les projets conceptuels de Vitrine+ : création de sites internet, webdesign, identité visuelle, SEO et expériences digitales."
  canonical="/realisations"
       />
      {/* HERO */}
      <PageHero
  eyebrow="Nos réalisations"
  title={
    <>
      Des projets pensés pour
      <br />
      <span className="text-black/30">faire la différence.</span>
    </>
  }
  text="Découvrez notre approche à travers une sélection de projets conceptuels imaginés par Vitrine+."
/>

      {/* PROJETS */}
      <section className="px-6 pb-28 md:px-12 lg:px-20">
        <div className="mx-auto max-w-7xl">
          <div className="grid gap-8 md:grid-cols-2">
            {projects.map((project, index) => (
              <article
                key={project.id}
                onClick={() => setSelectedProject(project)}
                className="group cursor-pointer overflow-hidden rounded-[2rem] border border-black/[0.07] bg-white shadow-[0_10px_40px_rgba(0,0,0,0.04)] transition-all duration-500 hover:-translate-y-1 hover:shadow-[0_25px_70px_rgba(0,0,0,0.09)]"
              >
                {/* VISUEL */}
                <div className="relative aspect-[16/10] overflow-hidden bg-[#eeeae3]">
                  <div className="absolute inset-0 bg-gradient-to-br from-[#f8f5ef] via-[#e8e1d5] to-[#d8c8aa] transition-transform duration-700 group-hover:scale-105 md:will-change-transform" />

                  <div className="absolute inset-0 flex items-center justify-center">
                    <div className="text-center">
                      <span className="mb-4 inline-flex rounded-full border border-black/10 bg-white/90 px-4 py-1.5 text-[10px] font-semibold uppercase tracking-[0.2em]">
                        Projet conceptuel
                      </span>

                      <h2 className="text-3xl font-semibold tracking-[-0.04em] md:text-4xl">
                        {project.visual}
                      </h2>
                    </div>
                  </div>

                  <div className="absolute left-6 top-6 flex h-10 w-10 items-center justify-center rounded-full bg-white/90 text-xs font-semibold">
                    0{index + 1}
                  </div>

                  <div className="absolute bottom-6 right-6 flex h-11 w-11 items-center justify-center rounded-full bg-[#171717] text-white transition-transform duration-300 group-hover:rotate-45">
                    ↗
                  </div>
                </div>

                {/* CONTENU */}
                <div className="p-7 md:p-8">
                  <p className="text-xs font-semibold uppercase tracking-[0.2em] text-[#b08a45]">
                    {project.category}
                  </p>

                  <h3 className="mt-2 text-2xl font-semibold tracking-[-0.03em]">
                    {project.title}
                  </h3>

                  <p className="mt-3 text-sm leading-6 text-neutral-600">
                    {project.subtitle}
                  </p>

                  <div className="mt-6 flex items-center justify-between">
                    <div className="flex flex-wrap gap-2">
                      {project.services.slice(0, 3).map((service) => (
                        <span
                          key={service}
                          className="rounded-full bg-neutral-100 px-3 py-1.5 text-[11px] font-medium text-neutral-600"
                        >
                          {service}
                        </span>
                      ))}
                    </div>

                    <span className="ml-4 hidden whitespace-nowrap text-xs font-semibold text-[#171717] md:block">
                      Découvrir →
                    </span>
                  </div>
                </div>
              </article>
            ))}
          </div>

          {/* CTA */}
          <div className="mt-20 overflow-hidden rounded-[2rem] bg-[#171717] px-7 py-14 text-center text-white md:px-12 md:py-20">
            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-[#d1ad6c]">
              Votre projet
            </p>

            <h2 className="mx-auto mt-4 max-w-3xl text-3xl font-semibold tracking-[-0.04em] md:text-5xl">
              Et si votre entreprise devenait notre prochaine réalisation ?
            </h2>

            <p className="mx-auto mt-5 max-w-2xl text-sm leading-6 text-white/60 md:text-base">
              Créons une présence digitale qui vous ressemble et qui donne
              envie de vous choisir.
            </p>

            <a
              href="/contact"
              onClick={(e) => e.stopPropagation()}
              className="mt-8 inline-flex rounded-full bg-[#d1ad6c] px-7 py-3.5 text-sm font-semibold text-[#171717] transition-all duration-300 hover:scale-[1.03] hover:bg-[#dfbf82]"
            >
              Parlons de votre projet
            </a>
          </div>
        </div>
      </section>

      {/* MODALE PROJET */}
      {selectedProject && (
        <div
          className="fixed inset-0 z-[100] overflow-y-auto bg-black/60 px-4 py-8 backdrop-blur-sm md:px-8"
          onClick={() => setSelectedProject(null)}
        >
          <div
            className="mx-auto max-w-4xl overflow-hidden rounded-[2rem] bg-white shadow-2xl"
            onClick={(e) => e.stopPropagation()}
          >
            {/* HEADER MODALE */}
            <div className="relative overflow-hidden bg-[#eeeae3] px-7 py-14 md:px-12 md:py-20">
              <button
                onClick={() => setSelectedProject(null)}
                className="absolute right-5 top-5 flex h-11 w-11 items-center justify-center rounded-full bg-white/80 text-lg transition-transform hover:rotate-90"
                aria-label="Fermer"
              >
                ×
              </button>

              <div className="relative z-10 max-w-3xl">
                <span className="inline-flex rounded-full border border-black/10 bg-white/90 px-4 py-1.5 text-[10px] font-semibold uppercase tracking-[0.2em]">
                  Projet conceptuel
                </span>

                <p className="mt-7 text-xs font-semibold uppercase tracking-[0.2em] text-[#b08a45]">
                  {selectedProject.category}
                </p>

                <h2 className="mt-2 text-4xl font-semibold tracking-[-0.04em] md:text-6xl">
                  {selectedProject.title}
                </h2>

                <p className="mt-5 max-w-2xl text-base leading-7 text-neutral-600 md:text-lg">
                  {selectedProject.subtitle}
                </p>
              </div>
            </div>

            {/* CONTENU MODALE */}
            <div className="space-y-10 p-7 md:p-12">
              <div>
                <h3 className="text-xs font-semibold uppercase tracking-[0.2em] text-[#b08a45]">
                  Le projet
                </h3>

                <p className="mt-4 text-base leading-8 text-neutral-600">
                  {selectedProject.description}
                </p>
              </div>

              <div className="grid gap-8 md:grid-cols-3">
                <div>
                  <h3 className="text-lg font-semibold">
                    La problématique
                  </h3>

                  <p className="mt-3 text-sm leading-6 text-neutral-600">
                    {selectedProject.problem}
                  </p>
                </div>

                <div>
                  <h3 className="text-lg font-semibold">
                    Notre approche
                  </h3>

                  <p className="mt-3 text-sm leading-6 text-neutral-600">
                    {selectedProject.solution}
                  </p>
                </div>

                <div>
                  <h3 className="text-lg font-semibold">
                    Le résultat
                  </h3>

                  <p className="mt-3 text-sm leading-6 text-neutral-600">
                    {selectedProject.result}
                  </p>
                </div>
              </div>

              <div>
                <h3 className="text-xs font-semibold uppercase tracking-[0.2em] text-[#b08a45]">
                  Services
                </h3>

                <div className="mt-4 flex flex-wrap gap-2">
                  {selectedProject.services.map((service) => (
                    <span
                      key={service}
                      className="rounded-full bg-neutral-100 px-4 py-2 text-sm font-medium text-neutral-700"
                    >
                      {service}
                    </span>
                  ))}
                </div>
              </div>

              <div className="rounded-[1.5rem] bg-[#171717] p-7 text-white md:p-9">
                <p className="text-xs font-semibold uppercase tracking-[0.2em] text-[#d1ad6c]">
                  Vous avez un projet similaire ?
                </p>

                <h3 className="mt-3 text-2xl font-semibold tracking-[-0.03em]">
                  Créons quelque chose d'unique.
                </h3>

                <a
                  href="/contact"
                  className="mt-6 inline-flex rounded-full bg-[#d1ad6c] px-6 py-3 text-sm font-semibold text-[#171717] transition-all hover:scale-[1.03]"
                >
                  Parler de votre projet
                </a>
              </div>
            </div>
          </div>
        </div>
      )}
    </main>
  );
}
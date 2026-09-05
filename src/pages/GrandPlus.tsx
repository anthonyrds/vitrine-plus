import { FormEvent, useEffect, useState } from "react";
import { ArrowRight, Check, Gift, Sparkles } from "lucide-react";
import { Link } from "react-router-dom";

import SEO from "../components/SEO";

const sectors = [
  "Artisan",
  "Commerce",
  "Restaurant / café",
  "Immobilier",
  "Profession libérale",
  "BTP",
  "Beauté / bien-être",
  "Service",
  "Association",
  "Autre",
];

const problems = [
  "Mon site est vieillissant",
  "Mon site ne reflète plus mon entreprise",
  "Je manque de visibilité",
  "Mon site fonctionne mal sur mobile",
  "Je n'ai pas encore de site",
  "Mon site ne génère pas assez de contacts",
  "Je veux simplement faire mieux",
];

const faqs: [string, string][] = [
  [
    "Combien coûte la participation ?",
    "Rien. La participation au Grand + est entièrement gratuite et ne nécessite aucun achat.",
  ],
  [
    "Que gagne l'entreprise sélectionnée ?",
    "L'entreprise sélectionnée bénéficie d'une refonte complète de son site internet, conçue par Vitrine+ et adaptée à son activité.",
  ],
  [
    "Faut-il déjà avoir un site internet ?",
    "Non. Vous pouvez participer avec un site existant à refondre ou, selon le projet sélectionné, avec un besoin de création.",
  ],
  [
    "Quand a lieu le tirage ?",
    "Une entreprise est sélectionnée chaque mois parmi les participations éligibles reçues pendant la période correspondante.",
  ],
  [
    "Est-ce vraiment sans obligation d'achat ?",
    "Oui. La participation au Grand + n'est pas conditionnée à l'achat d'un service Vitrine+.",
  ],
  [
    "Que se passe-t-il après ma participation ?",
    "Votre candidature est enregistrée pour le tirage du mois. Si vous êtes sélectionné, Vitrine+ vous contacte directement afin de définir les modalités du projet.",
  ],
];

type Winner = {
  hasWinner: boolean;
  month: string;
  company: string;
  description: string;
  website: string;
  image: string;
};

export default function GrandPlus() {
  const [winner, setWinner] = useState<Winner | null>(null);

  const [submitted, setSubmitted] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  const [form, setForm] = useState({
    name: "",
    company: "",
    email: "",
    phone: "",
    sector: "",
    website: "",
    problem: "",
    consent: false,
    marketing: false,
    websiteCheck: "",
  });

  useEffect(() => {
    fetch("/grand-plus-winner.json")
      .then((response) => {
        if (!response.ok) {
          throw new Error("Impossible de récupérer le gagnant.");
        }

        return response.json();
      })
      .then((data: Winner) => {
        setWinner(data);
      })
      .catch(() => {
        setWinner(null);
      });
  }, []);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    setError("");
    setLoading(true);

    try {
      const response = await fetch("/grand-plus.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(form),
      });

      const data = await response.json();

      if (!response.ok || !data.success) {
        throw new Error(
          data.message ||
            "Une erreur est survenue. Veuillez réessayer dans quelques instants.",
        );
      }

      setSubmitted(true);

      window.scrollTo({
        top: 0,
        behavior: "smooth",
      });
    } catch (submitError) {
      setError(
        submitError instanceof Error
          ? submitError.message
          : "Une erreur est survenue. Veuillez réessayer.",
      );
    } finally {
      setLoading(false);
    }
  }

  if (submitted) {
    return (
      <>
        <SEO
          title="Participation enregistrée — Le Grand + | Vitrine+"
          description="Votre participation au Grand + de Vitrine+ a bien été enregistrée."
          canonical="/le-grand-plus"
          noindex
        />

        <main className="min-h-screen bg-[#080808] px-6 pb-24 pt-36 text-white lg:px-8 lg:pt-44">
          <div className="mx-auto max-w-4xl">
            <div className="max-w-2xl">
              <div className="flex h-14 w-14 items-center justify-center rounded-full bg-[#c8a45d] text-[#080808]">
                <Check size={26} strokeWidth={2.5} />
              </div>

              <p className="mt-8 text-xs font-bold uppercase tracking-[0.28em] text-[#c8a45d]">
                Le Grand +
              </p>

              <h1 className="display mt-5 text-5xl font-extrabold leading-[0.95] tracking-[-0.05em] sm:text-7xl">
                C'est enregistré.
              </h1>

              <p className="mt-8 max-w-xl text-lg leading-8 text-white/55">
                Votre participation au Grand + a bien été prise en compte.
                Peut-être que le prochain site à changer sera le vôtre.
              </p>
            </div>

            <div className="mt-16 grid gap-4 sm:grid-cols-2">
              <Link
                to="/audit"
                className="group flex items-center justify-between rounded-[1.5rem] bg-white px-6 py-5 font-bold !text-[#080808] transition hover:bg-[#c8a45d] hover:!text-[#080808]"
              >
                <span>Analyser mon site gratuitement</span>

                <ArrowRight
                  size={19}
                  className="text-[#080808] transition-transform group-hover:translate-x-1"
                />
              </Link>

              <Link
                to="/rendez-vous"
                className="group flex items-center justify-between rounded-[1.5rem] border border-white/15 px-6 py-5 font-bold !text-white transition hover:border-white/30 hover:bg-white/5 hover:!text-white"
              >
                <span>Parler de mon projet</span>

                <ArrowRight
                  size={19}
                  className="text-white transition-transform group-hover:translate-x-1"
                />
              </Link>
            </div>

            <div className="mt-20 border-t border-white/10 pt-8 text-sm leading-7 text-white/35">
              Une participation au Grand + ne constitue pas une commande de
              prestation et n'entraîne aucune obligation d'achat.
            </div>
          </div>
        </main>
      </>
    );
  }

  return (
    <>
      <SEO
        title="Le Grand + — Gagnez la refonte de votre site | Vitrine+"
        description="Chaque mois, Vitrine+ offre la refonte complète du site internet d'une entreprise. Participez gratuitement au Grand +."
        canonical="/le-grand-plus"
        service={{
          name: "Le Grand +",
          description:
            "Opération mensuelle permettant à une entreprise de bénéficier gratuitement d'une refonte complète de son site internet.",
        }}
        faqs={faqs}
      />

      <main className="overflow-hidden bg-[#080808] text-white">
        {/* HERO */}
        <section className="relative min-h-[760px] px-6 pb-24 pt-36 lg:min-h-[820px] lg:px-8 lg:pb-32 lg:pt-48">
          <div
  className="pointer-events-none absolute inset-0"
  aria-hidden="true"
>
  <div className="absolute inset-0 bg-[radial-gradient(circle_at_82%_18%,rgba(200,164,93,0.10),transparent_32%),radial-gradient(circle_at_8%_92%,rgba(255,255,255,0.035),transparent_28%)]" />
</div>

          <div className="relative mx-auto max-w-7xl">
            <div className="grid items-end gap-16 lg:grid-cols-[1.15fr_.85fr]">
              <div>
                <div className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/[0.04] px-4 py-2 text-[10px] font-bold uppercase tracking-[0.24em] !text-white/60">
                  <Sparkles
                    size={13}
                    className="text-[#c8a45d]"
                  />

                  Une opération Vitrine+
                </div>

                <p className="mt-8 text-sm font-bold uppercase tracking-[0.28em] text-[#c8a45d]">
                  Le Grand +
                </p>

                <h1 className="display mt-5 max-w-5xl text-6xl font-extrabold leading-[0.88] tracking-[-0.06em] sm:text-8xl lg:text-[8.5rem]">
                  Et si votre site
                  <br />
                  était le prochain ?
                </h1>

                <p className="mt-10 max-w-2xl text-lg leading-8 text-white/55 sm:text-xl">
                  Chaque mois, Vitrine+ sélectionne une entreprise et lui
                  offre la refonte complète de son site internet.
                </p>

                <div className="mt-10 flex flex-col gap-3 sm:flex-row">
                  <a
                    href="#participer"
                    className="group inline-flex items-center justify-center gap-3 rounded-full bg-white px-7 py-4 text-sm font-bold !text-[#080808] transition hover:bg-[#c8a45d] hover:!text-[#080808]"
                  >
                    <span>Je participe au Grand +</span>

                    <ArrowRight
                      size={18}
                      className="text-[#080808] transition-transform group-hover:translate-x-1"
                    />
                  </a>

                  <a
                    href="#fonctionnement"
                    className="inline-flex items-center justify-center rounded-full border border-white/15 px-7 py-4 text-sm font-bold !text-white/75 transition hover:border-white/30 hover:!text-white"
                  >
                    Comment ça marche ?
                  </a>
                </div>

                <p className="mt-5 text-xs text-white/30">
                  Participation gratuite · Sans obligation d'achat
                </p>
              </div>

              <div className="relative lg:pb-4">
                <div className="rounded-[2rem] border border-white/10 bg-[#111111] p-7 sm:p-9">
                  <div className="flex items-start justify-between gap-6">
                    <div>
                      <p className="text-xs font-bold uppercase tracking-[0.2em] text-white/35">
                        Le gain
                      </p>

                      <h2 className="display mt-4 text-3xl font-extrabold leading-tight tracking-[-0.04em]">
                        Votre site.
                        <br />
                        Entièrement repensé.
                      </h2>
                    </div>

                    <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-[#c8a45d] text-[#080808]">
                      <Gift size={22} />
                    </div>
                  </div>

                  <div className="mt-8 space-y-4 border-t border-white/10 pt-7">
                    {[
                      "Direction artistique",
                      "UX / UI",
                      "Développement responsive",
                      "Structure et contenu",
                      "Fondations SEO",
                      "Optimisation de conversion",
                    ].map((item) => (
                      <div
                        key={item}
                        className="flex items-center gap-3 text-sm text-white/65"
                      >
                        <span className="flex h-5 w-5 items-center justify-center rounded-full bg-white/10">
                          <Check size={12} />
                        </span>

                        {item}
                      </div>
                    ))}
                  </div>

                  <div className="mt-8 rounded-2xl bg-[#c8a45d] px-5 py-4 text-sm font-bold !text-[#080808]">
                    100 % offert par Vitrine+
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        {/* POSITIONNEMENT */}
        <section className="border-y border-white/10 bg-[#f4f4f1] px-6 py-24 text-[#080808] lg:px-8 lg:py-32">
          <div className="mx-auto max-w-7xl">
            <div className="grid gap-14 lg:grid-cols-[.8fr_1.2fr] lg:items-end">
              <div>
                <p className="text-xs font-bold uppercase tracking-[0.24em] text-black/35">
                  Pourquoi Le Grand +
                </p>

                <h2 className="display mt-5 text-4xl font-extrabold leading-[0.95] tracking-[-0.05em] sm:text-6xl">
                  Parce qu'une entreprise mérite parfois de repartir sur de
                  bonnes bases.
                </h2>
              </div>

              <div className="max-w-2xl text-base leading-8 text-black/55 sm:text-lg">
                <p>
                  Votre entreprise évolue. Votre image, vos services, vos
                  clients et vos ambitions aussi. Pourtant, votre site peut
                  rester figé pendant des années.
                </p>

                <p className="mt-5">
                  Le Grand + donne chaque mois à une entreprise la possibilité
                  de repartir avec une présence digitale plus claire, plus
                  actuelle et plus efficace.
                </p>
              </div>
            </div>
          </div>
        </section>

        {/* GAGNANT DU MOIS */}
        <section className="border-y border-white/10 bg-[#111111] px-6 py-20 lg:px-8 lg:py-28">
          <div className="mx-auto max-w-7xl">
            <div className="grid gap-10 lg:grid-cols-[.65fr_1.35fr] lg:items-center">
              <div>
                <div className="inline-flex items-center gap-2 rounded-full border border-[#c8a45d]/20 bg-[#c8a45d]/10 px-4 py-2 text-[10px] font-extrabold uppercase tracking-[0.22em] !text-[#c8a45d]">
                  <Gift size={13} />
                  Le gagnant du mois
                </div>

                <p className="mt-5 text-sm font-semibold text-white/35">
                  {winner?.month || "Ce mois-ci"}
                </p>
              </div>

              <div>
                {winner?.hasWinner && winner.company ? (
                  <div className="grid gap-8 lg:grid-cols-[180px_1fr] lg:items-center">
                    {winner.image ? (
                      <div className="aspect-square overflow-hidden rounded-[1.5rem] border border-white/10 bg-white/5">
                        <img
                          src={winner.image}
                          alt={winner.company}
                          className="h-full w-full object-cover"
                          loading="lazy"
                        />
                      </div>
                    ) : (
                      <div className="flex aspect-square items-center justify-center rounded-[1.5rem] border border-white/10 bg-white/[0.04]">
                        <Gift
                          size={34}
                          strokeWidth={1.5}
                          className="text-[#c8a45d]"
                        />
                      </div>
                    )}

                    <div>
                      <p className="text-xs font-bold uppercase tracking-[0.2em] text-[#c8a45d]">
                        Félicitations
                      </p>

                      <h2 className="display mt-3 text-4xl font-extrabold leading-[0.95] tracking-[-0.05em] sm:text-6xl">
                        {winner.company}
                      </h2>

                      {winner.description && (
                        <p className="mt-5 max-w-2xl text-base leading-7 text-white/50">
                          {winner.description}
                        </p>
                      )}

                      {winner.website && (
                        <a
                          href={winner.website}
                          target="_blank"
                          rel="noreferrer"
                          className="mt-7 inline-flex items-center gap-2 text-sm font-bold !text-white transition hover:!text-[#c8a45d]"
                        >
                          Découvrir l'entreprise
                          <ArrowRight size={16} />
                        </a>
                      )}
                    </div>
                  </div>
                ) : (
                  <div>
                    <p className="text-xs font-bold uppercase tracking-[0.2em] text-[#c8a45d]">
                      Le prochain gagnant sera peut-être vous
                    </p>

                    <h2 className="display mt-4 max-w-4xl text-4xl font-extrabold leading-[0.95] tracking-[-0.05em] sm:text-6xl">
                      Qui sera la prochaine entreprise à changer de site ?
                    </h2>

                    <p className="mt-6 max-w-2xl text-base leading-7 text-white/45">
                      Le tirage du mois n'a pas encore été annoncé. Participez
                      gratuitement au Grand + pour tenter de bénéficier de
                      votre propre refonte offerte par Vitrine+.
                    </p>

                    <a
                      href="#participer"
                      className="group mt-8 inline-flex items-center gap-3 rounded-full bg-white px-6 py-3.5 text-sm font-bold !text-[#080808] transition hover:bg-[#c8a45d] hover:!text-[#080808]"
                    >
                      Participer

                      <ArrowRight
                        size={17}
                        className="text-[#080808] transition-transform group-hover:translate-x-1"
                      />
                    </a>
                  </div>
                )}
              </div>
            </div>
          </div>
        </section>

        {/* FONCTIONNEMENT */}
        <section
          id="fonctionnement"
          className="px-6 py-24 lg:px-8 lg:py-36"
        >
          <div className="mx-auto max-w-7xl">
            <div className="max-w-2xl">
              <p className="text-xs font-bold uppercase tracking-[0.24em] text-[#c8a45d]">
                Le principe
              </p>

              <h2 className="display mt-5 text-5xl font-extrabold leading-[0.92] tracking-[-0.05em] sm:text-7xl">
                Trois étapes.
                <br />
                Rien de plus.
              </h2>
            </div>

            <div className="mt-16 grid gap-px overflow-hidden rounded-[2rem] border border-white/10 bg-white/10 md:grid-cols-3">
              {[
                {
                  number: "01",
                  title: "Présentez votre entreprise",
                  text: "Quelques informations suffisent pour comprendre votre activité et votre situation digitale.",
                },
                {
                  number: "02",
                  title: "Expliquez ce qui bloque",
                  text: "Site vieillissant, manque de visibilité, mauvaise expérience mobile ou envie de repartir autrement.",
                },
                {
                  number: "03",
                  title: "Laissez-nous faire",
                  text: "Une entreprise est sélectionnée chaque mois pour bénéficier de la refonte offerte par Vitrine+.",
                },
              ].map((step) => (
                <article
                  key={step.number}
                  className="bg-[#080808] p-8 sm:p-10 lg:p-12"
                >
                  <span className="text-xs font-bold tracking-[0.2em] text-[#c8a45d]">
                    {step.number}
                  </span>

                  <h3 className="display mt-16 text-2xl font-extrabold tracking-[-0.03em]">
                    {step.title}
                  </h3>

                  <p className="mt-5 text-sm leading-7 text-white/45">
                    {step.text}
                  </p>
                </article>
              ))}
            </div>
          </div>
        </section>

        {/* CE QUI EST OFFERT */}
        <section className="bg-white px-6 py-24 text-[#080808] lg:px-8 lg:py-36">
          <div className="mx-auto max-w-7xl">
            <div className="grid gap-16 lg:grid-cols-[.8fr_1.2fr]">
              <div>
                <p className="text-xs font-bold uppercase tracking-[0.24em] text-black/35">
                  Ce que vous gagnez
                </p>

                <h2 className="display mt-5 text-5xl font-extrabold leading-[0.92] tracking-[-0.05em] sm:text-7xl">
                  Pas juste
                  <br />
                  un nouveau look.
                </h2>
              </div>

              <div className="grid gap-x-10 gap-y-10 sm:grid-cols-2">
                {[
                  {
                    title: "Une vraie direction",
                    text: "Une identité digitale cohérente avec votre entreprise, votre positionnement et vos clients.",
                  },
                  {
                    title: "Une expérience moderne",
                    text: "Une navigation claire, une hiérarchie efficace et une expérience pensée pour tous les écrans.",
                  },
                  {
                    title: "Une base technique solide",
                    text: "Structure, performance, responsive et fondations techniques pensées dès la conception.",
                  },
                  {
                    title: "Un site utile",
                    text: "Le design ne sert pas uniquement à être beau. Il doit aider vos visiteurs à comprendre et à agir.",
                  },
                ].map((item) => (
                  <article
                    key={item.title}
                    className="border-t border-black/10 pt-6"
                  >
                    <h3 className="text-lg font-extrabold tracking-[-0.02em]">
                      {item.title}
                    </h3>

                    <p className="mt-3 text-sm leading-7 text-black/50">
                      {item.text}
                    </p>
                  </article>
                ))}
              </div>
            </div>
          </div>
        </section>

        {/* FORMULAIRE */}
        <section
          id="participer"
          className="bg-[#f4f4f1] px-6 py-24 text-[#080808] lg:px-8 lg:py-36"
        >
          <div className="mx-auto max-w-7xl">
            <div className="grid gap-16 lg:grid-cols-[.7fr_1.3fr]">
              <div className="lg:sticky lg:top-32 lg:self-start">
                <p className="text-xs font-bold uppercase tracking-[0.24em] text-black/35">
                  Votre candidature
                </p>

                <h2 className="display mt-5 text-5xl font-extrabold leading-[0.92] tracking-[-0.05em] sm:text-7xl">
                  À vous
                  <br />
                  de jouer.
                </h2>

                <p className="mt-7 max-w-md text-base leading-7 text-black/50">
                  Présentez-nous votre entreprise et dites-nous ce que vous
                  aimeriez améliorer. Cela ne prend que quelques minutes.
                </p>

                <div className="mt-10 space-y-4 text-sm text-black/55">
                  <div className="flex items-center gap-3">
                    <Check size={17} />
                    Participation gratuite
                  </div>

                  <div className="flex items-center gap-3">
                    <Check size={17} />
                    Aucun achat nécessaire
                  </div>

                  <div className="flex items-center gap-3">
                    <Check size={17} />
                    Une entreprise sélectionnée chaque mois
                  </div>
                </div>
              </div>

              <div className="rounded-[2rem] bg-white p-7 shadow-[0_20px_80px_rgba(0,0,0,.07)] sm:p-10 lg:p-12">
                <form onSubmit={handleSubmit} className="space-y-8">
                  <div>
                    <p className="text-xs font-bold uppercase tracking-[0.2em] text-black/30">
                      01 — Votre entreprise
                    </p>

                    <div className="mt-6 grid gap-5 sm:grid-cols-2">
                      <label className="grid gap-2">
                        <span className="text-sm font-bold">Nom *</span>

                        <input
                          required
                          value={form.name}
                          onChange={(event) =>
                            setForm({
                              ...form,
                              name: event.target.value,
                            })
                          }
                          className="h-14 rounded-2xl border border-black/10 bg-[#f7f7f5] px-4 outline-none transition focus:border-black/30"
                          placeholder="Votre nom"
                        />
                      </label>

                      <label className="grid gap-2">
                        <span className="text-sm font-bold">
                          Entreprise *
                        </span>

                        <input
                          required
                          value={form.company}
                          onChange={(event) =>
                            setForm({
                              ...form,
                              company: event.target.value,
                            })
                          }
                          className="h-14 rounded-2xl border border-black/10 bg-[#f7f7f5] px-4 outline-none transition focus:border-black/30"
                          placeholder="Nom de votre entreprise"
                        />
                      </label>

                      <label className="grid gap-2">
                        <span className="text-sm font-bold">
                          E-mail professionnel *
                        </span>

                        <input
                          required
                          type="email"
                          value={form.email}
                          onChange={(event) =>
                            setForm({
                              ...form,
                              email: event.target.value,
                            })
                          }
                          className="h-14 rounded-2xl border border-black/10 bg-[#f7f7f5] px-4 outline-none transition focus:border-black/30"
                          placeholder="vous@entreprise.fr"
                        />
                      </label>

                      <label className="grid gap-2">
                        <span className="text-sm font-bold">
                          Téléphone *
                        </span>

                        <input
                          required
                          type="tel"
                          value={form.phone}
                          onChange={(event) =>
                            setForm({
                              ...form,
                              phone: event.target.value,
                            })
                          }
                          className="h-14 rounded-2xl border border-black/10 bg-[#f7f7f5] px-4 outline-none transition focus:border-black/30"
                          placeholder="06 00 00 00 00"
                        />
                      </label>

                      <label className="grid gap-2 sm:col-span-2">
                        <span className="text-sm font-bold">
                          Site internet actuel
                        </span>

                        <input
                          type="url"
                          value={form.website}
                          onChange={(event) =>
                            setForm({
                              ...form,
                              website: event.target.value,
                            })
                          }
                          className="h-14 rounded-2xl border border-black/10 bg-[#f7f7f5] px-4 outline-none transition focus:border-black/30"
                          placeholder="https://votre-site.fr"
                        />
                      </label>

                      <label className="grid gap-2">
                        <span className="text-sm font-bold">
                          Votre activité *
                        </span>

                        <select
                          required
                          value={form.sector}
                          onChange={(event) =>
                            setForm({
                              ...form,
                              sector: event.target.value,
                            })
                          }
                          className="h-14 rounded-2xl border border-black/10 bg-[#f7f7f5] px-4 outline-none transition focus:border-black/30"
                        >
                          <option value="">Sélectionner</option>

                          {sectors.map((sector) => (
                            <option key={sector} value={sector}>
                              {sector}
                            </option>
                          ))}
                        </select>
                      </label>

                      <label className="grid gap-2">
                        <span className="text-sm font-bold">
                          Ce qui vous bloque le plus *
                        </span>

                        <select
                          required
                          value={form.problem}
                          onChange={(event) =>
                            setForm({
                              ...form,
                              problem: event.target.value,
                            })
                          }
                          className="h-14 rounded-2xl border border-black/10 bg-[#f7f7f5] px-4 outline-none transition focus:border-black/30"
                        >
                          <option value="">Sélectionner</option>

                          {problems.map((problem) => (
                            <option key={problem} value={problem}>
                              {problem}
                            </option>
                          ))}
                        </select>
                      </label>
                    </div>
                  </div>

                  <div className="border-t border-black/10 pt-8">
                    <p className="text-xs font-bold uppercase tracking-[0.2em] text-black/30">
                      02 — Validation
                    </p>

                    <div className="mt-6 space-y-5">
                      <label className="flex items-start gap-3 text-sm leading-6 text-black/55">
  <input
    required
    type="checkbox"
    checked={form.consent}
    onChange={(event) =>
      setForm({
        ...form,
        consent: event.target.checked,
      })
    }
    className="mt-1 h-4 w-4 shrink-0 accent-[#080808]"
  />

  <span>
    J'ai lu et j'accepte{" "}
    <Link
      to="/reglement-grand-plus"
      target="_blank"
      rel="noreferrer"
      className="font-bold !text-[#080808] underline underline-offset-2 transition hover:!text-[#9a773d]"
    >
      le règlement du Grand +
    </Link>
    . *
  </span>
</label>

                      <label className="flex items-start gap-3 text-sm leading-6 text-black/55">
                        <input
                          type="checkbox"
                          checked={form.marketing}
                          onChange={(event) =>
                            setForm({
                              ...form,
                              marketing: event.target.checked,
                            })
                          }
                          className="mt-1 h-4 w-4 accent-[#080808]"
                        />

                        <span>
                          J'accepte de recevoir occasionnellement les actualités
                          et offres de Vitrine+. Je peux retirer mon
                          consentement à tout moment.
                        </span>
                      </label>

                      <input
                        type="text"
                        value={form.websiteCheck}
                        onChange={(event) =>
                          setForm({
                            ...form,
                            websiteCheck: event.target.value,
                          })
                        }
                        tabIndex={-1}
                        autoComplete="off"
                        aria-hidden="true"
                        className="hidden"
                      />
                    </div>
                  </div>

                  {error && (
                    <div
                      role="alert"
                      className="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-medium text-red-700"
                    >
                      {error}
                    </div>
                  )}

                  <button
                    type="submit"
                    disabled={loading}
                    className="group flex w-full items-center justify-between rounded-2xl bg-[#080808] px-6 py-5 text-left font-bold !text-white transition hover:bg-[#c8a45d] hover:!text-[#080808] disabled:cursor-not-allowed disabled:opacity-50"
                  >
                    <span>
                      {loading
                        ? "Enregistrement..."
                        : "Participer au Grand +"}
                    </span>

                    <ArrowRight
                      size={20}
                      className="text-white transition-transform group-hover:translate-x-1 group-hover:text-[#080808]"
                    />
                  </button>

                  <p className="text-center text-xs leading-5 text-black/35">
                    Vos informations sont utilisées pour gérer votre
                    participation au Grand +. La participation est gratuite et
                    sans obligation d'achat.
                  </p>
                </form>
              </div>
            </div>
          </div>
        </section>

        {/* TRANSPARENCE */}
        <section className="px-6 py-24 lg:px-8 lg:py-32">
          <div className="mx-auto max-w-5xl">
            <div className="rounded-[2rem] border border-white/10 bg-white/[0.035] p-8 sm:p-12">
              <p className="text-xs font-bold uppercase tracking-[0.24em] text-[#c8a45d]">
                En toute transparence
              </p>

              <h2 className="display mt-5 max-w-3xl text-4xl font-extrabold leading-[0.95] tracking-[-0.04em] sm:text-6xl">
                Une opération simple,
                <br />
                avec des règles claires.
              </h2>

              <div className="mt-10 grid gap-8 text-sm leading-7 text-white/45 sm:grid-cols-2">
                <p>
                  Le Grand + permet chaque mois à une entreprise de tenter de
                  bénéficier d'une refonte complète de son site internet
                  offerte par Vitrine+.
                </p>

                <p>
                  La participation est gratuite et indépendante de toute
                  commande. Les modalités précises de sélection sont définies
                  dans le règlement de l'opération.
                </p>
              </div>

              <Link
                to="/mentions-legales"
                className="mt-10 inline-flex items-center gap-2 text-sm font-bold !text-white transition hover:!text-[#c8a45d]"
              >
                Consulter les informations légales
                <ArrowRight size={16} />
              </Link>
            </div>
          </div>
        </section>

        {/* FAQ */}
        <section className="bg-white px-6 py-24 text-[#080808] lg:px-8 lg:py-36">
          <div className="mx-auto max-w-5xl">
            <div className="max-w-2xl">
              <p className="text-xs font-bold uppercase tracking-[0.24em] text-black/35">
                Questions fréquentes
              </p>

              <h2 className="display mt-5 text-5xl font-extrabold leading-[0.92] tracking-[-0.05em] sm:text-7xl">
                Vous vous demandez
                <br />
                comment ça marche ?
              </h2>
            </div>

            <div className="mt-14 divide-y divide-black/10 border-y border-black/10">
              {faqs.map(([question, answer]) => (
                <details key={question} className="group py-6">
                  <summary className="flex cursor-pointer list-none items-center justify-between gap-8 text-base font-bold sm:text-lg">
                    <span>{question}</span>

                    <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-black/5 text-black/50 transition group-open:rotate-45">
                      +
                    </span>
                  </summary>

                  <p className="max-w-3xl pt-4 pr-12 text-sm leading-7 text-black/50">
                    {answer}
                  </p>
                </details>
              ))}
            </div>
          </div>
        </section>

        {/* CTA FINAL */}
        <section className="px-6 py-24 lg:px-8 lg:py-36">
          <div className="mx-auto max-w-7xl">
            <div className="overflow-hidden rounded-[2.5rem] bg-[#c8a45d] px-7 py-14 text-[#080808] sm:px-12 sm:py-20 lg:px-20">
              <div className="grid gap-12 lg:grid-cols-[1fr_auto] lg:items-end">
                <div>
                  <p className="text-xs font-bold uppercase tracking-[0.24em] text-black/45">
                    Et si vous alliez plus loin ?
                  </p>

                  <h2 className="display mt-5 max-w-4xl text-5xl font-extrabold leading-[0.9] tracking-[-0.05em] sm:text-7xl">
                    Votre site peut déjà être analysé gratuitement.
                  </h2>

                  <p className="mt-7 max-w-xl text-base leading-7 text-black/55">
                    Même sans attendre le prochain tirage, découvrez ce qui
                    fonctionne et ce qui peut être amélioré sur votre site.
                  </p>
                </div>

                <Link
                  to="/audit"
                  className="group inline-flex items-center justify-between gap-8 rounded-full bg-[#080808] px-7 py-4 text-sm font-bold !text-white transition hover:bg-white hover:!text-[#080808]"
                >
                  <span>Faire mon audit gratuit</span>

                  <ArrowRight
                    size={18}
                    className="text-white transition-transform group-hover:translate-x-1 group-hover:text-[#080808]"
                  />
                </Link>
              </div>
            </div>
          </div>
        </section>
      </main>
    </>
  );
}
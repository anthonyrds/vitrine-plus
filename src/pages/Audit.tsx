import { FormEvent, useState } from "react";

import {
  AlertCircle,
  ArrowRight,
  Check,
  Clock3,
  Globe2,
  Loader2,
  Search,
  ShieldCheck,
  Sparkles,
} from "lucide-react";

import { Link } from "react-router-dom";

import SEO from "../components/SEO";
import SectionLabel from "../components/SectionLabel";

type Category = {
  score: number;
  label: string;
};

type AuditResult = {
  success: boolean;
  score: number;
  pagesAnalyzed: number;
  pagesDiscovered: number;
  categories: Record<string, Category>;
  strengths: string[];
  recommendations: string[];
  responseTime: number;
  message?: string;
};

const categoryLabels: Record<string, string> = {
  seo: "SEO",
  structure: "Structure",
  mobile: "Mobile",
  content: "Contenu",
  performance: "Performance technique",
  social: "Partage social",
  conversion: "Conversion",
};

export default function Audit() {
  const [url, setUrl] = useState("");
  const [loading, setLoading] = useState(false);
  const [result, setResult] =
    useState<AuditResult | null>(null);
  const [error, setError] = useState("");

  async function handleSubmit(
    event: FormEvent<HTMLFormElement>
  ) {
    event.preventDefault();

    setError("");
    setResult(null);

    let normalizedUrl = url.trim();

    if (!normalizedUrl) {
      setError("Entrez l’adresse de votre site.");
      return;
    }

    if (!/^https?:\/\//i.test(normalizedUrl)) {
      normalizedUrl = `https://${normalizedUrl}`;
    }

    try {
      new URL(normalizedUrl);
    } catch {
      setError("L’adresse du site n’est pas valide.");
      return;
    }

    setLoading(true);

    try {
      const body = new URLSearchParams();

      body.set("action", "analyze");
      body.set("url", normalizedUrl);

      const response = await fetch(
        "/audit.php",
        {
          method: "POST",
          headers: {
            "Content-Type":
              "application/x-www-form-urlencoded",
          },
          body: body.toString(),
        }
      );

      const data = await response.json();

      if (!response.ok || !data.success) {
        throw new Error(
          data.message ||
            "Impossible d’analyser ce site pour le moment."
        );
      }

      setResult(data);
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Une erreur est survenue pendant l’analyse."
      );
    } finally {
      setLoading(false);
    }
  }

  return (
    <>
      <SEO
        title="Audit digital gratuit — Analyse complète de votre site | Vitrine+"
        description="Analysez gratuitement votre site internet : SEO, structure, mobile, contenu, performance, partage social et conversion."
        canonical="/audit"
      />

      <section className="relative overflow-hidden px-6 pb-14 pt-32 lg:px-8 lg:pb-20 lg:pt-40">
        <div className="absolute -right-40 top-10 h-[500px] w-[500px] rounded-full bg-[#c8a45d]/10 blur-[110px]" />

        <div className="relative mx-auto max-w-7xl">
          <SectionLabel>
            Audit digital gratuit
          </SectionLabel>

          <h1 className="display mt-7 max-w-5xl text-5xl font-extrabold leading-[.95] sm:text-7xl lg:text-[92px]">
            Découvrez ce qui freine
            <span className="block text-black/25">
              votre site internet.
            </span>
          </h1>

          <p className="mt-7 max-w-2xl text-lg leading-8 text-black/55">
            Entrez l’adresse de votre site. Vitrine+ analyse
            les pages accessibles de votre domaine et vous
            donne un score ainsi que les principales priorités
            d’amélioration.
          </p>
        </div>
      </section>

      <section className="px-6 pb-16 lg:px-8 lg:pb-24">
        <div className="mx-auto max-w-7xl">
          <div className="rounded-[2rem] bg-[#080808] p-7 text-white sm:p-10 lg:p-14">
            <div className="grid gap-10 lg:grid-cols-[1fr_.7fr] lg:items-end">
              <div>
                <div className="flex items-center gap-3">
                  <div className="flex h-10 w-10 items-center justify-center rounded-full bg-[#c8a45d] text-black">
                    <Search size={18} />
                  </div>

                  <span className="text-sm font-bold">
                    Analyse complète du site
                  </span>
                </div>

                <h2 className="display mt-6 max-w-2xl text-3xl font-extrabold sm:text-5xl">
                  Quel est le niveau réel de votre présence digitale ?
                </h2>

                <p className="mt-5 max-w-xl leading-7 text-white/50">
                  L’analyse porte sur les pages découvertes
                  sur votre domaine et pas uniquement sur votre
                  page d’accueil.
                </p>
              </div>

              <form onSubmit={handleSubmit}>
                <label className="mb-3 block text-sm font-semibold text-white/60">
                  Adresse de votre site
                </label>

                <div className="flex flex-col gap-3 sm:flex-row">
                  <input
                    value={url}
                    onChange={(event) =>
                      setUrl(event.target.value)
                    }
                    placeholder="https://votre-site.fr"
                    type="text"
                    inputMode="url"
                    autoComplete="url"
                    disabled={loading}
                    className="min-w-0 flex-1 rounded-full border border-white/10 bg-white/[0.06] px-5 py-4 text-sm text-white outline-none transition placeholder:text-white/25 focus:border-[#c8a45d]"
                  />

                  <button
                    type="submit"
                    disabled={loading}
                    className="inline-flex shrink-0 items-center justify-center gap-2 rounded-full bg-[#c8a45d] px-6 py-4 text-sm font-extrabold text-black transition hover:bg-white disabled:cursor-not-allowed disabled:opacity-60"
                  >
                    {loading ? (
                      <>
                        <Loader2
                          size={17}
                          className="animate-spin"
                        />
                        Analyse…
                      </>
                    ) : (
                      <>
                        Analyser
                        <ArrowRight size={17} />
                      </>
                    )}
                  </button>
                </div>

                {error && (
                  <div className="mt-4 flex items-start gap-2 rounded-2xl border border-red-400/20 bg-red-400/10 p-4 text-sm text-red-200">
                    <AlertCircle
                      size={17}
                      className="mt-0.5 shrink-0"
                    />

                    <span>{error}</span>
                  </div>
                )}
              </form>
            </div>
          </div>
        </div>
      </section>

      {loading && (
        <section className="px-6 pb-20 lg:px-8">
          <div className="mx-auto max-w-7xl">
            <div className="rounded-[2rem] border border-black/10 bg-[#f5f5f2] p-8 sm:p-12">
              <div className="flex items-center gap-5">
                <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-[#080808] text-[#c8a45d]">
                  <Loader2
                    size={22}
                    className="animate-spin"
                  />
                </div>

                <div>
                  <p className="font-bold">
                    Analyse en cours…
                  </p>

                  <p className="mt-1 text-sm text-black/45">
                    Nous parcourons les pages de votre site et
                    vérifions ses principaux signaux techniques.
                  </p>
                </div>
              </div>
            </div>
          </div>
        </section>
      )}

      {result && (
        <section className="px-6 pb-20 lg:px-8 lg:pb-28">
          <div className="mx-auto max-w-7xl">
            <div className="grid gap-5 lg:grid-cols-[.7fr_1.3fr]">
              <div className="rounded-[2rem] bg-[#080808] p-8 text-white sm:p-10">
                <p className="text-xs font-bold uppercase tracking-[.2em] text-white/35">
                  Score global
                </p>

                <div className="mt-6 flex items-baseline gap-3">
                  <span className="display text-8xl font-extrabold leading-none tracking-[-.07em] sm:text-9xl">
                    {result.score}
                  </span>

                  <span className="text-3xl font-semibold text-white/65">
                    /100
                  </span>
                </div>

                <div className="mt-7 h-2 overflow-hidden rounded-full bg-white/10">
                  <div
                    className="h-full rounded-full bg-[#c8a45d] transition-all duration-700"
                    style={{
                      width: `${result.score}%`,
                    }}
                  />
                </div>

                <div className="mt-8 grid grid-cols-2 gap-4 border-t border-white/10 pt-6">
                  <div>
                    <p className="text-xs text-white/35">
                      Pages analysées
                    </p>

                    <p className="mt-1 text-2xl font-bold">
                      {result.pagesAnalyzed}
                    </p>
                  </div>

                  <div>
                    <p className="text-xs text-white/35">
                      Temps d’analyse
                    </p>

                    <p className="mt-1 text-2xl font-bold">
                      {result.responseTime}s
                    </p>
                  </div>
                </div>
              </div>

              <div className="rounded-[2rem] border border-black/10 bg-[#f5f5f2] p-8 sm:p-10">
                <div className="flex items-center gap-3">
                  <Globe2
                    size={20}
                    className="text-[#c8a45d]"
                  />

                  <span className="text-sm font-bold">
                    Analyse de votre domaine
                  </span>
                </div>

                <p className="mt-4 max-w-2xl text-sm leading-6 text-black/50">
                  {result.pagesDiscovered >
                  result.pagesAnalyzed
                    ? `${result.pagesAnalyzed} pages ont été analysées sur ${result.pagesDiscovered} pages découvertes.`
                    : `${result.pagesAnalyzed} pages ont été analysées sur votre site.`}
                </p>

                <div className="mt-8 grid gap-3 sm:grid-cols-2">
                  {Object.entries(
                    result.categories
                  ).map(
                    ([key, category]) => (
                      <div
                        key={key}
                        className="rounded-2xl bg-white p-5"
                      >
                        <div className="flex items-center justify-between gap-3">
                          <span className="text-sm font-bold">
                            {categoryLabels[key] ||
                              category.label}
                          </span>

                          <span className="text-sm font-extrabold">
                            {category.score}/100
                          </span>
                        </div>

                        <div className="mt-3 h-1.5 overflow-hidden rounded-full bg-black/5">
                          <div
                            className="h-full rounded-full bg-[#c8a45d]"
                            style={{
                              width: `${category.score}%`,
                            }}
                          />
                        </div>
                      </div>
                    )
                  )}
                </div>
              </div>
            </div>

            <div className="mt-5 rounded-[2rem] border border-black/10 p-8 sm:p-10">
              <SectionLabel>
                Les 3 priorités
              </SectionLabel>

              <h2 className="display mt-5 text-3xl font-extrabold sm:text-4xl">
                Les conseils qui méritent votre attention en premier.
              </h2>

              <div className="mt-8 grid gap-5">
                {result.recommendations
                  .slice(0, 3)
                  .map((item, index) => (
                    <div
                      key={`${index}-${item}`}
                      className="flex gap-4"
                    >
                      <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#f5f5f2] text-xs font-extrabold">
                        0{index + 1}
                      </span>

                      <p className="pt-1 text-sm font-bold leading-6 text-black/75">
                        {item}
                      </p>
                    </div>
                  ))}
              </div>
            </div>

            {result.strengths.length > 0 && (
              <div className="mt-5 rounded-[2rem] bg-[#f5f5f2] p-8 sm:p-10">
                <SectionLabel>
                  Vos points forts
                </SectionLabel>

                <div className="mt-7 grid gap-3 sm:grid-cols-2">
                  {result.strengths.map(
                    (item) => (
                      <div
                        key={item}
                        className="flex gap-3 rounded-2xl bg-white p-5"
                      >
                        <Check
                          size={18}
                          className="mt-0.5 shrink-0 text-[#c8a45d]"
                        />

                        <span className="text-sm leading-6 text-black/60">
                          {item}
                        </span>
                      </div>
                    )
                  )}
                </div>
              </div>
            )}

            <div className="mt-5 rounded-[2rem] bg-[#080808] p-8 text-white sm:p-10">
              <div className="flex items-center gap-3">
                <Sparkles
                  size={20}
                  className="text-[#c8a45d]"
                />

                <SectionLabel>
                  Les + que Vitrine+ peut vous apporter
                </SectionLabel>
              </div>

              <h2 className="display mt-5 text-3xl font-extrabold sm:text-4xl">
                Nous ne nous contentons pas de vous montrer les problèmes.
              </h2>

              <p className="mt-5 max-w-xl text-sm leading-7 text-white/50">
                Nous pouvons transformer les recommandations
                de cet audit en améliorations concrètes pour
                votre entreprise.
              </p>

              <div className="mt-8 grid gap-4 sm:grid-cols-2">
                {[
                  "Un site plus clair et plus performant",
                  "Une meilleure visibilité sur Google",
                  "Une identité digitale plus forte",
                  "Un parcours pensé pour convertir",
                  "Des outils et automatisations adaptés",
                  "Un accompagnement digital dans la durée",
                ].map((item) => (
                  <div
                    key={item}
                    className="flex items-center gap-3 rounded-xl border border-white/10 bg-white/[0.03] px-4 py-3"
                  >
                    <Check
                      size={16}
                      className="shrink-0 text-[#c8a45d]"
                    />

                    <span className="text-sm text-white/70">
                      {item}
                    </span>
                  </div>
                ))}
              </div>

              <div className="mt-8 flex flex-wrap gap-3">
                <Link
                  to="/rendez-vous"
                  className="inline-flex items-center gap-3 rounded-full bg-[#c8a45d] px-7 py-4 text-sm font-extrabold text-black transition hover:bg-white"
                >
                  Prendre rendez-vous
                  <ArrowRight size={17} />
                </Link>

                <Link
                  to="/contact"
                  className="inline-flex items-center gap-3 rounded-full border border-white/15 px-7 py-4 text-sm font-extrabold text-white transition hover:bg-white hover:text-black"
                >
                  Parler de mon projet
                </Link>
              </div>
            </div>

            <div className="mt-5 rounded-[2rem] border border-black/10 p-8 sm:p-10">
              <div className="grid gap-8 md:grid-cols-3">
                <div>
                  <ShieldCheck
                    size={22}
                    className="text-[#c8a45d]"
                  />

                  <h3 className="mt-4 font-extrabold">
                    SEO & technique
                  </h3>

                  <p className="mt-2 text-sm leading-6 text-black/45">
                    Balises, structure, indexation, mobile,
                    images et signaux techniques.
                  </p>
                </div>

                <div>
                  <Clock3
                    size={22}
                    className="text-[#c8a45d]"
                  />

                  <h3 className="mt-4 font-extrabold">
                    Performance
                  </h3>

                  <p className="mt-2 text-sm leading-6 text-black/45">
                    Temps de réponse, poids HTML et éléments
                    techniques observables.
                  </p>
                </div>

                <div>
                  <Sparkles
                    size={22}
                    className="text-[#c8a45d]"
                  />

                  <h3 className="mt-4 font-extrabold">
                    Conversion
                  </h3>

                  <p className="mt-2 text-sm leading-6 text-black/45">
                    CTA, contact, parcours et signaux permettant
                    de transformer une visite en demande.
                  </p>
                </div>
              </div>
            </div>

            <p className="mt-6 text-center text-xs leading-5 text-black/35">
              Cet audit fournit une analyse indicative basée
              sur les signaux accessibles publiquement. Il ne
              remplace pas un audit SEO approfondi ni une mesure
              complète des Core Web Vitals dans un navigateur réel.
            </p>
          </div>
        </section>
      )}
    </>
  );
}
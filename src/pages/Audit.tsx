import { FormEvent, useState } from "react";
import {
  AlertCircle,
  ArrowRight,
  Check,
  Clock3,
  Globe2,
  Loader2,
  Mail,
  Phone,
  Search,
  ShieldCheck,
  Sparkles,
  User,
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
  performance: "Performance",
  social: "Partage social",
};

type TrackingParams = Record<
  string,
  string | number | boolean | undefined
>;

function trackEvent(
  name: string,
  params: TrackingParams = {}
) {
  if (typeof window === "undefined") return;

  const win = window as Window & {
    gtag?: (
      command: string,
      eventName: string,
      eventParams?: TrackingParams
    ) => void;
    dataLayer?: Array<Record<string, unknown>>;
  };

  if (typeof win.gtag === "function") {
    win.gtag("event", name, params);
  }

  if (Array.isArray(win.dataLayer)) {
    win.dataLayer.push({
      event: name,
      ...params,
    });
  }
}

export default function Audit() {
  const [url, setUrl] = useState("");
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState<AuditResult | null>(null);
  const [error, setError] = useState("");

  const [leadName, setLeadName] = useState("");
  const [leadEmail, setLeadEmail] = useState("");
  const [leadPhone, setLeadPhone] = useState("");
  const [leadConsent, setLeadConsent] = useState(false);
  const [leadLoading, setLeadLoading] = useState(false);
  const [leadSubmitted, setLeadSubmitted] = useState(false);
  const [leadError, setLeadError] = useState("");

  async function handleSubmit(
    event: FormEvent<HTMLFormElement>
  ) {
    event.preventDefault();

    setError("");
    setResult(null);
    setLeadSubmitted(false);
    setLeadError("");

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

    trackEvent("audit_start", {
      website: normalizedUrl,
    });

    setLoading(true);

    try {
      const body = new URLSearchParams();
      body.set("action", "analyze");
      body.set("url", normalizedUrl);

      const response = await fetch("/audit.php", {
        method: "POST",
        headers: {
          "Content-Type":
            "application/x-www-form-urlencoded",
        },
        body: body.toString(),
      });

      const data = await response.json();

      if (!response.ok || !data.success) {
        throw new Error(
          data.message ||
            "Impossible d’analyser ce site pour le moment."
        );
      }

      setUrl(normalizedUrl);
      setResult(data);

      trackEvent("audit_complete", {
        website: normalizedUrl,
        score: data.score,
        pages_analyzed: data.pagesAnalyzed,
        pages_discovered: data.pagesDiscovered,
      });

      fetch("/audit-notify.php", {
  method: "POST",
  headers: {
    "Content-Type": "application/x-www-form-urlencoded",
  },
  body: new URLSearchParams({
    website: normalizedUrl,
    score: String(data.score),
    pagesAnalyzed: String(data.pagesAnalyzed),
    pagesDiscovered: String(data.pagesDiscovered),
    responseTime: String(data.responseTime),
    categories: JSON.stringify(data.categories),
  }).toString(),
}).catch(() => {
  // La notification ne doit jamais bloquer l'audit.
});

      fetch("/audit-notify.php", {
        method: "POST",
        headers: {
          "Content-Type":
            "application/x-www-form-urlencoded",
        },
        body: new URLSearchParams({
          website: normalizedUrl,
          score: String(data.score),
          pagesAnalyzed: String(data.pagesAnalyzed),
          pagesDiscovered: String(data.pagesDiscovered),
          responseTime: String(data.responseTime),
          categories: JSON.stringify(data.categories),
        }).toString(),
      }).catch(() => {
        // La notification ne doit jamais bloquer l'audit.
      });

      setTimeout(() => {
        document
          .getElementById("audit-result")
          ?.scrollIntoView({
            behavior: "smooth",
            block: "start",
          });
      }, 100);
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Une erreur est survenue pendant l’analyse."
      );

      trackEvent("audit_error");
    } finally {
      setLoading(false);
    }
  }

  async function handleLeadSubmit(
    event: FormEvent<HTMLFormElement>
  ) {
    event.preventDefault();

    if (!result) return;

    setLeadError("");

    if (!leadName.trim()) {
      setLeadError("Indiquez votre nom.");
      return;
    }

    if (!leadEmail.trim()) {
      setLeadError("Indiquez votre adresse e-mail.");
      return;
    }

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(leadEmail)) {
      setLeadError(
        "Indiquez une adresse e-mail valide."
      );
      return;
    }

    if (!leadConsent) {
      setLeadError(
        "Vous devez accepter d’être recontacté."
      );
      return;
    }

    trackEvent("audit_lead_submit", {
      score: result.score,
      website: url,
    });

    setLeadLoading(true);

    try {
      const body = new URLSearchParams();

      body.set("name", leadName.trim());
      body.set("email", leadEmail.trim());
      body.set("phone", leadPhone.trim());
      body.set("website", url);
      body.set("score", String(result.score));

      result.recommendations
        .slice(0, 3)
        .forEach((recommendation, index) => {
          body.set(
            `recommendation_${index + 1}`,
            recommendation
          );
        });

      const response = await fetch("/audit-lead.php", {
        method: "POST",
        headers: {
          "Content-Type":
            "application/x-www-form-urlencoded",
        },
        body: body.toString(),
      });

      const data = await response.json();

      if (!response.ok || !data.success) {
        throw new Error(
          data.message ||
            "Impossible d’envoyer votre demande."
        );
      }

      setLeadSubmitted(true);

      trackEvent("audit_lead_success", {
        score: result.score,
        website: url,
      });
    } catch (err) {
      setLeadError(
        err instanceof Error
          ? err.message
          : "Une erreur est survenue. Réessayez."
      );

      trackEvent("audit_lead_error");
    } finally {
      setLeadLoading(false);
    }
  }

  function handleBookingClick() {
    trackEvent("audit_booking_click", {
      score: result?.score,
      website: url,
    });
  }

  return (
    <>
      <SEO
        title="Audit digital gratuit — Analyse complète de votre site | Vitrine+"
        description="Analysez gratuitement votre site internet : SEO, structure, mobile, contenu, performance et partage social. Découvrez ce qui freine votre visibilité et vos conversions."
        canonical="/audit"
      />

      {/* HERO */}
      <section className="relative overflow-hidden px-6 pb-14 pt-32 lg:px-8 lg:pb-20 lg:pt-40">
        <div className="absolute -right-40 top-10 h-[500px] w-[500px] rounded-full bg-[#c8a45d]/10 blur-[110px]" />

        <div className="relative mx-auto max-w-7xl">
          <SectionLabel>
            Audit digital gratuit
          </SectionLabel>

          <h1 className="display mt-7 max-w-5xl text-5xl font-extrabold leading-[.95] sm:text-7xl lg:text-[92px]">
            Votre site vous apporte-t-il vraiment des clients ?
            <span className="block text-black/25">
              Découvrez ce qui bloque.
            </span>
          </h1>

          <p className="mt-7 max-w-2xl text-lg leading-8 text-black/55">
            Entrez l’adresse de votre site. Vitrine+ analyse
            automatiquement les pages accessibles de votre
            domaine et identifie les principaux points qui
            peuvent freiner votre visibilité, votre crédibilité
            ou vos conversions.
          </p>
        </div>
      </section>

      {/* FORMULAIRE AUDIT */}
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
                  L’analyse porte sur les pages découvertes sur
                  votre domaine, et pas uniquement sur votre page
                  d’accueil.
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

                <p className="mt-4 text-xs text-white/30">
                  Gratuit. Sans engagement. Aucune carte bancaire
                  demandée.
                </p>
              </form>
            </div>
          </div>
        </div>
      </section>

      {/* LOADING */}
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

      {/* RESULTATS */}
      {result && (
        <section
          id="audit-result"
          className="scroll-mt-20 px-6 pb-20 lg:px-8 lg:pb-28"
        >
          <div className="mx-auto max-w-7xl">
            {/* SCORE */}
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
                  {Object.entries(result.categories).map(
                    ([key, category]) => (
                      <div
                        key={key}
                        className="rounded-2xl bg-white p-5"
                      >
                        <div className="flex items-center justify-between">
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

            {/* RECOMMANDATIONS */}
            <div className="mt-5 rounded-[2rem] border border-black/10 p-8 sm:p-10">
              <SectionLabel>
                Recommandations
              </SectionLabel>

              <h2 className="display mt-5 text-3xl font-extrabold sm:text-4xl">
                Les 3 priorités à traiter.
              </h2>

              <div className="mt-8 grid gap-5">
                {result.recommendations
                  .slice(0, 3)
                  .map((item, index) => (
                    <div
                      key={item}
                      className="flex gap-4"
                    >
                      <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#f5f5f2] text-xs font-extrabold">
                        0{index + 1}
                      </span>

                      <div>
                        <p className="text-sm font-bold leading-6 text-black/75">
                          {item}
                        </p>

                        <p className="mt-1 text-xs leading-5 text-black/40">
                          Recommandation issue de l’analyse de
                          votre site.
                        </p>
                      </div>
                    </div>
                  ))}
              </div>
            </div>

            {/* LEAD CAPTURE */}
            <div className="mt-5 rounded-[2rem] bg-[#080808] p-8 text-white sm:p-10 lg:p-12">
              {!leadSubmitted ? (
                <>
                  <div className="grid gap-10 lg:grid-cols-[1fr_.9fr] lg:items-center">
                    <div>
                      <div className="flex items-center gap-3">
                        <Sparkles
                          size={20}
                          className="text-[#c8a45d]"
                        />

                        <SectionLabel>
                          Votre analyse personnalisée
                        </SectionLabel>
                      </div>

                      <h2 className="display mt-5 max-w-2xl text-3xl font-extrabold sm:text-5xl">
                        Votre score n’est que le début.
                      </h2>

                      <p className="mt-5 max-w-xl text-sm leading-7 text-white/50">
                        Recevez une lecture personnalisée de
                        votre audit : les 3 priorités à traiter,
                        les opportunités les plus intéressantes
                        et les prochaines actions à envisager.
                      </p>

                      <div className="mt-7 space-y-3">
                        {[
                          "Les 3 corrections prioritaires",
                          "Les opportunités SEO à exploiter",
                          "Les prochaines actions recommandées",
                        ].map((item) => (
                          <div
                            key={item}
                            className="flex items-center gap-3"
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
                    </div>

                    <form
                      onSubmit={handleLeadSubmit}
                      className="rounded-[1.5rem] border border-white/10 bg-white/[0.04] p-6 sm:p-7"
                    >
                      <div className="space-y-4">
                        <div>
                          <label className="mb-2 block text-xs font-bold text-white/50">
                            Votre nom
                          </label>

                          <div className="relative">
                            <User
                              size={17}
                              className="absolute left-4 top-1/2 -translate-y-1/2 text-white/25"
                            />

                            <input
                              value={leadName}
                              onChange={(event) =>
                                setLeadName(
                                  event.target.value
                                )
                              }
                              type="text"
                              autoComplete="name"
                              placeholder="Jean Dupont"
                              className="w-full rounded-xl border border-white/10 bg-white/[0.06] py-3.5 pl-11 pr-4 text-sm text-white outline-none transition placeholder:text-white/25 focus:border-[#c8a45d]"
                            />
                          </div>
                        </div>

                        <div>
                          <label className="mb-2 block text-xs font-bold text-white/50">
                            E-mail professionnel
                          </label>

                          <div className="relative">
                            <Mail
                              size={17}
                              className="absolute left-4 top-1/2 -translate-y-1/2 text-white/25"
                            />

                            <input
                              value={leadEmail}
                              onChange={(event) =>
                                setLeadEmail(
                                  event.target.value
                                )
                              }
                              type="email"
                              autoComplete="email"
                              placeholder="vous@entreprise.fr"
                              className="w-full rounded-xl border border-white/10 bg-white/[0.06] py-3.5 pl-11 pr-4 text-sm text-white outline-none transition placeholder:text-white/25 focus:border-[#c8a45d]"
                            />
                          </div>
                        </div>

                        <div>
                          <label className="mb-2 block text-xs font-bold text-white/50">
                            Téléphone
                            <span className="ml-1 font-normal text-white/25">
                              facultatif
                            </span>
                          </label>

                          <div className="relative">
                            <Phone
                              size={17}
                              className="absolute left-4 top-1/2 -translate-y-1/2 text-white/25"
                            />

                            <input
                              value={leadPhone}
                              onChange={(event) =>
                                setLeadPhone(
                                  event.target.value
                                )
                              }
                              type="tel"
                              autoComplete="tel"
                              placeholder="06 00 00 00 00"
                              className="w-full rounded-xl border border-white/10 bg-white/[0.06] py-3.5 pl-11 pr-4 text-sm text-white outline-none transition placeholder:text-white/25 focus:border-[#c8a45d]"
                            />
                          </div>
                        </div>

                        <label className="flex cursor-pointer items-start gap-3 pt-1">
                          <input
                            type="checkbox"
                            checked={leadConsent}
                            onChange={(event) =>
                              setLeadConsent(
                                event.target.checked
                              )
                            }
                            className="mt-0.5 h-4 w-4 shrink-0 accent-[#c8a45d]"
                          />

                          <span className="text-xs leading-5 text-white/40">
                            J’accepte que Vitrine+ utilise mes
                            coordonnées pour me recontacter au
                            sujet de mon audit et de mon projet.
                          </span>
                        </label>

                        {leadError && (
                          <div className="flex items-start gap-2 rounded-xl border border-red-400/20 bg-red-400/10 p-3 text-xs text-red-200">
                            <AlertCircle
                              size={15}
                              className="mt-0.5 shrink-0"
                            />
                            <span>{leadError}</span>
                          </div>
                        )}

                        <button
                          type="submit"
                          disabled={leadLoading}
                          className="inline-flex w-full items-center justify-center gap-2 rounded-full bg-[#c8a45d] px-6 py-4 text-sm font-extrabold text-black transition hover:bg-white disabled:cursor-not-allowed disabled:opacity-60"
                        >
                          {leadLoading ? (
                            <>
                              <Loader2
                                size={17}
                                className="animate-spin"
                              />
                              Envoi…
                            </>
                          ) : (
                            <>
                              Recevoir mon analyse personnalisée
                              <ArrowRight size={17} />
                            </>
                          )}
                        </button>

                        <p className="text-center text-[11px] leading-4 text-white/25">
                          Aucun spam. Vos coordonnées servent
                          uniquement à vous recontacter concernant
                          votre audit.
                        </p>
                      </div>
                    </form>
                  </div>
                </>
              ) : (
                <div className="mx-auto max-w-2xl text-center">
                  <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-[#c8a45d] text-black">
                    <Check size={25} />
                  </div>

                  <SectionLabel>
                    Demande envoyée
                  </SectionLabel>

                  <h2 className="display mt-5 text-3xl font-extrabold sm:text-5xl">
                    Votre analyse personnalisée est en route.
                  </h2>

                  <p className="mt-5 text-sm leading-7 text-white/50">
                    Nous avons bien reçu vos coordonnées.
                    Vitrine+ reviendra vers vous avec une lecture
                    plus concrète de votre site et de ses
                    principales opportunités.
                  </p>

                  <Link
                    to="/rendez-vous"
                    onClick={handleBookingClick}
                    className="mt-8 inline-flex items-center gap-3 rounded-full bg-[#c8a45d] px-7 py-4 text-sm font-extrabold text-black transition hover:bg-white"
                  >
                    Prendre rendez-vous
                    <ArrowRight size={17} />
                  </Link>
                </div>
              )}
            </div>

            {/* VALEUR VITRINE+ */}
            <div className="mt-5 rounded-[2rem] border border-black/10 p-8 sm:p-10">
              <div className="grid gap-8 md:grid-cols-3">
                <div>
                  <ShieldCheck
                    size={22}
                    className="text-[#c8a45d]"
                  />

                  <h3 className="mt-4 font-extrabold">
                    Analyse technique
                  </h3>

                  <p className="mt-2 text-sm leading-6 text-black/45">
                    Structure, balises, mobile, images, sécurité
                    et signaux SEO essentiels.
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
                    Temps de réponse, poids des pages et éléments
                    pouvant ralentir l’expérience.
                  </p>
                </div>

                <div>
                  <Sparkles
                    size={22}
                    className="text-[#c8a45d]"
                  />

                  <h3 className="mt-4 font-extrabold">
                    Opportunités
                  </h3>

                  <p className="mt-2 text-sm leading-6 text-black/45">
                    Les corrections prioritaires pour améliorer
                    votre présence digitale.
                  </p>
                </div>
              </div>
            </div>

            {/* CTA FINAL */}
            <div className="mt-5 rounded-[2rem] bg-[#080808] p-8 text-white sm:p-12 lg:p-14">
              <div className="max-w-3xl">
                <SectionLabel>
                  La suite
                </SectionLabel>

                <h2 className="display mt-5 text-4xl font-extrabold sm:text-6xl">
                  Vous savez maintenant où agir.
                  <span className="block text-white/35">
                    Nous pouvons vous aider à le faire.
                  </span>
                </h2>

                <p className="mt-6 max-w-2xl leading-7 text-white/50">
                  Vitrine+ peut transformer les recommandations
                  de cet audit en un plan d’action concret pour
                  votre entreprise.
                </p>

                <Link
                  to="/rendez-vous"
                  onClick={handleBookingClick}
                  className="mt-8 inline-flex items-center gap-3 rounded-full bg-[#c8a45d] px-7 py-4 text-sm font-extrabold text-black transition hover:bg-white"
                >
                  Parler de mon projet
                  <ArrowRight size={17} />
                </Link>
              </div>
            </div>

            <p className="mt-6 text-center text-xs leading-5 text-black/35">
              Cet audit fournit une analyse indicative basée sur
              les signaux accessibles publiquement. Il ne remplace
              pas un audit SEO approfondi ni une mesure complète
              des Core Web Vitals.
            </p>
          </div>
        </section>
      )}
    </>
  );
}
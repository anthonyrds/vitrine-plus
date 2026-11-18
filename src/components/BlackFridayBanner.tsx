import { Link } from "react-router-dom";
import { useBlackFridayActive } from "../lib/blackFridayPromo";

type Props = { variant?: "home" | "solutions" };

export default function BlackFridayBanner({ variant = "solutions" }: Props) {
  const active = useBlackFridayActive();
  if (!active) return null;

  if (variant === "home") {
    return (
      <section aria-label="Offre Black Friday Vitrine+" className="px-6 pt-8 lg:px-8">
        <div className="mx-auto max-w-7xl overflow-hidden rounded-[2rem] bg-[#090909] text-white">
          <div className="relative px-6 py-8 sm:px-10 sm:py-10">
            <div className="absolute inset-0 opacity-30" style={{ background: "radial-gradient(circle at 80% 20%, #c8a45d 0, transparent 42%), linear-gradient(110deg, transparent 35%, rgba(190,70,120,.25))" }} />
            <div className="relative flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <p className="text-[10px] font-extrabold uppercase tracking-[.24em] text-[#d8b873]">Black Friday · 19—30 novembre 2026</p>
                <h2 className="display mt-3 text-3xl font-extrabold sm:text-5xl">Votre prochain niveau. <span className="text-[#d8b873]">-40 %.</span></h2>
                <p className="mt-3 max-w-xl text-sm leading-6 text-white/65">Trois solutions digitales pour faire évoluer votre entreprise. Une offre limitée dans le temps.</p>
              </div>
              <Link to="/solutions" className="inline-flex shrink-0 items-center justify-center rounded-full bg-[#d8b873] px-6 py-3 text-sm font-extrabold text-black transition hover:bg-white">Découvrir l’offre <span aria-hidden="true" className="ml-2">→</span></Link>
            </div>
          </div>
        </div>
      </section>
    );
  }

  return (
    <section aria-label="Offre Black Friday Vitrine+" className="mx-6 mb-10 overflow-hidden rounded-[1.75rem] bg-[#080808] text-white sm:mx-8">
      <div className="relative px-6 py-7 text-center sm:px-10 sm:py-9">
        <div className="absolute inset-0 opacity-35" style={{ background: "radial-gradient(ellipse at 50% 0%, #bd5c83 0, transparent 55%), linear-gradient(120deg, transparent, rgba(200,164,93,.18))" }} />
        <div className="relative">
          <p className="text-[10px] font-extrabold uppercase tracking-[.25em] text-[#d8b873]">Une opération spéciale Vitrine+</p>
          <h2 className="display mt-3 text-3xl font-extrabold sm:text-5xl">BLACK FRIDAY <span className="text-[#d8b873]">−40 %</span></h2>
          <p className="mt-3 text-sm text-white/70">Une esthétique événementielle. Toute l’exigence Vitrine+.</p>
          <p className="mt-2 text-xs font-semibold tracking-wide text-white/45">Du 19 au 30 novembre 2026 inclus · Nouveaux clients</p>
        </div>
      </div>
    </section>
  );
}

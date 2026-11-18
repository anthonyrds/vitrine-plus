
import { Link } from "react-router-dom";
import { useBlackFridayActive } from "../lib/blackFridayPromo";

type Props = { variant?: "home" | "solutions" };

export default function BlackFridayBanner({ variant = "solutions" }: Props) {
  const active = useBlackFridayActive();
  if (!active) return null;

  if (variant === "home") {
    const message = (
      <>
        <span className="font-extrabold tracking-[.18em] text-[#d8b873]">
          BLACK FRIDAY
        </span>
        <span className="mx-4 text-white/40">·</span>
        <span className="font-extrabold text-[#d8b873]">−40 %</span>
        <span className="mx-4 text-white/40">·</span>
        <span className="text-white">Sur toutes nos solutions</span>
        <span className="mx-4 text-white/40">·</span>
        <span className="text-white/70">Du 19 au 30 novembre 2026</span>
      </>
    );

    return (
      <section
        aria-label="Offre Black Friday Vitrine+"
        className="overflow-hidden bg-[#090909] py-3 text-xs sm:text-sm"
      >
        <div className="flex w-max animate-black-friday-marquee motion-reduce:animate-none">
          <div className="flex shrink-0 items-center whitespace-nowrap px-6">
            {message}
          </div>
          <div
            aria-hidden="true"
            className="flex shrink-0 items-center whitespace-nowrap px-6"
          >
            {message}
          </div>
        </div>
      </section>
    );
  }

  return (
    <section
      aria-label="Offre Black Friday Vitrine+"
      className="mx-6 mb-10 overflow-hidden rounded-[1.75rem] bg-[#080808] text-white sm:mx-8"
    >
      <div className="relative px-6 py-7 text-center sm:px-10 sm:py-9">
        <div
          className="absolute inset-0 opacity-35"
          style={{
            background:
              "radial-gradient(ellipse at 50% 0%, #bd5c83 0, transparent 55%), linear-gradient(120deg, transparent, rgba(200,164,93,.18))",
          }}
        />
        <div className="relative">
          <p className="text-[10px] font-extrabold uppercase tracking-[.25em] text-[#d8b873]">
            Une opération spéciale Vitrine+
          </p>
          <h2 className="display mt-3 text-3xl font-extrabold sm:text-5xl">
            BLACK FRIDAY <span className="text-[#d8b873]">−40 %</span>
          </h2>
          <p className="mt-3 text-sm text-white/70">
            Une esthétique événementielle. Toute l’exigence Vitrine+.
          </p>
          <p className="mt-2 text-xs font-semibold tracking-wide text-white/45">
            Du 19 au 30 novembre 2026 inclus · Nouveaux clients
          </p>
        </div>
      </div>
    </section>
  );
}

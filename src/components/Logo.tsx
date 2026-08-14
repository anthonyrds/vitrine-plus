import { Link } from "react-router-dom";

export default function Logo() {
  return (
    <Link
      to="/"
      className="focus-ring inline-flex items-center gap-2.5"
      aria-label="Vitrine+ accueil"
    >
      <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-black/10 bg-white text-[15px] font-extrabold leading-none">
        V<span className="text-[#c8a45d]">+</span>
      </span>

      <span className="display whitespace-nowrap text-[19px] font-extrabold leading-none tracking-[-0.055em]">
        Vitrine<span className="text-[#c8a45d]">+</span>
      </span>
    </Link>
  );
}
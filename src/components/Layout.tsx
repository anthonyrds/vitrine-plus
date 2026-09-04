import { Menu, X } from "lucide-react";
import { Link, NavLink, Outlet, useLocation } from "react-router-dom";
import { useEffect, useState } from "react";
import Logo from "./Logo";

const nav: [string, string][] = [
  ["/services", "Services"],
  ["/realisations", "Réalisations"],
  ["/solutions", "Solutions"],
  ["/a-propos", "À propos"],
];

export default function Layout() {
  const [open, setOpen] = useState(false);
  const location = useLocation();

  useEffect(() => {
    setOpen(false);

    window.scrollTo({
      top: 0,
      behavior: "instant" as ScrollBehavior,
    });
  }, [location.pathname]);

  return (
    <div className="min-h-screen bg-white text-[#080808]">
      <header className="fixed inset-x-0 top-0 z-50 px-4 pt-4 sm:px-6 lg:px-8">
        <div
          className="
            
            mx-auto flex max-w-7xl items-center justify-between
            rounded-full border border-black/10
            bg-white/95
            px-4 py-2.5
            shadow-[0_10px_40px_rgba(0,0,0,.06)]
            sm:px-5
            lg:bg-white/85
            backdrop-blur-none lg:backdrop-blur-xl
          "
        >
          <Logo />

          <nav className="hidden items-center gap-7 text-sm font-semibold text-black/55 lg:flex">
            {nav.map(([to, label]) => (
              <NavLink
                key={to}
                to={to}
                className={({ isActive }) =>
                  `transition hover:text-black ${
                    isActive ? "text-black" : ""
                  }`
                }
              >
                {label}
              </NavLink>
            ))}
          </nav>

          <div className="hidden items-center gap-2 lg:flex">
            <Link
              to="/audit"
              className="focus-ring rounded-full px-4 py-3 text-sm font-bold text-black/60 transition hover:text-black"
            >
              Audit gratuit
            </Link>

            <Link
              to="/rendez-vous"
              className="focus-ring rounded-full bg-[#080808] px-5 py-3 text-sm font-bold !text-white transition hover:bg-[#c8a45d] hover:!text-[#080808]"
            >
              Prendre rendez-vous
            </Link>
          </div>

          <button
            type="button"
            className="focus-ring rounded-full p-2 lg:hidden"
            onClick={() => setOpen((value) => !value)}
            aria-label={open ? "Fermer le menu" : "Ouvrir le menu"}
          >
            {open ? <X /> : <Menu />}
          </button>
        </div>

        {open && (
          <div className="mx-auto mt-2 max-w-7xl rounded-3xl border border-black/10 bg-white p-5 shadow-[0_10px_30px_rgba(0,0,0,0.08)] lg:hidden">
            <nav className="grid gap-2">
              {nav.map(([to, label]) => (
                <Link
                  key={to}
                  to={to}
                  className="rounded-2xl px-4 py-3 font-semibold hover:bg-black/5"
                >
                  {label}
                </Link>
              ))}

              <Link
                to="/audit"
                className="mt-2 rounded-2xl border border-black/10 px-4 py-3 text-center font-bold"
              >
                Audit gratuit
              </Link>

              <Link
                to="/rendez-vous"
                className="rounded-2xl bg-[#080808] px-4 py-3 text-center font-bold !text-white"
              >
                Prendre rendez-vous
              </Link>
            </nav>
          </div>
        )}
      </header>

      <main style={{ transform: "translateZ(0)" }}>
  <Outlet />
</main>

      <footer className="border-t border-black/10 bg-white px-6 py-14 lg:px-8">
        <div className="mx-auto grid max-w-7xl gap-12 md:grid-cols-4">
          <div className="md:col-span-2">
            <Logo />

            <p className="mt-5 max-w-md text-sm leading-7 text-black/50">
              Vitrine+ accompagne les entreprises dans leur transformation
              digitale : stratégie, web, visibilité, acquisition, identité,
              automatisation et IA.
            </p>

            <div className="mt-6 flex flex-wrap gap-3">
              <Link
                to="/audit"
                className="rounded-full border border-black/10 px-4 py-2 text-xs font-bold"
              >
                Audit gratuit
              </Link>

              <Link
                to="/rendez-vous"
                className="rounded-full bg-[#080808] px-4 py-2 text-xs font-bold !text-white"
              >
                Prendre rendez-vous
              </Link>
            </div>
          </div>

          <div>
            <p className="text-xs font-bold uppercase tracking-[.2em] text-black/35">
              Explorer
            </p>

            <div className="mt-5 grid gap-3 text-sm font-semibold text-black/60">
              <Link to="/services">Services</Link>
              <Link to="/realisations">Réalisations</Link>
              <Link to="/solutions">Solutions</Link>
              <Link to="/a-propos">À propos</Link>
            </div>
          </div>

          <div>
            <p className="text-xs font-bold uppercase tracking-[.2em] text-black/35">
              Projet
            </p>

            <div className="mt-5 grid gap-3 text-sm font-semibold text-black/60">
              <Link to="/audit">Audit gratuit</Link>
              <Link to="/rendez-vous">Rendez-vous</Link>
              <Link to="/contact">Contact</Link>
              <Link to="/mentions-legales">Mentions légales</Link>
            </div>
          </div>
        </div>

        <div className="mx-auto mt-14 flex max-w-7xl flex-col gap-3 border-t border-black/10 pt-6 text-xs text-black/35 sm:flex-row sm:items-center sm:justify-between">
          <span>
            © {new Date().getFullYear()} Vitrine+. Tous droits réservés.
          </span>

          <span>Votre entreprise. En mieux.</span>
        </div>
      </footer>
    </div>
  );
}
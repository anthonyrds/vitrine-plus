import { Link } from "react-router-dom";
import SEO from "../components/SEO";

export default function NotFound() {
  return (
    <>
      <SEO
        title="Page introuvable | Vitrine+"
        description="La page que vous recherchez n'existe pas ou n'est plus disponible."
        noindex
      />

      <section className="grid min-h-screen place-items-center px-6 pt-24">
        <div className="text-center">
          <p className="text-sm font-bold uppercase tracking-[.2em] text-[#c8a45d]">
            404
          </p>

          <h1 className="display mt-4 text-6xl font-extrabold">
            Page introuvable.
          </h1>

          <p className="mx-auto mt-5 max-w-md leading-7 text-black/50">
            La page que vous recherchez n'existe pas ou n'est plus disponible.
          </p>

          <div className="mt-8 flex flex-wrap justify-center gap-3">
            <Link
              to="/"
              className="inline-block rounded-full bg-black px-6 py-3 text-sm font-bold text-white transition hover:bg-[#c8a45d] hover:text-black"
            >
              Retour à l'accueil
            </Link>

            <Link
              to="/services"
              className="inline-block rounded-full border border-black/10 px-6 py-3 text-sm font-bold transition hover:bg-black/5"
            >
              Voir nos services
            </Link>
          </div>
        </div>
      </section>
    </>
  );
}
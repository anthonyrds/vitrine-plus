import Button from "./Button";
import SectionLabel from "./SectionLabel";

export default function CTA({ title = "Votre prochain projet commence ici.", text = "Parlons de votre entreprise, de vos objectifs et de ce que nous pouvons construire ensemble." }: { title?: string; text?: string }) {
  return (
    <section className="px-6 pb-24 lg:px-8 lg:pb-32">
      <div className="noise relative mx-auto max-w-7xl overflow-hidden rounded-[2rem] bg-[#080808] px-7 py-16 text-white sm:px-12 lg:px-20 lg:py-24">
        <div className="absolute -right-20 -top-20 h-72 w-72 rounded-full bg-[#c8a45d]/15 blur-[90px]" />
        <div className="relative max-w-3xl">
          <SectionLabel>Parlons de votre projet</SectionLabel>
          <h2 className="display mt-6 text-4xl font-extrabold leading-tight tracking-[-.055em] sm:text-6xl">{title}</h2>
          <p className="mt-6 max-w-2xl text-lg leading-8 text-white/55">{text}</p>
          <div className="mt-9 flex flex-wrap gap-3">
            <Button to="/contact" dark={false}>Démarrer un projet</Button>
            <Button to="/audit" dark={false} className="border border-white/15 bg-transparent text-white hover:bg-white hover:text-black">Audit gratuit</Button>
          </div>
        </div>
      </div>
    </section>
  );
}
import SectionLabel from "./SectionLabel";

export default function PageHero({ eyebrow, title, text }: { eyebrow: string; title: React.ReactNode; text?: string }) {
  return (
    <section className="relative overflow-hidden px-6 pb-20 pt-40 lg:px-8 lg:pb-28 lg:pt-48">
      <div className="absolute -right-40 top-20 h-[520px] w-[520px] rounded-full bg-[#c8a45d]/10 blur-0 lg:blur-[120px]" />
      <div className="relative mx-auto max-w-7xl reveal">
        <SectionLabel>{eyebrow}</SectionLabel>
        <h1 className="display mt-7 max-w-5xl text-5xl font-extrabold leading-[.95] sm:text-7xl lg:text-[104px]">{title}</h1>
        {text && <p className="mt-8 max-w-2xl text-lg leading-8 text-black/55">{text}</p>}
      </div>
    </section>
  );
}
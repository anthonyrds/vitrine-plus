import { useState } from "react";

type Props = { audit?: boolean };

export default function LeadForm({ audit = false }: Props) {
  const [sent, setSent] = useState(false);
  const [error, setError] = useState("");

  async function submit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setError("");
    const form = e.currentTarget;
    const data = new FormData(form);
    data.set("type", audit ? "audit" : "contact");
    try {
      const res = await fetch(audit ? "/audit.php" : "/contact.php", { method: "POST", body: data });
      const json = await res.json();
      if (!res.ok || !json.success) throw new Error(json.message || "Erreur");
      setSent(true);
      form.reset();
    } catch {
      setError("Le formulaire n’a pas pu être envoyé. Vous pouvez aussi nous écrire directement à contact@vitrineplus.fr.");
    }
  }

  if (sent) {
    return (
      <div className="rounded-3xl border border-[#c8a45d]/30 bg-[#c8a45d]/10 p-8">
        <p className="text-xs font-bold uppercase tracking-[.2em] text-[#8a6a31]">Demande reçue</p>
        <h3 className="display mt-3 text-3xl font-extrabold">Merci. Nous revenons vers vous rapidement.</h3>
        <p className="mt-3 leading-7 text-black/55">Votre demande a bien été transmise à Vitrine+.</p>
      </div>
    );
  }

  return (
    <form onSubmit={submit} className="grid gap-5">
      <div className="grid gap-5 sm:grid-cols-2">
        <Field label="Nom / prénom" name="name" required />
        <Field label="Entreprise" name="company" required />
      </div>
      <div className="grid gap-5 sm:grid-cols-2">
        <Field label="E-mail" name="email" type="email" required />
        <Field label="Téléphone" name="phone" />
      </div>
      {audit && (
        <div className="grid gap-5 sm:grid-cols-2">
          <Field label="Site actuel" name="website" />
          <Select label="Votre objectif" name="goal" options={["Créer un site", "Améliorer ma visibilité", "Générer plus de prospects", "Automatiser mon activité", "Autre"]} />
        </div>
      )}
      <Select label="Budget indicatif" name="budget" options={["Moins de 1 000 €", "1 000 à 2 000 €", "2 000 à 5 000 €", "5 000 € et plus", "Je ne sais pas encore"]} />
      <div>
        <label className="mb-2 block text-sm font-semibold">Votre projet</label>
        <textarea name="message" required rows={6} className="w-full resize-y rounded-2xl border border-black/10 bg-white px-4 py-4 outline-none transition focus:border-[#c8a45d]" placeholder="Décrivez votre activité, votre besoin et votre objectif..." />
      </div>
      <label className="flex gap-3 text-xs leading-5 text-black/50">
        <input type="checkbox" required className="mt-1" />
        J’accepte que les informations saisies soient utilisées pour répondre à ma demande.
      </label>
      {error && <p className="rounded-2xl bg-red-50 p-4 text-sm text-red-700">{error}</p>}
      <button className="group inline-flex w-fit items-center gap-2 rounded-full bg-[#080808] px-7 py-4 text-sm font-bold text-white transition hover:bg-[#c8a45d] hover:text-[#080808]" type="submit">
        {audit ? "Recevoir mon audit" : "Envoyer ma demande"}
      </button>
    </form>
  );
}

function Field({ label, name, type = "text", required = false }: { label: string; name: string; type?: string; required?: boolean }) {
  return (
    <div>
      <label className="mb-2 block text-sm font-semibold">{label}</label>
      <input name={name} type={type} required={required} className="w-full rounded-2xl border border-black/10 bg-white px-4 py-3.5 outline-none transition focus:border-[#c8a45d]" />
    </div>
  );
}

function Select({ label, name, options }: { label: string; name: string; options: string[] }) {
  return (
    <div>
      <label className="mb-2 block text-sm font-semibold">{label}</label>
      <select name={name} defaultValue="" className="w-full rounded-2xl border border-black/10 bg-white px-4 py-3.5 outline-none transition focus:border-[#c8a45d]" required>
        <option value="" disabled>Choisissez une option</option>
        {options.map(o => <option key={o}>{o}</option>)}
      </select>
    </div>
  );
}
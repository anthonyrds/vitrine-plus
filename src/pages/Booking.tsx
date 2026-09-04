import {
  FormEvent,
  useEffect,
  useMemo,
  useState,
} from "react";

import {
  ArrowRight,
  CalendarDays,
  CheckCircle2,
  Clock3,
  Loader2,
  Phone,
  ShieldCheck,
} from "lucide-react";

import { Link } from "react-router-dom";

import SEO from "../components/SEO";
import SectionLabel from "../components/SectionLabel";

type Slot = {
  time: string;
  available: boolean;
};

const TIMEZONE = "Europe/Paris";

function getNextBusinessDays(count: number) {
  const days: string[] = [];

  const formatter = new Intl.DateTimeFormat("en-CA", {
    timeZone: TIMEZONE,
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  });

  const now = new Date();

  for (
    let offset = 0;
    days.length < count && offset < 60;
    offset++
  ) {
    const date = new Date(now);

    date.setDate(
      date.getDate() + offset
    );

    const weekday =
      new Intl.DateTimeFormat("en-US", {
        timeZone: TIMEZONE,
        weekday: "short",
      }).format(date);

    if (
      weekday === "Sat" ||
      weekday === "Sun"
    ) {
      continue;
    }

    days.push(
      formatter.format(date)
    );
  }

  return days;
}

function formatDate(date: string) {
  return new Intl.DateTimeFormat("fr-FR", {
    weekday: "long",
    day: "numeric",
    month: "long",
    timeZone: TIMEZONE,
  }).format(
    new Date(`${date}T12:00:00`)
  );
}

function generateSlots(): Slot[] {
  const slots: Slot[] = [];

  for (
    let minutes = 9 * 60;
    minutes < 18 * 60;
    minutes += 30
  ) {
    const hours =
      Math.floor(minutes / 60);

    const mins =
      minutes % 60;

    slots.push({
      time:
        `${String(hours).padStart(2, "0")}:` +
        `${String(mins).padStart(2, "0")}`,
      available: true,
    });
  }

  return slots;
}

function getParisDateTime(
  date: string,
  time: string
) {
  return new Date(
    `${date}T${time}:00`
  );
}

function isSlotInPast(
  date: string,
  time: string
) {
  const now = new Date();

  const slot =
    getParisDateTime(
      date,
      time
    );

  return (
    slot.getTime() <=
    now.getTime() +
      30 * 60 * 1000
  );
}

export default function Booking() {
  const dates = useMemo(
    () => getNextBusinessDays(15),
    []
  );

  const [date, setDate] = useState(
    dates[0] ?? ""
  );

  const [slots, setSlots] =
    useState<Slot[]>(
      generateSlots()
    );

  const [time, setTime] =
    useState("");

  const [loadingSlots, setLoadingSlots] =
    useState(false);

  const [sending, setSending] =
    useState(false);

  const [error, setError] =
    useState("");

  const [confirmation, setConfirmation] =
    useState<{
      reference: string;
      date: string;
      time: string;
    } | null>(null);

  /*
  |--------------------------------------------------------------------------
  | CHARGEMENT DES CRÉNEAUX
  |--------------------------------------------------------------------------
  |
  | Les horaires sont générés côté navigateur.
  | Le PHP sert uniquement à connaître les créneaux
  | déjà réservés.
  |
  */

  useEffect(() => {
    async function loadAvailability() {
      if (!date) {
        setSlots([]);
        return;
      }

      setLoadingSlots(true);
      setError("");
      setTime("");

      /*
       * Toujours afficher les horaires.
       * On ne dépend plus d'une réponse PHP pour
       * construire l'interface.
       */
      const localSlots =
        generateSlots().map(
          (slot) => ({
            ...slot,
            available:
              !isSlotInPast(
                date,
                slot.time
              ),
          })
        );

      setSlots(localSlots);

      try {
        const params =
          new URLSearchParams({
            action: "slots",
            date,
          });

        const response =
          await fetch(
            `/booking.php?${params.toString()}`,
            {
              method: "GET",
              cache: "no-store",
              headers: {
                Accept:
                  "application/json",
              },
            }
          );

        /*
         * Si PHP répond correctement,
         * on utilise ses disponibilités réelles.
         */
        const contentType =
          response.headers.get(
            "content-type"
          ) ?? "";

        if (
          response.ok &&
          contentType.includes(
            "application/json"
          )
        ) {
          const data =
            await response.json();

          if (
            data?.success &&
            Array.isArray(
              data.slots
            )
          ) {
            const serverSlots =
              data.slots as Slot[];

            setSlots(
              localSlots.map(
                (localSlot) => {
                  const serverSlot =
                    serverSlots.find(
                      (item) =>
                        item.time ===
                        localSlot.time
                    );

                  return {
                    ...localSlot,
                    available:
                      serverSlot
                        ? Boolean(
                            serverSlot.available
                          )
                        : localSlot.available,
                  };
                }
              )
            );
          }
        }
      } catch {
        /*
         * Très important :
         * une erreur du PHP ne doit jamais
         * faire disparaître les horaires.
         *
         * Les horaires locaux restent affichés.
         */
      } finally {
        setLoadingSlots(false);
      }
    }

    loadAvailability();
  }, [date]);

  async function submit(
    event: FormEvent<HTMLFormElement>
  ) {
    event.preventDefault();

    setError("");

    if (!date || !time) {
      setError(
        "Choisissez une date et un créneau."
      );

      return;
    }

    setSending(true);

    const formData =
      new FormData(
        event.currentTarget
      );

    formData.set(
      "action",
      "book"
    );

    formData.set(
      "date",
      date
    );

    formData.set(
      "time",
      time
    );

    try {
      const response =
        await fetch(
          "/booking.php",
          {
            method: "POST",
            body: formData,
            cache: "no-store",
          }
        );

      const contentType =
        response.headers.get(
          "content-type"
        ) ?? "";

      if (
        !contentType.includes(
          "application/json"
        )
      ) {
        throw new Error(
          "Le serveur n’a pas retourné une réponse valide."
        );
      }

      const data =
        await response.json();

      if (
        !response.ok ||
        !data.success
      ) {
        throw new Error(
          data.message ||
            "La réservation n’a pas pu être enregistrée."
        );
      }

      setConfirmation({
        reference:
          data.reference,
        date,
        time,
      });
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "La réservation n’a pas pu être enregistrée."
      );
    } finally {
      setSending(false);
    }
  }

  if (confirmation) {
    return (
      <>
        <SEO
          title="Rendez-vous confirmé | Vitrine+"
          description="Votre rendez-vous téléphonique Vitrine+ est confirmé."
          canonical="/rendez-vous"
        />

        <section className="px-6 pb-24 pt-36 lg:px-8 lg:pt-44">
          <div className="mx-auto max-w-3xl rounded-[2rem] bg-[#080808] p-8 text-white sm:p-14">
            <CheckCircle2
              size={44}
              className="text-[#c8a45d]"
            />

            <SectionLabel>
              Rendez-vous confirmé
            </SectionLabel>

            <h1 className="display mt-5 text-5xl font-extrabold sm:text-7xl">
              À bientôt.
            </h1>

            <p className="mt-5 max-w-xl text-lg leading-8 text-white/55">
              Votre rendez-vous téléphonique
              est bien enregistré. Nous vous
              appellerons au numéro indiqué lors
              de la réservation.
            </p>

            <div className="mt-8 rounded-2xl border border-white/10 bg-white/[.04] p-6">
              <div className="flex items-center gap-3">
                <Phone
                  size={19}
                  className="text-[#c8a45d]"
                />

                <span className="font-bold">
                  Rendez-vous téléphonique
                </span>
              </div>

              <div className="mt-6 grid gap-5 sm:grid-cols-2">
                <div>
                  <p className="text-xs text-white/35">
                    Date
                  </p>

                  <p className="mt-1 font-bold capitalize">
                    {formatDate(
                      confirmation.date
                    )}
                  </p>
                </div>

                <div>
                  <p className="text-xs text-white/35">
                    Heure
                  </p>

                  <p className="mt-1 font-bold">
                    {confirmation.time}
                  </p>
                </div>
              </div>
            </div>

            <p className="mt-5 text-xs text-white/35">
              Référence :{" "}
              {confirmation.reference}
            </p>

            <Link
              to="/"
              className="mt-8 inline-flex items-center gap-2 rounded-full bg-[#c8a45d] px-6 py-3.5 text-sm font-bold text-black"
            >
              Retour à l’accueil

              <ArrowRight size={16} />
            </Link>
          </div>
        </section>
      </>
    );
  }

  return (
    <>
      <SEO
        title="Prendre rendez-vous téléphonique — Vitrine+"
        description="Choisissez directement une date et une heure pour être rappelé par Vitrine+ au sujet de votre projet digital."
        canonical="/rendez-vous"
      />

      <section className="relative overflow-hidden px-6 pb-14 pt-32 lg:px-8 lg:pb-20 lg:pt-40">
        <div className="absolute -right-40 top-10 h-[500px] w-[500px] rounded-full bg-[#c8a45d]/10 blur-[110px]" />

        <div className="relative mx-auto max-w-7xl">
          <SectionLabel>
            Prendre rendez-vous
          </SectionLabel>

          <h1 className="display mt-7 max-w-5xl text-5xl font-extrabold leading-[.95] sm:text-7xl lg:text-[92px]">
            Parlons de votre projet.

            <span className="block text-black/25">
              Au téléphone.
            </span>
          </h1>

          <p className="mt-7 max-w-2xl text-lg leading-8 text-black/55">
            Choisissez directement un jour
            et une heure. Nous vous appellerons
            au numéro que vous indiquez.
          </p>
        </div>
      </section>

      <section className="px-6 pb-24 lg:px-8 lg:pb-32">
        <div className="mx-auto max-w-7xl">
          <div className="grid gap-5 lg:grid-cols-[.75fr_1.25fr]">

            <div className="rounded-[2rem] bg-[#080808] p-8 text-white sm:p-10">
              <div className="flex h-12 w-12 items-center justify-center rounded-full bg-[#c8a45d] text-black">
                <Phone size={21} />
              </div>

              <h2 className="display mt-8 text-4xl font-extrabold">
                Un échange simple.
              </h2>

              <p className="mt-5 text-base leading-7 text-white/50">
                Pas de visio à installer.
                Pas de formulaire interminable.
                Vous choisissez votre créneau
                et nous vous appelons.
              </p>

              <div className="mt-8 grid gap-4 border-t border-white/10 pt-7">
                <div className="flex gap-3">
                  <CalendarDays
                    size={18}
                    className="shrink-0 text-[#c8a45d]"
                  />

                  <span className="text-sm text-white/60">
                    Choisissez votre date.
                  </span>
                </div>

                <div className="flex gap-3">
                  <Clock3
                    size={18}
                    className="shrink-0 text-[#c8a45d]"
                  />

                  <span className="text-sm text-white/60">
                    Choisissez votre heure.
                  </span>
                </div>

                <div className="flex gap-3">
                  <Phone
                    size={18}
                    className="shrink-0 text-[#c8a45d]"
                  />

                  <span className="text-sm text-white/60">
                    Nous vous appelons.
                  </span>
                </div>
              </div>

              <div className="mt-8 border-t border-white/10 pt-6">
                <div className="flex gap-3">
                  <ShieldCheck
                    size={18}
                    className="shrink-0 text-[#c8a45d]"
                  />

                  <p className="text-xs leading-5 text-white/40">
                    Vos informations servent
                    uniquement à organiser votre
                    rendez-vous et à vous recontacter
                    dans ce cadre.
                  </p>
                </div>
              </div>
            </div>

            <div className="rounded-[2rem] border border-black/10 bg-[#f5f5f2] p-7 sm:p-10">
              <div className="grid gap-10 lg:grid-cols-[.85fr_1.15fr]">

                {/* DATES */}

                <div>
                  <div className="flex items-center gap-3">
                    <CalendarDays
                      size={20}
                      className="text-[#c8a45d]"
                    />

                    <span className="text-sm font-bold">
                      Choisissez une date
                    </span>
                  </div>

                  <div className="mt-5 grid gap-2">
                    {dates.map(
                      (item) => (
                        <button
                          key={item}
                          type="button"
                          onClick={() =>
                            setDate(item)
                          }
                         className={`rounded-2xl px-4 py-3 text-left text-sm font-semibold capitalize transition ${
  date === item
    ? "!bg-[#080808] !text-white"
    : "bg-white text-black hover:bg-black/5"
}`}
                        >
                          {formatDate(item)}
                        </button>
                      )
                    )}
                  </div>
                </div>

                {/* HORAIRES */}

                <div>
                  <div className="flex items-center justify-between">
                    <span className="text-sm font-bold">
                      Choisissez une heure
                    </span>

                    {loadingSlots && (
                      <Loader2
                        size={17}
                        className="animate-spin text-[#c8a45d]"
                      />
                    )}
                  </div>

                  <p className="mt-2 text-xs text-black/40">
                    Horaires disponibles :
                    09h00 à 18h00,
                    du lundi au vendredi.
                  </p>

                  <div className="mt-5 grid grid-cols-2 gap-2 sm:grid-cols-3">
                    {slots.map(
                      (slot) => (
                        <button
                          key={slot.time}
                          type="button"
                          disabled={
                            !slot.available
                          }
                          onClick={() =>
                            setTime(
                              slot.time
                            )
                          }
                          className={`rounded-xl border px-3 py-3 text-sm font-bold transition ${
  time === slot.time
    ? "!border-[#080808] !bg-[#080808] !text-white"
    : slot.available
      ? "border-black/10 bg-white text-black hover:border-[#c8a45d]"
      : "cursor-not-allowed border-black/5 bg-black/5 text-black/20"
}`}
                        >
                          {slot.time}
                        </button>
                      )
                    )}
                  </div>

                  {slots.length === 0 && (
                    <p className="mt-5 text-sm text-black/45">
                      Aucun créneau disponible
                      cette journée.
                    </p>
                  )}
                </div>
              </div>

              {/* FORMULAIRE */}

              <form
                onSubmit={submit}
                className="mt-10 border-t border-black/10 pt-10"
              >
                <p className="text-xs font-bold uppercase tracking-[.2em] text-black/35">
                  Pour que nous puissions vous appeler
                </p>

                <div className="mt-5 grid gap-5 sm:grid-cols-2">
                  <Field
                    label="Nom / prénom"
                    name="name"
                    required
                  />

                  <Field
                    label="Téléphone"
                    name="phone"
                    type="tel"
                    required
                  />

                  <Field
                    label="Entreprise"
                    name="company"
                  />
                </div>

                <div className="mt-5">
                  <label
                    htmlFor="reason"
                    className="mb-2 block text-sm font-semibold"
                  >
                    Pourquoi souhaitez-vous être rappelé ?
                  </label>

                  <textarea
                    id="reason"
                    name="reason"
                    rows={5}
                    required
                    placeholder="Ex. Je souhaite refaire mon site et améliorer ma visibilité sur Google..."
                    className="w-full rounded-2xl border border-black/10 bg-white px-4 py-3.5 outline-none transition focus:border-[#c8a45d]"
                  />
                </div>

                <div className="hidden">
                  <label>
                    Ne pas remplir

                    <input
                      name="website"
                      autoComplete="off"
                      tabIndex={-1}
                    />
                  </label>
                </div>

                <label className="mt-5 flex gap-3 text-xs leading-5 text-black/50">
                  <input
                    name="consent"
                    type="checkbox"
                    required
                    className="mt-1"
                  />

                  J’accepte que les informations
                  saisies soient utilisées pour
                  organiser ce rendez-vous et
                  répondre à ma demande.
                </label>

                {error && (
                  <p className="mt-5 rounded-2xl bg-red-50 p-4 text-sm leading-6 text-red-700">
                    {error}
                  </p>
                )}

                <button
                  type="submit"
                  disabled={
                    sending ||
                    !time
                  }
                  className="mt-7 inline-flex items-center justify-center gap-2 rounded-full bg-[#080808] px-7 py-4 text-sm font-bold text-white transition hover:bg-[#c8a45d] hover:text-[#080808] disabled:cursor-not-allowed disabled:opacity-40"
                >
                  {sending ? (
                    <>
                      <Loader2
                        size={17}
                        className="animate-spin"
                      />

                      Réservation…
                    </>
                  ) : (
                    <>
                      Réserver mon appel

                      <ArrowRight
                        size={17}
                      />
                    </>
                  )}
                </button>

                <p className="mt-4 text-xs text-black/35">
                  Rendez-vous téléphonique
                  gratuit et sans engagement.
                </p>
              </form>
            </div>
          </div>
        </div>
      </section>
    </>
  );
}

function Field({
  label,
  name,
  type = "text",
  required = false,
}: {
  label: string;
  name: string;
  type?: string;
  required?: boolean;
}) {
  return (
    <div>
      <label
        htmlFor={name}
        className="mb-2 block text-sm font-semibold"
      >
        {label}

        {!required && (
          <span className="ml-1 font-normal text-black/35">
            (facultatif)
          </span>
        )}
      </label>

      <input
        id={name}
        name={name}
        type={type}
        required={required}
        className="w-full rounded-2xl border border-black/10 bg-white px-4 py-3.5 outline-none transition focus:border-[#c8a45d]"
      />
    </div>
  );
}
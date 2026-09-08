import {
  useEffect,
  useMemo,
  useState,
} from "react";

import {
  ArrowRight,
  CalendarDays,
  Check,
  ChevronLeft,
  ChevronRight,
  Clock3,
  Loader2,
  Phone,
} from "lucide-react";

import SEO from "../components/SEO";

type Slot = {
  time: string;
  available: boolean;
};

type BookingResponse = {
  success?: boolean;
  message?: string;
  slots?: Slot[];
};

const WEEK_DAYS = [
  "dimanche",
  "lundi",
  "mardi",
  "mercredi",
  "jeudi",
  "vendredi",
  "samedi",
];

const MONTHS = [
  "janvier",
  "février",
  "mars",
  "avril",
  "mai",
  "juin",
  "juillet",
  "août",
  "septembre",
  "octobre",
  "novembre",
  "décembre",
];

function formatDate(
  value: Date,
) {
  return `${value.getFullYear()}-${String(
    value.getMonth() + 1,
  ).padStart(
    2,
    "0",
  )}-${String(
    value.getDate(),
  ).padStart(2, "0")}`;
}

function formatLongDate(
  value: Date,
) {
  return `${WEEK_DAYS[value.getDay()]} ${value.getDate()} ${
    MONTHS[value.getMonth()]
  } ${value.getFullYear()}`;
}

function startOfDay(
  value: Date,
) {
  const date = new Date(value);
  date.setHours(
    0,
    0,
    0,
    0,
  );
  return date;
}

function addDays(
  value: Date,
  amount: number,
) {
  const date = new Date(value);
  date.setDate(
    date.getDate() + amount,
  );
  return date;
}

function sameDate(
  a: Date,
  b: Date,
) {
  return (
    a.getFullYear() ===
      b.getFullYear() &&
    a.getMonth() ===
      b.getMonth() &&
    a.getDate() ===
      b.getDate()
  );
}

export default function Booking() {
  const today = startOfDay(
    new Date(),
  );

  const [selectedDate, setSelectedDate] =
    useState<Date>(today);

  const [month, setMonth] =
    useState<Date>(
      new Date(
        today.getFullYear(),
        today.getMonth(),
        1,
      ),
    );

  const [slots, setSlots] =
    useState<Slot[]>([]);

  const [selectedTime, setSelectedTime] =
    useState("");

  const [loading, setLoading] =
    useState(false);

  const [bookingLoading, setBookingLoading] =
    useState(false);

  const [error, setError] =
    useState("");

  const [success, setSuccess] =
    useState(false);

  const [form, setForm] =
    useState({
      name: "",
      company: "",
      phone: "",
      email: "",
      reason: "",
    });

  const firstDay = new Date(
    month.getFullYear(),
    month.getMonth(),
    1,
  );

  const lastDay = new Date(
    month.getFullYear(),
    month.getMonth() + 1,
    0,
  );

  const calendarDays = useMemo(() => {
    const days: Array<
      Date | null
    > = [];

    const offset =
      (firstDay.getDay() + 6) %
      7;

    for (
      let index = 0;
      index < offset;
      index += 1
    ) {
      days.push(null);
    }

    for (
      let day = 1;
      day <= lastDay.getDate();
      day += 1
    ) {
      days.push(
        new Date(
          month.getFullYear(),
          month.getMonth(),
          day,
        ),
      );
    }

    return days;
  }, [
    month,
    firstDay,
    lastDay,
  ]);

  async function loadSlots(
    date: Date,
  ) {
    setLoading(true);
    setError("");
    setSelectedTime("");
    setSlots([]);

    try {
      const response = await fetch(
        `/booking.php?action=slots&date=${formatDate(
          date,
        )}&ts=${Date.now()}`,
        {
          cache: "no-store",
        },
      );

      const json: BookingResponse =
        await response.json();

      if (
        !response.ok ||
        !json.success
      ) {
        throw new Error(
          json.message ||
            "Impossible de charger les créneaux.",
        );
      }

      setSlots(
        Array.isArray(json.slots)
          ? json.slots
          : [],
      );
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Impossible de charger les créneaux.",
      );
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadSlots(selectedDate);
  }, [selectedDate]);

  function updateForm(
    key: keyof typeof form,
    value: string,
  ) {
    setForm((current) => ({
      ...current,
      [key]: value,
    }));
  }

  async function submitBooking(
    event: React.FormEvent,
  ) {
    event.preventDefault();

    if (!selectedTime) {
      setError(
        "Choisissez d'abord un créneau.",
      );
      return;
    }

    if (!form.name.trim()) {
      setError(
        "Veuillez indiquer votre nom.",
      );
      return;
    }

    if (!form.phone.trim()) {
      setError(
        "Veuillez indiquer votre numéro de téléphone.",
      );
      return;
    }

    setBookingLoading(true);
    setError("");

    try {
      const response = await fetch(
        "/booking.php",
        {
          method: "POST",
          headers: {
            "Content-Type":
              "application/json",
          },
          body: JSON.stringify({
            action: "book",
            date: formatDate(
              selectedDate,
            ),
            time: selectedTime,
            name: form.name.trim(),
            company:
              form.company.trim(),
            phone: form.phone.trim(),
            email:
              form.email.trim(),
            reason:
              form.reason.trim(),
          }),
        },
      );

      const json =
        await response.json();

      if (
        !response.ok ||
        !json.success
      ) {
        throw new Error(
          json.message ||
            "Impossible de confirmer le rendez-vous.",
        );
      }

      setSuccess(true);
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Impossible de confirmer le rendez-vous.",
      );

      await loadSlots(
        selectedDate,
      );
    } finally {
      setBookingLoading(false);
    }
  }

  function previousMonth() {
    const previous = new Date(
      month.getFullYear(),
      month.getMonth() - 1,
      1,
    );

    const minimum = new Date(
      today.getFullYear(),
      today.getMonth(),
      1,
    );

    if (
      previous.getTime() <
      minimum.getTime()
    ) {
      return;
    }

    setMonth(previous);
  }

  function nextMonth() {
    const maximum = new Date(
      today.getFullYear(),
      today.getMonth() + 2,
      0,
    );

    const next = new Date(
      month.getFullYear(),
      month.getMonth() + 1,
      1,
    );

    if (
      next.getTime() >
      new Date(
        maximum.getFullYear(),
        maximum.getMonth(),
        1,
      ).getTime()
    ) {
      return;
    }

    setMonth(next);
  }

  if (success) {
    return (
      <>
        <SEO
          title="Rendez-vous confirmé — Vitrine+"
          description="Votre rendez-vous avec Vitrine+ est confirmé."
          canonical="/rendez-vous"
        />

        <main className="min-h-screen bg-[#080808] px-6 py-20 text-white">
          <div className="mx-auto max-w-2xl pt-20 text-center">
            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-[#c8a45d] text-black">
              <Check size={28} />
            </div>

            <div className="mt-8 text-[10px] font-extrabold uppercase tracking-[.3em] text-[#c8a45d]">
              Rendez-vous confirmé
            </div>

            <h1 className="mt-5 text-4xl font-extrabold tracking-tight sm:text-6xl">
              À très bientôt.
            </h1>

            <p className="mx-auto mt-6 max-w-xl text-sm leading-7 text-white/50">
              Votre demande de rendez-vous a bien été enregistrée. Nous vous appellerons au numéro indiqué.
            </p>

            <div className="mx-auto mt-8 max-w-md rounded-[2rem] border border-white/10 bg-white/[.04] p-6 text-left">
              <div className="flex items-center gap-3">
                <CalendarDays
                  size={20}
                  className="text-[#c8a45d]"
                />

                <div>
                  <div className="font-extrabold">
                    {formatLongDate(
                      selectedDate,
                    )}
                  </div>

                  <div className="mt-1 text-sm text-white/40">
                    {selectedTime}
                  </div>
                </div>
              </div>

              <div className="mt-5 flex items-center gap-3 text-sm text-white/50">
                <Phone
                  size={17}
                  className="text-[#c8a45d]"
                />

                {form.phone}
              </div>
            </div>

            <a
              href="/"
              className="mt-8 inline-flex items-center gap-2 rounded-full bg-[#c8a45d] px-7 py-4 text-sm font-extrabold text-black transition hover:bg-white"
            >
              Retour au site
              <ArrowRight size={17} />
            </a>
          </div>
        </main>
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

      <main className="min-h-screen bg-[#f4f4f1] px-5 pb-20 pt-28 text-[#080808] sm:px-8 lg:px-12">
        <div className="mx-auto max-w-7xl">
          <div className="max-w-3xl">
            <div className="text-[10px] font-extrabold uppercase tracking-[.3em] text-[#a17e32]">
              Vitrine+
            </div>

            <h1 className="mt-5 text-4xl font-extrabold tracking-tight sm:text-6xl">
              Prendre rendez-vous.
            </h1>

            <p className="mt-5 max-w-2xl text-base leading-7 text-black/50">
              Choisissez un créneau pour échanger directement sur votre entreprise, votre site et vos objectifs digitaux.
            </p>
          </div>

          <div className="mt-12 grid gap-8 lg:grid-cols-[1.05fr_.95fr]">
            <section className="rounded-[2rem] border border-black/10 bg-white p-5 sm:p-8">
              <div className="flex items-center justify-between">
                <div>
                  <div className="text-xs font-extrabold uppercase tracking-[.15em] text-black/35">
                    Choisir une date
                  </div>

                  <div className="mt-2 text-xl font-extrabold">
                    {MONTHS[
                      month.getMonth()
                    ]}{" "}
                    {month.getFullYear()}
                  </div>
                </div>

                <div className="flex gap-2">
                  <button
                    onClick={
                      previousMonth
                    }
                    className="rounded-full border border-black/10 p-2 transition hover:bg-black hover:text-white"
                  >
                    <ChevronLeft
                      size={18}
                    />
                  </button>

                  <button
                    onClick={
                      nextMonth
                    }
                    className="rounded-full border border-black/10 p-2 transition hover:bg-black hover:text-white"
                  >
                    <ChevronRight
                      size={18}
                    />
                  </button>
                </div>
              </div>

              <div className="mt-7 grid grid-cols-7 gap-1 text-center text-[10px] font-extrabold uppercase tracking-wider text-black/30">
                <span>L</span>
                <span>M</span>
                <span>M</span>
                <span>J</span>
                <span>V</span>
                <span>S</span>
                <span>D</span>
              </div>

              <div className="mt-3 grid grid-cols-7 gap-1">
                {calendarDays.map(
                  (date, index) => {
                    if (!date) {
                      return (
                        <div
                          key={`empty-${index}`}
                          className="aspect-square"
                        />
                      );
                    }

                    const isPast =
                      startOfDay(
                        date,
                      ).getTime() <
                      today.getTime();

                    const isSelected =
                      sameDate(
                        date,
                        selectedDate,
                      );

                    const isSunday =
                      date.getDay() ===
                      0;

                    const disabled =
                      isPast ||
                      isSunday;

                    return (
                      <button
                        key={formatDate(
                          date,
                        )}
                        disabled={
                          disabled
                        }
                        onClick={() =>
                          setSelectedDate(
                            date,
                          )
                        }
                        className={`aspect-square rounded-xl text-sm font-bold transition ${
                          isSelected
                            ? "bg-[#080808] text-white"
                            : disabled
                              ? "cursor-not-allowed text-black/15"
                              : "hover:bg-[#c8a45d]/20"
                        }`}
                      >
                        {date.getDate()}
                      </button>
                    );
                  },
                )}
              </div>

              <div className="mt-8 border-t border-black/10 pt-7">
                <div className="flex items-center gap-3">
                  <CalendarDays
                    size={18}
                    className="text-[#c8a45d]"
                  />

                  <div>
                    <div className="text-xs font-extrabold uppercase tracking-[.15em] text-black/35">
                      Date sélectionnée
                    </div>

                    <div className="mt-1 font-extrabold capitalize">
                      {formatLongDate(
                        selectedDate,
                      )}
                    </div>
                  </div>
                </div>

                <div className="mt-6">
                  <div className="flex items-center gap-3">
                    <Clock3
                      size={18}
                      className="text-[#c8a45d]"
                    />

                    <div className="text-xs font-extrabold uppercase tracking-[.15em] text-black/35">
                      Créneaux disponibles
                    </div>
                  </div>

                  {loading ? (
                    <div className="flex justify-center py-12">
                      <Loader2
                        size={24}
                        className="animate-spin text-[#c8a45d]"
                      />
                    </div>
                  ) : slots.filter(
                      (slot) =>
                        slot.available,
                    ).length ===
                    0 ? (
                    <div className="mt-4 rounded-2xl border border-black/10 bg-black/[.02] p-5 text-sm text-black/45">
                      Aucun créneau disponible pour cette date.
                    </div>
                  ) : (
                    <div className="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3">
                      {slots
                        .filter(
                          (slot) =>
                            slot.available,
                        )
                        .map(
                          (slot) => (
                            <button
                              key={
                                slot.time
                              }
                              onClick={() =>
                                setSelectedTime(
                                  slot.time,
                                )
                              }
                              className={`rounded-xl border px-4 py-3 text-sm font-bold transition ${
                                selectedTime ===
                                slot.time
                                  ? "border-[#c8a45d] bg-[#c8a45d] text-black"
                                  : "border-black/10 bg-white hover:border-[#c8a45d]"
                              }`}
                            >
                              {slot.time}
                            </button>
                          ),
                        )}
                    </div>
                  )}
                </div>
              </div>
            </section>

            <section className="rounded-[2rem] bg-[#080808] p-6 text-white sm:p-8">
              <div className="text-[10px] font-extrabold uppercase tracking-[.3em] text-[#c8a45d]">
                Vos coordonnées
              </div>

              <h2 className="mt-4 text-3xl font-extrabold">
                Parlons de votre projet.
              </h2>

              <p className="mt-4 text-sm leading-7 text-white/45">
                Nous vous appelons directement au créneau choisi.
              </p>

              <form
                onSubmit={
                  submitBooking
                }
                className="mt-8 space-y-4"
              >
                <Field
                  label="Nom *"
                  value={form.name}
                  onChange={(value) =>
                    updateForm(
                      "name",
                      value,
                    )
                  }
                />

                <Field
                  label="Entreprise"
                  value={
                    form.company
                  }
                  onChange={(value) =>
                    updateForm(
                      "company",
                      value,
                    )
                  }
                />

                <Field
                  label="Téléphone *"
                  type="tel"
                  inputMode="tel"
                  value={
                    form.phone
                  }
                  onChange={(value) =>
                    updateForm(
                      "phone",
                      value,
                    )
                  }
                />

                <Field
                  label="E-mail"
                  type="text"
                  inputMode="email"
                  autoComplete="email"
                  value={
                    form.email
                  }
                  onChange={(value) =>
                    updateForm(
                      "email",
                      value,
                    )
                  }
                />

                <label className="block">
                  <span className="mb-2 block text-xs font-bold text-white/45">
                    Sujet
                  </span>

                  <textarea
                    value={
                      form.reason
                    }
                    onChange={(
                      event,
                    ) =>
                      updateForm(
                        "reason",
                        event.target
                          .value,
                      )
                    }
                    rows={4}
                    placeholder="Décrivez rapidement votre projet..."
                    className="w-full rounded-2xl border border-white/10 bg-white/[.04] px-4 py-3 text-sm text-white outline-none placeholder:text-white/20 focus:border-[#c8a45d]"
                  />
                </label>

                {error ? (
                  <div className="rounded-xl border border-red-400/20 bg-red-400/10 px-4 py-3 text-xs font-bold text-red-200">
                    {error}
                  </div>
                ) : null}

                <button
                  type="submit"
                  disabled={
                    bookingLoading ||
                    !selectedTime
                  }
                  className="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-full bg-[#c8a45d] px-6 py-4 text-sm font-extrabold text-black transition hover:bg-white disabled:cursor-not-allowed disabled:opacity-40"
                >
                  {bookingLoading ? (
                    <>
                      <Loader2
                        size={17}
                        className="animate-spin"
                      />
                      Confirmation...
                    </>
                  ) : (
                    <>
                      Confirmer le rendez-vous
                      <ArrowRight
                        size={17}
                      />
                    </>
                  )}
                </button>

                <p className="text-center text-[11px] leading-5 text-white/25">
                  En confirmant, vous demandez simplement à être rappelé au créneau sélectionné.
                </p>
              </form>
            </section>
          </div>
        </div>
      </main>
    </>
  );
}

function Field({
  label,
  value,
  onChange,
  type = "text",
  inputMode,
  autoComplete,
}: {
  label: string;
  value: string;
  onChange: (
    value: string,
  ) => void;
  type?: string;
  inputMode?:
    | "email"
    | "tel"
    | "text";
  autoComplete?: string;
}) {
  return (
    <label className="block">
      <span className="mb-2 block text-xs font-bold text-white/45">
        {label}
      </span>

      <input
        type={type}
        inputMode={inputMode}
        autoComplete={
          autoComplete
        }
        value={value}
        onChange={(event) =>
          onChange(
            event.target.value,
          )
        }
        className="w-full rounded-2xl border border-white/10 bg-white/[.04] px-4 py-3 text-sm text-white outline-none placeholder:text-white/20 focus:border-[#c8a45d]"
      />
    </label>
  );
}
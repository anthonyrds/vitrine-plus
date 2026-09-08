import { useEffect, useMemo, useState } from "react";

import {
  Activity,
  BarChart3,
  CalendarDays,
  Check,
  ChevronRight,
  CircleDollarSign,
  Clock3,
  Gift,
  LayoutDashboard,
  LogOut,
  Mail,
  Menu,
  Phone,
  Plus,
  Search,
  Target,
  TrendingUp,
  Users,
  X,
} from "lucide-react";

type Interaction = {
  id: string;
  created_at: string;
  type: string;
  text: string;
};

type Prospect = {
  id: string;
  source: string;
  created_at: string;

  name: string;
  company: string;
  email: string;
  phone: string;
  website: string;

  audit_score?: number | null;
  recommendations?: string[];

  marketing_consent?: boolean;
  grand_plus_status?: string;
  month_key?: string;

  booking_reference?: string;
  booking_date?: string;
  booking_time?: string;
  booking_status?: string;

  reason?: string;
  sector?: string;
  problem?: string;

  status: string;
  offer: string;

  estimated_value: number;
  recurring_value: number;

  last_contact_at: string;
  next_action: string;
  next_action_at: string;

  notes: string;

  interactions: Interaction[];
};

type Booking = {
  reference: string;
  created_at: string;
  date: string;
  time: string;
  duration?: number;
  name: string;
  phone: string;
  company: string;
  email?: string;
  reason: string;
  status: string;
};

type Block = {
  id: string;
  date: string;
  all_day: boolean;
  start_time: string | null;
  end_time: string | null;
  reason: string;
  created_at?: string;
};

type Grand = {
  id?: string;
  month_key?: string;
  month_label?: string;
  name?: string;
  company?: string;
  email?: string;
  phone?: string;
  website?: string;
  marketing_consent?: boolean;
  status?: string;
};

type Data = {
  success: boolean;
  generated_at: string;

  stats: Record<string, number>;
  sources: Record<string, number>;
  pipeline: Record<string, number>;

  prospects: Prospect[];
  bookings: Booking[];
  grand_plus: Grand[];
};

const EMPTY: Data = {
  success: true,
  generated_at: "",

  stats: {
    prospects: 0,
    new: 0,
    qualified: 0,
    meetings: 0,
    won: 0,
    lost: 0,
    signed_revenue: 0,
    potential_revenue: 0,
    today_actions: 0,
    overdue_actions: 0,
    upcoming_bookings: 0,
    confirmed_bookings: 0,
  },

  sources: {},
  pipeline: {},

  prospects: [],
  bookings: [],
  grand_plus: [],
};

const STATUSES = [
  "new",
  "contacted",
  "qualified",
  "meeting",
  "proposal",
  "negotiation",
  "won",
  "lost",
];

const STATUS_LABEL: Record<string, string> = {
  new: "Nouveau",
  contacted: "Contacté",
  qualified: "Qualifié",
  meeting: "Rendez-vous",
  proposal: "Proposition",
  negotiation: "Négociation",
  won: "Gagné",
  lost: "Perdu",
};

const SOURCE_LABEL: Record<string, string> = {
  booking: "Rendez-vous",
  audit: "Audit",
  contact: "Contact",
  "grand-plus": "Grand+",
  manual: "Manuel",
};

function euro(value: number) {
  return new Intl.NumberFormat("fr-FR", {
    style: "currency",
    currency: "EUR",
    maximumFractionDigits: 0,
  }).format(Number(value) || 0);
}

function frDate(value: string) {
  if (!value) return "—";

  const date = new Date(
    value.includes("T") ? value : `${value}T12:00:00`,
  );

  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return date.toLocaleDateString("fr-FR", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
  });
}

function frDateTime(value: string) {
  if (!value) return "—";

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return date.toLocaleString("fr-FR", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

function statusClass(status: string) {
  if (status === "won") {
    return "bg-emerald-100 text-emerald-700";
  }

  if (status === "lost") {
    return "bg-red-100 text-red-700";
  }

  if (status === "meeting") {
    return "bg-blue-100 text-blue-700";
  }

  if (status === "proposal") {
    return "bg-purple-100 text-purple-700";
  }

  if (status === "negotiation") {
    return "bg-orange-100 text-orange-700";
  }

  if (status === "qualified") {
    return "bg-[#c8a45d]/20 text-[#85671f]";
  }

  return "bg-black/5 text-black/60";
}

function Badge({
  status,
}: {
  status: string;
}) {
  return (
    <span
      className={`rounded-full px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-[.1em] ${statusClass(
        status,
      )}`}
    >
      {STATUS_LABEL[status] ?? status}
    </span>
  );
}

export default function AdminDashboard() {
  const [data, setData] = useState<Data>(EMPTY);
  const [loading, setLoading] = useState(true);

  const [section, setSection] = useState("dashboard");

  const [selected, setSelected] =
    useState<Prospect | null>(null);

  const [query, setQuery] = useState("");
  const [source, setSource] = useState("all");
  const [status, setStatus] = useState("all");

  const [toast, setToast] = useState("");
  const [newOpen, setNewOpen] = useState(false);
  const [mobile, setMobile] = useState(false);

  const [blocks, setBlocks] = useState<Block[]>([]);
  const [blockDate, setBlockDate] = useState("");
  const [allDay, setAllDay] = useState(true);
  const [start, setStart] = useState("09:00");
  const [end, setEnd] = useState("18:00");
  const [reason, setReason] = useState("Indisponible");

  async function load() {
    setLoading(true);

    try {
      const response = await fetch(
        `/crm-api.php?ts=${Date.now()}`,
        {
          cache: "no-store",
          credentials: "same-origin",
        },
      );

      if (response.status === 401) {
        window.location.href =
          "/grand-plus-admin.php";
        return;
      }

      const json = await response.json();

      if (!response.ok || !json.success) {
        throw new Error(
          json.message ||
            "Impossible de charger l'administration.",
        );
      }

      setData(json);

      if (selected) {
        setSelected(
          (json.prospects as Prospect[]).find(
            (prospect) =>
              prospect.id === selected.id,
          ) ?? null,
        );
      }
    } catch (error) {
      setToast(
        error instanceof Error
          ? error.message
          : "Erreur de chargement.",
      );
    } finally {
      setLoading(false);
    }
  }

  async function loadBlocks() {
    try {
      const response = await fetch(
        `/availability.php?action=list&ts=${Date.now()}`,
        {
          cache: "no-store",
          credentials: "same-origin",
        },
      );

      if (response.status === 401) {
        window.location.href =
          "/grand-plus-admin.php";
        return;
      }

      const json = await response.json();

      if (!response.ok || !json.success) {
        throw new Error(
          json.message ||
            "Impossible de charger les indisponibilités.",
        );
      }

      setBlocks(
        Array.isArray(json.blocks)
          ? json.blocks
          : [],
      );
    } catch (error) {
      setToast(
        error instanceof Error
          ? error.message
          : "Erreur de chargement.",
      );
    }
  }

  async function api(
    payload: Record<string, unknown>,
  ) {
    const response = await fetch(
      "/crm-api.php",
      {
        method: "POST",
        headers: {
          "Content-Type":
            "application/json",
        },
        credentials: "same-origin",
        body: JSON.stringify(payload),
      },
    );

    if (response.status === 401) {
      window.location.href =
        "/grand-plus-admin.php";
      return;
    }

    const json = await response.json();

    if (!response.ok || !json.success) {
      throw new Error(
        json.message ||
          "Action impossible.",
      );
    }

    await load();

    return json;
  }

  async function availability(
    payload: Record<string, unknown>,
  ) {
    const response = await fetch(
      "/availability.php",
      {
        method: "POST",
        headers: {
          "Content-Type":
            "application/json",
        },
        credentials: "same-origin",
        body: JSON.stringify(payload),
      },
    );

    if (response.status === 401) {
      window.location.href =
        "/grand-plus-admin.php";
      return;
    }

    const json = await response.json();

    if (!response.ok || !json.success) {
      throw new Error(
        json.message ||
          "Action impossible.",
      );
    }

    setBlocks(
      Array.isArray(json.blocks)
        ? json.blocks
        : [],
    );
  }

  useEffect(() => {
    load();
    loadBlocks();
  }, []);

  useEffect(() => {
    if (!toast) {
      return;
    }

    const timer = setTimeout(
      () => setToast(""),
      3500,
    );

    return () => clearTimeout(timer);
  }, [toast]);

  const filtered = useMemo(() => {
    const search =
      query.trim().toLowerCase();

    return data.prospects.filter(
      (prospect) => {
        const haystack = [
          prospect.name,
          prospect.company,
          prospect.email,
          prospect.phone,
          prospect.website,
          prospect.source,
          prospect.sector,
          prospect.reason,
        ]
          .join(" ")
          .toLowerCase();

        return (
          (!search ||
            haystack.includes(search)) &&
          (source === "all" ||
            prospect.source === source) &&
          (status === "all" ||
            prospect.status === status)
        );
      },
    );
  }, [
    data.prospects,
    query,
    source,
    status,
  ]);

  const upcoming = useMemo(
    () =>
      [...data.bookings]
        .filter(
          (booking) =>
            booking.status !==
            "cancelled",
        )
        .sort((a, b) =>
          `${a.date} ${a.time}`.localeCompare(
            `${b.date} ${b.time}`,
          ),
        ),
    [data.bookings],
  );

  const menu = [
    [
      "dashboard",
      "Vue d'ensemble",
      LayoutDashboard,
    ],
    [
      "prospects",
      "Prospects",
      Users,
    ],
    [
      "pipeline",
      "Pipeline",
      Target,
    ],
    [
      "calendar",
      "Rendez-vous",
      CalendarDays,
    ],
    [
      "availability",
      "Disponibilités",
      Clock3,
    ],
    [
      "grand-plus",
      "Grand+",
      Gift,
    ],
    [
      "stats",
      "Statistiques",
      BarChart3,
    ],
  ] as const;

  function navigate(value: string) {
    setSection(value);
    setMobile(false);
  }

  async function saveProspect(
    payload: Record<string, unknown>,
  ) {
    if (!selected) {
      return;
    }

    try {
      await api({
        action: "update_prospect",
        ...payload,
        id: selected.id,
      });

      setToast(
        "Prospect enregistré.",
      );
    } catch (error) {
      setToast(
        error instanceof Error
          ? error.message
          : "Erreur.",
      );
    }
  }

  async function addInteraction(
    text: string,
    type: string,
  ) {
    if (!selected) {
      return;
    }

    try {
      await api({
        action: "add_interaction",
        id: selected.id,
        text,
        type,
      });

      setToast(
        "Interaction ajoutée.",
      );
    } catch (error) {
      setToast(
        error instanceof Error
          ? error.message
          : "Erreur.",
      );
    }
  }

  async function addBlock() {
    if (!blockDate) {
      setToast(
        "Choisissez une date.",
      );
      return;
    }

    if (
      !allDay &&
      start >= end
    ) {
      setToast(
        "L'heure de début doit être avant l'heure de fin.",
      );
      return;
    }

    try {
      await availability({
        action: "add_block",
        date: blockDate,
        all_day: allDay,
        start_time: allDay
          ? null
          : start,
        end_time: allDay
          ? null
          : end,
        reason:
          reason.trim() ||
          "Indisponible",
      });

      setToast(
        "Indisponibilité ajoutée.",
      );

      setBlockDate("");
      setAllDay(true);
      setStart("09:00");
      setEnd("18:00");
      setReason("Indisponible");
    } catch (error) {
      setToast(
        error instanceof Error
          ? error.message
          : "Erreur.",
      );
    }
  }

  async function deleteBlock(
    id: string,
  ) {
    if (
      !window.confirm(
        "Supprimer cette indisponibilité ?",
      )
    ) {
      return;
    }

    try {
      await availability({
        action: "delete_block",
        id,
      });

      setToast(
        "Indisponibilité supprimée.",
      );
    } catch (error) {
      setToast(
        error instanceof Error
          ? error.message
          : "Erreur.",
      );
    }
  }

  return (
    <div className="min-h-screen bg-[#f4f4f1] text-[#080808]">
      <header className="sticky top-0 z-30 border-b border-black/10 bg-[#f4f4f1]/95 backdrop-blur">
        <div className="flex h-16 items-center justify-between px-5 lg:px-8">
          <div className="flex items-center gap-3">
            <button
              onClick={() =>
                setMobile(true)
              }
              className="rounded-full border border-black/10 p-2 lg:hidden"
            >
              <Menu size={18} />
            </button>

            <div>
              <div className="text-[10px] font-extrabold uppercase tracking-[.25em] text-black/35">
                Vitrine+
              </div>

              <div className="font-extrabold">
                Administration commerciale
              </div>
            </div>
          </div>

          <div className="flex items-center gap-2">
            <button
              onClick={() => {
                load();
                loadBlocks();
              }}
              className="hidden rounded-full border border-black/10 bg-white px-4 py-2 text-xs font-bold sm:block"
            >
              Actualiser
            </button>

            <a
              href="/grand-plus-admin.php?logout=1"
              className="rounded-full border border-black/10 bg-white p-2"
            >
              <LogOut size={16} />
            </a>
          </div>
        </div>
      </header>

      <div className="flex">
        <aside
          className={`${
            mobile
              ? "fixed inset-0 z-50"
              : "hidden"
          } w-72 shrink-0 border-r border-black/10 bg-[#080808] text-white lg:sticky lg:top-16 lg:block lg:h-[calc(100vh-4rem)]`}
        >
          <div className="flex h-full flex-col p-4">
            <div className="mb-6 flex items-center justify-between px-3 pt-2">
              <span className="text-xs font-extrabold uppercase tracking-[.25em] text-white/40">
                V+ Admin
              </span>

              <button
                onClick={() =>
                  setMobile(false)
                }
                className="lg:hidden"
              >
                <X size={18} />
              </button>
            </div>

            <nav className="space-y-1">
              {menu.map(
                ([
                  key,
                  label,
                  Icon,
                ]) => (
                  <button
                    key={key}
                    onClick={() =>
                      navigate(key)
                    }
                    className={`flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left text-sm font-bold transition ${
                      section === key
                        ? "bg-[#c8a45d] text-black"
                        : "text-white/65 hover:bg-white/5 hover:text-white"
                    }`}
                  >
                    <Icon size={17} />

                    {label}

                    {key ===
                      "calendar" &&
                    data.stats
                      .upcoming_bookings >
                      0 ? (
                      <span className="ml-auto rounded-full bg-white/10 px-2 py-0.5 text-[10px]">
                        {
                          data.stats
                            .upcoming_bookings
                        }
                      </span>
                    ) : null}
                  </button>
                ),
              )}
            </nav>

            <div className="mt-auto rounded-2xl border border-white/10 bg-white/[.04] p-4 text-xs text-white/45">
              Les données sont lues directement depuis les fichiers réels du site.

              <br />

              <span className="text-white/70">
                Dernière synchro :{" "}
                {data.generated_at
                  ? frDateTime(
                      data.generated_at,
                    )
                  : "—"}
              </span>
            </div>
          </div>
        </aside>

        <main className="min-w-0 flex-1 p-5 lg:p-8">
          {loading ? (
            <div className="flex min-h-[50vh] items-center justify-center">
              <div className="h-8 w-8 animate-spin rounded-full border-2 border-black/10 border-t-[#c8a45d]" />
            </div>
          ) : section ===
            "dashboard" ? (
            <Dashboard
              data={data}
              upcoming={upcoming}
              onOpen={setSelected}
              onNavigate={navigate}
            />
          ) : section ===
            "prospects" ? (
            <Prospects
              data={data}
              filtered={filtered}
              query={query}
              setQuery={setQuery}
              source={source}
              setSource={setSource}
              status={status}
              setStatus={setStatus}
              onOpen={setSelected}
              onNew={() =>
                setNewOpen(true)
              }
            />
          ) : section ===
            "pipeline" ? (
            <Pipeline
              data={data}
              onOpen={setSelected}
              onMove={async (
                id,
                newStatus,
              ) => {
                try {
                  await api({
                    action:
                      "update_prospect",
                    id,
                    status:
                      newStatus,
                  });

                  setToast(
                    "Statut mis à jour.",
                  );
                } catch (error) {
                  setToast(
                    error instanceof Error
                      ? error.message
                      : "Erreur.",
                  );
                }
              }}
            />
          ) : section ===
            "calendar" ? (
            <Calendar
              bookings={upcoming}
              onOpen={(booking) => {
                const prospect =
                  data.prospects.find(
                    (item) =>
                      item.booking_reference ===
                      booking.reference,
                  );

                if (prospect) {
                  setSelected(
                    prospect,
                  );
                }
              }}
              onRefresh={load}
            />
          ) : section ===
            "availability" ? (
            <Availability
              blocks={blocks}
              date={blockDate}
              setDate={setBlockDate}
              allDay={allDay}
              setAllDay={setAllDay}
              start={start}
              setStart={setStart}
              end={end}
              setEnd={setEnd}
              reason={reason}
              setReason={setReason}
              onAdd={addBlock}
              onDelete={deleteBlock}
            />
          ) : section ===
            "grand-plus" ? (
            <GrandPlus
              data={data.grand_plus}
            />
          ) : (
            <Stats data={data} />
          )}
        </main>
      </div>

      {selected ? (
        <ProspectModal
          prospect={selected}
          onClose={() =>
            setSelected(null)
          }
          onSave={saveProspect}
          onInteraction={
            addInteraction
          }
        />
      ) : null}

      {newOpen ? (
        <NewProspect
          onClose={() =>
            setNewOpen(false)
          }
          api={api}
          onCreated={() => {
            setNewOpen(false);
            setSection("prospects");
            setToast(
              "Prospect créé.",
            );
          }}
        />
      ) : null}

      {toast ? (
        <div className="fixed bottom-5 right-5 z-[100] max-w-sm rounded-2xl bg-[#080808] px-5 py-4 text-sm font-bold text-white shadow-2xl">
          {toast}
        </div>
      ) : null}
    </div>
  );
}

function Dashboard({
  data,
  upcoming,
  onOpen,
  onNavigate,
}: {
  data: Data;
  upcoming: Booking[];
  onOpen: (prospect: Prospect) => void;
  onNavigate: (section: string) => void;
}) {
  const recent = data.prospects.slice(
    0,
    6,
  );

  return (
    <div className="space-y-6">
      <Title
        eyebrow="Vue d'ensemble"
        title="Pilotez votre activité."
        text="Votre activité commerciale, vos prospects et vos rendez-vous au même endroit."
      />

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <Metric
          icon={Users}
          label="Prospects"
          value={String(
            data.stats.prospects ?? 0,
          )}
        />

        <Metric
          icon={Target}
          label="Nouveaux"
          value={String(
            data.stats.new ?? 0,
          )}
        />

        <Metric
          icon={CalendarDays}
          label="Rendez-vous"
          value={String(
            data.stats.upcoming_bookings ??
              0,
          )}
        />

        <Metric
          icon={CircleDollarSign}
          label="CA potentiel"
          value={euro(
            data.stats.potential_revenue ??
              0,
          )}
        />
      </div>

      <div className="grid gap-6 xl:grid-cols-[1.4fr_.6fr]">
        <Panel
          title="Prospects récents"
          action={
            <button
              onClick={() =>
                onNavigate("prospects")
              }
              className="text-xs font-extrabold text-[#a17e32]"
            >
              Voir tout
            </button>
          }
        >
          <div className="divide-y divide-black/10">
            {recent.map(
              (prospect) => (
                <button
                  key={prospect.id}
                  onClick={() =>
                    onOpen(prospect)
                  }
                  className="flex w-full items-center justify-between gap-4 py-4 text-left"
                >
                  <div className="min-w-0">
                    <div className="truncate font-extrabold">
                      {prospect.name ||
                        "Prospect"}
                    </div>

                    <div className="mt-1 truncate text-xs text-black/40">
                      {prospect.company ||
                        prospect.email ||
                        "—"}
                    </div>
                  </div>

                  <div className="flex shrink-0 items-center gap-3">
                    <Badge
                      status={
                        prospect.status
                      }
                    />
                    <ChevronRight
                      size={16}
                      className="text-black/25"
                    />
                  </div>
                </button>
              ),
            )}

            {recent.length === 0 ? (
              <Empty text="Aucun prospect." />
            ) : null}
          </div>
        </Panel>

        <Panel
          title="Prochains rendez-vous"
          action={
            <button
              onClick={() =>
                onNavigate("calendar")
              }
              className="text-xs font-extrabold text-[#a17e32]"
            >
              Agenda
            </button>
          }
        >
          <div className="space-y-3">
            {upcoming
              .slice(0, 5)
              .map((booking) => (
                <div
                  key={
                    booking.reference
                  }
                  className="rounded-2xl border border-black/10 p-4"
                >
                  <div className="flex items-center justify-between gap-3">
                    <b>
                      {booking.name ||
                        "Client"}
                    </b>

                    <span className="text-xs font-bold text-[#a17e32]">
                      {booking.time}
                    </span>
                  </div>

                  <div className="mt-1 text-xs text-black/45">
                    {frDate(
                      booking.date,
                    )}{" "}
                    ·{" "}
                    {booking.company ||
                      "—"}
                  </div>
                </div>
              ))}

            {upcoming.length === 0 ? (
              <Empty text="Aucun rendez-vous à venir." />
            ) : null}
          </div>
        </Panel>
      </div>
    </div>
  );
}

function Prospects({
  data,
  filtered,
  query,
  setQuery,
  source,
  setSource,
  status,
  setStatus,
  onOpen,
  onNew,
}: {
  data: Data;
  filtered: Prospect[];
  query: string;
  setQuery: (value: string) => void;
  source: string;
  setSource: (value: string) => void;
  status: string;
  setStatus: (value: string) => void;
  onOpen: (prospect: Prospect) => void;
  onNew: () => void;
}) {
  const sourceOptions =
    Object.keys(data.sources);

  return (
    <div className="space-y-6">
      <Title
        eyebrow="CRM"
        title="Prospects"
        text="Tous vos contacts commerciaux issus du site et ajoutés manuellement."
        action={
          <button
            onClick={onNew}
            className="inline-flex items-center gap-2 rounded-full bg-[#080808] px-5 py-3 text-sm font-extrabold text-white"
          >
            <Plus size={16} />
            Nouveau prospect
          </button>
        }
      />

      <Panel title="Recherche et filtres">
        <div className="grid gap-3 lg:grid-cols-[1fr_200px_200px]">
          <div className="relative">
            <Search
              size={16}
              className="absolute left-4 top-1/2 -translate-y-1/2 text-black/30"
            />

            <input
              value={query}
              onChange={(event) =>
                setQuery(
                  event.target.value,
                )
              }
              placeholder="Nom, entreprise, e-mail, téléphone..."
              className="w-full rounded-xl border border-black/10 bg-white py-3 pl-11 pr-4 text-sm outline-none focus:border-[#c8a45d]"
            />
          </div>

          <select
            value={source}
            onChange={(event) =>
              setSource(
                event.target.value,
              )
            }
            className="rounded-xl border border-black/10 bg-white px-4 py-3 text-sm outline-none"
          >
            <option value="all">
              Toutes les sources
            </option>

            {sourceOptions.map(
              (item) => (
                <option
                  key={item}
                  value={item}
                >
                  {SOURCE_LABEL[item] ??
                    item}
                </option>
              ),
            )}
          </select>

          <select
            value={status}
            onChange={(event) =>
              setStatus(
                event.target.value,
              )
            }
            className="rounded-xl border border-black/10 bg-white px-4 py-3 text-sm outline-none"
          >
            <option value="all">
              Tous les statuts
            </option>

            {STATUSES.map(
              (item) => (
                <option
                  key={item}
                  value={item}
                >
                  {STATUS_LABEL[item]}
                </option>
              ),
            )}
          </select>
        </div>
      </Panel>

      <Panel
        title={`${filtered.length} prospect${
          filtered.length > 1
            ? "s"
            : ""
        }`}
      >
        <div className="overflow-x-auto">
          <table className="w-full min-w-[800px]">
            <thead>
              <tr className="border-b border-black/10 text-left text-[10px] font-extrabold uppercase tracking-[.12em] text-black/35">
                <th className="pb-3 pr-4">
                  Prospect
                </th>
                <th className="pb-3 pr-4">
                  Source
                </th>
                <th className="pb-3 pr-4">
                  Statut
                </th>
                <th className="pb-3 pr-4">
                  Valeur
                </th>
                <th className="pb-3">
                  Créé
                </th>
              </tr>
            </thead>

            <tbody>
              {filtered.map(
                (prospect) => (
                  <tr
                    key={prospect.id}
                    onClick={() =>
                      onOpen(prospect)
                    }
                    className="cursor-pointer border-b border-black/5 transition hover:bg-black/[.025]"
                  >
                    <td className="py-4 pr-4">
                      <div className="font-extrabold">
                        {prospect.name ||
                          "Sans nom"}
                      </div>

                      <div className="mt-1 text-xs text-black/40">
                        {prospect.company ||
                          prospect.email ||
                          prospect.phone ||
                          "—"}
                      </div>
                    </td>

                    <td className="py-4 pr-4 text-xs font-bold">
                      {SOURCE_LABEL[
                        prospect.source
                      ] ??
                        prospect.source}
                    </td>

                    <td className="py-4 pr-4">
                      <Badge
                        status={
                          prospect.status
                        }
                      />
                    </td>

                    <td className="py-4 pr-4 text-sm font-extrabold">
                      {euro(
                        prospect.estimated_value ??
                          0,
                      )}
                    </td>

                    <td className="py-4 text-xs text-black/45">
                      {frDate(
                        prospect.created_at,
                      )}
                    </td>
                  </tr>
                ),
              )}
            </tbody>
          </table>

          {filtered.length === 0 ? (
            <Empty text="Aucun prospect ne correspond à votre recherche." />
          ) : null}
        </div>
      </Panel>
    </div>
  );
}

function Pipeline({
  data,
  onOpen,
  onMove,
}: {
  data: Data;
  onOpen: (prospect: Prospect) => void;
  onMove: (
    id: string,
    status: string,
  ) => Promise<void>;
}) {
  return (
    <div className="space-y-6">
      <Title
        eyebrow="Pipeline"
        title="Suivez chaque opportunité."
        text="Déplacez vos prospects d'une étape commerciale à l'autre."
      />

      <div className="grid gap-4 xl:grid-cols-4 2xl:grid-cols-8">
        {STATUSES.map(
          (pipelineStatus) => {
            const prospects =
              data.prospects.filter(
                (prospect) =>
                  prospect.status ===
                  pipelineStatus,
              );

            return (
              <div
                key={pipelineStatus}
                className="min-h-[320px] rounded-[1.5rem] border border-black/10 bg-white p-4"
              >
                <div className="mb-4 flex items-center justify-between">
                  <span className="text-xs font-extrabold">
                    {
                      STATUS_LABEL[
                        pipelineStatus
                      ]
                    }
                  </span>

                  <span className="rounded-full bg-black/5 px-2 py-1 text-[10px] font-bold">
                    {
                      prospects.length
                    }
                  </span>
                </div>

                <div className="space-y-2">
                  {prospects.map(
                    (prospect) => (
                      <div
                        key={
                          prospect.id
                        }
                        className="rounded-xl border border-black/10 p-3"
                      >
                        <button
                          onClick={() =>
                            onOpen(
                              prospect,
                            )
                          }
                          className="w-full text-left"
                        >
                          <div className="font-bold">
                            {prospect.name ||
                              "Prospect"}
                          </div>

                          <div className="mt-1 truncate text-[11px] text-black/40">
                            {prospect.company ||
                              prospect.email ||
                              "—"}
                          </div>

                          <div className="mt-3 text-xs font-extrabold">
                            {euro(
                              prospect.estimated_value ??
                                0,
                            )}
                          </div>
                        </button>

                        <select
                          value={
                            prospect.status
                          }
                          onChange={(event) =>
                            onMove(
                              prospect.id,
                              event.target
                                .value,
                            )
                          }
                          className="mt-3 w-full rounded-lg border border-black/10 bg-white px-2 py-2 text-[11px]"
                        >
                          {STATUSES.map(
                            (item) => (
                              <option
                                key={item}
                                value={
                                  item
                                }
                              >
                                {
                                  STATUS_LABEL[
                                    item
                                  ]
                                }
                              </option>
                            ),
                          )}
                        </select>
                      </div>
                    ),
                  )}

                  {prospects.length ===
                  0 ? (
                    <div className="py-8 text-center text-[11px] text-black/25">
                      Vide
                    </div>
                  ) : null}
                </div>
              </div>
            );
          },
        )}
      </div>
    </div>
  );
}

function Calendar({
  bookings,
  onOpen,
  onRefresh,
}: {
  bookings: Booking[];
  onOpen: (booking: Booking) => void;
  onRefresh: () => void;
}) {
  return (
    <div className="space-y-6">
      <Title
        eyebrow="Agenda"
        title="Rendez-vous"
        text="Les rendez-vous réellement enregistrés par le système de réservation."
        action={
          <button
            onClick={onRefresh}
            className="rounded-full border border-black/10 bg-white px-5 py-3 text-sm font-bold"
          >
            Actualiser
          </button>
        }
      />

      <Panel
        title={`${bookings.length} rendez-vous`}
      >
        <div className="space-y-3">
          {bookings.map(
            (booking) => (
              <button
                key={
                  booking.reference
                }
                onClick={() =>
                  onOpen(booking)
                }
                className="flex w-full flex-col gap-4 rounded-2xl border border-black/10 p-5 text-left transition hover:border-[#c8a45d] sm:flex-row sm:items-center sm:justify-between"
              >
                <div className="flex items-start gap-4">
                  <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[#080808] text-white">
                    <CalendarDays
                      size={20}
                    />
                  </div>

                  <div>
                    <div className="font-extrabold">
                      {booking.name ||
                        "Client"}
                    </div>

                    <div className="mt-1 text-sm text-black/45">
                      {booking.company ||
                        "—"}
                    </div>

                    <div className="mt-2 flex flex-wrap gap-3 text-xs text-black/45">
                      <span>
                        {frDate(
                          booking.date,
                        )}
                      </span>

                      <span>
                        {booking.time}
                      </span>

                      {booking.phone ? (
                        <span>
                          {
                            booking.phone
                          }
                        </span>
                      ) : null}
                    </div>
                  </div>
                </div>

                <div className="flex items-center gap-3">
                  <span
                    className={`rounded-full px-3 py-1 text-[10px] font-extrabold uppercase ${
                      booking.status ===
                      "cancelled"
                        ? "bg-red-100 text-red-700"
                        : "bg-emerald-100 text-emerald-700"
                    }`}
                  >
                    {booking.status ===
                    "cancelled"
                      ? "Annulé"
                      : "Confirmé"}
                  </span>

                  <ChevronRight
                    size={16}
                    className="text-black/25"
                  />
                </div>
              </button>
            ),
          )}

          {bookings.length === 0 ? (
            <Empty text="Aucun rendez-vous." />
          ) : null}
        </div>
      </Panel>
    </div>
  );
}

function Availability({
  blocks,
  date,
  setDate,
  allDay,
  setAllDay,
  start,
  setStart,
  end,
  setEnd,
  reason,
  setReason,
  onAdd,
  onDelete,
}: {
  blocks: Block[];
  date: string;
  setDate: (value: string) => void;
  allDay: boolean;
  setAllDay: (value: boolean) => void;
  start: string;
  setStart: (value: string) => void;
  end: string;
  setEnd: (value: string) => void;
  reason: string;
  setReason: (value: string) => void;
  onAdd: () => Promise<void>;
  onDelete: (id: string) => Promise<void>;
}) {
  const sortedBlocks =
    [...blocks].sort((a, b) =>
      `${a.date}${a.start_time ?? ""}`.localeCompare(
        `${b.date}${b.start_time ?? ""}`,
      ),
    );

  return (
    <div className="space-y-6">
      <Title
        eyebrow="Disponibilités"
        title="Bloquez votre agenda."
        text="Rendez indisponible une journée complète ou seulement une plage horaire. Les créneaux disparaîtront automatiquement du site public."
      />

      <Panel title="Ajouter une indisponibilité">
        <div className="grid gap-3 lg:grid-cols-[180px_180px_150px_150px_1fr_auto]">
          <input
            type="date"
            value={date}
            onChange={(event) =>
              setDate(
                event.target.value,
              )
            }
            className="rounded-xl border border-black/10 bg-white px-4 py-3 text-sm"
          />

          <label className="flex items-center gap-2 rounded-xl border border-black/10 bg-white px-4 py-3 text-sm font-bold">
            <input
              type="checkbox"
              checked={allDay}
              onChange={(event) =>
                setAllDay(
                  event.target.checked,
                )
              }
            />
            Journée entière
          </label>

          {!allDay ? (
            <>
              <input
                type="time"
                value={start}
                onChange={(event) =>
                  setStart(
                    event.target.value,
                  )
                }
                className="rounded-xl border border-black/10 bg-white px-4 py-3 text-sm"
              />

              <input
                type="time"
                value={end}
                onChange={(event) =>
                  setEnd(
                    event.target.value,
                  )
                }
                className="rounded-xl border border-black/10 bg-white px-4 py-3 text-sm"
              />
            </>
          ) : null}

          <input
            value={reason}
            onChange={(event) =>
              setReason(
                event.target.value,
              )
            }
            placeholder="Motif"
            className="rounded-xl border border-black/10 bg-white px-4 py-3 text-sm"
          />

          <button
            onClick={onAdd}
            className="rounded-full bg-[#080808] px-5 py-3 text-sm font-extrabold text-white"
          >
            Bloquer
          </button>
        </div>
      </Panel>

      <Panel title="Blocages actuels">
        <div className="space-y-3">
          {sortedBlocks.map(
            (block) => (
              <div
                key={block.id}
                className="flex flex-col justify-between gap-3 rounded-2xl border border-black/10 p-4 sm:flex-row sm:items-center"
              >
                <div>
                  <div className="font-extrabold">
                    {frDate(
                      block.date,
                    )}
                  </div>

                  <div className="mt-1 text-xs text-black/45">
                    {block.all_day
                      ? "Journée entière"
                      : `${block.start_time} → ${block.end_time}`}{" "}
                    ·{" "}
                    {block.reason}
                  </div>
                </div>

                <button
                  onClick={() =>
                    onDelete(
                      block.id,
                    )
                  }
                  className="rounded-full border border-red-200 px-4 py-2 text-xs font-bold text-red-600"
                >
                  Supprimer
                </button>
              </div>
            ),
          )}

          {sortedBlocks.length ===
          0 ? (
            <Empty text="Aucune indisponibilité." />
          ) : null}
        </div>
      </Panel>
    </div>
  );
}

function GrandPlus({
  data,
}: {
  data: Grand[];
}) {
  return (
    <div className="space-y-6">
      <Title
        eyebrow="Grand+"
        title="Participants"
        text="Les participations réelles enregistrées par le formulaire Grand+."
      />

      <Panel
        title={`${data.length} participation${
          data.length > 1
            ? "s"
            : ""
        }`}
      >
        <div className="divide-y divide-black/10">
          {data.map(
            (participant, index) => (
              <div
                key={
                  participant.id ??
                  index
                }
                className="py-4"
              >
                <div className="font-extrabold">
                  {participant.name ||
                    "Sans nom"}
                  {participant.company
                    ? ` · ${participant.company}`
                    : ""}
                </div>

                <div className="mt-1 text-xs text-black/45">
                  {participant.email ||
                    "—"}{" "}
                  ·{" "}
                  {participant.phone ||
                    "—"}{" "}
                  ·{" "}
                  {participant.month_label ||
                    participant.month_key ||
                    "—"}
                </div>
              </div>
            ),
          )}

          {data.length === 0 ? (
            <Empty text="Aucune participation." />
          ) : null}
        </div>
      </Panel>
    </div>
  );
}

function Stats({
  data,
}: {
  data: Data;
}) {
  const sources =
    Object.entries(data.sources);

  const wonRate = data.stats
    .prospects
    ? Math.round(
        ((data.stats.won ?? 0) /
          data.stats.prospects) *
          100,
      )
    : 0;

  return (
    <div className="space-y-6">
      <Title
        eyebrow="Statistiques"
        title="Performance commerciale"
        text="Lecture des données actuelles du CRM et des réservations."
      />

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Metric
          icon={Activity}
          label="Nouveaux"
          value={String(
            data.stats.new ?? 0,
          )}
        />

        <Metric
          icon={Target}
          label="Qualifiés"
          value={String(
            data.stats.qualified ??
              0,
          )}
        />

        <Metric
          icon={CalendarDays}
          label="RDV confirmés"
          value={String(
            data.stats.confirmed_bookings ??
              0,
          )}
        />

        <Metric
          icon={TrendingUp}
          label="Taux gagné"
          value={`${wonRate}%`}
        />
      </div>

      <Panel title="Chiffre d'affaires">
        <div className="grid gap-4 sm:grid-cols-2">
          <div className="rounded-2xl bg-[#080808] p-6 text-white">
            <div className="text-xs text-white/40">
              CA signé
            </div>

            <div className="mt-2 text-3xl font-extrabold">
              {euro(
                data.stats.signed_revenue ??
                  0,
              )}
            </div>
          </div>

          <div className="rounded-2xl border border-black/10 p-6">
            <div className="text-xs text-black/40">
              CA potentiel
            </div>

            <div className="mt-2 text-3xl font-extrabold">
              {euro(
                data.stats.potential_revenue ??
                  0,
              )}
            </div>
          </div>
        </div>
      </Panel>

      <Panel title="Sources">
        <div className="space-y-4">
          {sources.map(
            ([key, value]) => (
              <div
                key={key}
                className="flex items-center justify-between"
              >
                <span className="font-bold">
                  {SOURCE_LABEL[key] ??
                    key}
                </span>

                <b>{value}</b>
              </div>
            ),
          )}

          {sources.length === 0 ? (
            <Empty text="Aucune donnée." />
          ) : null}
        </div>
      </Panel>
    </div>
  );
}

function ProspectModal({
  prospect,
  onClose,
  onSave,
  onInteraction,
}: {
  prospect: Prospect;
  onClose: () => void;
  onSave: (
    payload: Record<string, unknown>,
  ) => Promise<void>;
  onInteraction: (
    text: string,
    type: string,
  ) => Promise<void>;
}) {
  const [form, setForm] =
    useState({
      name: prospect.name,
      company: prospect.company,
      email: prospect.email,
      phone: prospect.phone,
      website: prospect.website,
      status: prospect.status,
      offer: prospect.offer,
      estimated_value: String(
        prospect.estimated_value ??
          0,
      ),
      recurring_value: String(
        prospect.recurring_value ??
          0,
      ),
      next_action:
        prospect.next_action,
      next_action_at:
        prospect.next_action_at,
      notes: prospect.notes,
    });

  const [interaction, setInteraction] =
    useState("");

  return (
    <Overlay>
      <div className="max-h-[90vh] w-full max-w-3xl overflow-auto rounded-[2rem] bg-[#f4f4f1] p-6 shadow-2xl sm:p-8">
        <div className="flex items-start justify-between">
          <div>
            <div className="text-[10px] font-extrabold uppercase tracking-[.25em] text-black/35">
              Fiche prospect
            </div>

            <h2 className="mt-2 text-2xl font-extrabold">
              {prospect.name ||
                "Prospect"}
            </h2>

            <div className="mt-1 text-sm text-black/45">
              {SOURCE_LABEL[
                prospect.source
              ] ??
                prospect.source}
            </div>
          </div>

          <button
            onClick={onClose}
          >
            <X />
          </button>
        </div>

        <div className="mt-6 grid gap-4 sm:grid-cols-2">
          <InputField
            label="Nom"
            value={form.name}
            onChange={(value) =>
              setForm({
                ...form,
                name: value,
              })
            }
          />

          <InputField
            label="Entreprise"
            value={form.company}
            onChange={(value) =>
              setForm({
                ...form,
                company: value,
              })
            }
          />

          <InputField
            label="E-mail"
            value={form.email}
            onChange={(value) =>
              setForm({
                ...form,
                email: value,
              })
            }
            inputMode="email"
          />

          <InputField
            label="Téléphone"
            value={form.phone}
            onChange={(value) =>
              setForm({
                ...form,
                phone: value,
              })
            }
            inputMode="tel"
          />

          <InputField
            label="Site internet"
            value={form.website}
            onChange={(value) =>
              setForm({
                ...form,
                website: value,
              })
            }
          />

          <InputField
            label="Offre"
            value={form.offer}
            onChange={(value) =>
              setForm({
                ...form,
                offer: value,
              })
            }
          />

          <InputField
            label="Valeur"
            value={
              form.estimated_value
            }
            onChange={(value) =>
              setForm({
                ...form,
                estimated_value:
                  value,
              })
            }
            type="number"
          />

          <InputField
            label="Récurrent"
            value={
              form.recurring_value
            }
            onChange={(value) =>
              setForm({
                ...form,
                recurring_value:
                  value,
              })
            }
            type="number"
          />

          <InputField
            label="Prochaine action"
            value={
              form.next_action
            }
            onChange={(value) =>
              setForm({
                ...form,
                next_action: value,
              })
            }
          />

          <InputField
            label="Date de relance"
            value={
              form.next_action_at
            }
            onChange={(value) =>
              setForm({
                ...form,
                next_action_at:
                  value,
              })
            }
            type="date"
          />

          <label className="text-xs font-bold">
            <span className="mb-2 block text-black/45">
              Statut
            </span>

            <select
              value={form.status}
              onChange={(event) =>
                setForm({
                  ...form,
                  status:
                    event.target.value,
                })
              }
              className="w-full rounded-xl border border-black/10 bg-white px-4 py-3 outline-none"
            >
              {STATUSES.map(
                (item) => (
                  <option
                    key={item}
                    value={item}
                  >
                    {
                      STATUS_LABEL[
                        item
                      ]
                    }
                  </option>
                ),
              )}
            </select>
          </label>

          <label className="text-xs font-bold sm:col-span-2">
            <span className="mb-2 block text-black/45">
              Notes
            </span>

            <textarea
              value={form.notes}
              onChange={(event) =>
                setForm({
                  ...form,
                  notes:
                    event.target.value,
                })
              }
              rows={4}
              className="w-full rounded-xl border border-black/10 bg-white px-4 py-3 outline-none"
            />
          </label>
        </div>

        <div className="mt-6 flex flex-wrap gap-2">
          {prospect.phone ? (
            <a
              href={`tel:${prospect.phone}`}
              className="rounded-full bg-[#080808] px-4 py-2 text-xs font-bold text-white"
            >
              <Phone
                size={14}
                className="mr-2 inline"
              />
              Appeler
            </a>
          ) : null}

          {prospect.email ? (
            <a
              href={`mailto:${prospect.email}`}
              className="rounded-full border border-black/10 bg-white px-4 py-2 text-xs font-bold"
            >
              <Mail
                size={14}
                className="mr-2 inline"
              />
              E-mail
            </a>
          ) : null}

          {prospect.website ? (
            <a
              href={
                prospect.website.startsWith(
                  "http",
                )
                  ? prospect.website
                  : `https://${prospect.website}`
              }
              target="_blank"
              rel="noreferrer"
              className="rounded-full border border-black/10 bg-white px-4 py-2 text-xs font-bold"
            >
              Site internet
            </a>
          ) : null}
        </div>

        <div className="mt-7 border-t border-black/10 pt-6">
          <b>
            Ajouter une interaction
          </b>

          <div className="mt-3 flex gap-2">
            <input
              value={interaction}
              onChange={(event) =>
                setInteraction(
                  event.target.value,
                )
              }
              placeholder="Note, appel, message..."
              className="min-w-0 flex-1 rounded-xl border border-black/10 bg-white px-4 py-3 outline-none"
            />

            <button
              onClick={async () => {
                if (
                  !interaction.trim()
                ) {
                  return;
                }

                await onInteraction(
                  interaction,
                  "note",
                );

                setInteraction("");
              }}
              className="rounded-full bg-[#c8a45d] px-5 py-3 text-xs font-extrabold"
            >
              Ajouter
            </button>
          </div>
        </div>

        <div className="mt-7 flex justify-end gap-2">
          <button
            onClick={onClose}
            className="rounded-full border border-black/10 bg-white px-5 py-3 text-xs font-bold"
          >
            Fermer
          </button>

          <button
            onClick={async () => {
              await onSave({
                ...form,
                estimated_value:
                  Number(
                    form.estimated_value,
                  ),
                recurring_value:
                  Number(
                    form.recurring_value,
                  ),
              });
            }}
            className="rounded-full bg-[#080808] px-5 py-3 text-xs font-extrabold text-white"
          >
            Enregistrer
          </button>
        </div>
      </div>
    </Overlay>
  );
}

function NewProspect({
  onClose,
  onCreated,
  api,
}: {
  onClose: () => void;
  onCreated: () => void;
  api: (
    payload: Record<string, unknown>,
  ) => Promise<any>;
}) {
  const [form, setForm] =
    useState({
      name: "",
      company: "",
      email: "",
      phone: "",
      website: "",
      notes: "",
    });

  const [busy, setBusy] =
    useState(false);

  const [error, setError] =
    useState("");

  async function create() {
    if (!form.name.trim()) {
      setError(
        "Le nom du prospect est obligatoire.",
      );
      return;
    }

    setError("");
    setBusy(true);

    try {
      await api({
        action: "create_prospect",
        name: form.name.trim(),
        company:
          form.company.trim(),
        email:
          form.email.trim(),
        phone:
          form.phone.trim(),
        website:
          form.website.trim(),
        notes:
          form.notes.trim(),
      });

      onCreated();
    } catch (error) {
      setError(
        error instanceof Error
          ? error.message
          : "Impossible de créer le prospect.",
      );
    } finally {
      setBusy(false);
    }
  }

  return (
    <Overlay>
      <div className="w-full max-w-xl rounded-[2rem] bg-[#f4f4f1] p-7 shadow-2xl">
        <div className="flex justify-between">
          <div>
            <div className="text-[10px] font-extrabold uppercase tracking-[.25em] text-black/35">
              CRM
            </div>

            <h2 className="mt-2 text-2xl font-extrabold">
              Nouveau prospect
            </h2>
          </div>

          <button
            onClick={onClose}
          >
            <X />
          </button>
        </div>

        <div className="mt-6 grid gap-4 sm:grid-cols-2">
          <InputField
            label="Nom"
            value={form.name}
            onChange={(value) =>
              setForm({
                ...form,
                name: value,
              })
            }
          />

          <InputField
            label="Entreprise"
            value={form.company}
            onChange={(value) =>
              setForm({
                ...form,
                company: value,
              })
            }
          />

          <InputField
            label="E-mail"
            value={form.email}
            onChange={(value) =>
              setForm({
                ...form,
                email: value,
              })
            }
            inputMode="email"
          />

          <InputField
            label="Téléphone"
            value={form.phone}
            onChange={(value) =>
              setForm({
                ...form,
                phone: value,
              })
            }
            inputMode="tel"
          />

          <InputField
            label="Site internet"
            value={form.website}
            onChange={(value) =>
              setForm({
                ...form,
                website: value,
              })
            }
          />

          <label className="text-xs font-bold sm:col-span-2">
            <span className="mb-2 block text-black/45">
              Notes
            </span>

            <textarea
              value={form.notes}
              onChange={(event) =>
                setForm({
                  ...form,
                  notes:
                    event.target.value,
                })
              }
              rows={4}
              className="w-full rounded-xl border border-black/10 bg-white px-4 py-3 outline-none"
            />
          </label>
        </div>

        {error ? (
          <div className="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs font-bold text-red-700">
            {error}
          </div>
        ) : null}

        <div className="mt-6 flex justify-end gap-2">
          <button
            onClick={onClose}
            className="rounded-full border border-black/10 bg-white px-5 py-3 text-xs font-bold"
          >
            Annuler
          </button>

          <button
            disabled={busy}
            onClick={create}
            className="rounded-full bg-[#080808] px-5 py-3 text-xs font-extrabold text-white disabled:opacity-40"
          >
            {busy
              ? "Création..."
              : "Créer le prospect"}
          </button>
        </div>
      </div>
    </Overlay>
  );
}

function InputField({
  label,
  value,
  onChange,
  type = "text",
  inputMode,
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
}) {
  return (
    <label className="text-xs font-bold">
      <span className="mb-2 block text-black/45">
        {label}
      </span>

      <input
        type={type}
        inputMode={inputMode}
        value={value}
        onChange={(event) =>
          onChange(
            event.target.value,
          )
        }
        className="w-full rounded-xl border border-black/10 bg-white px-4 py-3 outline-none focus:border-[#c8a45d]"
      />
    </label>
  );
}

function Title({
  eyebrow,
  title,
  text,
  action,
}: {
  eyebrow: string;
  title: string;
  text: string;
  action?: React.ReactNode;
}) {
  return (
    <div className="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
      <div>
        <div className="text-[10px] font-extrabold uppercase tracking-[.25em] text-[#a17e32]">
          {eyebrow}
        </div>

        <h1 className="mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl">
          {title}
        </h1>

        <p className="mt-2 max-w-2xl text-sm leading-6 text-black/45">
          {text}
        </p>
      </div>

      {action}
    </div>
  );
}

function Metric({
  icon: Icon,
  label,
  value,
}: {
  icon: React.ComponentType<{
    size?: number;
    className?: string;
  }>;
  label: string;
  value: string;
}) {
  return (
    <div className="rounded-[1.5rem] border border-black/10 bg-white p-5">
      <Icon
        size={18}
        className="text-[#c8a45d]"
      />

      <div className="mt-5 text-xs font-bold text-black/40">
        {label}
      </div>

      <div className="mt-1 text-2xl font-extrabold">
        {value}
      </div>
    </div>
  );
}

function Panel({
  title,
  action,
  children,
}: {
  title: string;
  action?: React.ReactNode;
  children: React.ReactNode;
}) {
  return (
    <section className="rounded-[1.5rem] border border-black/10 bg-white p-5">
      <div className="mb-4 flex items-center justify-between gap-3">
        <h2 className="font-extrabold">
          {title}
        </h2>

        {action}
      </div>

      {children}
    </section>
  );
}

function Empty({
  text,
}: {
  text: string;
}) {
  return (
    <div className="py-10 text-center text-sm text-black/35">
      {text}
    </div>
  );
}

function Overlay({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <div className="fixed inset-0 z-[70] flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
      {children}
    </div>
  );
}
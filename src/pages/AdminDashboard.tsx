import { useEffect, useMemo, useState } from "react";
import type { ReactNode } from "react";

import {
  Activity,
  BarChart3,
  CalendarDays,
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

/* ================================================================
   TYPES
================================================================ */

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

type AvailabilityBlock = {
  id: string;
  date: string;
  all_day: boolean;
  start_time: string | null;
  end_time: string | null;
  reason: string;
  created_at?: string;
};

type GrandPlusParticipant = {
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
  grand_plus: GrandPlusParticipant[];
};

/* ================================================================
   CONSTANTES
================================================================ */

const EMPTY_DATA: Data = {
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

/* ================================================================
   HELPERS
================================================================ */

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
  switch (status) {
    case "won":
      return "bg-emerald-100 text-emerald-700";

    case "lost":
      return "bg-red-100 text-red-700";

    case "meeting":
      return "bg-blue-100 text-blue-700";

    case "proposal":
      return "bg-purple-100 text-purple-700";

    case "negotiation":
      return "bg-orange-100 text-orange-700";

    case "qualified":
      return "bg-[#c8a45d]/20 text-[#85671f]";

    default:
      return "bg-black/5 text-black/60";
  }
}

function StatusBadge({
  status,
}: {
  status: string;
}) {
  return (
    <span
      className={`inline-flex rounded-full px-3 py-1.5 text-[10px] font-extrabold uppercase tracking-[0.1em] ${statusClass(
        status,
      )}`}
    >
      {STATUS_LABEL[status] ?? status}
    </span>
  );
}

/* ================================================================
   STYLES ADMIN
================================================================ */

function AdminButtonStyles() {
  return (
    <style>
      {`
        .vplus-admin,
        .vplus-admin button,
        .vplus-admin input,
        .vplus-admin select,
        .vplus-admin textarea {
          font-family: inherit;
        }

        .vplus-admin button {
          color: inherit !important;
        }

        .vplus-admin .admin-white-button {
          color: #080808 !important;
          background: #ffffff !important;
        }

        .vplus-admin .admin-dark-button {
          color: #ffffff !important;
          background: #080808 !important;
        }

        .vplus-admin .admin-gold-button {
          color: #080808 !important;
          background: #c8a45d !important;
        }

        .vplus-admin .admin-danger-button {
          color: #dc2626 !important;
          background: #ffffff !important;
        }

        .vplus-admin .admin-sidebar-button {
          color: rgba(255,255,255,.65) !important;
        }

        .vplus-admin .admin-sidebar-button-active {
          color: #080808 !important;
          background: #c8a45d !important;
        }

        .vplus-admin a {
          color: inherit;
        }

        .vplus-admin input::placeholder,
        .vplus-admin textarea::placeholder {
          color: rgba(0,0,0,.35);
        }
      `}
    </style>
  );
}

/* ================================================================
   COMPONENT PRINCIPAL
================================================================ */

export default function AdminDashboard() {
  const [data, setData] = useState<Data>(EMPTY_DATA);

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

  const [blocks, setBlocks] =
    useState<AvailabilityBlock[]>([]);

  const [blockDate, setBlockDate] = useState("");

  const [allDay, setAllDay] = useState(true);

  const [start, setStart] = useState("09:00");

  const [end, setEnd] = useState("18:00");

  const [reason, setReason] =
    useState("Indisponible");

  /* ==============================================================
     CHARGEMENT CRM
  ============================================================== */

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
        const refreshed =
          (json.prospects as Prospect[]).find(
            (prospect) =>
              prospect.id === selected.id,
          ) ?? null;

        setSelected(refreshed);
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

  /* ==============================================================
     CHARGEMENT DISPONIBILITÉS
  ============================================================== */

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
            "Impossible de charger les disponibilités.",
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

  /* ==============================================================
     API CRM
  ============================================================== */

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

  /* ==============================================================
     API DISPONIBILITÉS
  ============================================================== */

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
    if (!toast) return;

    const timer = window.setTimeout(
      () => setToast(""),
      3500,
    );

    return () =>
      window.clearTimeout(timer);
  }, [toast]);

  /* ==============================================================
     FILTRES
  ============================================================== */

  const filtered = useMemo(() => {
    const q =
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

        const matchesQuery =
          !q || haystack.includes(q);

        const matchesSource =
          source === "all" ||
          prospect.source === source;

        const matchesStatus =
          status === "all" ||
          prospect.status === status;

        return (
          matchesQuery &&
          matchesSource &&
          matchesStatus
        );
      },
    );
  }, [
    data.prospects,
    query,
    source,
    status,
  ]);

  const upcoming = useMemo(() => {
    return [...data.bookings]
      .filter(
        (booking) =>
          booking.status !==
          "cancelled",
      )
      .sort((a, b) =>
        `${a.date} ${a.time}`.localeCompare(
          `${b.date} ${b.time}`,
        ),
      );
  }, [data.bookings]);

  /* ==============================================================
     NAVIGATION
  ============================================================== */

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

  function navigate(
    sectionName: string,
  ) {
    setSection(sectionName);
    setSelected(null);
    setMobile(false);
  }

  /* ==============================================================
     PROSPECT
  ============================================================== */

  async function saveProspect(
    payload: Record<string, unknown>,
  ) {
    if (!selected) return;

    try {
      await api({
        action: "update_prospect",
        id: selected.id,
        ...payload,
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
    if (!selected) return;

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

  async function deleteProspect(
    id: string,
  ) {
    const confirmed =
      window.confirm(
        "Supprimer définitivement ce prospect ?\n\nCette action supprimera également sa donnée source si elle provient d'un formulaire du site.\n\nCette action est irréversible.",
      );

    if (!confirmed) return;

    try {
      await api({
        action: "delete_prospect",
        id,
      });

      setSelected(null);

      setToast(
        "Prospect supprimé.",
      );
    } catch (error) {
      setToast(
        error instanceof Error
          ? error.message
          : "Erreur.",
      );
    }
  }

  /* ==============================================================
     RENDEZ-VOUS
  ============================================================== */

  async function updateBooking(
    reference: string,
    newStatus: string,
  ) {
    try {
      await api({
        action: "update_booking",
        reference,
        status: newStatus,
      });

      setToast(
        "Rendez-vous mis à jour.",
      );
    } catch (error) {
      setToast(
        error instanceof Error
          ? error.message
          : "Erreur.",
      );
    }
  }

  async function deleteBooking(
    reference: string,
  ) {
    const confirmed =
      window.confirm(
        "Supprimer définitivement ce rendez-vous ?\n\nCette action est irréversible.",
      );

    if (!confirmed) return;

    try {
      await api({
        action: "delete_booking",
        reference,
      });

      setToast(
        "Rendez-vous supprimé.",
      );
    } catch (error) {
      setToast(
        error instanceof Error
          ? error.message
          : "Erreur.",
      );
    }
  }

  /* ==============================================================
     RENDER
  ============================================================== */

  return (
    <div className="vplus-admin min-h-screen bg-[#f4f4f1] text-[#080808]">
      <AdminButtonStyles />

      {/* HEADER */}

      <header className="sticky top-0 z-30 border-b border-black/10 bg-[#f4f4f1]/95 backdrop-blur">
        <div className="flex h-16 items-center justify-between px-5 lg:px-8">
          <div className="flex items-center gap-3">
            <button
              type="button"
              onClick={() =>
                setMobile(true)
              }
              className="admin-white-button inline-flex items-center justify-center rounded-full border border-black/10 p-2 lg:hidden"
            >
              <Menu size={18} />
            </button>

            <div>
              <div className="text-[10px] font-extrabold uppercase tracking-[0.25em] text-black/35">
                Vitrine+
              </div>

              <div className="font-extrabold">
                Administration commerciale
              </div>
            </div>
          </div>

          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={() => {
                load();
                loadBlocks();
              }}
              className="admin-white-button hidden rounded-full border border-black/10 px-4 py-2 text-xs font-bold sm:inline-flex"
            >
              Actualiser
            </button>

            <a
              href="/vitrine.fr?logout=1"
              className="admin-white-button inline-flex rounded-full border border-black/10 p-2"
            >
              <LogOut size={16} />
            </a>
          </div>
        </div>
      </header>

      <div className="flex">
        {/* SIDEBAR */}

        <aside
          className={`${
            mobile
              ? "fixed inset-0 z-50"
              : "hidden"
          } w-72 shrink-0 border-r border-black/10 bg-[#080808] text-white lg:sticky lg:top-16 lg:block lg:h-[calc(100vh-4rem)]`}
        >
          <div className="flex h-full flex-col p-4">
            <div className="mb-6 flex items-center justify-between px-3 pt-2">
              <span className="text-xs font-extrabold uppercase tracking-[0.25em] text-white/40">
                V+ Admin
              </span>

              <button
                type="button"
                onClick={() =>
                  setMobile(false)
                }
                className="text-white lg:hidden"
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
                    type="button"
                    onClick={() =>
                      navigate(key)
                    }
                    className={`flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left text-sm font-bold transition ${
                      section === key
                        ? "admin-sidebar-button-active"
                        : "admin-sidebar-button hover:bg-white/5 hover:text-white"
                    }`}
                  >
                    <Icon size={17} />

                    <span>{label}</span>

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

            <div className="mt-auto rounded-2xl border border-white/10 bg-white/[0.04] p-4 text-xs text-white/45">
              Les données sont lues
              directement depuis les
              fichiers réels du site.

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

        {/* MAIN */}

        <main className="min-w-0 flex-1 p-5 lg:p-8">
          {loading ? (
            <div className="flex min-h-[60vh] items-center justify-center">
              <div className="h-8 w-8 animate-spin rounded-full border-2 border-black/10 border-t-[#c8a45d]" />
            </div>
          ) : section ===
            "dashboard" ? (
            <Dashboard
              data={data}
              onNavigate={navigate}
              onOpen={setSelected}
            />
          ) : section ===
            "prospects" ? (
            <Prospects
              prospects={filtered}
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
              prospects={data.prospects}
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
                    error instanceof
                      Error
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
              onStatus={updateBooking}
              onDelete={deleteBooking}
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
              onAdd={async () => {
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
                  await availability(
                    {
                      action:
                        "add_block",
                      date: blockDate,
                      all_day: allDay,
                      start_time:
                        allDay
                          ? null
                          : start,
                      end_time:
                        allDay
                          ? null
                          : end,
                      reason:
                        reason.trim() ||
                        "Indisponible",
                    },
                  );

                  setBlockDate("");

                  setToast(
                    "Indisponibilité ajoutée.",
                  );
                } catch (error) {
                  setToast(
                    error instanceof
                      Error
                      ? error.message
                      : "Erreur.",
                  );
                }
              }}
              onDelete={async (id) => {
                const confirmed =
                  window.confirm(
                    "Supprimer cette indisponibilité ?",
                  );

                if (!confirmed) {
                  return;
                }

                try {
                  await availability(
                    {
                      action:
                        "delete_block",
                      id,
                    },
                  );

                  setToast(
                    "Indisponibilité supprimée.",
                  );
                } catch (error) {
                  setToast(
                    error instanceof
                      Error
                      ? error.message
                      : "Erreur.",
                  );
                }
              }}
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

      {/* FICHE PROSPECT */}

      {selected && (
        <ProspectModal
          prospect={selected}
          onClose={() =>
            setSelected(null)
          }
          onSave={saveProspect}
          onInteraction={
            addInteraction
          }
          onDelete={
            deleteProspect
          }
        />
      )}

      {/* NOUVEAU PROSPECT */}

      {newOpen && (
        <NewProspect
          onClose={() =>
            setNewOpen(false)
          }
          api={api}
          onCreated={() => {
            setNewOpen(false);
            setToast(
              "Prospect créé.",
            );
          }}
        />
      )}

      {/* TOAST */}

      {toast && (
        <div className="fixed bottom-5 right-5 z-[80] max-w-sm rounded-2xl bg-[#080808] px-5 py-4 text-sm font-bold text-white shadow-2xl">
          {toast}
        </div>
      )}
    </div>
  );
}

/* ================================================================
   DASHBOARD
================================================================ */

function Dashboard({
  data,
  onNavigate,
  onOpen,
}: {
  data: Data;
  onNavigate: (section: string) => void;
  onOpen: (prospect: Prospect) => void;
}) {
  const urgent =
    data.prospects
      .filter(
        (prospect) =>
          prospect.next_action_at &&
          !["won", "lost"].includes(
            prospect.status,
          ),
      )
      .sort((a, b) =>
        a.next_action_at.localeCompare(
          b.next_action_at,
        ),
      )
      .slice(0, 6);

  return (
    <div className="space-y-7">
      <Title
        eyebrow="Vue d'ensemble"
        title="Pilotez Vitrine+ depuis un seul endroit."
        text="Prospects, rendez-vous, pipeline et Grand+ sont réunis ici."
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
          icon={CalendarDays}
          label="RDV à venir"
          value={String(
            data.stats
              .upcoming_bookings ?? 0,
          )}
        />

        <Metric
          icon={CircleDollarSign}
          label="CA signé"
          value={euro(
            data.stats
              .signed_revenue ?? 0,
          )}
        />

        <Metric
          icon={TrendingUp}
          label="Potentiel"
          value={euro(
            data.stats
              .potential_revenue ?? 0,
          )}
        />
      </div>

      <div className="grid gap-5 xl:grid-cols-[1.4fr_.6fr]">
        <Panel
          title="Prochains rendez-vous"
          action={
            <button
              type="button"
              onClick={() =>
                onNavigate("calendar")
              }
              className="admin-white-button inline-flex items-center gap-1 rounded-full px-3 py-2 text-xs font-extrabold"
            >
              Tout voir
              <ChevronRight size={14} />
            </button>
          }
        >
          <div className="divide-y divide-black/10">
            {data.bookings
              .filter(
                (booking) =>
                  booking.status !==
                  "cancelled",
              )
              .slice(0, 6)
              .map((booking) => (
                <div
                  key={
                    booking.reference
                  }
                  className="flex items-center justify-between gap-4 py-4"
                >
                  <div>
                    <div className="font-extrabold">
                      {booking.name}

                      {booking.company
                        ? ` · ${booking.company}`
                        : ""}
                    </div>

                    <div className="mt-1 text-xs text-black/45">
                      {frDate(
                        booking.date,
                      )}{" "}
                      à{" "}
                      {booking.time}{" "}
                      ·{" "}
                      {
                        booking.reference
                      }
                    </div>
                  </div>

                  <StatusBadge
                    status={
                      booking.status ===
                      "confirmed"
                        ? "meeting"
                        : "lost"
                    }
                  />
                </div>
              ))}

            {data.bookings.length ===
              0 && (
              <Empty text="Aucun rendez-vous enregistré." />
            )}
          </div>
        </Panel>

        <Panel title="Pipeline">
          <div className="space-y-3">
            {STATUSES.map(
              (pipelineStatus) => (
                <div
                  key={
                    pipelineStatus
                  }
                  className="flex items-center gap-3"
                >
                  <span className="w-24 text-xs font-bold">
                    {
                      STATUS_LABEL[
                        pipelineStatus
                      ]
                    }
                  </span>

                  <div className="h-2 flex-1 overflow-hidden rounded-full bg-black/5">
                    <div
                      className="h-full rounded-full bg-[#c8a45d]"
                      style={{
                        width: `${Math.min(
                          100,
                          (data.pipeline[
                            pipelineStatus
                          ] ?? 0) * 20,
                        )}%`,
                      }}
                    />
                  </div>

                  <b className="w-5 text-right text-xs">
                    {data.pipeline[
                      pipelineStatus
                    ] ?? 0}
                  </b>
                </div>
              ),
            )}
          </div>
        </Panel>
      </div>

      <Panel title="Actions commerciales">
        <div className="grid gap-3 sm:grid-cols-3">
          {urgent.map((prospect) => (
            <button
              key={prospect.id}
              type="button"
              onClick={() =>
                onOpen(prospect)
              }
              className="admin-white-button rounded-2xl border border-black/10 p-4 text-left hover:border-[#c8a45d]"
            >
              <div className="font-extrabold">
                {prospect.name ||
                  "Sans nom"}
              </div>

              <div className="mt-1 text-xs text-black/45">
                {prospect.next_action ||
                  "Action à définir"}
              </div>

              <div className="mt-3 text-[10px] font-bold uppercase tracking-wider text-black/35">
                {frDate(
                  prospect.next_action_at,
                )}
              </div>
            </button>
          ))}

          {urgent.length === 0 && (
            <Empty text="Aucune relance urgente." />
          )}
        </div>
      </Panel>
    </div>
  );
}

/* ================================================================
   PROSPECTS
================================================================ */

function Prospects({
  prospects,
  query,
  setQuery,
  source,
  setSource,
  status,
  setStatus,
  onOpen,
  onNew,
}: {
  prospects: Prospect[];
  query: string;
  setQuery: (value: string) => void;
  source: string;
  setSource: (value: string) => void;
  status: string;
  setStatus: (value: string) => void;
  onOpen: (prospect: Prospect) => void;
  onNew: () => void;
}) {
  return (
    <div className="space-y-6">
      <Title
        eyebrow="CRM"
        title="Prospects"
        text="Toutes les sources commerciales dans une même liste."
        action={
          <button
            type="button"
            onClick={onNew}
            className="admin-dark-button inline-flex items-center rounded-full px-5 py-3 text-xs font-extrabold"
          >
            <Plus
              size={15}
              className="mr-2"
            />
            Nouveau prospect
          </button>
        }
      />

      <div className="grid gap-2 md:grid-cols-[1fr_180px_180px]">
        <div className="relative">
          <Search
            size={17}
            className="absolute left-4 top-1/2 -translate-y-1/2 text-black/30"
          />

          <input
            value={query}
            onChange={(event) =>
              setQuery(
                event.target.value,
              )
            }
            placeholder="Rechercher…"
            className="w-full rounded-2xl border border-black/10 bg-white px-11 py-3.5 text-sm text-[#080808] outline-none focus:border-[#c8a45d]"
          />
        </div>

        <select
          value={source}
          onChange={(event) =>
            setSource(
              event.target.value,
            )
          }
          className="rounded-2xl border border-black/10 bg-white px-4 py-3 text-sm font-semibold text-[#080808]"
        >
          <option value="all">
            Toutes les sources
          </option>

          {Object.entries(
            SOURCE_LABEL,
          ).map(([key, label]) => (
            <option
              key={key}
              value={key}
            >
              {label}
            </option>
          ))}
        </select>

        <select
          value={status}
          onChange={(event) =>
            setStatus(
              event.target.value,
            )
          }
          className="rounded-2xl border border-black/10 bg-white px-4 py-3 text-sm font-semibold text-[#080808]"
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

      <div className="overflow-hidden rounded-[1.5rem] border border-black/10 bg-white">
        <div className="hidden grid-cols-[1.4fr_1fr_1fr_140px] gap-4 border-b border-black/10 bg-black/[0.02] px-5 py-3 text-[10px] font-extrabold uppercase tracking-wider text-black/35 md:grid">
          <span>Prospect</span>
          <span>Contact</span>
          <span>Source</span>
          <span>Statut</span>
        </div>

        {prospects.map(
          (prospect) => (
            <button
              key={prospect.id}
              type="button"
              onClick={() =>
                onOpen(prospect)
              }
              className="admin-white-button grid w-full gap-2 border-b border-black/10 px-5 py-4 text-left hover:bg-black/[0.02] md:grid-cols-[1.4fr_1fr_1fr_140px] md:items-center"
            >
              <div>
                <div className="font-extrabold">
                  {prospect.name ||
                    "Sans nom"}
                </div>

                <div className="text-xs text-black/45">
                  {prospect.company ||
                    "Entreprise non renseignée"}
                </div>
              </div>

              <div className="text-xs text-black/60">
                {prospect.email ||
                  prospect.phone ||
                  "—"}
              </div>

              <div>
                <span className="text-xs font-bold">
                  {
                    SOURCE_LABEL[
                      prospect.source
                    ]
                  }
                </span>

                {prospect.booking_reference && (
                  <div className="mt-1 text-[10px] text-black/35">
                    {
                      prospect.booking_reference
                    }
                  </div>
                )}
              </div>

              <StatusBadge
                status={
                  prospect.status
                }
              />
            </button>
          ),
        )}

        {prospects.length === 0 && (
          <Empty text="Aucun prospect ne correspond aux filtres." />
        )}
      </div>
    </div>
  );
}

/* ================================================================
   PIPELINE
================================================================ */

function Pipeline({
  prospects,
  onOpen,
  onMove,
}: {
  prospects: Prospect[];
  onOpen: (prospect: Prospect) => void;
  onMove: (
    id: string,
    status: string,
  ) => Promise<void>;
}) {
  return (
    <div className="space-y-6">
      <Title
        eyebrow="Commercial"
        title="Pipeline"
        text="Déplacez chaque opportunité jusqu'à la signature."
      />

      <div className="flex gap-4 overflow-x-auto pb-3">
        {STATUSES.map(
          (pipelineStatus) => (
            <div
              key={
                pipelineStatus
              }
              className="min-w-[270px] flex-1 rounded-[1.5rem] border border-black/10 bg-white p-3"
            >
              <div className="flex items-center justify-between px-2 py-2">
                <b>
                  {
                    STATUS_LABEL[
                      pipelineStatus
                    ]
                  }
                </b>

                <span className="rounded-full bg-black/5 px-2 py-1 text-xs">
                  {
                    prospects.filter(
                      (prospect) =>
                        prospect.status ===
                        pipelineStatus,
                    ).length
                  }
                </span>
              </div>

              <div className="space-y-2">
                {prospects
                  .filter(
                    (prospect) =>
                      prospect.status ===
                      pipelineStatus,
                  )
                  .map(
                    (prospect) => (
                      <div
                        key={
                          prospect.id
                        }
                        className="rounded-2xl border border-black/10 p-4"
                      >
                        <button
                          type="button"
                          onClick={() =>
                            onOpen(
                              prospect,
                            )
                          }
                          className="admin-white-button w-full rounded-xl p-1 text-left"
                        >
                          <b>
                            {prospect.name ||
                              "Sans nom"}
                          </b>

                          <div className="mt-1 text-xs text-black/45">
                            {prospect.company ||
                              "—"}
                          </div>

                          <div className="mt-3 font-extrabold">
                            {euro(
                              prospect.estimated_value,
                            )}
                          </div>
                        </button>

                        <select
                          value={
                            prospect.status
                          }
                          onChange={(
                            event,
                          ) =>
                            onMove(
                              prospect.id,
                              event.target
                                .value,
                            )
                          }
                          className="mt-3 w-full rounded-xl border border-black/10 bg-white px-3 py-2 text-xs font-semibold text-[#080808]"
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
                      </div>
                    ),
                  )}
              </div>
            </div>
          ),
        )}
      </div>
    </div>
  );
}

/* ================================================================
   CALENDRIER / RENDEZ-VOUS
================================================================ */

function Calendar({
  bookings,
  onStatus,
  onDelete,
}: {
  bookings: Booking[];
  onStatus: (
    reference: string,
    status: string,
  ) => Promise<void>;
  onDelete: (
    reference: string,
  ) => Promise<void>;
}) {
  return (
    <div className="space-y-6">
      <Title
        eyebrow="Agenda"
        title="Rendez-vous"
        text="Cette vue est alimentée directement par bookings.json, la même source utilisée par le formulaire public."
      />

      <div className="space-y-3">
        {bookings.map(
          (booking) => (
            <div
              key={
                booking.reference
              }
              className="rounded-[1.5rem] border border-black/10 bg-white p-5"
            >
              <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                <div className="flex gap-4">
                  <div className="flex h-14 w-14 shrink-0 flex-col items-center justify-center rounded-2xl bg-[#080808] text-white">
                    <span className="text-[10px] font-bold uppercase">
                      {new Date(
                        `${booking.date}T12:00:00`,
                      ).toLocaleDateString(
                        "fr-FR",
                        {
                          weekday:
                            "short",
                        },
                      )}
                    </span>

                    <b>
                      {new Date(
                        `${booking.date}T12:00:00`,
                      ).getDate()}
                    </b>
                  </div>

                  <div>
                    <div className="font-extrabold">
                      {
                        booking.name
                      }

                      {booking.company
                        ? ` · ${booking.company}`
                        : ""}
                    </div>

                    <div className="mt-1 text-sm text-black/55">
                      {frDate(
                        booking.date,
                      )}{" "}
                      ·{" "}
                      <b>
                        {
                          booking.time
                        }
                      </b>{" "}
                      ·{" "}
                      {
                        booking.reference
                      }
                    </div>

                    <div className="mt-2 text-xs text-black/45">
                      {
                        booking.phone
                      }

                      {booking.email
                        ? ` · ${booking.email}`
                        : ""}
                    </div>

                    {booking.reason && (
                      <div className="mt-2 text-sm text-black/65">
                        {
                          booking.reason
                        }
                      </div>
                    )}
                  </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                  <select
                    value={
                      booking.status
                    }
                    onChange={(
                      event,
                    ) =>
                      onStatus(
                        booking.reference,
                        event.target
                          .value,
                      )
                    }
                    className="rounded-full border border-black/10 bg-white px-4 py-2 text-xs font-bold text-[#080808]"
                  >
                    <option value="confirmed">
                      Confirmé
                    </option>

                    <option value="cancelled">
                      Annulé
                    </option>

                    <option value="done">
                      Terminé
                    </option>

                    <option value="no_show">
                      Absent
                    </option>
                  </select>

                  <button
                    type="button"
                    onClick={() =>
                      onDelete(
                        booking.reference,
                      )
                    }
                    className="admin-danger-button rounded-full border border-red-200 px-4 py-2 text-xs font-bold"
                  >
                    Supprimer
                  </button>
                </div>
              </div>
            </div>
          ),
        )}

        {bookings.length === 0 && (
          <Empty text="Aucun rendez-vous enregistré." />
        )}
      </div>
    </div>
  );
}

/* ================================================================
   DISPONIBILITÉS
================================================================ */

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
  blocks: AvailabilityBlock[];
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
  onDelete: (
    id: string,
  ) => Promise<void>;
}) {
  const sortedBlocks =
    [...blocks].sort(
      (a, b) =>
        `${a.date}${a.start_time ?? ""}`.localeCompare(
          `${b.date}${b.start_time ?? ""}`,
        ),
    );

  return (
    <div className="space-y-6">
      <Title
        eyebrow="Agenda"
        title="Disponibilités"
        text="Bloquez une journée ou une plage horaire. Le formulaire public respecte immédiatement ces blocages."
      />

      <Panel title="Ajouter une indisponibilité">
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
          <input
            type="date"
            value={date}
            onChange={(event) =>
              setDate(
                event.target.value,
              )
            }
            className="rounded-2xl border border-black/10 bg-white px-4 py-3 text-sm text-[#080808]"
          />

          <label className="flex items-center gap-2 rounded-2xl border border-black/10 bg-white px-4 py-3 text-sm font-bold text-[#080808]">
            <input
              type="checkbox"
              checked={allDay}
              onChange={(event) =>
                setAllDay(
                  event.target
                    .checked,
                )
              }
            />

            Journée entière
          </label>

          {!allDay && (
            <>
              <input
                type="time"
                value={start}
                onChange={(event) =>
                  setStart(
                    event.target
                      .value,
                  )
                }
                className="rounded-2xl border border-black/10 bg-white px-4 py-3 text-sm text-[#080808]"
              />

              <input
                type="time"
                value={end}
                onChange={(event) =>
                  setEnd(
                    event.target
                      .value,
                  )
                }
                className="rounded-2xl border border-black/10 bg-white px-4 py-3 text-sm text-[#080808]"
              />
            </>
          )}

          <input
            value={reason}
            onChange={(event) =>
              setReason(
                event.target.value,
              )
            }
            placeholder="Motif"
            className="rounded-2xl border border-black/10 bg-white px-4 py-3 text-sm text-[#080808]"
          />

          <button
            type="button"
            onClick={onAdd}
            className="admin-dark-button rounded-full px-5 py-3 text-sm font-extrabold"
          >
            Bloquer
          </button>
        </div>
      </Panel>

      <Panel title="Blocages actuels">
        <div className="space-y-2">
          {sortedBlocks.map(
            (block) => (
              <div
                key={block.id}
                className="flex flex-col justify-between gap-3 rounded-2xl border border-black/10 p-4 sm:flex-row sm:items-center"
              >
                <div>
                  <b>
                    {frDate(
                      block.date,
                    )}
                  </b>

                  <div className="mt-1 text-xs text-black/45">
                    {block.all_day
                      ? "Journée entière"
                      : `${block.start_time} → ${block.end_time}`}
                    {" · "}
                    {block.reason}
                  </div>
                </div>

                <button
                  type="button"
                  onClick={() =>
                    onDelete(
                      block.id,
                    )
                  }
                  className="admin-danger-button rounded-full border border-red-200 px-4 py-2 text-xs font-bold"
                >
                  Supprimer
                </button>
              </div>
            ),
          )}

          {blocks.length === 0 && (
            <Empty text="Aucune indisponibilité." />
          )}
        </div>
      </Panel>
    </div>
  );
}

/* ================================================================
   GRAND+
================================================================ */

function GrandPlus({
  data,
}: {
  data: GrandPlusParticipant[];
}) {
  return (
    <div className="space-y-6">
      <Title
        eyebrow="Grand+"
        title="Participants"
        text="Les participations enregistrées par le formulaire Grand+ sont visibles ici."
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
                    "—"}
                  {" · "}
                  {participant.phone ||
                    "—"}
                  {" · "}
                  {participant.month_label ||
                    participant.month_key ||
                    "—"}
                </div>
              </div>
            ),
          )}

          {data.length === 0 && (
            <Empty text="Aucune participation." />
          )}
        </div>
      </Panel>
    </div>
  );
}

/* ================================================================
   STATS
================================================================ */

function Stats({
  data,
}: {
  data: Data;
}) {
  const sources =
    Object.entries(
      data.sources,
    );

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
            data.stats
              .qualified ?? 0,
          )}
        />

        <Metric
          icon={CalendarDays}
          label="RDV confirmés"
          value={String(
            data.stats
              .confirmed_bookings ??
              0,
          )}
        />

        <Metric
          icon={TrendingUp}
          label="Taux gagné"
          value={`${
            data.stats.prospects
              ? Math.round(
                  ((data.stats.won ??
                    0) /
                    data.stats.prospects) *
                    100,
                )
              : 0
          }%`}
        />
      </div>

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
        </div>
      </Panel>
    </div>
  );
}

/* ================================================================
   FICHE PROSPECT
================================================================ */

function ProspectModal({
  prospect,
  onClose,
  onSave,
  onInteraction,
  onDelete,
}: {
  prospect: Prospect;
  onClose: () => void;
  onSave: (
    payload: Record<
      string,
      unknown
    >,
  ) => Promise<void>;
  onInteraction: (
    text: string,
    type: string,
  ) => Promise<void>;
  onDelete: (
    id: string,
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
            <div className="text-[10px] font-extrabold uppercase tracking-[0.25em] text-black/35">
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
            type="button"
            onClick={onClose}
            className="admin-white-button rounded-full p-2"
          >
            <X />
          </button>
        </div>

        <div className="mt-6 grid gap-4 sm:grid-cols-2">
          {[
            ["name", "Nom"],
            [
              "company",
              "Entreprise",
            ],
            ["email", "E-mail"],
            [
              "phone",
              "Téléphone",
            ],
            [
              "website",
              "Site internet",
            ],
            ["offer", "Offre"],
            [
              "estimated_value",
              "Valeur",
            ],
            [
              "recurring_value",
              "Récurrent",
            ],
            [
              "next_action",
              "Prochaine action",
            ],
            [
              "next_action_at",
              "Date de relance",
            ],
          ].map(
            ([key, label]) => (
              <label
                key={key}
                className="text-xs font-bold text-[#080808]"
              >
                <span className="mb-2 block text-black/45">
                  {label}
                </span>

                <input
                  type={
                    key.includes(
                      "value",
                    )
                      ? "number"
                      : key.includes(
                            "at",
                          )
                        ? "date"
                        : "text"
                  }
                  value={
                    (
                      form as Record<
                        string,
                        string
                      >
                    )[key]
                  }
                  onChange={(event) =>
                    setForm({
                      ...form,
                      [key]:
                        event.target
                          .value,
                    })
                  }
                  className="w-full rounded-xl border border-black/10 bg-white px-4 py-3 text-[#080808] outline-none focus:border-[#c8a45d]"
                />
              </label>
            ),
          )}

          <label className="text-xs font-bold text-[#080808]">
            <span className="mb-2 block text-black/45">
              Statut
            </span>

            <select
              value={form.status}
              onChange={(event) =>
                setForm({
                  ...form,
                  status:
                    event.target
                      .value,
                })
              }
              className="w-full rounded-xl border border-black/10 bg-white px-4 py-3 text-[#080808]"
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

          <label className="text-xs font-bold text-[#080808] sm:col-span-2">
            <span className="mb-2 block text-black/45">
              Notes
            </span>

            <textarea
              value={form.notes}
              onChange={(event) =>
                setForm({
                  ...form,
                  notes:
                    event.target
                      .value,
                })
              }
              rows={4}
              className="w-full rounded-xl border border-black/10 bg-white px-4 py-3 text-[#080808] outline-none focus:border-[#c8a45d]"
            />
          </label>
        </div>

        <div className="mt-6 flex flex-wrap gap-2">
          {prospect.phone && (
            <a
              href={`tel:${prospect.phone}`}
              className="admin-dark-button inline-flex items-center rounded-full px-4 py-2 text-xs font-bold"
            >
              <Phone
                size={14}
                className="mr-2"
              />
              Appeler
            </a>
          )}

          {prospect.email && (
            <a
              href={`mailto:${prospect.email}`}
              className="admin-white-button inline-flex items-center rounded-full border border-black/10 px-4 py-2 text-xs font-bold"
            >
              <Mail
                size={14}
                className="mr-2"
              />
              E-mail
            </a>
          )}
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
                  event.target
                    .value,
                )
              }
              placeholder="Note, appel, message…"
              className="min-w-0 flex-1 rounded-xl border border-black/10 bg-white px-4 py-3 text-[#080808]"
            />

            <button
              type="button"
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
              className="admin-gold-button rounded-full px-5 py-3 text-xs font-extrabold"
            >
              Ajouter
            </button>
          </div>
        </div>

        <div className="mt-7 flex flex-wrap justify-end gap-2">
          <button
            type="button"
            onClick={() =>
              onDelete(
                prospect.id,
              )
            }
            className="admin-danger-button rounded-full border border-red-200 px-5 py-3 text-xs font-bold"
          >
            Supprimer
          </button>

          <button
            type="button"
            onClick={onClose}
            className="admin-white-button rounded-full border border-black/10 px-5 py-3 text-xs font-bold"
          >
            Fermer
          </button>

          <button
            type="button"
            onClick={() =>
              onSave({
                ...form,
                estimated_value:
                  Number(
                    form.estimated_value,
                  ),
                recurring_value:
                  Number(
                    form.recurring_value,
                  ),
              })
            }
            className="admin-dark-button rounded-full px-5 py-3 text-xs font-extrabold"
          >
            Enregistrer
          </button>
        </div>
      </div>
    </Overlay>
  );
}

/* ================================================================
   NOUVEAU PROSPECT
================================================================ */

function NewProspect({
  onClose,
  onCreated,
  api,
}: {
  onClose: () => void;
  onCreated: () => void;
  api: (
    payload: Record<
      string,
      unknown
    >,
  ) => Promise<unknown>;
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

  return (
    <Overlay>
      <div className="w-full max-w-xl rounded-[2rem] bg-[#f4f4f1] p-7 shadow-2xl">
        <div className="flex items-center justify-between">
          <h2 className="text-2xl font-extrabold">
            Nouveau prospect
          </h2>

          <button
            type="button"
            onClick={onClose}
            className="admin-white-button rounded-full p-2"
          >
            <X />
          </button>
        </div>

        <div className="mt-6 grid gap-4 sm:grid-cols-2">
          {[
            ["name", "Nom"],
            [
              "company",
              "Entreprise",
            ],
            ["email", "E-mail"],
            [
              "phone",
              "Téléphone",
            ],
            [
              "website",
              "Site internet",
            ],
          ].map(
            ([key, label]) => (
              <label
                key={key}
                className="text-xs font-bold text-[#080808]"
              >
                <span className="mb-2 block text-black/45">
                  {label}
                </span>

                <input
                  type="text"
                  inputMode={
                    key === "email"
                      ? "email"
                      : key === "phone"
                        ? "tel"
                        : undefined
                  }
                  value={
                    (
                      form as Record<
                        string,
                        string
                      >
                    )[key]
                  }
                  onChange={(event) =>
                    setForm({
                      ...form,
                      [key]:
                        event.target
                          .value,
                    })
                  }
                  className="w-full rounded-xl border border-black/10 bg-white px-4 py-3 text-[#080808] outline-none focus:border-[#c8a45d]"
                />
              </label>
            ),
          )}

          <label className="text-xs font-bold text-[#080808] sm:col-span-2">
            <span className="mb-2 block text-black/45">
              Notes
            </span>

            <textarea
              value={form.notes}
              onChange={(event) =>
                setForm({
                  ...form,
                  notes:
                    event.target
                      .value,
                })
              }
              rows={4}
              className="w-full rounded-xl border border-black/10 bg-white px-4 py-3 text-[#080808]"
            />
          </label>
        </div>

        <div className="mt-6 flex justify-end gap-2">
          <button
            type="button"
            onClick={onClose}
            className="admin-white-button rounded-full border border-black/10 px-5 py-3 text-xs font-bold"
          >
            Annuler
          </button>

          <button
            type="button"
            disabled={busy}
            onClick={async () => {
              if (!form.name.trim()) {
                return;
              }

              setBusy(true);

              try {
                await api({
                  action:
                    "create_prospect",
                  ...form,
                });

                onCreated();
              } finally {
                setBusy(false);
              }
            }}
            className="admin-dark-button rounded-full px-5 py-3 text-xs font-extrabold disabled:opacity-40"
          >
            {busy
              ? "Création…"
              : "Créer le prospect"}
          </button>
        </div>
      </div>
    </Overlay>
  );
}

/* ================================================================
   COMPOSANTS UI
================================================================ */

function Title({
  eyebrow,
  title,
  text,
  action,
}: {
  eyebrow: string;
  title: string;
  text: string;
  action?: ReactNode;
}) {
  return (
    <div className="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
      <div>
        <div className="text-[10px] font-extrabold uppercase tracking-[0.25em] text-[#a17e32]">
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
  icon: typeof Users;
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
  action?: ReactNode;
  children: ReactNode;
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
  children: ReactNode;
}) {
  return (
    <div className="fixed inset-0 z-[70] flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
      {children}
    </div>
  );
}
import {
  useEffect,
  useMemo,
  useState,
  type ComponentType,
  type Dispatch,
  type SetStateAction,
} from "react";

import {
  ArrowLeft,
  BarChart3,
  CalendarDays,
  Check,
  ChevronRight,
  CircleDollarSign,
  Clock,
  ExternalLink,
  FileText,
  Gift,
  LayoutDashboard,
  LogOut,
  Mail,
  Menu,
  MessageCircle,
  Phone,
  Plus,
  RefreshCw,
  Search,
  Target,
  TrendingUp,
  UserPlus,
  Users,
  X,
} from "lucide-react";

/* ================================================================
   TYPES
================================================================ */

type ProspectStatus =
  | "new"
  | "contacted"
  | "qualified"
  | "meeting"
  | "proposal"
  | "negotiation"
  | "won"
  | "lost";

type ProspectSource =
  | "grand-plus"
  | "audit"
  | "booking"
  | "contact"
  | "manual"
  | string;

type Prospect = {
  id: string;
  name: string;
  company: string;
  email: string;
  phone: string;
  website: string;
  sector: string;
  source: ProspectSource;
  status: ProspectStatus | string;
  offer: string;
  estimated_value: number;
  recurring_value: number;
  created_at: string;
  updated_at?: string;
  last_contact_at?: string;
  next_action?: string;
  next_action_at?: string;
  notes?: string;
};

type Interaction = {
  id?: string;
  prospect_id?: string;
  text: string;
  type: string;
  created_at?: string;
};

type Appointment = {
  id?: string;
  name?: string;
  email?: string;
  phone?: string;
  date?: string;
  time?: string;
  message?: string;
  status?: string;
};

type GrandPlusParticipant = {
  id?: string;
  name?: string;
  email?: string;
  company?: string;
  phone?: string;
  created_at?: string;
  marketing_consent?: boolean;
};

type GrandPlusData = {
  participants?: GrandPlusParticipant[];
  winner?: GrandPlusParticipant | null;
  winner_email_sent?: boolean;
};

type StatsData = {
  prospects: number;
  won: number;
  lost: number;
  signed_revenue: number;
  potential_revenue: number;
};

type Data = {
  prospects: Prospect[];
  interactions: Interaction[];
  appointments: Appointment[];
  grand_plus: GrandPlusData;
  stats: StatsData;
};

type AvailabilityBlock = {
  id: string;
  date: string;
  all_day: boolean;
  start_time?: string;
  end_time?: string;
  reason?: string;
  created_at?: string;
};

type IconType = ComponentType<{
  size?: number | string;
  className?: string;
}>;

/* ================================================================
   CONSTANTES
================================================================ */

const statuses: [string, string][] = [
  ["new", "Nouveau"],
  ["contacted", "Contacté"],
  ["qualified", "Qualifié"],
  ["meeting", "Rendez-vous"],
  ["proposal", "Proposition"],
  ["negotiation", "Négociation"],
  ["won", "Gagné"],
  ["lost", "Perdu"],
];

const emptyData: Data = {
  prospects: [],
  interactions: [],
  appointments: [],
  grand_plus: {
    participants: [],
    winner: null,
    winner_email_sent: false,
  },
  stats: {
    prospects: 0,
    won: 0,
    lost: 0,
    signed_revenue: 0,
    potential_revenue: 0,
  },
};

const buttonLight =
  "inline-flex items-center justify-center rounded-xl border border-black/10 bg-white text-[#080808] font-bold transition hover:bg-black/[0.03]";

const buttonDark =
  "inline-flex items-center justify-center rounded-xl bg-[#080808] text-white font-bold transition hover:bg-black/90";

const buttonGold =
  "inline-flex items-center justify-center rounded-xl bg-[#c8a45d] text-[#080808] font-bold transition hover:bg-[#b8944c]";

/* ================================================================
   HELPERS
================================================================ */

function euro(value: number | string | undefined | null) {
  const amount = Number(value || 0);

  return new Intl.NumberFormat("fr-FR", {
    style: "currency",
    currency: "EUR",
    maximumFractionDigits: 0,
  }).format(amount);
}

function dateFr(value: string | undefined | null) {
  if (!value) return "—";

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return date.toLocaleDateString("fr-FR", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
  });
}

function dateTimeFr(value: string | undefined | null) {
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

function sourceLabel(source: string) {
  switch (source) {
    case "grand-plus":
      return "Grand+";

    case "audit":
      return "Audit";

    case "booking":
      return "Rendez-vous";

    case "contact":
      return "Contact";

    default:
      return "Manuel";
  }
}

function statusLabel(status: string) {
  return (
    statuses.find(([key]) => key === status)?.[1] ??
    status
  );
}

function statusClass(status: string) {
  switch (status) {
    case "won":
      return "!bg-emerald-100 !text-emerald-700";

    case "lost":
      return "!bg-red-100 !text-red-700";

    case "meeting":
      return "!bg-blue-100 !text-blue-700";

    case "proposal":
      return "!bg-purple-100 !text-purple-700";

    case "negotiation":
      return "!bg-orange-100 !text-orange-700";

    case "qualified":
      return "!bg-[#c8a45d]/20 !text-[#8a6a25]";

    case "contacted":
      return "!bg-slate-100 !text-slate-700";

    default:
      return "!bg-black/5 !text-black/60";
  }
}

/* ================================================================
   STATUS BADGE
================================================================ */

function StatusBadge({
  status,
}: {
  status: string;
}) {
  return (
    <span
      className={`inline-flex rounded-full px-3 py-1.5 text-[10px] font-extrabold uppercase tracking-[0.12em] ${statusClass(
        status,
      )}`}
    >
      {statusLabel(status)}
    </span>
  );
}

/* ================================================================
   COMPOSANT PRINCIPAL
================================================================ */

export default function AdminDashboard() {
  const [data, setData] =
    useState<Data>(emptyData);

  const [loading, setLoading] =
    useState(true);

  const [section, setSection] =
    useState("dashboard");

  const [selected, setSelected] =
    useState<Prospect | null>(null);

  const [query, setQuery] =
    useState("");

  const [sourceFilter, setSourceFilter] =
    useState("all");

  const [statusFilter, setStatusFilter] =
    useState("all");

  const [mobileMenu, setMobileMenu] =
    useState(false);

  const [toast, setToast] =
    useState("");

  const [newProspect, setNewProspect] =
    useState(false);

  /* ==============================================================
     DISPONIBILITÉS
  ============================================================== */

  const [availabilityBlocks, setAvailabilityBlocks] =
    useState<AvailabilityBlock[]>([]);

  const [availabilityLoading, setAvailabilityLoading] =
    useState(false);

  const [availabilityDate, setAvailabilityDate] =
    useState("");

  const [availabilityAllDay, setAvailabilityAllDay] =
    useState(true);

  const [availabilityStart, setAvailabilityStart] =
    useState("09:00");

  const [availabilityEnd, setAvailabilityEnd] =
    useState("18:00");

  const [availabilityReason, setAvailabilityReason] =
    useState("Indisponible");

  /* ==============================================================
     CHARGEMENT
  ============================================================== */

  async function load() {
    setLoading(true);

    try {
      const response = await fetch(
        "/admin-api.php?action=dashboard",
        {
          credentials: "same-origin",
          cache: "no-store",
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
            "Impossible de charger les données.",
        );
      }

      setData({
        prospects: Array.isArray(
          json.data?.prospects,
        )
          ? json.data.prospects
          : [],

        interactions: Array.isArray(
          json.data?.interactions,
        )
          ? json.data.interactions
          : [],

        appointments: Array.isArray(
          json.data?.appointments,
        )
          ? json.data.appointments
          : [],

        grand_plus:
          json.data?.grand_plus ??
          emptyData.grand_plus,

        stats:
          json.data?.stats ??
          emptyData.stats,
      });
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

  async function action(
    payload: Record<string, unknown>,
  ) {
    const response = await fetch(
      "/admin-api.php",
      {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
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
          "Une erreur est survenue.",
      );
    }

    return json;
  }

  useEffect(() => {
    load();
  }, []);

  /* ==============================================================
     DISPONIBILITÉS
  ============================================================== */

  async function loadAvailability() {
    setAvailabilityLoading(true);

    try {
      const response = await fetch(
        "/availability.php",
        {
          method: "GET",
          credentials: "same-origin",
          cache: "no-store",
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

      setAvailabilityBlocks(
        Array.isArray(json.blocks)
          ? json.blocks
          : [],
      );
    } catch (error) {
      setToast(
        error instanceof Error
          ? error.message
          : "Erreur.",
      );
    } finally {
      setAvailabilityLoading(false);
    }
  }

  async function addAvailabilityBlock() {
    if (!availabilityDate) {
      setToast(
        "Choisissez une date.",
      );

      return;
    }

    if (
      !availabilityAllDay &&
      (!availabilityStart ||
        !availabilityEnd)
    ) {
      setToast(
        "Indiquez une heure de début et une heure de fin.",
      );

      return;
    }

    if (
      !availabilityAllDay &&
      availabilityStart >= availabilityEnd
    ) {
      setToast(
        "L'heure de fin doit être après l'heure de début.",
      );

      return;
    }

    setAvailabilityLoading(true);

    try {
      const response = await fetch(
        "/availability.php",
        {
          method: "POST",
          headers: {
            "Content-Type":
              "application/json",
          },
          credentials: "same-origin",
          body: JSON.stringify({
            action: "add_block",
            date: availabilityDate,
            all_day: availabilityAllDay,
            start_time:
              availabilityAllDay
                ? null
                : availabilityStart,
            end_time:
              availabilityAllDay
                ? null
                : availabilityEnd,
            reason:
              availabilityReason.trim() ||
              "Indisponible",
          }),
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
            "Impossible d'ajouter l'indisponibilité.",
        );
      }

      setAvailabilityBlocks(
        Array.isArray(json.blocks)
          ? json.blocks
          : [],
      );

      setToast(
        "Indisponibilité ajoutée.",
      );

      setAvailabilityDate("");
      setAvailabilityAllDay(true);
      setAvailabilityStart("09:00");
      setAvailabilityEnd("18:00");
      setAvailabilityReason(
        "Indisponible",
      );
    } catch (error) {
      setToast(
        error instanceof Error
          ? error.message
          : "Erreur.",
      );
    } finally {
      setAvailabilityLoading(false);
    }
  }

  async function deleteAvailabilityBlock(
    id: string,
  ) {
    if (
      !window.confirm(
        "Supprimer cette indisponibilité ?",
      )
    ) {
      return;
    }

    setAvailabilityLoading(true);

    try {
      const response = await fetch(
        "/availability.php",
        {
          method: "POST",
          headers: {
            "Content-Type":
              "application/json",
          },
          credentials: "same-origin",
          body: JSON.stringify({
            action: "delete_block",
            id,
          }),
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
            "Impossible de supprimer l'indisponibilité.",
        );
      }

      setAvailabilityBlocks(
        Array.isArray(json.blocks)
          ? json.blocks
          : [],
      );

      setToast(
        "Indisponibilité supprimée.",
      );
    } catch (error) {
      setToast(
        error instanceof Error
          ? error.message
          : "Erreur.",
      );
    } finally {
      setAvailabilityLoading(false);
    }
  }

  useEffect(() => {
    if (section === "availability") {
      loadAvailability();
    }
  }, [section]);

  /* ==============================================================
     FILTRES
  ============================================================== */

  const filtered = useMemo(() => {
    const normalizedQuery =
      query.trim().toLowerCase();

    return data.prospects.filter(
      (prospect) => {
        const haystack = [
          prospect.name,
          prospect.company,
          prospect.email,
          prospect.phone,
          prospect.website,
          prospect.sector,
        ]
          .join(" ")
          .toLowerCase();

        const matchesQuery =
          !normalizedQuery ||
          haystack.includes(
            normalizedQuery,
          );

        const matchesSource =
          sourceFilter === "all" ||
          prospect.source ===
            sourceFilter;

        const matchesStatus =
          statusFilter === "all" ||
          prospect.status ===
            statusFilter;

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
    sourceFilter,
    statusFilter,
  ]);

  /* ==============================================================
     RELANCES
  ============================================================== */

  const today = new Date()
    .toISOString()
    .slice(0, 10);

  const urgent =
    data.prospects
      .filter((prospect) => {
        if (
          !prospect.next_action_at ||
          ["won", "lost"].includes(
            prospect.status,
          )
        ) {
          return false;
        }

        return (
          prospect.next_action_at ===
            today ||
          prospect.next_action_at <
            today
        );
      })
      .sort((a, b) =>
        (
          a.next_action_at || ""
        ).localeCompare(
          b.next_action_at || "",
        ),
      )
      .slice(0, 8);

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
      Clock,
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

  function navigate(name: string) {
    setSection(name);
    setSelected(null);
    setMobileMenu(false);
  }

  /* ==============================================================
     RENDER
  ============================================================== */

  return (
    <div className="min-h-screen !bg-[#f5f5f3] !text-[#080808]">
      {/* =========================================================
          SIDEBAR
      ========================================================= */}

      <aside
        className={`fixed inset-y-0 left-0 z-50 w-[270px] border-r border-white/10 !bg-[#080808] !text-white transition-transform duration-300 ${
          mobileMenu
            ? "translate-x-0"
            : "-translate-x-full lg:translate-x-0"
        }`}
      >
        <div className="flex h-full flex-col p-5">
          <div className="flex items-center justify-between px-2 py-3">
            <div className="text-2xl font-black tracking-[-0.06em] !text-white">
              Vitrine
              <span className="!text-[#c8a45d]">
                +
              </span>
            </div>

            <button
              type="button"
              onClick={() =>
                setMobileMenu(false)
              }
              className="!inline-flex !items-center !justify-center !rounded-full !border-0 !bg-white/10 !p-2 !text-white hover:!bg-white/20 lg:hidden"
            >
              <X size={20} />
            </button>
          </div>

          <div className="mt-8 px-2 text-[10px] font-bold uppercase tracking-[0.24em] !text-white/30">
            Cockpit commercial
          </div>

          <nav className="mt-4 grid gap-1.5">
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
                  className={`flex items-center gap-3 rounded-2xl px-3.5 py-3 text-left text-sm font-bold transition ${
                    section === key
                      ? "!bg-white !text-[#080808]"
                      : "!text-white/55 hover:!bg-white/5 hover:!text-white"
                  }`}
                >
                  <Icon size={18} />
                  <span>{label}</span>
                </button>
              ),
            )}
          </nav>

          <div className="mt-auto grid gap-2 border-t border-white/10 pt-5">
            <a
              href="/"
              className="flex items-center gap-3 rounded-2xl px-3.5 py-3 text-sm font-semibold !text-white/55 hover:!bg-white/5 hover:!text-white"
            >
              <ArrowLeft size={18} />
              Retour au site
            </a>

            <a
              href="/grand-plus-admin.php"
              className="flex items-center gap-3 rounded-2xl px-3.5 py-3 text-sm font-semibold !text-white/55 hover:!bg-white/5 hover:!text-white"
            >
              <Gift size={18} />
              Administration Grand+
            </a>

            <a
              href="/grand-plus-admin.php?logout=1"
              className="flex items-center gap-3 rounded-2xl px-3.5 py-3 text-sm font-semibold !text-white/55 hover:!bg-white/5 hover:!text-white"
            >
              <LogOut size={18} />
              Déconnexion
            </a>
          </div>
        </div>
      </aside>

      {/* =========================================================
          MAIN
      ========================================================= */}

      <main className="min-h-screen lg:ml-[270px]">
        <header className="sticky top-0 z-40 border-b border-black/10 !bg-[#f5f5f3]/90 backdrop-blur-xl">
          <div className="flex h-[74px] items-center justify-between px-5 sm:px-8 lg:px-10">
            <div className="flex items-center gap-3">
              <button
                type="button"
                onClick={() =>
                  setMobileMenu(true)
                }
                className="!inline-flex !items-center !justify-center !rounded-xl !border !border-black/10 !bg-white !p-2 !text-[#080808] lg:hidden"
              >
                <Menu size={20} />
              </button>

              <div>
                <div className="text-[10px] font-bold uppercase tracking-[0.24em] !text-black/35">
                  Administration privée
                </div>

                <div className="text-lg font-black !text-[#080808]">
                  {section ===
                  "dashboard"
                    ? "Vue d'ensemble"
                    : section ===
                        "grand-plus"
                      ? "Le Grand+"
                      : section ===
                          "stats"
                        ? "Statistiques"
                        : section ===
                            "calendar"
                          ? "Rendez-vous"
                          : section ===
                              "availability"
                            ? "Disponibilités"
                            : section ===
                                "pipeline"
                              ? "Pipeline commercial"
                              : "Prospects"}
                </div>
              </div>
            </div>

            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={load}
                className={`${buttonLight} hidden px-4 py-2 text-xs sm:inline-flex`}
              >
                <RefreshCw
                  size={14}
                  className="mr-2"
                />
                Actualiser
              </button>

              <div className="flex h-9 w-9 items-center justify-center rounded-full !bg-[#080808] text-xs font-black !text-[#c8a45d]">
                V+
              </div>
            </div>
          </div>
        </header>

        <div className="mx-auto max-w-[1500px] px-5 py-7 sm:px-8 lg:px-10 lg:py-10">
          {toast && (
            <div className="mb-5 flex items-center justify-between rounded-2xl border border-[#c8a45d]/30 !bg-[#c8a45d]/10 px-4 py-3 text-sm font-semibold !text-[#080808]">
              <span>{toast}</span>

              <button
                type="button"
                onClick={() =>
                  setToast("")
                }
                className="!inline-flex !items-center !justify-center !border-0 !bg-transparent !p-1 !text-[#080808]"
              >
                <X size={16} />
              </button>
            </div>
          )}

          {/* =====================================================
              ROUTAGE DES SECTIONS
          ===================================================== */}

          {loading ? (
            <div className="flex min-h-[50vh] items-center justify-center">
              <div className="h-7 w-7 animate-spin rounded-full border-2 border-black/10 border-t-[#c8a45d]" />
            </div>
          ) : section ===
            "dashboard" ? (
            <Dashboard
              data={data}
              urgent={urgent}
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
              source={sourceFilter}
              setSource={
                setSourceFilter
              }
              status={statusFilter}
              setStatus={
                setStatusFilter
              }
              onOpen={setSelected}
              onNew={() =>
                setNewProspect(true)
              }
            />
          ) : section ===
            "pipeline" ? (
            <Pipeline
              data={data}
              onOpen={setSelected}
              onMove={async (
                id,
                status,
              ) => {
                try {
                  await action({
                    action:
                      "update_prospect",
                    id,
                    status,
                  });

                  await load();

                  setToast(
                    "Statut mis à jour.",
                  );
                } catch (error) {
                  setToast(
                    error instanceof Error
                      ? error.message
                      : "Erreur",
                  );
                }
              }}
            />
          ) : section ===
            "calendar" ? (
            <Calendar
              data={data}
              onOpen={setSelected}
            />
          ) : section ===
            "availability" ? (
            <AvailabilityPanel
              blocks={
                availabilityBlocks
              }
              loading={
                availabilityLoading
              }
              date={
                availabilityDate
              }
              setDate={
                setAvailabilityDate
              }
              allDay={
                availabilityAllDay
              }
              setAllDay={
                setAvailabilityAllDay
              }
              startTime={
                availabilityStart
              }
              setStartTime={
                setAvailabilityStart
              }
              endTime={
                availabilityEnd
              }
              setEndTime={
                setAvailabilityEnd
              }
              reason={
                availabilityReason
              }
              setReason={
                setAvailabilityReason
              }
              onAdd={
                addAvailabilityBlock
              }
              onDelete={
                deleteAvailabilityBlock
              }
            />
          ) : section ===
            "grand-plus" ? (
            <GrandPlusPanel
              data={data}
              onOpen={setSelected}
            />
          ) : (
            <Stats data={data} />
          )}
        </div>
      </main>

      {/* =========================================================
          PROSPECT MODAL
      ========================================================= */}

      {selected &&
        (() => {
          const selectedProspect =
            selected;

          return (
            <ProspectModal
              prospect={
                selectedProspect
              }
              onClose={() =>
                setSelected(null)
              }
              onSave={async (
                payload,
              ) => {
                try {
                  await action({
                    action:
                      "update_prospect",
                    id: selectedProspect.id,
                    ...payload,
                  });

                  await load();

                  setToast(
                    "Prospect mis à jour.",
                  );
                } catch (error) {
                  setToast(
                    error instanceof Error
                      ? error.message
                      : "Erreur",
                  );
                }
              }}
              onInteraction={async (
                text,
                type,
              ) => {
                try {
                  await action({
                    action:
                      "add_interaction",
                    id: selectedProspect.id,
                    text,
                    type,
                  });

                  await load();

                  setToast(
                    "Interaction ajoutée.",
                  );
                } catch (error) {
                  setToast(
                    error instanceof Error
                      ? error.message
                      : "Erreur",
                  );
                }
              }}
            />
          );
        })()}

      {/* =========================================================
          NEW PROSPECT
      ========================================================= */}

     {newProspect && (
  <NewProspectModal
    onClose={() =>
      setNewProspect(false)
    }
    onCreate={async (prospect) => {
      await action({
        action: "create_prospect",
        ...prospect,
      });

      setNewProspect(false);
      setSection("prospects");
      setToast("Prospect créé.");
    }}
  />
)}
    </div>
  );
}

/* ================================================================
   DASHBOARD
================================================================ */

function Dashboard({
  data,
  urgent,
  onOpen,
  onNavigate,
}: {
  data: Data;
  urgent: Prospect[];
  onOpen: (
    prospect: Prospect,
  ) => void;
  onNavigate: (
    section: string,
  ) => void;
}) {
  const conversion =
    data.stats.prospects > 0
      ? (data.stats.won /
          data.stats.prospects) *
        100
      : 0;

  return (
    <div className="grid gap-8">
      <div>
        <div className="text-[10px] font-bold uppercase tracking-[0.28em] !text-[#c8a45d]">
          Vitrine+ / Commercial
        </div>

        <h1 className="mt-3 text-4xl font-black tracking-[-0.06em] !text-[#080808] sm:text-6xl">
          Pilotez votre
          <br />
          activité.
        </h1>

        <p className="mt-5 max-w-2xl text-base leading-7 !text-black/50">
          Un seul cockpit pour
          suivre vos prospects, vos
          rendez-vous, votre pipeline
          et votre chiffre d'affaires.
        </p>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <MetricCard
          icon={Users}
          label="Prospects actifs"
          value={String(
            data.stats.prospects -
              data.stats.won -
              data.stats.lost,
          )}
        />

        <MetricCard
          icon={CircleDollarSign}
          label="CA signé"
          value={euro(
            data.stats.signed_revenue,
          )}
        />

        <MetricCard
          icon={TrendingUp}
          label="CA potentiel"
          value={euro(
            data.stats.potential_revenue,
          )}
        />

        <MetricCard
          icon={Target}
          label="Conversion"
          value={`${conversion.toFixed(
            1,
          )} %`}
        />
      </div>

      <div className="grid gap-5 xl:grid-cols-[1.35fr_.65fr]">
        <div className="rounded-[30px] border border-black/10 !bg-white p-6 sm:p-8">
          <div className="flex items-center justify-between gap-4">
            <div>
              <div className="text-[10px] font-bold uppercase tracking-[0.18em] !text-black/35">
                Pipeline
              </div>

              <h2 className="mt-2 text-2xl font-black tracking-[-0.04em] !text-[#080808]">
                Opportunités commerciales
              </h2>
            </div>

            <button
              type="button"
              onClick={() =>
                onNavigate(
                  "pipeline",
                )
              }
              className={`${buttonLight} px-4 py-2 text-xs`}
            >
              Voir le pipeline
            </button>
          </div>

          <div className="mt-8 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            {statuses
              .map(
                ([key, label]) => {
                  const count =
                    data.prospects.filter(
                      (prospect) =>
                        prospect.status ===
                        key,
                    ).length;

                  return (
                    <div
                      key={key}
                      className="rounded-2xl !bg-[#f5f5f3] p-4"
                    >
                      <div className="text-[10px] font-bold uppercase tracking-[0.13em] !text-black/35">
                        {label}
                      </div>

                      <div className="mt-2 text-2xl font-black !text-[#080808]">
                        {count}
                      </div>
                    </div>
                  );
                },
              )}
          </div>
        </div>

        <div className="rounded-[30px] border border-black/10 !bg-white p-6 sm:p-8">
          <div className="text-[10px] font-bold uppercase tracking-[0.18em] !text-black/35">
            À traiter
          </div>

          <h2 className="mt-2 text-2xl font-black tracking-[-0.04em] !text-[#080808]">
            Relances
          </h2>

          <div className="mt-6 grid gap-2">
            {urgent.length === 0 ? (
              <div className="rounded-2xl !bg-[#f5f5f3] p-5 text-sm !text-black/45">
                Aucune relance urgente.
              </div>
            ) : (
              urgent.map(
                (prospect) => (
                  <button
                    key={prospect.id}
                    type="button"
                    onClick={() =>
                      onOpen(
                        prospect,
                      )
                    }
                    className="flex items-center justify-between gap-4 rounded-2xl !bg-[#f5f5f3] p-4 text-left transition hover:!bg-black/5"
                  >
                    <div className="min-w-0">
                      <div className="truncate font-bold !text-[#080808]">
                        {prospect.company ||
                          prospect.name}
                      </div>

                      <div className="mt-1 text-xs !text-black/40">
                        {prospect.next_action ||
                          "Relancer"}
                      </div>
                    </div>

                    <ChevronRight
                      size={17}
                      className="shrink-0 !text-black/30"
                    />
                  </button>
                ),
              )
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

/* ================================================================
   METRIC CARD
================================================================ */

function MetricCard({
  icon: Icon,
  label,
  value,
}: {
  icon: IconType;
  label: string;
  value: string;
}) {
  return (
    <div className="rounded-[26px] border border-black/10 !bg-white p-6">
      <div className="flex h-10 w-10 items-center justify-center rounded-xl !bg-[#080808] !text-[#c8a45d]">
        <Icon size={18} />
      </div>

      <div className="mt-5 text-[10px] font-bold uppercase tracking-[0.18em] !text-black/35">
        {label}
      </div>

      <div className="mt-2 text-3xl font-black tracking-[-0.05em] !text-[#080808]">
        {value}
      </div>
    </div>
  );
}

/* ================================================================
   PROSPECTS
================================================================ */

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
  setQuery: Dispatch<
    SetStateAction<string>
  >;
  source: string;
  setSource: Dispatch<
    SetStateAction<string>
  >;
  status: string;
  setStatus: Dispatch<
    SetStateAction<string>
  >;
  onOpen: (
    prospect: Prospect,
  ) => void;
  onNew: () => void;
}) {
  const sources = Array.from(
    new Set(
      data.prospects.map(
        (prospect) =>
          prospect.source,
      ),
    ),
  );

  return (
    <div className="grid gap-6">
      <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
          <div className="text-[10px] font-bold uppercase tracking-[0.28em] !text-[#c8a45d]">
            CRM
          </div>

          <h1 className="mt-2 text-4xl font-black tracking-[-0.06em] !text-[#080808]">
            Prospects
          </h1>
        </div>

        <button
          type="button"
          onClick={onNew}
          className={`${buttonGold} px-5 py-3 text-sm`}
        >
          <Plus
            size={17}
            className="mr-2"
          />
          Nouveau prospect
        </button>
      </div>

      <div className="rounded-[26px] border border-black/10 !bg-white p-5">
        <div className="grid gap-3 lg:grid-cols-[1fr_200px_200px]">
          <div className="relative">
            <Search
              size={17}
              className="absolute left-4 top-1/2 -translate-y-1/2 !text-black/30"
            />

            <input
              value={query}
              onChange={(event) =>
                setQuery(
                  event.target.value,
                )
              }
              placeholder="Rechercher un prospect..."
              className="h-12 w-full rounded-xl border border-black/10 !bg-[#f5f5f3] pl-11 pr-4 text-sm outline-none focus:border-[#c8a45d]"
            />
          </div>

          <select
            value={source}
            onChange={(event) =>
              setSource(
                event.target.value,
              )
            }
            className="h-12 rounded-xl border border-black/10 !bg-[#f5f5f3] px-4 text-sm outline-none"
          >
            <option value="all">
              Toutes les sources
            </option>

            {sources.map(
              (item) => (
                <option
                  key={item}
                  value={item}
                >
                  {sourceLabel(item)}
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
            className="h-12 rounded-xl border border-black/10 !bg-[#f5f5f3] px-4 text-sm outline-none"
          >
            <option value="all">
              Tous les statuts
            </option>

            {statuses.map(
              ([key, label]) => (
                <option
                  key={key}
                  value={key}
                >
                  {label}
                </option>
              ),
            )}
          </select>
        </div>
      </div>

      <div className="overflow-hidden rounded-[30px] border border-black/10 !bg-white">
        <div className="hidden grid-cols-[1.2fr_1fr_170px_150px_80px] gap-4 border-b border-black/10 px-6 py-4 text-[10px] font-bold uppercase tracking-[0.15em] !text-black/35 md:grid">
          <div>Prospect</div>
          <div>Contact</div>
          <div>Source</div>
          <div>Statut</div>
          <div />
        </div>

        {filtered.length === 0 ? (
          <div className="p-10 text-center text-sm !text-black/40">
            Aucun prospect trouvé.
          </div>
        ) : (
          <div>
            {filtered.map(
              (prospect) => (
                <button
                  key={prospect.id}
                  type="button"
                  onClick={() =>
                    onOpen(
                      prospect,
                    )
                  }
                  className="grid w-full gap-4 border-b border-black/5 px-6 py-5 text-left transition last:border-0 hover:!bg-[#f5f5f3] md:grid-cols-[1.2fr_1fr_170px_150px_80px] md:items-center"
                >
                  <div>
                    <div className="font-bold !text-[#080808]">
                      {prospect.company ||
                        prospect.name}
                    </div>

                    <div className="mt-1 text-xs !text-black/40">
                      {prospect.name}
                    </div>
                  </div>

                  <div>
                    <div className="text-sm !text-black/70">
                      {prospect.email ||
                        "—"}
                    </div>

                    <div className="mt-1 text-xs !text-black/35">
                      {prospect.phone ||
                        "—"}
                    </div>
                  </div>

                  <div className="text-sm !text-black/55">
                    {sourceLabel(
                      prospect.source,
                    )}
                  </div>

                  <div>
                    <StatusBadge
                      status={
                        prospect.status
                      }
                    />
                  </div>

                  <div className="flex justify-end">
                    <ChevronRight
                      size={17}
                      className="!text-black/30"
                    />
                  </div>
                </button>
              ),
            )}
          </div>
        )}
      </div>
    </div>
  );
}

/* ================================================================
   PIPELINE
================================================================ */

function Pipeline({
  data,
  onOpen,
  onMove,
}: {
  data: Data;
  onOpen: (
    prospect: Prospect,
  ) => void;
  onMove: (
    id: string,
    status: string,
  ) => Promise<void>;
}) {
  return (
    <div className="grid gap-6">
      <div>
        <div className="text-[10px] font-bold uppercase tracking-[0.28em] !text-[#c8a45d]">
          Commercial
        </div>

        <h1 className="mt-2 text-4xl font-black tracking-[-0.06em] !text-[#080808]">
          Pipeline
        </h1>
      </div>

      <div className="grid gap-4 overflow-x-auto pb-2 xl:grid-cols-4">
        {statuses
          .filter(
            ([key]) =>
              ![
                "new",
                "lost",
              ].includes(key),
          )
          .map(
            ([key, label]) => {
              const prospects =
                data.prospects.filter(
                  (prospect) =>
                    prospect.status ===
                    key,
                );

              return (
                <div
                  key={key}
                  className="min-w-[280px] rounded-[26px] border border-black/10 !bg-white p-4"
                >
                  <div className="flex items-center justify-between">
                    <div className="text-xs font-black !text-[#080808]">
                      {label}
                    </div>

                    <div className="rounded-full !bg-[#f5f5f3] px-2.5 py-1 text-[10px] font-black !text-black/45">
                      {prospects.length}
                    </div>
                  </div>

                  <div className="mt-4 grid gap-3">
                    {prospects.length ===
                    0 ? (
                      <div className="rounded-2xl !bg-[#f5f5f3] p-5 text-xs !text-black/35">
                        Aucun prospect.
                      </div>
                    ) : (
                      prospects.map(
                        (
                          prospect,
                        ) => (
                          <div
                            key={
                              prospect.id
                            }
                            className="rounded-2xl border border-black/10 !bg-[#f5f5f3] p-4"
                          >
                            <button
                              type="button"
                              onClick={() =>
                                onOpen(
                                  prospect,
                                )
                              }
                              className="w-full text-left"
                            >
                              <div className="font-bold !text-[#080808]">
                                {prospect.company ||
                                  prospect.name}
                              </div>

                              <div className="mt-1 text-xs !text-black/40">
                                {
                                  prospect.name
                                }
                              </div>

                              <div className="mt-3 font-black !text-[#080808]">
                                {euro(
                                  prospect.estimated_value,
                                )}
                              </div>
                            </button>

                            <div className="mt-4">
                              <select
                                value={
                                  prospect.status
                                }
                                onChange={(
                                  event,
                                ) =>
                                  onMove(
                                    prospect.id,
                                    event
                                      .target
                                      .value,
                                  )
                                }
                                className="h-9 w-full rounded-lg border border-black/10 !bg-white px-2 text-xs font-semibold outline-none"
                              >
                                {statuses.map(
                                  ([
                                    statusKey,
                                    statusLabelValue,
                                  ]) => (
                                    <option
                                      key={
                                        statusKey
                                      }
                                      value={
                                        statusKey
                                      }
                                    >
                                      {
                                        statusLabelValue
                                      }
                                    </option>
                                  ),
                                )}
                              </select>
                            </div>
                          </div>
                        ),
                      )
                    )}
                  </div>
                </div>
              );
            },
          )}
      </div>
    </div>
  );
}

/* ================================================================
   CALENDAR
================================================================ */

function Calendar({
  data,
  onOpen,
}: {
  data: Data;
  onOpen: (
    prospect: Prospect,
  ) => void;
}) {
  return (
    <div className="grid gap-6">
      <div>
        <div className="text-[10px] font-bold uppercase tracking-[0.28em] !text-[#c8a45d]">
          Agenda
        </div>

        <h1 className="mt-2 text-4xl font-black tracking-[-0.06em] !text-[#080808]">
          Rendez-vous
        </h1>
      </div>

      <div className="grid gap-3">
        {data.appointments.length ===
        0 ? (
          <div className="rounded-[26px] border border-black/10 !bg-white p-8 text-center text-sm !text-black/40">
            Aucun rendez-vous.
          </div>
        ) : (
          data.appointments.map(
            (appointment) => {
              const matchingProspect =
                data.prospects.find(
                  (prospect) =>
                    prospect.email &&
                    appointment.email &&
                    prospect.email ===
                      appointment.email,
                );

              return (
                <div
                  key={
                    appointment.id ||
                    `${appointment.date}-${appointment.time}-${appointment.email}`
                  }
                  className="rounded-[26px] border border-black/10 !bg-white p-6"
                >
                  <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div>
                      <div className="font-black !text-[#080808]">
                        {appointment.name ||
                          "Rendez-vous"}
                      </div>

                      <div className="mt-1 text-sm !text-black/45">
                        {appointment.date ||
                          "—"}{" "}
                        ·{" "}
                        {appointment.time ||
                          "—"}
                      </div>
                    </div>

                    {matchingProspect && (
                      <button
                        type="button"
                        onClick={() =>
                          onOpen(
                            matchingProspect,
                          )
                        }
                        className={`${buttonLight} px-4 py-2 text-xs`}
                      >
                        Voir le prospect
                      </button>
                    )}
                  </div>

                  <div className="mt-5 grid gap-3 text-sm sm:grid-cols-3">
                    <div>
                      <div className="text-[10px] font-bold uppercase tracking-[0.14em] !text-black/30">
                        Email
                      </div>

                      <div className="mt-1 !text-black/70">
                        {appointment.email ||
                          "—"}
                      </div>
                    </div>

                    <div>
                      <div className="text-[10px] font-bold uppercase tracking-[0.14em] !text-black/30">
                        Téléphone
                      </div>

                      <div className="mt-1 !text-black/70">
                        {appointment.phone ||
                          "—"}
                      </div>
                    </div>

                    <div>
                      <div className="text-[10px] font-bold uppercase tracking-[0.14em] !text-black/30">
                        Statut
                      </div>

                      <div className="mt-1 !text-black/70">
                        {appointment.status ||
                          "—"}
                      </div>
                    </div>
                  </div>

                  {appointment.message && (
                    <div className="mt-5 rounded-2xl !bg-[#f5f5f3] p-4 text-sm leading-6 !text-black/55">
                      {
                        appointment.message
                      }
                    </div>
                  )}
                </div>
              );
            },
          )
        )}
      </div>
    </div>
  );
}

/* ================================================================
   DISPONIBILITÉS
================================================================ */

function AvailabilityPanel({
  blocks,
  loading,
  date,
  setDate,
  allDay,
  setAllDay,
  startTime,
  setStartTime,
  endTime,
  setEndTime,
  reason,
  setReason,
  onAdd,
  onDelete,
}: {
  blocks: AvailabilityBlock[];
  loading: boolean;
  date: string;
  setDate: Dispatch<
    SetStateAction<string>
  >;
  allDay: boolean;
  setAllDay: Dispatch<
    SetStateAction<boolean>
  >;
  startTime: string;
  setStartTime: Dispatch<
    SetStateAction<string>
  >;
  endTime: string;
  setEndTime: Dispatch<
    SetStateAction<string>
  >;
  reason: string;
  setReason: Dispatch<
    SetStateAction<string>
  >;
  onAdd: () => Promise<void>;
  onDelete: (
    id: string,
  ) => Promise<void>;
}) {
  const sortedBlocks = [
    ...blocks,
  ].sort((a, b) => {
    const first = `${a.date} ${
      a.start_time || "00:00"
    }`;

    const second = `${b.date} ${
      b.start_time || "00:00"
    }`;

    return first.localeCompare(
      second,
    );
  });

  return (
    <div className="grid gap-6">
      <div>
        <div className="text-[10px] font-bold uppercase tracking-[0.28em] !text-[#c8a45d]">
          Agenda
        </div>

        <h1 className="mt-2 text-4xl font-black tracking-[-0.06em] !text-[#080808]">
          Disponibilités
        </h1>

        <p className="mt-4 max-w-2xl text-sm leading-6 !text-black/45">
          Bloquez une journée complète
          ou une plage horaire. Les
          créneaux concernés disparaîtront
          automatiquement du système de
          prise de rendez-vous.
        </p>
      </div>

      <div className="grid gap-5 xl:grid-cols-[.85fr_1.15fr]">
        <div className="rounded-[30px] border border-black/10 !bg-white p-6 sm:p-8">
          <div className="text-[10px] font-bold uppercase tracking-[0.18em] !text-black/35">
            Nouvelle indisponibilité
          </div>

          <h2 className="mt-2 text-2xl font-black tracking-[-0.04em] !text-[#080808]">
            Bloquer un créneau
          </h2>

          <div className="mt-7 grid gap-5">
            <label className="grid gap-2">
              <span className="text-xs font-bold !text-black/55">
                Date
              </span>

              <input
                type="date"
                value={date}
                onChange={(event) =>
                  setDate(
                    event.target.value,
                  )
                }
                className="h-12 rounded-xl border border-black/10 !bg-[#f5f5f3] px-4 text-sm outline-none focus:border-[#c8a45d]"
              />
            </label>

            <label className="flex items-center gap-3 rounded-2xl !bg-[#f5f5f3] p-4">
              <input
                type="checkbox"
                checked={allDay}
                onChange={(event) =>
                  setAllDay(
                    event.target.checked,
                  )
                }
                className="h-4 w-4 accent-[#c8a45d]"
              />

              <span className="text-sm font-bold !text-[#080808]">
                Journée complète
              </span>
            </label>

            {!allDay && (
              <div className="grid gap-4 sm:grid-cols-2">
                <label className="grid gap-2">
                  <span className="text-xs font-bold !text-black/55">
                    Début
                  </span>

                  <input
                    type="time"
                    value={startTime}
                    onChange={(
                      event,
                    ) =>
                      setStartTime(
                        event.target
                          .value,
                      )
                    }
                    className="h-12 rounded-xl border border-black/10 !bg-[#f5f5f3] px-4 text-sm outline-none focus:border-[#c8a45d]"
                  />
                </label>

                <label className="grid gap-2">
                  <span className="text-xs font-bold !text-black/55">
                    Fin
                  </span>

                  <input
                    type="time"
                    value={endTime}
                    onChange={(
                      event,
                    ) =>
                      setEndTime(
                        event.target
                          .value,
                      )
                    }
                    className="h-12 rounded-xl border border-black/10 !bg-[#f5f5f3] px-4 text-sm outline-none focus:border-[#c8a45d]"
                  />
                </label>
              </div>
            )}

            <label className="grid gap-2">
              <span className="text-xs font-bold !text-black/55">
                Motif
              </span>

              <input
                type="text"
                value={reason}
                onChange={(event) =>
                  setReason(
                    event.target.value,
                  )
                }
                placeholder="Ex. Congés, déplacement..."
                className="h-12 rounded-xl border border-black/10 !bg-[#f5f5f3] px-4 text-sm outline-none focus:border-[#c8a45d]"
              />
            </label>

            <button
              type="button"
              onClick={onAdd}
              disabled={loading}
              className={`${buttonGold} min-h-12 px-5 text-sm disabled:cursor-not-allowed disabled:opacity-50`}
            >
              {loading ? (
                <RefreshCw
                  size={16}
                  className="mr-2 animate-spin"
                />
              ) : (
                <Plus
                  size={16}
                  className="mr-2"
                />
              )}

              Bloquer ce créneau
            </button>
          </div>
        </div>

        <div className="rounded-[30px] border border-black/10 !bg-white p-6 sm:p-8">
          <div className="flex items-center justify-between gap-4">
            <div>
              <div className="text-[10px] font-bold uppercase tracking-[0.18em] !text-black/35">
                Planning
              </div>

              <h2 className="mt-2 text-2xl font-black tracking-[-0.04em] !text-[#080808]">
                Indisponibilités
              </h2>
            </div>

            <div className="rounded-full !bg-[#f5f5f3] px-3 py-1.5 text-xs font-black !text-black/45">
              {blocks.length}
            </div>
          </div>

          <div className="mt-7 grid gap-3">
            {loading &&
            blocks.length ===
              0 ? (
              <div className="flex min-h-[180px] items-center justify-center">
                <RefreshCw
                  size={22}
                  className="animate-spin !text-[#c8a45d]"
                />
              </div>
            ) : sortedBlocks.length ===
              0 ? (
              <div className="rounded-2xl !bg-[#f5f5f3] p-6 text-center text-sm !text-black/40">
                Aucune indisponibilité
                enregistrée.
              </div>
            ) : (
              sortedBlocks.map(
                (block) => (
                  <div
                    key={block.id}
                    className="flex flex-col justify-between gap-4 rounded-2xl !bg-[#f5f5f3] p-5 sm:flex-row sm:items-center"
                  >
                    <div className="flex items-start gap-4">
                      <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl !bg-[#080808] !text-[#c8a45d]">
                        <Clock
                          size={17}
                        />
                      </div>

                      <div>
                        <div className="font-black !text-[#080808]">
                          {dateFr(
                            block.date,
                          )}
                        </div>

                        <div className="mt-1 text-sm !text-black/45">
                          {block.all_day
                            ? "Journée complète"
                            : `${block.start_time || "—"} → ${
                                block.end_time ||
                                "—"
                              }`}
                        </div>

                        {block.reason && (
                          <div className="mt-2 text-xs font-semibold !text-black/35">
                            {
                              block.reason
                            }
                          </div>
                        )}
                      </div>
                    </div>

                    <button
                      type="button"
                      onClick={() =>
                        onDelete(
                          block.id,
                        )
                      }
                      disabled={loading}
                      className="inline-flex items-center justify-center rounded-xl border border-red-200 !bg-white px-4 py-2 text-xs font-bold !text-red-600 transition hover:!bg-red-50 disabled:opacity-50"
                    >
                      <X
                        size={14}
                        className="mr-2"
                      />
                      Supprimer
                    </button>
                  </div>
                ),
              )
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

/* ================================================================
   GRAND+
================================================================ */

function GrandPlusPanel({
  data,
  onOpen,
}: {
  data: Data;
  onOpen: (
    prospect: Prospect,
  ) => void;
}) {
  const participants =
    data.grand_plus
      .participants || [];

  const winner =
    data.grand_plus.winner;

  return (
    <div className="grid gap-6">
      <div>
        <div className="text-[10px] font-bold uppercase tracking-[0.28em] !text-[#c8a45d]">
          Vitrine+
        </div>

        <h1 className="mt-2 text-4xl font-black tracking-[-0.06em] !text-[#080808]">
          Le Grand+
        </h1>

        <p className="mt-4 max-w-2xl text-sm leading-6 !text-black/45">
          Suivez les participations
          et le gagnant du tirage.
        </p>
      </div>

      <div className="grid gap-5 lg:grid-cols-3">
        <MetricCard
          icon={Users}
          label="Participants"
          value={String(
            participants.length,
          )}
        />

        <MetricCard
          icon={Gift}
          label="Gagnant"
          value={
            winner?.name ||
            "À tirer"
          }
        />

        <MetricCard
          icon={Mail}
          label="Email gagnant"
          value={
            data.grand_plus
              .winner_email_sent
              ? "Envoyé"
              : "Non envoyé"
          }
        />
      </div>

      <div className="rounded-[30px] border border-black/10 !bg-white p-6 sm:p-8">
        <div className="flex items-center justify-between gap-4">
          <div>
            <div className="text-[10px] font-bold uppercase tracking-[0.18em] !text-black/35">
              Participations
            </div>

            <h2 className="mt-2 text-2xl font-black tracking-[-0.04em] !text-[#080808]">
              Participants au Grand+
            </h2>
          </div>
        </div>

        <div className="mt-7 grid gap-3">
          {participants.length ===
          0 ? (
            <div className="rounded-2xl !bg-[#f5f5f3] p-6 text-center text-sm !text-black/40">
              Aucun participant.
            </div>
          ) : (
            participants.map(
              (
                participant,
                index,
              ) => {
                const prospect =
                  data.prospects.find(
                    (item) =>
                      participant.email &&
                      item.email ===
                        participant.email,
                  );

                return (
                  <div
                    key={
                      participant.id ||
                      participant.email ||
                      index
                    }
                    className="flex flex-col justify-between gap-4 rounded-2xl !bg-[#f5f5f3] p-5 sm:flex-row sm:items-center"
                  >
                    <div>
                      <div className="font-bold !text-[#080808]">
                        {participant.name ||
                          "Participant"}
                      </div>

                      <div className="mt-1 text-sm !text-black/45">
                        {
                          participant.email
                        }
                      </div>

                      <div className="mt-1 text-xs !text-black/30">
                        {dateTimeFr(
                          participant.created_at,
                        )}
                      </div>
                    </div>

                    {prospect && (
                      <button
                        type="button"
                        onClick={() =>
                          onOpen(
                            prospect,
                          )
                        }
                        className={`${buttonLight} px-4 py-2 text-xs`}
                      >
                        Voir le prospect
                      </button>
                    )}
                  </div>
                );
              },
            )
          )}
        </div>
      </div>
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
  const total =
    data.prospects.length;

  const averageValue =
    total > 0
      ? data.prospects.reduce(
          (
            sum,
            prospect,
          ) =>
            sum +
            Number(
              prospect.estimated_value ||
                0,
            ),
          0,
        ) / total
      : 0;

  return (
    <div className="grid gap-6">
      <div>
        <div className="text-[10px] font-bold uppercase tracking-[0.28em] !text-[#c8a45d]">
          Analyse
        </div>

        <h1 className="mt-2 text-4xl font-black tracking-[-0.06em] !text-[#080808]">
          Statistiques
        </h1>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <MetricCard
          icon={Users}
          label="Prospects"
          value={String(total)}
        />

        <MetricCard
          icon={Check}
          label="Affaires gagnées"
          value={String(
            data.stats.won,
          )}
        />

        <MetricCard
          icon={CircleDollarSign}
          label="Valeur moyenne"
          value={euro(
            averageValue,
          )}
        />

        <MetricCard
          icon={TrendingUp}
          label="CA potentiel"
          value={euro(
            data.stats.potential_revenue,
          )}
        />
      </div>

      <div className="rounded-[30px] border border-black/10 !bg-white p-6 sm:p-8">
        <div className="text-[10px] font-bold uppercase tracking-[0.18em] !text-black/35">
          Répartition
        </div>

        <div className="mt-7 grid gap-3">
          {statuses.map(
            ([key, label]) => {
              const count =
                data.prospects.filter(
                  (prospect) =>
                    prospect.status ===
                    key,
                ).length;

              const percentage =
                total > 0
                  ? (count /
                      total) *
                    100
                  : 0;

              return (
                <div
                  key={key}
                  className="grid gap-2"
                >
                  <div className="flex items-center justify-between text-sm">
                    <span className="font-bold !text-[#080808]">
                      {label}
                    </span>

                    <span className="!text-black/40">
                      {count}
                    </span>
                  </div>

                  <div className="h-2 overflow-hidden rounded-full !bg-[#f5f5f3]">
                    <div
                      className="h-full rounded-full !bg-[#c8a45d]"
                      style={{
                        width: `${percentage}%`,
                      }}
                    />
                  </div>
                </div>
              );
            },
          )}
        </div>
      </div>
    </div>
  );
}

/* ================================================================
   PROSPECT MODAL
================================================================ */

function ProspectModal({
  prospect,
  onClose,
  onSave,
  onInteraction,
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
}) {
  const [form, setForm] =
    useState({
      status: prospect.status,
      offer: prospect.offer,

      estimated_value: String(
        prospect.estimated_value ||
          "",
      ),

      recurring_value: String(
        prospect.recurring_value ||
          "",
      ),

      last_contact_at:
        prospect.last_contact_at ||
        "",

      next_action:
        prospect.next_action ||
        "",

      next_action_at:
        prospect.next_action_at ||
        "",

      notes: prospect.notes || "",
    });

  const [interaction, setInteraction] =
    useState("");

  const [type, setType] =
    useState("Note");

  const [saving, setSaving] =
    useState(false);

  const [sendingInteraction, setSendingInteraction] =
    useState(false);

  async function save() {
    setSaving(true);

    try {
      await onSave({
        status: form.status,
        offer: form.offer,
        estimated_value:
          Number(
            form.estimated_value ||
              0,
          ),
        recurring_value:
          Number(
            form.recurring_value ||
              0,
          ),
        last_contact_at:
          form.last_contact_at,
        next_action:
          form.next_action,
        next_action_at:
          form.next_action_at,
        notes: form.notes,
      });
    } finally {
      setSaving(false);
    }
  }

  async function addInteraction() {
    if (!interaction.trim()) {
      return;
    }

    setSendingInteraction(true);

    try {
      await onInteraction(
        interaction.trim(),
        type,
      );

      setInteraction("");
    } finally {
      setSendingInteraction(false);
    }
  }

  return (
    <div className="fixed inset-0 z-[100] overflow-y-auto !bg-black/50 p-4 backdrop-blur-sm">
      <div className="mx-auto my-6 max-w-5xl rounded-[30px] !bg-[#f5f5f3] shadow-2xl">
        <div className="flex items-center justify-between border-b border-black/10 px-6 py-5 sm:px-8">
          <div>
            <div className="text-[10px] font-bold uppercase tracking-[0.2em] !text-[#c8a45d]">
              Fiche prospect
            </div>

            <h2 className="mt-1 text-2xl font-black tracking-[-0.04em] !text-[#080808]">
              {prospect.company ||
                prospect.name}
            </h2>
          </div>

          <button
            type="button"
            onClick={onClose}
            className="!inline-flex !items-center !justify-center !rounded-full !border !border-black/10 !bg-white !p-2 !text-[#080808]"
          >
            <X size={18} />
          </button>
        </div>

        <div className="grid gap-5 p-5 sm:p-8 lg:grid-cols-[1.1fr_.9fr]">
          <div className="grid gap-5">
            <div className="rounded-[24px] !bg-white p-6">
              <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                  <div className="font-bold !text-[#080808]">
                    {prospect.name}
                  </div>

                  <div className="mt-2 text-xs !text-black/45">
                    {sourceLabel(
                      prospect.source,
                    )}{" "}
                    ·{" "}
                    {dateFr(
                      prospect.created_at,
                    )}
                  </div>
                </div>

                <StatusBadge
                  status={
                    form.status
                  }
                />
              </div>

              <div className="mt-6 grid gap-4 sm:grid-cols-2">
                <InfoItem
                  icon={Mail}
                  label="Email"
                  value={
                    prospect.email ||
                    "—"
                  }
                />

                <InfoItem
                  icon={Phone}
                  label="Téléphone"
                  value={
                    prospect.phone ||
                    "—"
                  }
                />

                <InfoItem
                  icon={ExternalLink}
                  label="Site"
                  value={
                    prospect.website ||
                    "—"
                  }
                />

                <InfoItem
                  icon={Target}
                  label="Secteur"
                  value={
                    prospect.sector ||
                    "—"
                  }
                />
              </div>
            </div>

            <div className="rounded-[24px] !bg-white p-6">
              <div className="text-[10px] font-bold uppercase tracking-[0.18em] !text-black/35">
                Commercial
              </div>

              <div className="mt-5 grid gap-4 sm:grid-cols-2">
                <label className="grid gap-2">
                  <span className="text-xs font-bold !text-black/55">
                    Statut
                  </span>

                  <select
                    value={
                      form.status
                    }
                    onChange={(
                      event,
                    ) =>
                      setForm(
                        (
                          current,
                        ) => ({
                          ...current,
                          status:
                            event
                              .target
                              .value,
                        }),
                      )
                    }
                    className="h-11 rounded-xl border border-black/10 !bg-[#f5f5f3] px-3 text-sm outline-none"
                  >
                    {statuses.map(
                      ([
                        key,
                        label,
                      ]) => (
                        <option
                          key={key}
                          value={key}
                        >
                          {label}
                        </option>
                      ),
                    )}
                  </select>
                </label>

                <label className="grid gap-2">
                  <span className="text-xs font-bold !text-black/55">
                    Offre
                  </span>

                  <input
                    value={
                      form.offer
                    }
                    onChange={(
                      event,
                    ) =>
                      setForm(
                        (
                          current,
                        ) => ({
                          ...current,
                          offer:
                            event
                              .target
                              .value,
                        }),
                      )
                    }
                    className="h-11 rounded-xl border border-black/10 !bg-[#f5f5f3] px-3 text-sm outline-none"
                  />
                </label>

                <label className="grid gap-2">
                  <span className="text-xs font-bold !text-black/55">
                    Valeur estimée
                  </span>

                  <input
                    type="number"
                    value={
                      form.estimated_value
                    }
                    onChange={(
                      event,
                    ) =>
                      setForm(
                        (
                          current,
                        ) => ({
                          ...current,
                          estimated_value:
                            event
                              .target
                              .value,
                        }),
                      )
                    }
                    className="h-11 rounded-xl border border-black/10 !bg-[#f5f5f3] px-3 text-sm outline-none"
                  />
                </label>

                <label className="grid gap-2">
                  <span className="text-xs font-bold !text-black/55">
                    Récurrent
                  </span>

                  <input
                    type="number"
                    value={
                      form.recurring_value
                    }
                    onChange={(
                      event,
                    ) =>
                      setForm(
                        (
                          current,
                        ) => ({
                          ...current,
                          recurring_value:
                            event
                              .target
                              .value,
                        }),
                      )
                    }
                    className="h-11 rounded-xl border border-black/10 !bg-[#f5f5f3] px-3 text-sm outline-none"
                  />
                </label>

                <label className="grid gap-2">
                  <span className="text-xs font-bold !text-black/55">
                    Prochaine action
                  </span>

                  <input
                    value={
                      form.next_action
                    }
                    onChange={(
                      event,
                    ) =>
                      setForm(
                        (
                          current,
                        ) => ({
                          ...current,
                          next_action:
                            event
                              .target
                              .value,
                        }),
                      )
                    }
                    className="h-11 rounded-xl border border-black/10 !bg-[#f5f5f3] px-3 text-sm outline-none"
                  />
                </label>

                <label className="grid gap-2">
                  <span className="text-xs font-bold !text-black/55">
                    Date de relance
                  </span>

                  <input
                    type="date"
                    value={
                      form.next_action_at
                    }
                    onChange={(
                      event,
                    ) =>
                      setForm(
                        (
                          current,
                        ) => ({
                          ...current,
                          next_action_at:
                            event
                              .target
                              .value,
                        }),
                      )
                    }
                    className="h-11 rounded-xl border border-black/10 !bg-[#f5f5f3] px-3 text-sm outline-none"
                  />
                </label>
              </div>

              <label className="mt-4 grid gap-2">
                <span className="text-xs font-bold !text-black/55">
                  Notes
                </span>

                <textarea
                  value={
                    form.notes
                  }
                  onChange={(
                    event,
                  ) =>
                    setForm(
                      (
                        current,
                      ) => ({
                        ...current,
                        notes:
                          event
                            .target
                            .value,
                      }),
                    )
                  }
                  rows={5}
                  className="resize-none rounded-xl border border-black/10 !bg-[#f5f5f3] p-3 text-sm outline-none"
                />
              </label>

              <div className="mt-5 flex justify-end">
                <button
                  type="button"
                  onClick={save}
                  disabled={saving}
                  className={`${buttonGold} px-5 py-3 text-sm disabled:opacity-50`}
                >
                  {saving ? (
                    <RefreshCw
                      size={15}
                      className="mr-2 animate-spin"
                    />
                  ) : (
                    <Check
                      size={15}
                      className="mr-2"
                    />
                  )}
                  Enregistrer
                </button>
              </div>
            </div>
          </div>

          <div className="grid gap-5">
            <div className="rounded-[24px] !bg-white p-6">
              <div className="text-[10px] font-bold uppercase tracking-[0.18em] !text-black/35">
                Nouvelle interaction
              </div>

              <h3 className="mt-2 text-xl font-black !text-[#080808]">
                Ajouter une note
              </h3>

              <div className="mt-5 grid gap-4">
                <select
                  value={type}
                  onChange={(
                    event,
                  ) =>
                    setType(
                      event.target
                        .value,
                    )
                  }
                  className="h-11 rounded-xl border border-black/10 !bg-[#f5f5f3] px-3 text-sm outline-none"
                >
                  <option>
                    Note
                  </option>
                  <option>
                    Appel
                  </option>
                  <option>
                    Email
                  </option>
                  <option>
                    WhatsApp
                  </option>
                  <option>
                    Rendez-vous
                  </option>
                </select>

                <textarea
                  value={
                    interaction
                  }
                  onChange={(
                    event,
                  ) =>
                    setInteraction(
                      event.target
                        .value,
                    )
                  }
                  rows={6}
                  placeholder="Écrivez votre note..."
                  className="resize-none rounded-xl border border-black/10 !bg-[#f5f5f3] p-3 text-sm outline-none"
                />

                <button
                  type="button"
                  onClick={
                    addInteraction
                  }
                  disabled={
                    sendingInteraction ||
                    !interaction.trim()
                  }
                  className={`${buttonDark} min-h-11 px-4 text-sm disabled:opacity-50`}
                >
                  {sendingInteraction ? (
                    <RefreshCw
                      size={15}
                      className="mr-2 animate-spin"
                    />
                  ) : (
                    <MessageCircle
                      size={15}
                      className="mr-2"
                    />
                  )}
                  Ajouter
                </button>
              </div>
            </div>

            <div className="rounded-[24px] !bg-white p-6">
              <div className="text-[10px] font-bold uppercase tracking-[0.18em] !text-black/35">
                Informations
              </div>

              <div className="mt-5 grid gap-4">
                <InfoRow
                  label="Créé le"
                  value={dateTimeFr(
                    prospect.created_at,
                  )}
                />

                <InfoRow
                  label="Dernier contact"
                  value={dateTimeFr(
                    prospect.last_contact_at,
                  )}
                />

                <InfoRow
                  label="Valeur estimée"
                  value={euro(
                    prospect.estimated_value,
                  )}
                />

                <InfoRow
                  label="Récurrent"
                  value={euro(
                    prospect.recurring_value,
                  )}
                />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

/* ================================================================
   INFO ITEM
================================================================ */

function InfoItem({
  icon: Icon,
  label,
  value,
}: {
  icon: IconType;
  label: string;
  value: string;
}) {
  return (
    <div className="rounded-2xl !bg-[#f5f5f3] p-4">
      <div className="flex items-center gap-2">
        <Icon
          size={14}
          className="!text-[#c8a45d]"
        />

        <span className="text-[10px] font-bold uppercase tracking-[0.14em] !text-black/35">
          {label}
        </span>
      </div>

      <div className="mt-2 break-words text-sm font-semibold !text-[#080808]">
        {value}
      </div>
    </div>
  );
}

/* ================================================================
   INFO ROW
================================================================ */

function InfoRow({
  label,
  value,
}: {
  label: string;
  value: string;
}) {
  return (
    <div className="flex items-center justify-between gap-4 border-b border-black/5 pb-3 last:border-0 last:pb-0">
      <span className="text-xs font-semibold !text-black/40">
        {label}
      </span>

      <span className="text-right text-sm font-bold !text-[#080808]">
        {value}
      </span>
    </div>
  );
}

/* ================================================================
   NEW PROSPECT MODAL
================================================================ */

function NewProspectModal({
  onClose,
  onCreate,
}: {
  onClose: () => void;
  onCreate: (
    prospect: Record<string, string>,
  ) => Promise<void>;
}) {
  const [form, setForm] = useState({
    name: "",
    company: "",
    email: "",
    phone: "",
    website: "",
    notes: "",
  });

  const [error, setError] = useState("");

  function updateField(
    field: keyof typeof form,
    value: string,
  ) {
    setForm((current) => ({
      ...current,
      [field]: value,
    }));

    if (error) {
      setError("");
    }
  }

  function normalizeWebsite(value: string) {
    const website = value.trim();

    if (!website) {
      return "";
    }

    if (
      website.startsWith("http://") ||
      website.startsWith("https://")
    ) {
      return website;
    }

    return `https://${website}`;
  }

  function validateEmail(value: string) {
    if (!value.trim()) {
      return true;
    }

    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(
      value.trim(),
    );
  }

  function validateWebsite(value: string) {
    if (!value.trim()) {
      return true;
    }

    try {
      const url = new URL(
        normalizeWebsite(value),
      );

      return (
        url.protocol === "http:" ||
        url.protocol === "https:"
      );
    } catch {
      return false;
    }
  }

  async function handleCreate() {
    const name = form.name.trim();
    const company = form.company.trim();
    const email = form.email.trim();
    const phone = form.phone.trim();
    const website = normalizeWebsite(
      form.website,
    );
    const notes = form.notes.trim();

    if (!name) {
      setError("Veuillez renseigner le nom du prospect.");
      return;
    }

    if (!company) {
      setError(
        "Veuillez renseigner le nom de l'entreprise.",
      );
      return;
    }

    if (!validateEmail(email)) {
      setError(
        "Veuillez renseigner une adresse e-mail valide.",
      );
      return;
    }

    if (!validateWebsite(form.website)) {
      setError(
        "L'adresse du site internet semble invalide.",
      );
      return;
    }

    try {
      await onCreate({
        name,
        company,
        email,
        phone,
        website,
        notes,
      });
    } catch (error) {
      setError(
        error instanceof Error
          ? error.message
          : "Impossible de créer le prospect.",
      );
    }
  }

  return (
    <div className="fixed inset-0 z-[100] flex items-center justify-center !bg-black/50 p-4 backdrop-blur-sm">
      <div className="w-full max-w-2xl rounded-[30px] !bg-[#f5f5f3] p-6 shadow-2xl sm:p-8">
        <div className="flex justify-between">
          <div>
            <div className="text-[10px] font-bold uppercase tracking-[0.2em] !text-[#c8a45d]">
              Nouveau
            </div>

            <h2 className="mt-2 text-3xl font-black tracking-[-0.05em] !text-[#080808]">
              Ajouter un prospect
            </h2>
          </div>

          <button
            type="button"
            onClick={onClose}
            className="!inline-flex !items-center !justify-center !rounded-full !border !border-black/10 !bg-white !p-2 !text-[#080808]"
          >
            <X size={18} />
          </button>
        </div>

        <div className="mt-7 grid gap-4 sm:grid-cols-2">
          <Field label="Nom *">
            <input
              type="text"
              value={form.name}
              onChange={(event) =>
                updateField(
                  "name",
                  event.target.value,
                )
              }
              placeholder="Jean Dupont"
              autoComplete="name"
            />
          </Field>

          <Field label="Entreprise *">
            <input
              type="text"
              value={form.company}
              onChange={(event) =>
                updateField(
                  "company",
                  event.target.value,
                )
              }
              placeholder="Entreprise"
              autoComplete="organization"
            />
          </Field>

          <Field label="E-mail">
            <input
  type="text"
  inputMode="email"
  autoComplete="email"
  value={form.email}
  onChange={(event) =>
    setForm({
      ...form,
      email: event.target.value,
    })
  }
/>
          </Field>

          <Field label="Téléphone">
            <input
  type="text"
  inputMode="tel"
  autoComplete="tel"
  value={form.phone}
  onChange={(event) =>
    setForm({
      ...form,
      phone: event.target.value,
    })
  }
/>
          </Field>

          <Field label="Site internet">
            <input
              type="text"
              value={form.website}
              onChange={(event) =>
                updateField(
                  "website",
                  event.target.value,
                )
              }
              placeholder="entreprise.fr"
              autoComplete="url"
              inputMode="url"
            />
          </Field>

          <Field label="Notes">
            <input
              type="text"
              value={form.notes}
              onChange={(event) =>
                updateField(
                  "notes",
                  event.target.value,
                )
              }
              placeholder="Informations complémentaires..."
            />
          </Field>
        </div>

        {error && (
          <div className="mt-5 rounded-2xl border border-red-200 !bg-red-50 px-4 py-3 text-sm font-medium !text-red-700">
            {error}
          </div>
        )}

        <div className="mt-7 flex justify-end gap-2">
          <button
            type="button"
            onClick={onClose}
            className={`${buttonLight} px-5 py-3 text-sm`}
          >
            Annuler
          </button>

          <button
            type="button"
            disabled={
              !form.name.trim() ||
              !form.company.trim()
            }
            onClick={handleCreate}
            className={`${buttonDark} px-5 py-3 text-sm disabled:cursor-not-allowed disabled:opacity-40`}
          >
            Créer le prospect
          </button>
        </div>
      </div>
    </div>
  );
}

/* ================================================================
   FIELD
================================================================ */

function Field({
  label,
  children,
}: {
  label: string;
  children: React.ReactNode;
}) {
  return (
    <label className="grid gap-2 text-xs font-bold !text-black/45">
      {label}

      <div
        className="
          [&_input]:w-full
          [&_input]:rounded-2xl
          [&_input]:!bg-[#f5f5f3]
          [&_input]:px-4
          [&_input]:py-3
          [&_input]:text-sm
          [&_input]:font-medium
          [&_input]:!text-[#080808]
          [&_input]:outline-none
          [&_input]:placeholder:!text-black/30
          [&_input]:focus:border-[#c8a45d]
          [&_input]:focus:ring-2
          [&_input]:focus:ring-[#c8a45d]/10

          [&_select]:w-full
          [&_select]:rounded-2xl
          [&_select]:!bg-[#f5f5f3]
          [&_select]:px-4
          [&_select]:py-3
          [&_select]:text-sm
          [&_select]:font-medium
          [&_select]:!text-[#080808]
          [&_select]:outline-none
          [&_select]:focus:border-[#c8a45d]
          [&_select]:focus:ring-2
          [&_select]:focus:ring-[#c8a45d]/10

          [&_textarea]:w-full
          [&_textarea]:rounded-2xl
          [&_textarea]:!bg-[#f5f5f3]
          [&_textarea]:px-4
          [&_textarea]:py-3
          [&_textarea]:text-sm
          [&_textarea]:font-medium
          [&_textarea]:!text-[#080808]
          [&_textarea]:outline-none
          [&_textarea]:focus:border-[#c8a45d]
          [&_textarea]:focus:ring-2
          [&_textarea]:focus:ring-[#c8a45d]/10
        "
      >
        {children}
      </div>
    </label>
  );
}
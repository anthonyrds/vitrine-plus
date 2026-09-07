import { useEffect, useMemo, useState } from "react";
import {
  Activity,
  ArrowLeft,
  BarChart3,
  CalendarDays,
  Check,
  ChevronRight,
  CircleDollarSign,
  Clock3,
  FileSearch,
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
  UserRound,
  Users,
  X,
} from "lucide-react";

const statuses = [
  ["new", "Nouveau"],
  ["contacted", "Contacté"],
  ["qualified", "Qualifié"],
  ["meeting", "Rendez-vous"],
  ["proposal", "Proposition"],
  ["negotiation", "Négociation"],
  ["won", "Gagné"],
  ["lost", "Perdu"],
] as const;

const offers = [
  "START",
  "GROW",
  "SCALE",
  "V+ Care",
  "V+ Growth",
  "V+ Performance",
];

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
  reference?: string;
  created_at?: string;
  name?: string;
  company?: string;
  phone?: string;
  date?: string;
  time?: string;
  reason?: string;
  status?: string;
};

type Data = {
  stats: {
    prospects: number;
    new: number;
    qualified: number;
    meetings: number;
    won: number;
    lost: number;
    signed_revenue: number;
    potential_revenue: number;
    today_actions: number;
    overdue_actions: number;
  };

  sources: Record<string, number>;
  pipeline: Record<string, number>;

  prospects: Prospect[];
  grand_plus: any[];
  bookings: Booking[];
};

const emptyData: Data = {
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
  },
  sources: {},
  pipeline: {},
  prospects: [],
  grand_plus: [],
  bookings: [],
};

function euro(value: number) {
  return new Intl.NumberFormat("fr-FR", {
    style: "currency",
    currency: "EUR",
    maximumFractionDigits: 0,
  }).format(value || 0);
}

function dateFr(value: string) {
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

function dateTimeFr(value: string) {
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
  return statuses.find(([key]) => key === status)?.[1] ?? status;
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
      return "bg-[#c8a45d]/20 text-[#8a6a25]";
    case "contacted":
      return "bg-slate-100 text-slate-700";
    default:
      return "bg-black/5 text-black/60";
  }
}

function StatusBadge({ status }: { status: string }) {
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

export default function AdminDashboard() {
  const [data, setData] = useState<Data>(emptyData);
  const [loading, setLoading] = useState(true);

  const [section, setSection] = useState("dashboard");

  const [selected, setSelected] = useState<Prospect | null>(null);

  const [query, setQuery] = useState("");
  const [sourceFilter, setSourceFilter] = useState("all");
  const [statusFilter, setStatusFilter] = useState("all");

  const [mobileMenu, setMobileMenu] = useState(false);
  const [toast, setToast] = useState("");

  const [newProspect, setNewProspect] = useState(false);

  async function load() {
    setLoading(true);

    try {
      const response = await fetch(
        `/crm-api.php?ts=${Date.now()}`,
        {
          cache: "no-store",
        },
      );

      if (response.status === 401) {
        window.location.href = "/grand-plus-admin.php";
        return;
      }

      const json = await response.json();

      if (!response.ok || !json.success) {
        throw new Error(
          json.message || "Impossible de charger le CRM.",
        );
      }

      setData(json);

      if (selected) {
        setSelected(
          json.prospects.find(
            (prospect: Prospect) => prospect.id === selected.id,
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

  useEffect(() => {
    load();
  }, []);

  async function action(payload: Record<string, unknown>) {
    const response = await fetch("/crm-api.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(payload),
    });

    if (response.status === 401) {
      window.location.href = "/grand-plus-admin.php";
      return;
    }

    const json = await response.json();

    if (!response.ok || !json.success) {
      throw new Error(
        json.message || "Action impossible.",
      );
    }

    await load();

    return json;
  }

  const filtered = useMemo(() => {
    const normalizedQuery = query.trim().toLowerCase();

    return data.prospects.filter((prospect) => {
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
        haystack.includes(normalizedQuery);

      const matchesSource =
        sourceFilter === "all" ||
        prospect.source === sourceFilter;

      const matchesStatus =
        statusFilter === "all" ||
        prospect.status === statusFilter;

      return (
        matchesQuery &&
        matchesSource &&
        matchesStatus
      );
    });
  }, [
    data.prospects,
    query,
    sourceFilter,
    statusFilter,
  ]);

  const today = new Date()
    .toISOString()
    .slice(0, 10);

  const urgent = data.prospects
    .filter((prospect) => {
      if (
        !prospect.next_action_at ||
        ["won", "lost"].includes(prospect.status)
      ) {
        return false;
      }

      return (
        prospect.next_action_at === today ||
        prospect.next_action_at < today
      );
    })
    .sort((a, b) =>
      a.next_action_at.localeCompare(
        b.next_action_at,
      ),
    )
    .slice(0, 8);

  const menu = [
    ["dashboard", "Vue d'ensemble", LayoutDashboard],
    ["prospects", "Prospects", Users],
    ["pipeline", "Pipeline", Target],
    ["calendar", "Rendez-vous", CalendarDays],
    ["grand-plus", "Grand+", Gift],
    ["stats", "Statistiques", BarChart3],
  ] as const;

  function navigate(name: string) {
    setSection(name);
    setSelected(null);
    setMobileMenu(false);
  }

  return (
    <div className="min-h-screen bg-[#f5f5f3] text-[#080808]">
      {/* =========================================================
          SIDEBAR
      ========================================================= */}

      <aside
        className={`fixed inset-y-0 left-0 z-50 w-[270px] border-r border-white/10 bg-[#080808] text-white transition-transform duration-300 ${
          mobileMenu
            ? "translate-x-0"
            : "-translate-x-full lg:translate-x-0"
        }`}
      >
        <div className="flex h-full flex-col p-5">
          <div className="flex items-center justify-between px-2 py-3">
            <div className="text-2xl font-black tracking-[-0.06em]">
              Vitrine
              <span className="text-[#c8a45d]">+</span>
            </div>

            <button
              onClick={() => setMobileMenu(false)}
              className="rounded-xl p-2 text-white/50 lg:hidden"
            >
              <X size={20} />
            </button>
          </div>

          <div className="mt-8 px-2 text-[10px] font-bold uppercase tracking-[0.24em] text-white/30">
            Cockpit commercial
          </div>

          <nav className="mt-3 grid gap-1">
            {menu.map(([key, label, Icon]) => (
              <button
                key={key}
                onClick={() => navigate(key)}
                className={`flex items-center gap-3 rounded-2xl px-3.5 py-3 text-left text-sm font-semibold transition ${
                  section === key
                    ? "bg-white text-[#080808]"
                    : "text-white/55 hover:bg-white/5 hover:text-white"
                }`}
              >
                <Icon size={18} />
                {label}
              </button>
            ))}
          </nav>

          <div className="mt-auto grid gap-2 border-t border-white/10 pt-5">
            <a
              href="/"
              className="flex items-center gap-3 rounded-2xl px-3.5 py-3 text-sm font-semibold text-white/50 hover:bg-white/5 hover:text-white"
            >
              <ArrowLeft size={18} />
              Retour au site
            </a>

            <a
              href="/grand-plus-admin.php"
              className="flex items-center gap-3 rounded-2xl px-3.5 py-3 text-sm font-semibold text-white/50 hover:bg-white/5 hover:text-white"
            >
              <Gift size={18} />
              Administration Grand+
            </a>

            <a
              href="/grand-plus-admin.php?logout=1"
              className="flex items-center gap-3 rounded-2xl px-3.5 py-3 text-sm font-semibold text-white/50 hover:bg-white/5 hover:text-white"
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
        <header className="sticky top-0 z-40 border-b border-black/10 bg-[#f5f5f3]/90 backdrop-blur-xl">
          <div className="flex h-[74px] items-center justify-between px-5 sm:px-8 lg:px-10">
            <div className="flex items-center gap-3">
              <button
                onClick={() => setMobileMenu(true)}
                className="rounded-xl border border-black/10 p-2 lg:hidden"
              >
                <Menu size={20} />
              </button>

              <div>
                <div className="text-[10px] font-bold uppercase tracking-[0.24em] text-black/35">
                  Administration privée
                </div>

                <div className="mt-1 text-lg font-extrabold tracking-[-0.03em]">
                  {section === "dashboard"
                    ? "Vue d'ensemble"
                    : section === "grand-plus"
                      ? "Le Grand+"
                      : section === "stats"
                        ? "Statistiques"
                        : section === "calendar"
                          ? "Rendez-vous"
                          : section === "pipeline"
                            ? "Pipeline commercial"
                            : "Prospects"}
                </div>
              </div>
            </div>

            <div className="flex items-center gap-2">
              <button
                onClick={load}
                className="hidden rounded-full border border-black/10 px-4 py-2 text-xs font-bold sm:block"
              >
                Actualiser
              </button>

              <div className="flex h-9 w-9 items-center justify-center rounded-full bg-[#080808] text-xs font-black text-[#c8a45d]">
                V+
              </div>
            </div>
          </div>
        </header>

        <div className="mx-auto max-w-[1500px] px-5 py-7 sm:px-8 lg:px-10 lg:py-10">
          {toast && (
            <div className="mb-5 flex items-center justify-between rounded-2xl border border-[#c8a45d]/30 bg-[#c8a45d]/10 px-4 py-3 text-sm font-semibold">
              <span>{toast}</span>

              <button onClick={() => setToast("")}>
                <X size={16} />
              </button>
            </div>
          )}

          {loading ? (
            <div className="flex min-h-[50vh] items-center justify-center">
              <div className="h-7 w-7 animate-spin rounded-full border-2 border-black/10 border-t-[#c8a45d]" />
            </div>
          ) : section === "dashboard" ? (
            <Dashboard
              data={data}
              urgent={urgent}
              onOpen={setSelected}
              onNavigate={navigate}
            />
          ) : section === "prospects" ? (
            <Prospects
              data={data}
              filtered={filtered}
              query={query}
              setQuery={setQuery}
              source={sourceFilter}
              setSource={setSourceFilter}
              status={statusFilter}
              setStatus={setStatusFilter}
              onOpen={setSelected}
              onNew={() => setNewProspect(true)}
            />
          ) : section === "pipeline" ? (
            <Pipeline
              data={data}
              onOpen={setSelected}
              onMove={async (id, status) => {
                try {
                  await action({
                    action: "update_prospect",
                    id,
                    status,
                  });

                  setToast("Statut mis à jour.");
                } catch (error) {
                  setToast(
                    error instanceof Error
                      ? error.message
                      : "Erreur",
                  );
                }
              }}
            />
          ) : section === "calendar" ? (
            <Calendar
              data={data}
              onOpen={setSelected}
            />
          ) : section === "grand-plus" ? (
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

      {selected && (
        <ProspectModal
          prospect={selected}
          onClose={() => setSelected(null)}
          onSave={async (payload) => {
            try {
              await action({
                action: "update_prospect",
                id: selected.id,
                ...payload,
              });

              setToast("Prospect mis à jour.");
            } catch (error) {
              setToast(
                error instanceof Error
                  ? error.message
                  : "Erreur",
              );
            }
          }}
          onInteraction={async (text, type) => {
            try {
              await action({
                action: "add_interaction",
                id: selected.id,
                text,
                type,
              });

              setToast("Interaction ajoutée.");
            } catch (error) {
              setToast(
                error instanceof Error
                  ? error.message
                  : "Erreur",
              );
            }
          }}
        />
      )}

      {/* =========================================================
          NEW PROSPECT
      ========================================================= */}

      {newProspect && (
        <NewProspectModal
          onClose={() => setNewProspect(false)}
          onCreate={async (prospect) => {
            try {
              await action({
                action: "create_prospect",
                ...prospect,
              });

              setNewProspect(false);
              setSection("prospects");
              setToast("Prospect créé.");
            } catch (error) {
              setToast(
                error instanceof Error
                  ? error.message
                  : "Erreur",
              );
            }
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
  onOpen: (prospect: Prospect) => void;
  onNavigate: (section: string) => void;
}) {
  const conversion =
    data.stats.prospects > 0
      ? (data.stats.won / data.stats.prospects) * 100
      : 0;

  return (
    <div className="grid gap-8">
      <div>
        <div className="text-[10px] font-bold uppercase tracking-[0.28em] text-[#c8a45d]">
          Vitrine+ / Commercial
        </div>

        <h1 className="mt-3 text-4xl font-black tracking-[-0.06em] sm:text-6xl">
          Pilotez votre
          <br />
          activité.
        </h1>

        <p className="mt-5 max-w-2xl text-base leading-7 text-black/50">
          Un seul cockpit pour suivre vos prospects,
          vos rendez-vous, votre pipeline et votre
          chiffre d'affaires.
        </p>
      </div>

      {/* KPIs */}

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
          value={euro(data.stats.signed_revenue)}
        />

        <MetricCard
          icon={TrendingUp}
          label="CA potentiel"
          value={euro(data.stats.potential_revenue)}
        />

        <MetricCard
          icon={Target}
          label="Conversion"
          value={`${conversion.toFixed(1)} %`}
        />
      </div>

      <div className="grid gap-5 xl:grid-cols-[1.35fr_.65fr]">
        {/* PIPELINE */}

        <div className="rounded-[30px] border border-black/10 bg-white p-6 sm:p-8">
          <div className="flex items-center justify-between gap-4">
            <div>
              <div className="text-[10px] font-bold uppercase tracking-[0.18em] text-black/35">
                Pipeline
              </div>

              <h2 className="mt-2 text-2xl font-black tracking-[-0.04em]">
                Opportunités commerciales
              </h2>
            </div>

            <button
              onClick={() => onNavigate("pipeline")}
              className="rounded-full border border-black/10 px-4 py-2 text-xs font-bold"
            >
              Voir le pipeline
            </button>
          </div>

          <div className="mt-8 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            {statuses.slice(0, 6).map(([key, label]) => (
              <div
                key={key}
                className="rounded-2xl bg-[#f5f5f3] p-4"
              >
                <div className="text-[10px] font-bold uppercase tracking-[0.13em] text-black/35">
                  {label}
                </div>

                <div className="mt-3 text-2xl font-black">
                  {data.pipeline[key] || 0}
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* ACTIONS */}

        <div className="rounded-[30px] bg-[#080808] p-6 text-white sm:p-8">
          <div className="text-[10px] font-bold uppercase tracking-[0.18em] text-[#c8a45d]">
            À traiter
          </div>

          <h2 className="mt-2 text-2xl font-black tracking-[-0.04em]">
            Relances
          </h2>

          <div className="mt-6 grid gap-3">
            {urgent.length === 0 ? (
              <div className="rounded-2xl border border-white/10 p-4 text-sm text-white/45">
                Aucune relance urgente.
              </div>
            ) : (
              urgent.map((prospect) => (
                <button
                  key={prospect.id}
                  onClick={() => onOpen(prospect)}
                  className="rounded-2xl border border-white/10 bg-white/[0.04] p-4 text-left transition hover:bg-white/[0.08]"
                >
                  <div className="flex items-start justify-between gap-3">
                    <div>
                      <div className="font-bold">
                        {prospect.company ||
                          prospect.name}
                      </div>

                      <div className="mt-1 text-xs text-white/40">
                        {prospect.next_action ||
                          "Relancer le prospect"}
                      </div>
                    </div>

                    <ChevronRight
                      size={17}
                      className="text-white/30"
                    />
                  </div>
                </button>
              ))
            )}
          </div>
        </div>
      </div>

      {/* SOURCES + GRAND+ */}

      <div className="grid gap-5 lg:grid-cols-2">
        <div className="rounded-[30px] border border-black/10 bg-white p-6 sm:p-8">
          <div className="text-[10px] font-bold uppercase tracking-[0.18em] text-black/35">
            Acquisition
          </div>

          <h2 className="mt-2 text-2xl font-black tracking-[-0.04em]">
            D'où viennent vos prospects ?
          </h2>

          <div className="mt-8 grid gap-5">
            {Object.entries(data.sources)
              .sort((a, b) => b[1] - a[1])
              .map(([source, count]) => {
                const total =
                  data.stats.prospects || 1;

                return (
                  <div key={source}>
                    <div className="flex justify-between text-sm">
                      <span className="font-bold">
                        {sourceLabel(source)}
                      </span>

                      <span className="text-black/40">
                        {count} lead
                        {count > 1 ? "s" : ""}
                      </span>
                    </div>

                    <div className="mt-2 h-2 overflow-hidden rounded-full bg-black/5">
                      <div
                        className="h-full rounded-full bg-[#080808]"
                        style={{
                          width: `${Math.max(
                            4,
                            (count / total) * 100,
                          )}%`,
                        }}
                      />
                    </div>
                  </div>
                );
              })}

            {Object.keys(data.sources).length === 0 && (
              <div className="text-sm text-black/35">
                Aucune donnée pour le moment.
              </div>
            )}
          </div>
        </div>

        <div className="rounded-[30px] border border-[#c8a45d]/30 bg-[#080808] p-6 text-white sm:p-8">
          <div className="flex items-center gap-3">
            <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#c8a45d] text-[#080808]">
              <Gift size={21} />
            </div>

            <div>
              <div className="text-[10px] font-bold uppercase tracking-[0.18em] text-[#c8a45d]">
                Acquisition
              </div>

              <h2 className="mt-1 text-2xl font-black">
                Le Grand+
              </h2>
            </div>
          </div>

          <div className="mt-8 grid grid-cols-2 gap-3">
            <DarkStat
              label="Participants"
              value={String(
                data.grand_plus.length,
              )}
            />

            <DarkStat
              label="Ce mois"
              value={String(
                data.grand_plus.filter(
                  (item) =>
                    item.month_key ===
                    new Date()
                      .toISOString()
                      .slice(0, 7),
                ).length,
              )}
            />
          </div>

          <button
            onClick={() => onNavigate("grand-plus")}
            className="mt-5 flex w-full items-center justify-center gap-2 rounded-full bg-white px-5 py-3 text-sm font-bold text-[#080808]"
          >
            Voir les participants
            <ChevronRight size={16} />
          </button>
        </div>
      </div>

      {/* ACTIONS */}

      <div className="grid gap-4 sm:grid-cols-3">
        <QuickAction
          icon={Plus}
          title="Nouveau prospect"
          text="Ajouter une opportunité manuellement"
          onClick={() => onNavigate("prospects")}
        />

        <QuickAction
          icon={CalendarDays}
          title="Rendez-vous"
          text={`${data.stats.meetings} prospect${
            data.stats.meetings > 1 ? "s" : ""
          } actuellement au stade rendez-vous`}
          onClick={() => onNavigate("calendar")}
        />

        <QuickAction
          icon={Activity}
          title="Relances"
          text={`${data.stats.today_actions} à traiter aujourd'hui`}
          onClick={() => onNavigate("prospects")}
        />
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
  setQuery: (value: string) => void;
  source: string;
  setSource: (value: string) => void;
  status: string;
  setStatus: (value: string) => void;
  onOpen: (prospect: Prospect) => void;
  onNew: () => void;
}) {
  return (
    <div className="grid gap-7">
      <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
        <div>
          <div className="text-[10px] font-bold uppercase tracking-[0.28em] text-[#c8a45d]">
            CRM
          </div>

          <h1 className="mt-2 text-4xl font-black tracking-[-0.05em]">
            Prospects
          </h1>

          <p className="mt-3 text-sm text-black/45">
            {data.prospects.length} prospect
            {data.prospects.length > 1 ? "s" : ""} dans
            votre base commerciale.
          </p>
        </div>

        <button
          onClick={onNew}
          className="inline-flex items-center justify-center gap-2 rounded-full bg-[#080808] px-5 py-3 text-sm font-bold text-white"
        >
          <Plus size={17} />
          Nouveau prospect
        </button>
      </div>

      <div className="rounded-[28px] border border-black/10 bg-white p-4">
        <div className="grid gap-3 lg:grid-cols-[1fr_auto_auto]">
          <div className="flex items-center gap-3 rounded-2xl bg-[#f5f5f3] px-4">
            <Search
              size={17}
              className="text-black/30"
            />

            <input
              value={query}
              onChange={(event) =>
                setQuery(event.target.value)
              }
              placeholder="Rechercher un nom, une entreprise, un e-mail..."
              className="w-full bg-transparent py-3 text-sm font-medium outline-none"
            />
          </div>

          <select
            value={source}
            onChange={(event) =>
              setSource(event.target.value)
            }
            className="rounded-2xl bg-[#f5f5f3] px-4 py-3 text-sm font-semibold outline-none"
          >
            <option value="all">
              Toutes les sources
            </option>

            <option value="grand-plus">
              Grand+
            </option>

            <option value="audit">
              Audit
            </option>

            <option value="booking">
              Rendez-vous
            </option>

            <option value="contact">
              Contact
            </option>

            <option value="manual">
              Manuel
            </option>
          </select>

          <select
            value={status}
            onChange={(event) =>
              setStatus(event.target.value)
            }
            className="rounded-2xl bg-[#f5f5f3] px-4 py-3 text-sm font-semibold outline-none"
          >
            <option value="all">
              Tous les statuts
            </option>

            {statuses.map(([key, label]) => (
              <option key={key} value={key}>
                {label}
              </option>
            ))}
          </select>
        </div>
      </div>

      <div className="overflow-hidden rounded-[30px] border border-black/10 bg-white">
        <div className="overflow-x-auto">
          <table className="w-full min-w-[900px]">
            <thead>
              <tr className="border-b border-black/10 text-left">
                <th className="px-6 py-4 text-[10px] font-extrabold uppercase tracking-[0.15em] text-black/35">
                  Prospect
                </th>

                <th className="px-6 py-4 text-[10px] font-extrabold uppercase tracking-[0.15em] text-black/35">
                  Source
                </th>

                <th className="px-6 py-4 text-[10px] font-extrabold uppercase tracking-[0.15em] text-black/35">
                  Statut
                </th>

                <th className="px-6 py-4 text-[10px] font-extrabold uppercase tracking-[0.15em] text-black/35">
                  Valeur
                </th>

                <th className="px-6 py-4 text-[10px] font-extrabold uppercase tracking-[0.15em] text-black/35">
                  Créé
                </th>

                <th className="px-6 py-4" />
              </tr>
            </thead>

            <tbody>
              {filtered.map((prospect) => (
                <tr
                  key={prospect.id}
                  className="border-b border-black/5 transition hover:bg-[#f8f8f6]"
                >
                  <td className="px-6 py-5">
                    <button
                      onClick={() => onOpen(prospect)}
                      className="text-left"
                    >
                      <div className="font-extrabold">
                        {prospect.company ||
                          prospect.name}
                      </div>

                      <div className="mt-1 text-xs text-black/40">
                        {prospect.name}
                        {prospect.email
                          ? ` · ${prospect.email}`
                          : ""}
                      </div>
                    </button>
                  </td>

                  <td className="px-6 py-5">
                    <span className="rounded-full bg-black/5 px-3 py-1.5 text-[10px] font-bold uppercase tracking-[0.1em]">
                      {sourceLabel(
                        prospect.source,
                      )}
                    </span>
                  </td>

                  <td className="px-6 py-5">
                    <StatusBadge
                      status={prospect.status}
                    />
                  </td>

                  <td className="px-6 py-5 text-sm font-bold">
                    {prospect.estimated_value
                      ? euro(
                          prospect.estimated_value,
                        )
                      : "—"}
                  </td>

                  <td className="px-6 py-5 text-sm text-black/45">
                    {dateFr(
                      prospect.created_at,
                    )}
                  </td>

                  <td className="px-6 py-5">
                    <button
                      onClick={() => onOpen(prospect)}
                      className="rounded-full border border-black/10 p-2"
                    >
                      <ChevronRight size={16} />
                    </button>
                  </td>
                </tr>
              ))}

              {filtered.length === 0 && (
                <tr>
                  <td
                    colSpan={6}
                    className="px-6 py-20 text-center text-sm text-black/35"
                  >
                    Aucun prospect ne correspond à
                    votre recherche.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
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
  onOpen: (prospect: Prospect) => void;
  onMove: (
    id: string,
    status: string,
  ) => Promise<void>;
}) {
  return (
    <div className="grid gap-7">
      <div>
        <div className="text-[10px] font-bold uppercase tracking-[0.28em] text-[#c8a45d]">
          CRM
        </div>

        <h1 className="mt-2 text-4xl font-black tracking-[-0.05em]">
          Pipeline commercial
        </h1>

        <p className="mt-3 max-w-2xl text-sm leading-6 text-black/45">
          Visualisez chaque opportunité de la première
          prise de contact jusqu'à la signature.
        </p>
      </div>

      <div className="overflow-x-auto pb-4">
        <div className="flex min-w-[1500px] gap-4">
          {statuses.map(([status, label]) => {
            const prospects = data.prospects.filter(
              (prospect) =>
                prospect.status === status,
            );

            const value = prospects.reduce(
              (sum, prospect) =>
                sum +
                Number(
                  prospect.estimated_value || 0,
                ),
              0,
            );

            return (
              <div
                key={status}
                className="flex w-[180px] shrink-0 flex-col rounded-[26px] border border-black/10 bg-white p-3"
                onDragOver={(event) =>
                  event.preventDefault()
                }
                onDrop={async (event) => {
                  const id =
                    event.dataTransfer.getData(
                      "prospect-id",
                    );

                  if (id) {
                    await onMove(id, status);
                  }
                }}
              >
                <div className="flex items-center justify-between px-2 py-2">
                  <div className="text-xs font-extrabold">
                    {label}
                  </div>

                  <span className="rounded-full bg-black/5 px-2 py-1 text-[10px] font-bold">
                    {prospects.length}
                  </span>
                </div>

                <div className="px-2 pb-2 text-[10px] font-semibold text-black/35">
                  {euro(value)}
                </div>

                <div className="grid min-h-[180px] gap-2">
                  {prospects.map((prospect) => (
                    <button
                      key={prospect.id}
                      draggable
                      onDragStart={(event) =>
                        event.dataTransfer.setData(
                          "prospect-id",
                          prospect.id,
                        )
                      }
                      onClick={() =>
                        onOpen(prospect)
                      }
                      className="rounded-2xl border border-black/10 bg-[#f7f7f5] p-3 text-left transition hover:border-[#c8a45d]/50 hover:bg-white"
                    >
                      <div className="truncate text-sm font-extrabold">
                        {prospect.company ||
                          prospect.name}
                      </div>

                      <div className="mt-1 truncate text-[11px] text-black/40">
                        {prospect.name}
                      </div>

                      {prospect.estimated_value >
                        0 && (
                        <div className="mt-3 text-xs font-bold">
                          {euro(
                            prospect.estimated_value,
                          )}
                        </div>
                      )}

                      {prospect.next_action && (
                        <div className="mt-3 border-t border-black/5 pt-2 text-[10px] leading-4 text-black/40">
                          {prospect.next_action}
                        </div>
                      )}
                    </button>
                  ))}

                  {prospects.length === 0 && (
                    <div className="flex items-center justify-center rounded-2xl border border-dashed border-black/10 p-5 text-center text-[10px] font-semibold text-black/25">
                      Déposer ici
                    </div>
                  )}
                </div>
              </div>
            );
          })}
        </div>
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
  onOpen: (prospect: Prospect) => void;
}) {
  const bookings = [...data.bookings].sort(
    (a, b) =>
      `${a.date || ""} ${a.time || ""}`.localeCompare(
        `${b.date || ""} ${b.time || ""}`,
      ),
  );

  return (
    <div className="grid gap-7">
      <div>
        <div className="text-[10px] font-bold uppercase tracking-[0.28em] text-[#c8a45d]">
          Agenda
        </div>

        <h1 className="mt-2 text-4xl font-black tracking-[-0.05em]">
          Rendez-vous
        </h1>
      </div>

      <div className="grid gap-3">
        {bookings.length === 0 ? (
          <div className="rounded-[30px] border border-black/10 bg-white p-16 text-center text-sm text-black/35">
            Aucun rendez-vous enregistré.
          </div>
        ) : (
          bookings.map((booking, index) => {
            const matchingProspect =
              data.prospects.find(
                (prospect) =>
                  prospect.booking_reference ===
                    booking.reference ||
                  (prospect.name ===
                    booking.name &&
                    prospect.phone ===
                      booking.phone),
              );

            return (
              <button
                key={
                  booking.reference ||
                  `${booking.name}-${index}`
                }
                onClick={() => {
                  if (matchingProspect) {
                    onOpen(matchingProspect);
                  }
                }}
                className="grid gap-5 rounded-[28px] border border-black/10 bg-white p-5 text-left transition hover:border-[#c8a45d]/50 sm:grid-cols-[150px_1fr_auto] sm:items-center sm:p-6"
              >
                <div>
                  <div className="text-2xl font-black tracking-[-0.04em]">
                    {booking.time || "—"}
                  </div>

                  <div className="mt-1 text-xs font-bold text-black/35">
                    {booking.date || "—"}
                  </div>
                </div>

                <div>
                  <div className="font-extrabold">
                    {booking.company ||
                      booking.name ||
                      "Sans nom"}
                  </div>

                  <div className="mt-1 text-sm text-black/45">
                    {booking.name}
                    {booking.phone
                      ? ` · ${booking.phone}`
                      : ""}
                  </div>

                  {booking.reason && (
                    <div className="mt-3 text-xs leading-5 text-black/40">
                      {booking.reason}
                    </div>
                  )}
                </div>

                <div className="flex items-center gap-2">
                  <span className="rounded-full bg-emerald-100 px-3 py-1.5 text-[10px] font-bold uppercase tracking-[0.1em] text-emerald-700">
                    {booking.status ||
                      "confirmé"}
                  </span>

                  <ChevronRight
                    size={17}
                    className="text-black/25"
                  />
                </div>
              </button>
            );
          })
        )}
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
  onOpen: (prospect: Prospect) => void;
}) {
  const currentMonth = new Date()
    .toISOString()
    .slice(0, 7);

  const current = data.grand_plus.filter(
    (participant) =>
      participant.month_key === currentMonth,
  );

  return (
    <div className="grid gap-7">
      <div>
        <div className="text-[10px] font-bold uppercase tracking-[0.28em] text-[#c8a45d]">
          Acquisition
        </div>

        <h1 className="mt-2 text-4xl font-black tracking-[-0.05em]">
          Le Grand+
        </h1>

        <p className="mt-3 text-sm text-black/45">
          Participants et historique de l'opération.
        </p>
      </div>

      <div className="grid gap-4 sm:grid-cols-3">
        <MetricCard
          icon={Gift}
          label="Participants ce mois"
          value={String(current.length)}
        />

        <MetricCard
          icon={Users}
          label="Participants historiques"
          value={String(
            data.grand_plus.length,
          )}
        />

        <MetricCard
          icon={TrendingUp}
          label="Avec consentement marketing"
          value={String(
            data.grand_plus.filter(
              (item) =>
                item.marketing_consent,
            ).length,
          )}
        />
      </div>

      <div className="overflow-hidden rounded-[30px] border border-black/10 bg-white">
        <div className="overflow-x-auto">
          <table className="w-full min-w-[900px]">
            <thead>
              <tr className="border-b border-black/10 text-left">
                <th className="px-6 py-4 text-[10px] font-extrabold uppercase tracking-[0.15em] text-black/35">
                  Participant
                </th>

                <th className="px-6 py-4 text-[10px] font-extrabold uppercase tracking-[0.15em] text-black/35">
                  Mois
                </th>

                <th className="px-6 py-4 text-[10px] font-extrabold uppercase tracking-[0.15em] text-black/35">
                  Marketing
                </th>

                <th className="px-6 py-4 text-[10px] font-extrabold uppercase tracking-[0.15em] text-black/35">
                  Statut
                </th>

                <th className="px-6 py-4" />
              </tr>
            </thead>

            <tbody>
              {data.grand_plus.map(
                (participant, index) => {
                  const prospect =
                    data.prospects.find(
                      (item) =>
                        item.source ===
                          "grand-plus" &&
                        item.email ===
                          participant.email,
                    );

                  return (
                    <tr
                      key={
                        participant.id ||
                        `${participant.email}-${index}`
                      }
                      className="border-b border-black/5"
                    >
                      <td className="px-6 py-5">
                        <div className="font-extrabold">
                          {participant.company ||
                            participant.name}
                        </div>

                        <div className="mt-1 text-xs text-black/40">
                          {participant.name} ·{" "}
                          {participant.email}
                        </div>
                      </td>

                      <td className="px-6 py-5 text-sm">
                        {participant.month_label ||
                          participant.month_key ||
                          "—"}
                      </td>

                      <td className="px-6 py-5">
                        {participant.marketing_consent ? (
                          <span className="rounded-full bg-emerald-100 px-3 py-1.5 text-[10px] font-bold uppercase tracking-[0.1em] text-emerald-700">
                            Oui
                          </span>
                        ) : (
                          <span className="rounded-full bg-black/5 px-3 py-1.5 text-[10px] font-bold uppercase tracking-[0.1em] text-black/40">
                            Non
                          </span>
                        )}
                      </td>

                      <td className="px-6 py-5">
                        <StatusBadge
                          status={
                            participant.status ||
                            "pending"
                          }
                        />
                      </td>

                      <td className="px-6 py-5">
                        {prospect && (
                          <button
                            onClick={() =>
                              onOpen(prospect)
                            }
                            className="rounded-full border border-black/10 p-2"
                          >
                            <ChevronRight
                              size={16}
                            />
                          </button>
                        )}
                      </td>
                    </tr>
                  );
                },
              )}

              {data.grand_plus.length === 0 && (
                <tr>
                  <td
                    colSpan={5}
                    className="px-6 py-20 text-center text-sm text-black/35"
                  >
                    Aucun participant enregistré.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}

/* ================================================================
   STATS
================================================================ */

function Stats({ data }: { data: Data }) {
  const conversion =
    data.stats.prospects > 0
      ? (data.stats.won /
          data.stats.prospects) *
        100
      : 0;

  const averageWon =
    data.stats.won > 0
      ? data.stats.signed_revenue /
        data.stats.won
      : 0;

  const total =
    data.stats.prospects || 1;

  return (
    <div className="grid gap-7">
      <div>
        <div className="text-[10px] font-bold uppercase tracking-[0.28em] text-[#c8a45d]">
          Pilotage
        </div>

        <h1 className="mt-2 text-4xl font-black tracking-[-0.05em]">
          Statistiques commerciales
        </h1>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <MetricCard
          icon={Target}
          label="Conversion"
          value={`${conversion.toFixed(1)} %`}
        />

        <MetricCard
          icon={CircleDollarSign}
          label="Panier moyen gagné"
          value={euro(averageWon)}
        />

        <MetricCard
          icon={TrendingUp}
          label="CA signé"
          value={euro(
            data.stats.signed_revenue,
          )}
        />

        <MetricCard
          icon={Activity}
          label="CA potentiel"
          value={euro(
            data.stats.potential_revenue,
          )}
        />
      </div>

      <div className="grid gap-5 lg:grid-cols-2">
        <div className="rounded-[30px] border border-black/10 bg-white p-6 sm:p-8">
          <h2 className="text-xl font-black">
            Sources d'acquisition
          </h2>

          <div className="mt-7 grid gap-5">
            {Object.entries(data.sources)
              .sort((a, b) => b[1] - a[1])
              .map(([source, count]) => (
                <div key={source}>
                  <div className="flex justify-between text-sm">
                    <b>{sourceLabel(source)}</b>

                    <span className="text-black/45">
                      {count} lead
                      {count > 1 ? "s" : ""}
                    </span>
                  </div>

                  <div className="mt-2 h-3 rounded-full bg-[#f0f0ed]">
                    <div
                      className="h-full rounded-full bg-[#080808]"
                      style={{
                        width: `${Math.max(
                          3,
                          (count / total) *
                            100,
                        )}%`,
                      }}
                    />
                  </div>
                </div>
              ))}

            {Object.keys(data.sources).length ===
              0 && (
              <div className="text-sm text-black/35">
                Aucune donnée.
              </div>
            )}
          </div>
        </div>

        <div className="rounded-[30px] border border-black/10 bg-white p-6 sm:p-8">
          <h2 className="text-xl font-black">
            Répartition du pipeline
          </h2>

          <div className="mt-7 grid gap-3">
            {statuses.map(([status, label]) => (
              <div
                key={status}
                className="flex items-center justify-between rounded-2xl bg-[#f5f5f3] px-4 py-3"
              >
                <span className="text-sm font-semibold">
                  {label}
                </span>

                <b>
                  {data.pipeline[status] || 0}
                </b>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}

/* ================================================================
   METRIC
================================================================ */

function MetricCard({
  icon: Icon,
  label,
  value,
}: {
  icon: typeof Users;
  label: string;
  value: string;
}) {
  return (
    <div className="rounded-[26px] border border-black/10 bg-white p-6">
      <div className="flex items-center justify-between">
        <div className="text-xs font-bold uppercase tracking-[0.13em] text-black/35">
          {label}
        </div>

        <Icon
          size={18}
          className="text-[#c8a45d]"
        />
      </div>

      <div className="mt-7 text-3xl font-black tracking-[-0.04em]">
        {value}
      </div>
    </div>
  );
}

function DarkStat({
  label,
  value,
}: {
  label: string;
  value: string;
}) {
  return (
    <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-4">
      <div className="text-[10px] font-bold uppercase tracking-[0.13em] text-white/35">
        {label}
      </div>

      <div className="mt-2 text-2xl font-black">
        {value}
      </div>
    </div>
  );
}

function QuickAction({
  icon: Icon,
  title,
  text,
  onClick,
}: {
  icon: typeof Plus;
  title: string;
  text: string;
  onClick: () => void;
}) {
  return (
    <button
      onClick={onClick}
      className="group rounded-[26px] border border-black/10 bg-white p-5 text-left transition hover:-translate-y-0.5 hover:border-[#c8a45d]/40"
    >
      <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#080808] text-[#c8a45d]">
        <Icon size={18} />
      </div>

      <div className="mt-5 font-black">
        {title}
      </div>

      <div className="mt-1 text-sm leading-6 text-black/40">
        {text}
      </div>
    </button>
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
    payload: Record<string, unknown>,
  ) => Promise<void>;
  onInteraction: (
    text: string,
    type: string,
  ) => Promise<void>;
}) {
  const [form, setForm] = useState({
    status: prospect.status,
    offer: prospect.offer,
    estimated_value: String(
      prospect.estimated_value || "",
    ),
    recurring_value: String(
      prospect.recurring_value || "",
    ),
    last_contact_at:
      prospect.last_contact_at || "",
    next_action: prospect.next_action || "",
    next_action_at:
      prospect.next_action_at || "",
    notes: prospect.notes || "",
  });

  const [interaction, setInteraction] =
    useState("");

  const [type, setType] =
    useState("Note");

  return (
    <div className="fixed inset-0 z-[100] overflow-y-auto bg-black/50 p-4 backdrop-blur-sm">
      <div className="mx-auto my-6 max-w-5xl rounded-[30px] bg-[#f5f5f3] shadow-2xl">
        <div className="flex items-center justify-between border-b border-black/10 px-6 py-5 sm:px-8">
          <div>
            <div className="text-[10px] font-bold uppercase tracking-[0.2em] text-[#c8a45d]">
              Fiche prospect
            </div>

            <h2 className="mt-1 text-2xl font-black tracking-[-0.04em]">
              {prospect.company ||
                prospect.name}
            </h2>
          </div>

          <button
            onClick={onClose}
            className="rounded-full border border-black/10 bg-white p-2"
          >
            <X size={18} />
          </button>
        </div>

        <div className="grid gap-5 p-5 sm:p-8 lg:grid-cols-[1.1fr_.9fr]">
          <div className="grid gap-5">
            {/* CONTACT */}

            <div className="rounded-[24px] bg-white p-6">
              <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                  <div className="font-bold">
                    {prospect.name}
                  </div>

                  <div className="mt-2 text-xs text-black/45">
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
                  status={form.status}
                />
              </div>

              <div className="mt-6 grid gap-3 text-sm">
                {prospect.email && (
                  <a
                    href={`mailto:${prospect.email}`}
                    className="flex items-center gap-3 font-semibold"
                  >
                    <Mail size={16} />
                    {prospect.email}
                  </a>
                )}

                {prospect.phone && (
                  <a
                    href={`tel:${prospect.phone}`}
                    className="flex items-center gap-3 font-semibold"
                  >
                    <Phone size={16} />
                    {prospect.phone}
                  </a>
                )}

                {prospect.website && (
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
                    className="flex items-center gap-3 font-semibold"
                  >
                    <FileSearch size={16} />
                    {prospect.website}
                  </a>
                )}
              </div>

              <div className="mt-6 grid gap-3 sm:grid-cols-2">
                {prospect.sector && (
                  <InfoBox
                    label="Secteur"
                    value={prospect.sector}
                  />
                )}

                {prospect.reason && (
                  <InfoBox
                    label="Motif"
                    value={prospect.reason}
                  />
                )}
              </div>

              {prospect.problem && (
                <div className="mt-4 rounded-2xl bg-[#f5f5f3] p-4">
                  <div className="text-[10px] font-bold uppercase tracking-[0.13em] text-black/35">
                    Problématique déclarée
                  </div>

                  <div className="mt-2 text-sm leading-6 text-black/60">
                    {prospect.problem}
                  </div>
                </div>
              )}

              {prospect.audit_score != null && (
                <div className="mt-6 rounded-2xl bg-[#080808] p-4 text-white">
                  <div className="text-[10px] font-bold uppercase tracking-[0.16em] text-[#c8a45d]">
                    Score Audit
                  </div>

                  <div className="mt-2 text-3xl font-black">
                    {prospect.audit_score}
                    <span className="text-base text-white/35">
                      /100
                    </span>
                  </div>
                </div>
              )}

              {prospect.recommendations &&
                prospect.recommendations.length >
                  0 && (
                  <div className="mt-6">
                    <div className="text-[10px] font-bold uppercase tracking-[0.13em] text-black/35">
                      Recommandations Audit
                    </div>

                    <div className="mt-3 grid gap-2">
                      {prospect.recommendations
                        .slice(0, 3)
                        .map(
                          (
                            recommendation,
                            index,
                          ) => (
                            <div
                              key={index}
                              className="rounded-2xl bg-[#f5f5f3] px-4 py-3 text-sm text-black/60"
                            >
                              {recommendation}
                            </div>
                          ),
                        )}
                    </div>
                  </div>
                )}
            </div>

            {/* COMMERCIAL */}

            <div className="rounded-[24px] bg-white p-6">
              <h3 className="font-black">
                Suivi commercial
              </h3>

              <div className="mt-5 grid gap-4 sm:grid-cols-2">
                <Field label="Statut">
                  <select
                    value={form.status}
                    onChange={(event) =>
                      setForm({
                        ...form,
                        status:
                          event.target.value,
                      })
                    }
                  >
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
                </Field>

                <Field label="Offre">
                  <select
                    value={form.offer}
                    onChange={(event) =>
                      setForm({
                        ...form,
                        offer:
                          event.target.value,
                      })
                    }
                  >
                    <option value="">
                      À définir
                    </option>

                    {offers.map((offer) => (
                      <option
                        key={offer}
                        value={offer}
                      >
                        {offer}
                      </option>
                    ))}
                  </select>
                </Field>

                <Field label="Valeur du projet">
                  <input
                    type="number"
                    min="0"
                    value={
                      form.estimated_value
                    }
                    onChange={(event) =>
                      setForm({
                        ...form,
                        estimated_value:
                          event.target.value,
                      })
                    }
                    placeholder="1990"
                  />
                </Field>

                <Field label="Récurrent / mois">
                  <input
                    type="number"
                    min="0"
                    value={
                      form.recurring_value
                    }
                    onChange={(event) =>
                      setForm({
                        ...form,
                        recurring_value:
                          event.target.value,
                      })
                    }
                    placeholder="49"
                  />
                </Field>

                <Field label="Prochaine action">
                  <input
                    value={
                      form.next_action
                    }
                    onChange={(event) =>
                      setForm({
                        ...form,
                        next_action:
                          event.target.value,
                      })
                    }
                    placeholder="Relancer le prospect"
                  />
                </Field>

                <Field label="Date de relance">
                  <input
                    type="date"
                    value={
                      form.next_action_at
                    }
                    onChange={(event) =>
                      setForm({
                        ...form,
                        next_action_at:
                          event.target.value,
                      })
                    }
                  />
                </Field>
              </div>

              <div className="mt-4">
                <Field label="Notes">
                  <textarea
                    rows={5}
                    value={form.notes}
                    onChange={(event) =>
                      setForm({
                        ...form,
                        notes:
                          event.target.value,
                      })
                    }
                  />
                </Field>
              </div>

              <button
                onClick={() =>
                  onSave({
                    ...form,
                    estimated_value:
                      Number(
                        form.estimated_value,
                      ) || 0,
                    recurring_value:
                      Number(
                        form.recurring_value,
                      ) || 0,
                  })
                }
                className="mt-5 inline-flex items-center gap-2 rounded-full bg-[#080808] px-5 py-3 text-sm font-bold text-white"
              >
                <Check size={16} />
                Enregistrer
              </button>
            </div>
          </div>

          {/* HISTORIQUE */}

          <div className="rounded-[24px] bg-white p-6">
            <h3 className="font-black">
              Ajouter une interaction
            </h3>

            <div className="mt-4 grid gap-3">
              <select
                value={type}
                onChange={(event) =>
                  setType(event.target.value)
                }
                className="rounded-2xl bg-[#f5f5f3] px-4 py-3 text-sm font-semibold outline-none"
              >
                <option>Note</option>
                <option>Appel</option>
                <option>E-mail</option>
                <option>Rendez-vous</option>
                <option>Proposition</option>
                <option>Relance</option>
              </select>

              <textarea
                rows={4}
                value={interaction}
                onChange={(event) =>
                  setInteraction(
                    event.target.value,
                  )
                }
                placeholder="Ex. Prospect intéressé par une refonte GROW…"
                className="rounded-2xl bg-[#f5f5f3] px-4 py-3 text-sm outline-none"
              />

              <button
                disabled={!interaction.trim()}
                onClick={async () => {
                  await onInteraction(
                    interaction,
                    type,
                  );

                  setInteraction("");
                }}
                className="rounded-full border border-black/10 px-5 py-3 text-sm font-bold disabled:opacity-40"
              >
                Ajouter à l'historique
              </button>
            </div>

            <div className="mt-7 border-t border-black/10 pt-5">
              <h3 className="font-black">
                Historique
              </h3>

              <div className="mt-5 grid gap-5">
                {prospect.interactions?.length ? (
                  prospect.interactions.map(
                    (interaction) => (
                      <div
                        key={interaction.id}
                        className="border-l-2 border-[#c8a45d] pl-4"
                      >
                        <div className="text-[10px] font-bold uppercase tracking-[0.12em] text-black/35">
                          {interaction.type} ·{" "}
                          {dateTimeFr(
                            interaction.created_at,
                          )}
                        </div>

                        <div className="mt-1 text-sm leading-6 text-black/65">
                          {interaction.text}
                        </div>
                      </div>
                    ),
                  )
                ) : (
                  <div className="text-sm text-black/35">
                    Aucune interaction enregistrée.
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

/* ================================================================
   NEW PROSPECT
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

  return (
    <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
      <div className="w-full max-w-2xl rounded-[30px] bg-[#f5f5f3] p-6 shadow-2xl sm:p-8">
        <div className="flex justify-between">
          <div>
            <div className="text-[10px] font-bold uppercase tracking-[0.2em] text-[#c8a45d]">
              Nouveau
            </div>

            <h2 className="mt-2 text-3xl font-black tracking-[-0.05em]">
              Ajouter un prospect
            </h2>
          </div>

          <button
            onClick={onClose}
            className="rounded-full border border-black/10 bg-white p-2"
          >
            <X size={18} />
          </button>
        </div>

        <div className="mt-7 grid gap-4 sm:grid-cols-2">
          <Field label="Nom">
            <input
              value={form.name}
              onChange={(event) =>
                setForm({
                  ...form,
                  name: event.target.value,
                })
              }
            />
          </Field>

          <Field label="Entreprise">
            <input
              value={form.company}
              onChange={(event) =>
                setForm({
                  ...form,
                  company:
                    event.target.value,
                })
              }
            />
          </Field>

          <Field label="E-mail">
            <input
              type="email"
              value={form.email}
              onChange={(event) =>
                setForm({
                  ...form,
                  email:
                    event.target.value,
                })
              }
            />
          </Field>

          <Field label="Téléphone">
            <input
              value={form.phone}
              onChange={(event) =>
                setForm({
                  ...form,
                  phone:
                    event.target.value,
                })
              }
            />
          </Field>

          <Field label="Site internet">
            <input
              value={form.website}
              onChange={(event) =>
                setForm({
                  ...form,
                  website:
                    event.target.value,
                })
              }
            />
          </Field>

          <Field label="Notes">
            <input
              value={form.notes}
              onChange={(event) =>
                setForm({
                  ...form,
                  notes:
                    event.target.value,
                })
              }
            />
          </Field>
        </div>

        <div className="mt-7 flex justify-end gap-2">
          <button
            onClick={onClose}
            className="rounded-full border border-black/10 bg-white px-5 py-3 text-sm font-bold"
          >
            Annuler
          </button>

          <button
            disabled={
              !form.name && !form.company
            }
            onClick={() => onCreate(form)}
            className="rounded-full bg-[#080808] px-5 py-3 text-sm font-bold text-white disabled:opacity-40"
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
    <label className="grid gap-2 text-xs font-bold text-black/45">
      {label}

      <div className="[&_input]:w-full [&_input]:rounded-2xl [&_input]:bg-[#f5f5f3] [&_input]:px-4 [&_input]:py-3 [&_input]:text-sm [&_input]:font-medium [&_input]:text-black [&_input]:outline-none [&_select]:w-full [&_select]:rounded-2xl [&_select]:bg-[#f5f5f3] [&_select]:px-4 [&_select]:py-3 [&_select]:text-sm [&_select]:font-medium [&_select]:text-black [&_select]:outline-none [&_textarea]:w-full [&_textarea]:rounded-2xl [&_textarea]:bg-[#f5f5f3] [&_textarea]:px-4 [&_textarea]:py-3 [&_textarea]:text-sm [&_textarea]:font-medium [&_textarea]:text-black [&_textarea]:outline-none">
        {children}
      </div>
    </label>
  );
}

/* ================================================================
   INFO BOX
================================================================ */

function InfoBox({
  label,
  value,
}: {
  label: string;
  value: string;
}) {
  return (
    <div className="rounded-2xl bg-[#f5f5f3] p-4">
      <div className="text-[10px] font-bold uppercase tracking-[0.13em] text-black/35">
        {label}
      </div>

      <div className="mt-2 text-sm font-semibold text-black/65">
        {value}
      </div>
    </div>
  );
}
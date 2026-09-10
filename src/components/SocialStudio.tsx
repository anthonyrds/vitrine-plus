import { useEffect, useMemo, useState } from "react"
import {
  CalendarClock,
  Check,
  ChevronDown,
  Clock3,
  Copy,
  Film,
  Image as ImageIcon,
  Instagram,
  Loader2,
  MessageSquareText,
  Plus,
  RefreshCw,
  Save,
  Send,
  Sparkles,
  Trash2,
  WandSparkles,
  X,
} from "lucide-react"

type ContentType = "post" | "carousel" | "reel" | "story"

type SocialContent = {
  id: string
  type: ContentType
  topic: string
  objective: string
  caption: string
  slides?: string[]
  script?: string
  mediaUrl?: string
  scheduledAt?: string
  status: "draft" | "scheduled" | "published"
  createdAt: string
  updatedAt?: string
}

type SocialStudioProps = {
  onToast?: (message: string) => void
}

const CONTENT_TYPES: {
  value: ContentType
  label: string
  description: string
  icon: typeof ImageIcon
}[] = [
  {
    value: "post",
    label: "Publication",
    description: "Un post Instagram classique",
    icon: ImageIcon,
  },
  {
    value: "carousel",
    label: "Carrousel",
    description: "Plusieurs slides pédagogiques",
    icon: Copy,
  },
  {
    value: "reel",
    label: "Reel",
    description: "Vidéo courte avec script",
    icon: Film,
  },
  {
    value: "story",
    label: "Story",
    description: "Contenu vertical rapide",
    icon: Instagram,
  },
]

const OBJECTIVES = [
  "Gagner en visibilité",
  "Générer des prospects",
  "Promouvoir Vitrine+",
  "Éduquer mon audience",
  "Créer de l'engagement",
  "Promouvoir une offre",
]

function formatDate(date: string) {
  if (!date) return ""

  const value = new Date(date)

  if (Number.isNaN(value.getTime())) {
    return date
  }

  return value.toLocaleString("fr-FR", {
    dateStyle: "short",
    timeStyle: "short",
  })
}

function statusLabel(status: SocialContent["status"]) {
  switch (status) {
    case "published":
      return "Publié"
    case "scheduled":
      return "Programmé"
    default:
      return "Brouillon"
  }
}

function statusClasses(status: SocialContent["status"]) {
  switch (status) {
    case "published":
      return "bg-emerald-500/10 text-emerald-300 border-emerald-400/20"
    case "scheduled":
      return "bg-blue-500/10 text-blue-300 border-blue-400/20"
    default:
      return "bg-white/5 text-white/60 border-white/10"
  }
}

export default function SocialStudio({
  onToast,
}: SocialStudioProps) {
  const [contents, setContents] = useState<SocialContent[]>([])
  const [selected, setSelected] = useState<SocialContent | null>(null)

  const [type, setType] = useState<ContentType>("post")
  const [topic, setTopic] = useState("")
  const [objective, setObjective] = useState("Gagner en visibilité")

  const [caption, setCaption] = useState("")
  const [slides, setSlides] = useState<string[]>([])
  const [script, setScript] = useState("")

  const [mediaUrl, setMediaUrl] = useState("")
  const [scheduledAt, setScheduledAt] = useState("")

  const [loading, setLoading] = useState(false)
  const [publishing, setPublishing] = useState(false)
  const [saving, setSaving] = useState(false)
  const [loadingLibrary, setLoadingLibrary] = useState(false)
  const [generatingVisual, setGeneratingVisual] = useState(false)

  const [error, setError] = useState("")

  const notify = (message: string) => {
    if (onToast) {
      onToast(message)
    } else {
      window.alert(message)
    }
  }

  const selectedType = useMemo(
    () => CONTENT_TYPES.find((item) => item.value === type),
    [type],
  )

  async function apiRequest(
    action: string,
    payload: Record<string, unknown> = {},
  ) {
    const response = await fetch("/social-api.php", {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        action,
        ...payload,
      }),
    })

    if (response.status === 401) {
      window.location.href = "/grand-plus-admin.php"
      throw new Error("Session administrateur expirée.")
    }

    const data = await response.json().catch(() => null)

    if (!response.ok || !data?.success) {
      throw new Error(
        data?.message ||
          "Une erreur est survenue lors de la communication avec le serveur.",
      )
    }

    return data
  }

  async function loadContents() {
    try {
      setLoadingLibrary(true)
      setError("")

      const data = await apiRequest("list")

      const items = Array.isArray(data.contents)
        ? data.contents
        : []

      setContents(items)
    } catch (err) {
      const message =
        err instanceof Error
          ? err.message
          : "Impossible de charger les contenus."

      setError(message)
    } finally {
      setLoadingLibrary(false)
    }
  }

  useEffect(() => {
    loadContents()
  }, [])

  function resetEditor() {
    setSelected(null)
    setType("post")
    setTopic("")
    setObjective("Gagner en visibilité")
    setCaption("")
    setSlides([])
    setScript("")
    setMediaUrl("")
    setScheduledAt("")
    setError("")
  }

  function loadIntoEditor(content: SocialContent) {
    setSelected(content)
    setType(content.type)
    setTopic(content.topic || "")
    setObjective(content.objective || "Gagner en visibilité")
    setCaption(content.caption || "")
    setSlides(
      Array.isArray(content.slides)
        ? content.slides
        : [],
    )
    setScript(content.script || "")
    setMediaUrl(content.mediaUrl || "")
    setScheduledAt(content.scheduledAt || "")
    setError("")

    window.scrollTo({
      top: 0,
      behavior: "smooth",
    })
  }

  async function generateContent() {
    if (!topic.trim()) {
      notify("Indique d'abord le sujet du contenu.")
      return
    }

    try {
      setLoading(true)
      setError("")

      const data = await apiRequest("generate", {
        type,
        topic: topic.trim(),
        objective,
      })

      const generated = data.content

      if (!generated) {
        throw new Error("Le serveur n'a retourné aucun contenu.")
      }

      setSelected(null)

      setCaption(generated.caption || "")

      setSlides(
        Array.isArray(generated.slides)
          ? generated.slides
          : [],
      )

      setScript(generated.script || "")

      if (generated.mediaUrl) {
        setMediaUrl(generated.mediaUrl)
      }

      notify("Contenu généré avec l’IA.")
    } catch (err) {
      const message =
        err instanceof Error
          ? err.message
          : "Impossible de générer le contenu."

      setError(message)
      notify(message)
    } finally {
      setLoading(false)
    }
  }

  async function generateVisual() {
    if (!topic.trim() && !caption.trim()) {
      notify(
        "Indique d'abord un sujet ou génère une légende.",
      )
      return
    }

    try {
      setGeneratingVisual(true)
      setError("")

      const data = await apiRequest("generate_visual", {
        type,
        topic,
        caption,
      })

      if (!data.url) {
        throw new Error(
          "Le serveur n'a retourné aucune URL de visuel.",
        )
      }

      setMediaUrl(data.url)

      notify("Visuel Vitrine+ généré.")
    } catch (err) {
      const message =
        err instanceof Error
          ? err.message
          : "Impossible de générer le visuel."

      setError(message)
      notify(message)
    } finally {
      setGeneratingVisual(false)
    }
  }

  async function saveContent() {
    if (!caption.trim()) {
      notify("La légende est vide.")
      return
    }

    try {
      setSaving(true)
      setError("")

      const content = {
        id: selected?.id,
        type,
        topic,
        objective,
        caption,
        slides,
        script,
        mediaUrl,
      }

      const data = await apiRequest("save", {
        content,
        status: scheduledAt
          ? "scheduled"
          : "draft",
        scheduled_at: scheduledAt || "",
      })

      const saved = data.content

      if (saved) {
        setSelected(saved)

        setContents((current) => {
          const exists = current.some(
            (item) => item.id === saved.id,
          )

          if (exists) {
            return current.map((item) =>
              item.id === saved.id
                ? saved
                : item,
            )
          }

          return [saved, ...current]
        })
      }

      notify(
        scheduledAt
          ? "Publication programmée."
          : "Brouillon enregistré.",
      )
    } catch (err) {
      const message =
        err instanceof Error
          ? err.message
          : "Impossible d'enregistrer le contenu."

      setError(message)
      notify(message)
    } finally {
      setSaving(false)
    }
  }

  async function deleteContent(
    content: SocialContent,
  ) {
    const confirmed = window.confirm(
      "Supprimer définitivement ce contenu ?",
    )

    if (!confirmed) {
      return
    }

    try {
      await apiRequest("delete", {
        id: content.id,
      })

      setContents((current) =>
        current.filter(
          (item) => item.id !== content.id,
        ),
      )

      if (selected?.id === content.id) {
        resetEditor()
      }

      notify("Contenu supprimé.")
    } catch (err) {
      const message =
        err instanceof Error
          ? err.message
          : "Impossible de supprimer le contenu."

      notify(message)
    }
  }

  async function publishInstagram() {
    if (!caption.trim()) {
      notify("La légende est vide.")
      return
    }

    if (!mediaUrl.trim()) {
      notify(
        "Ajoute ou génère d'abord un visuel public.",
      )
      return
    }

    try {
      setPublishing(true)
      setError("")

      const data = await apiRequest("publish", {
        id: selected?.id || undefined,
        type,
        caption,
        image: mediaUrl,
        slides,
        script,
      })

      if (selected?.id) {
        setContents((current) =>
          current.map((item) =>
            item.id === selected.id
              ? {
                  ...item,
                  status: "published",
                  updatedAt:
                    new Date().toISOString(),
                }
              : item,
          ),
        )

        setSelected((current) =>
          current
            ? {
                ...current,
                status: "published",
                updatedAt:
                  new Date().toISOString(),
              }
            : current,
        )
      }

      notify(
        data.message ||
          "Publication envoyée sur Instagram.",
      )
    } catch (err) {
      const message =
        err instanceof Error
          ? err.message
          : "Erreur lors de la publication Instagram."

      setError(message)
      notify(message)
    } finally {
      setPublishing(false)
    }
  }

  function addSlide() {
    setSlides((current) => [
      ...current,
      "",
    ])
  }

  function updateSlide(
    index: number,
    value: string,
  ) {
    setSlides((current) =>
      current.map((slide, slideIndex) =>
        slideIndex === index
          ? value
          : slide,
      ),
    )
  }

  function removeSlide(index: number) {
    setSlides((current) =>
      current.filter(
        (_, slideIndex) =>
          slideIndex !== index,
      ),
    )
  }

  async function copyCaption() {
    if (!caption) {
      return
    }

    try {
      await navigator.clipboard.writeText(
        caption,
      )

      notify("Légende copiée.")
    } catch {
      notify(
        "Impossible de copier la légende.",
      )
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <div className="mb-2 flex items-center gap-2 text-xs font-black uppercase tracking-[0.22em] text-[#C8A45D]">
            <Instagram size={15} />
            Social Studio
          </div>

          <h1 className="text-2xl font-black tracking-tight text-white">
            Réseaux sociaux
          </h1>

          <p className="mt-1 max-w-2xl text-sm leading-6 text-white/50">
            Crée, prépare, programme et publie les
            contenus sociaux de Vitrine+ depuis ton
            administration.
          </p>
        </div>

        <button
          type="button"
          onClick={resetEditor}
          className="inline-flex items-center justify-center gap-2 rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-bold text-white transition hover:bg-white/10"
        >
          <Plus size={17} />
          Nouveau contenu
        </button>
      </div>

      {error && (
        <div className="flex items-start justify-between gap-4 rounded-2xl border border-red-400/20 bg-red-500/10 px-4 py-3 text-sm text-red-200">
          <div className="whitespace-pre-wrap">
            {error}
          </div>

          <button
            type="button"
            onClick={() => setError("")}
            className="shrink-0 text-red-200/60 hover:text-red-100"
          >
            <X size={16} />
          </button>
        </div>
      )}

      <div className="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(340px,0.65fr)]">
        <section className="rounded-3xl border border-white/10 bg-[#101010] p-5 shadow-2xl shadow-black/20 lg:p-6">
          <div className="mb-6 flex items-center justify-between gap-4">
            <div>
              <p className="text-xs font-black uppercase tracking-[0.18em] text-white/35">
                Création
              </p>

              <h2 className="mt-1 text-lg font-black text-white">
                Nouveau contenu
              </h2>
            </div>

            {selected && (
              <span className="rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-bold text-white/50">
                Édition
              </span>
            )}
          </div>

          <div>
            <label className="mb-3 block text-xs font-black uppercase tracking-[0.16em] text-white/45">
              Format
            </label>

            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
              {CONTENT_TYPES.map((item) => {
                const Icon = item.icon
                const active =
                  type === item.value

                return (
                  <button
                    key={item.value}
                    type="button"
                    onClick={() =>
                      setType(item.value)
                    }
                    className={[
                      "rounded-2xl border p-4 text-left transition",
                      active
                        ? "border-[#C8A45D]/50 bg-[#C8A45D]/10"
                        : "border-white/10 bg-white/[0.025] hover:border-white/20 hover:bg-white/[0.05]",
                    ].join(" ")}
                  >
                    <Icon
                      size={19}
                      className={
                        active
                          ? "text-[#C8A45D]"
                          : "text-white/45"
                      }
                    />

                    <div
                      className={[
                        "mt-3 text-sm font-black",
                        active
                          ? "text-white"
                          : "text-white/70",
                      ].join(" ")}
                    >
                      {item.label}
                    </div>

                    <div className="mt-1 text-[11px] leading-4 text-white/35">
                      {item.description}
                    </div>
                  </button>
                )
              })}
            </div>
          </div>

          <div className="mt-6">
            <label className="mb-2 block text-xs font-black uppercase tracking-[0.16em] text-white/45">
              Sujet
            </label>

            <textarea
              value={topic}
              onChange={(event) =>
                setTopic(event.target.value)
              }
              placeholder="Exemple : pourquoi un site internet professionnel est indispensable pour un artisan..."
              rows={4}
              className="w-full resize-none rounded-2xl border border-white/10 bg-black/30 px-4 py-3 text-sm leading-6 text-white outline-none transition placeholder:text-white/20 focus:border-[#C8A45D]/50"
            />
          </div>

          <div className="mt-5">
            <label className="mb-2 block text-xs font-black uppercase tracking-[0.16em] text-white/45">
              Objectif
            </label>

            <div className="relative">
              <select
                value={objective}
                onChange={(event) =>
                  setObjective(
                    event.target.value,
                  )
                }
                className="w-full appearance-none rounded-2xl border border-white/10 bg-black/30 px-4 py-3 pr-10 text-sm text-white outline-none transition focus:border-[#C8A45D]/50"
              >
                {OBJECTIVES.map((item) => (
                  <option
                    key={item}
                    value={item}
                    className="bg-[#101010]"
                  >
                    {item}
                  </option>
                ))}
              </select>

              <ChevronDown
                size={17}
                className="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-white/35"
              />
            </div>
          </div>

          <button
            type="button"
            onClick={generateContent}
            disabled={loading}
            className="mt-5 flex w-full items-center justify-center gap-2 rounded-2xl bg-[#C8A45D] px-5 py-3.5 text-sm font-black text-black transition hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-50"
          >
            {loading ? (
              <>
                <Loader2
                  size={18}
                  className="animate-spin"
                />
                Génération en cours...
              </>
            ) : (
              <>
                <WandSparkles size={18} />
                Générer avec l’IA
              </>
            )}
          </button>

          <div className="mt-7">
            <div className="mb-2 flex items-center justify-between gap-3">
              <label className="block text-xs font-black uppercase tracking-[0.16em] text-white/45">
                Légende Instagram
              </label>

              <button
                type="button"
                onClick={copyCaption}
                disabled={!caption}
                className="inline-flex items-center gap-1.5 text-xs font-bold text-white/40 transition hover:text-white disabled:opacity-30"
              >
                <Copy size={13} />
                Copier
              </button>
            </div>

            <textarea
              value={caption}
              onChange={(event) =>
                setCaption(
                  event.target.value,
                )
              }
              placeholder="La légende générée apparaîtra ici..."
              rows={11}
              className="w-full resize-y rounded-2xl border border-white/10 bg-black/30 px-4 py-3 text-sm leading-6 text-white outline-none transition placeholder:text-white/20 focus:border-[#C8A45D]/50"
            />

            <div className="mt-2 flex items-center justify-between text-[11px] text-white/25">
              <span>
                {caption.length} caractères
              </span>

              <span>
                Instagram · Vitrine+
              </span>
            </div>
          </div>

          <div className="mt-7 rounded-2xl border border-white/10 bg-white/[0.025] p-4">
            <div className="flex items-start gap-3">
              <div className="rounded-xl bg-[#C8A45D]/10 p-2 text-[#C8A45D]">
                <ImageIcon size={18} />
              </div>

              <div className="flex-1">
                <div className="text-sm font-black text-white">
                  Visuel de publication
                </div>

                <div className="mt-1 text-xs leading-5 text-white/35">
                  Le visuel doit être accessible
                  publiquement par Instagram.
                </div>
              </div>
            </div>

            <div className="mt-4">
              <input
                type="url"
                value={mediaUrl}
                onChange={(event) =>
                  setMediaUrl(
                    event.target.value,
                  )
                }
                placeholder="https://vitrineplus.fr/..."
                className="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-3 text-sm text-white outline-none placeholder:text-white/20 focus:border-[#C8A45D]/50"
              />

              <button
                type="button"
                onClick={generateVisual}
                disabled={generatingVisual}
                className="mt-2 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-[#C8A45D]/20 bg-[#C8A45D]/5 px-4 py-3 text-xs font-black text-[#C8A45D] transition hover:bg-[#C8A45D]/10 disabled:opacity-40"
              >
                {generatingVisual ? (
                  <>
                    <Loader2
                      size={15}
                      className="animate-spin"
                    />
                    Création du visuel...
                  </>
                ) : (
                  <>
                    <Sparkles size={15} />
                    Générer un visuel Vitrine+
                  </>
                )}
              </button>
            </div>

            {mediaUrl && (
              <div className="mt-4 overflow-hidden rounded-xl border border-white/10 bg-black">
                <img
                  src={mediaUrl}
                  alt="Visuel Instagram"
                  className="aspect-square w-full object-cover"
                  onError={() =>
                    setError(
                      "Le visuel indiqué n'est pas accessible depuis le navigateur.",
                    )
                  }
                />
              </div>
            )}
          </div>

          {type === "carousel" && (
            <div className="mt-7">
              <div className="mb-3 flex items-center justify-between">
                <label className="text-xs font-black uppercase tracking-[0.16em] text-white/45">
                  Slides du carrousel
                </label>

                <button
                  type="button"
                  onClick={addSlide}
                  className="inline-flex items-center gap-1.5 rounded-lg border border-white/10 bg-white/5 px-2.5 py-1.5 text-xs font-bold text-white/60 hover:bg-white/10 hover:text-white"
                >
                  <Plus size={13} />
                  Ajouter
                </button>
              </div>

              <div className="space-y-3">
                {slides.map(
                  (slide, index) => (
                    <div
                      key={`slide-${index}`}
                      className="rounded-2xl border border-white/10 bg-black/20 p-3"
                    >
                      <div className="mb-2 flex items-center justify-between">
                        <span className="text-xs font-black text-[#C8A45D]">
                          Slide {index + 1}
                        </span>

                        <button
                          type="button"
                          onClick={() =>
                            removeSlide(
                              index,
                            )
                          }
                          className="text-white/30 hover:text-red-300"
                        >
                          <Trash2 size={14} />
                        </button>
                      </div>

                      <textarea
                        value={slide}
                        onChange={(
                          event,
                        ) =>
                          updateSlide(
                            index,
                            event.target
                              .value,
                          )
                        }
                        rows={3}
                        className="w-full resize-none rounded-xl border border-white/10 bg-black/30 px-3 py-2 text-sm leading-5 text-white outline-none focus:border-[#C8A45D]/40"
                      />
                    </div>
                  ),
                )}

                {slides.length === 0 && (
                  <div className="rounded-2xl border border-dashed border-white/10 px-4 py-8 text-center text-sm text-white/30">
                    Aucun slide pour le moment.
                  </div>
                )}
              </div>
            </div>
          )}

          {type === "reel" && (
            <div className="mt-7">
              <label className="mb-2 block text-xs font-black uppercase tracking-[0.16em] text-white/45">
                Script du Reel
              </label>

              <textarea
                value={script}
                onChange={(event) =>
                  setScript(
                    event.target.value,
                  )
                }
                placeholder="Le script du Reel apparaîtra ici..."
                rows={12}
                className="w-full resize-y rounded-2xl border border-white/10 bg-black/30 px-4 py-3 text-sm leading-6 text-white outline-none focus:border-[#C8A45D]/50"
              />
            </div>
          )}

          <div className="mt-7 rounded-2xl border border-white/10 bg-white/[0.025] p-4">
            <div className="flex items-start gap-3">
              <div className="rounded-xl bg-[#C8A45D]/10 p-2 text-[#C8A45D]">
                <CalendarClock size={18} />
              </div>

              <div className="flex-1">
                <div className="text-sm font-black text-white">
                  Programmer la publication
                </div>

                <div className="mt-1 text-xs leading-5 text-white/35">
                  Laisse vide pour enregistrer
                  uniquement un brouillon.
                </div>
              </div>
            </div>

            <input
              type="datetime-local"
              value={scheduledAt}
              onChange={(event) =>
                setScheduledAt(
                  event.target.value,
                )
              }
              className="mt-4 w-full rounded-xl border border-white/10 bg-black/30 px-3 py-3 text-sm text-white outline-none focus:border-[#C8A45D]/50"
            />

            {scheduledAt && (
              <button
                type="button"
                onClick={() =>
                  setScheduledAt("")
                }
                className="mt-2 text-xs font-bold text-white/35 hover:text-white"
              >
                Annuler la programmation
              </button>
            )}
          </div>

          <div className="mt-6 grid gap-3 sm:grid-cols-2">
            <button
              type="button"
              onClick={saveContent}
              disabled={
                saving ||
                !caption.trim()
              }
              className="inline-flex items-center justify-center gap-2 rounded-2xl border border-white/10 bg-white/5 px-4 py-3.5 text-sm font-black text-white transition hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-40"
            >
              {saving ? (
                <Loader2
                  size={17}
                  className="animate-spin"
                />
              ) : (
                <Save size={17} />
              )}

              {scheduledAt
                ? "Programmer"
                : "Enregistrer"}
            </button>

            <button
              type="button"
              onClick={publishInstagram}
              disabled={
                publishing ||
                !caption.trim() ||
                !mediaUrl.trim()
              }
              className="inline-flex items-center justify-center gap-2 rounded-2xl bg-[#C8A45D] px-4 py-3.5 text-sm font-black text-black transition hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-40"
            >
              {publishing ? (
                <>
                  <Loader2
                    size={17}
                    className="animate-spin"
                  />
                  Publication...
                </>
              ) : (
                <>
                  <Send size={17} />
                  Publier sur Instagram
                </>
              )}
            </button>
          </div>
        </section>

        <aside className="space-y-6">
          <section className="rounded-3xl border border-white/10 bg-[#101010] p-5 shadow-2xl shadow-black/20">
            <div className="mb-5 flex items-center justify-between">
              <div>
                <p className="text-xs font-black uppercase tracking-[0.18em] text-white/35">
                  Aperçu
                </p>

                <h2 className="mt-1 text-lg font-black text-white">
                  Instagram
                </h2>
              </div>

              <Instagram
                size={20}
                className="text-[#C8A45D]"
              />
            </div>

            <div className="overflow-hidden rounded-2xl border border-white/10 bg-black">
              <div className="flex items-center gap-3 border-b border-white/10 px-4 py-3">
                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-[#C8A45D] text-xs font-black text-black">
                  V+
                </div>

                <div className="min-w-0 flex-1">
                  <div className="truncate text-xs font-black text-white">
                    @_vitrine_plus_
                  </div>

                  <div className="text-[10px] text-white/30">
                    Vitrine+
                  </div>
                </div>

                <div className="text-white/40">
                  •••
                </div>
              </div>

              <div className="flex aspect-square items-center justify-center overflow-hidden bg-black">
                {mediaUrl ? (
                  <img
                    src={mediaUrl}
                    alt="Aperçu"
                    className="h-full w-full object-cover"
                  />
                ) : type ===
                  "carousel" ? (
                  <div className="px-8 text-center">
                    <Copy
                      size={36}
                      className="mx-auto text-[#C8A45D]"
                    />

                    <p className="mt-3 text-sm font-black text-white">
                      Carrousel
                    </p>

                    <p className="mt-1 text-xs text-white/35">
                      {slides.length} slide
                      {slides.length > 1
                        ? "s"
                        : ""}
                    </p>
                  </div>
                ) : type === "reel" ? (
                  <div className="text-center">
                    <Film
                      size={36}
                      className="mx-auto text-[#C8A45D]"
                    />

                    <p className="mt-3 text-sm font-black text-white">
                      Reel Vitrine+
                    </p>
                  </div>
                ) : type === "story" ? (
                  <div className="text-center">
                    <Instagram
                      size={36}
                      className="mx-auto text-[#C8A45D]"
                    />

                    <p className="mt-3 text-sm font-black text-white">
                      Story Vitrine+
                    </p>
                  </div>
                ) : (
                  <div className="text-center">
                    <ImageIcon
                      size={36}
                      className="mx-auto text-[#C8A45D]"
                    />

                    <p className="mt-3 text-sm font-black text-white">
                      Visuel Vitrine+
                    </p>
                  </div>
                )}
              </div>

              <div className="p-4">
                <div className="mb-3 flex items-center gap-4 text-white">
                  <span>♡</span>
                  <span>◯</span>
                  <span>➤</span>
                  <span className="ml-auto">
                    ⌑
                  </span>
                </div>

                <div className="max-h-48 overflow-auto whitespace-pre-wrap text-xs leading-5 text-white/70">
                  {caption || (
                    <span className="text-white/20">
                      La légende apparaîtra
                      ici...
                    </span>
                  )}
                </div>
              </div>
            </div>

            <div className="mt-4 flex items-center gap-2 text-[11px] text-white/25">
              <Check size={13} />
              Aperçu indicatif
            </div>
          </section>

          <section className="rounded-3xl border border-white/10 bg-[#101010] p-5">
            <div className="flex items-start gap-3">
              <div className="rounded-xl bg-[#C8A45D]/10 p-2 text-[#C8A45D]">
                <Sparkles size={18} />
              </div>

              <div>
                <h3 className="text-sm font-black text-white">
                  Social Studio
                </h3>

                <p className="mt-1 text-xs leading-5 text-white/35">
                  L’IA crée une publication
                  différente à partir du sujet
                  et de l’objectif choisis.
                </p>
              </div>
            </div>

            <div className="mt-5 grid grid-cols-2 gap-2">
              <div className="rounded-xl border border-white/10 bg-white/[0.025] p-3">
                <div className="text-[10px] font-black uppercase tracking-wider text-white/25">
                  Format
                </div>

                <div className="mt-1 text-xs font-bold text-white/70">
                  {selectedType?.label}
                </div>
              </div>

              <div className="rounded-xl border border-white/10 bg-white/[0.025] p-3">
                <div className="text-[10px] font-black uppercase tracking-wider text-white/25">
                  Objectif
                </div>

                <div className="mt-1 truncate text-xs font-bold text-white/70">
                  {objective}
                </div>
              </div>
            </div>
          </section>
        </aside>
      </div>

      <section className="rounded-3xl border border-white/10 bg-[#101010] p-5 shadow-2xl shadow-black/20 lg:p-6">
        <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p className="text-xs font-black uppercase tracking-[0.18em] text-white/35">
              Bibliothèque
            </p>

            <h2 className="mt-1 text-lg font-black text-white">
              Tes contenus
            </h2>
          </div>

          <button
            type="button"
            onClick={loadContents}
            disabled={loadingLibrary}
            className="inline-flex items-center justify-center gap-2 rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs font-bold text-white/60 hover:bg-white/10 hover:text-white disabled:opacity-40"
          >
            <RefreshCw
              size={14}
              className={
                loadingLibrary
                  ? "animate-spin"
                  : ""
              }
            />
            Actualiser
          </button>
        </div>

        {loadingLibrary ? (
          <div className="flex min-h-32 items-center justify-center text-white/30">
            <Loader2
              size={22}
              className="animate-spin"
            />
          </div>
        ) : contents.length === 0 ? (
          <div className="rounded-2xl border border-dashed border-white/10 px-5 py-12 text-center">
            <MessageSquareText
              size={28}
              className="mx-auto text-white/15"
            />

            <p className="mt-3 text-sm font-bold text-white/40">
              Aucun contenu enregistré
            </p>

            <p className="mt-1 text-xs text-white/20">
              Tes brouillons et publications
              apparaîtront ici.
            </p>
          </div>
        ) : (
          <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            {contents.map((content) => (
              <div
                key={content.id}
                className="group rounded-2xl border border-white/10 bg-white/[0.025] p-4 transition hover:border-white/20 hover:bg-white/[0.04]"
              >
                <div className="flex items-start justify-between gap-3">
                  <div className="flex min-w-0 items-center gap-2">
                    <div className="rounded-lg bg-[#C8A45D]/10 p-2 text-[#C8A45D]">
                      {content.type ===
                      "reel" ? (
                        <Film size={15} />
                      ) : content.type ===
                        "carousel" ? (
                        <Copy size={15} />
                      ) : content.type ===
                        "story" ? (
                        <Instagram
                          size={15}
                        />
                      ) : (
                        <ImageIcon
                          size={15}
                        />
                      )}
                    </div>

                    <div className="min-w-0">
                      <div className="truncate text-xs font-black text-white">
                        {
                          CONTENT_TYPES.find(
                            (item) =>
                              item.value ===
                              content.type,
                          )?.label
                        }
                      </div>

                      <div className="truncate text-[10px] text-white/30">
                        {content.topic ||
                          "Sans sujet"}
                      </div>
                    </div>
                  </div>

                  <span
                    className={[
                      "shrink-0 rounded-full border px-2 py-1 text-[10px] font-bold",
                      statusClasses(
                        content.status,
                      ),
                    ].join(" ")}
                  >
                    {statusLabel(
                      content.status,
                    )}
                  </span>
                </div>

                <p className="mt-4 line-clamp-4 whitespace-pre-wrap text-xs leading-5 text-white/50">
                  {content.caption}
                </p>

                {content.mediaUrl && (
                  <div className="mt-4 overflow-hidden rounded-xl border border-white/10">
                    <img
                      src={content.mediaUrl}
                      alt=""
                      className="aspect-video w-full object-cover"
                    />
                  </div>
                )}

                <div className="mt-4 flex items-center gap-1.5 text-[10px] text-white/25">
                  <Clock3 size={12} />

                  {content.scheduledAt
                    ? `Programmé le ${formatDate(
                        content.scheduledAt,
                      )}`
                    : `Créé le ${formatDate(
                        content.createdAt,
                      )}`}
                </div>

                <div className="mt-4 flex gap-2">
                  <button
                    type="button"
                    onClick={() =>
                      loadIntoEditor(
                        content,
                      )
                    }
                    className="flex-1 rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs font-bold text-white/60 transition hover:bg-white/10 hover:text-white"
                  >
                    Modifier
                  </button>

                  <button
                    type="button"
                    onClick={() =>
                      deleteContent(
                        content,
                      )
                    }
                    className="rounded-xl border border-red-400/10 bg-red-500/5 px-3 py-2 text-red-300/60 transition hover:bg-red-500/10 hover:text-red-200"
                  >
                    <Trash2 size={14} />
                  </button>
                </div>
              </div>
            ))}
          </div>
        )}
      </section>
    </div>
  )
}
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
  scheduledAt?: string
  status: "draft" | "scheduled" | "published"
  createdAt: string
  updatedAt?: string
  image?: string
  images?: string[]
  video?: string
  instagramMediaId?: string
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

  const [scheduledAt, setScheduledAt] = useState("")
  const [image, setImage] = useState("")
  const [images, setImages] = useState<string[]>([])
  const [video, setVideo] = useState("")

  const [loading, setLoading] = useState(false)
  const [saving, setSaving] = useState(false)
  const [publishing, setPublishing] = useState(false)
  const [generatingVisual, setGeneratingVisual] = useState(false)
  const [uploading, setUploading] = useState(false)

  const [error, setError] = useState("")

  function notify(message: string) {
    onToast?.(message)
  }

  async function apiRequest(
    action: string,
    payload: Record<string, unknown> = {},
  ) {
    const response = await fetch("/social-api.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      credentials: "same-origin",
      body: JSON.stringify({
        action,
        ...payload,
      }),
    })

    const text = await response.text()

    let data: any

    try {
      data = JSON.parse(text)
    } catch {
      throw new Error(
        text ||
          `Erreur serveur (${response.status}).`,
      )
    }

    if (response.status === 401) {
      window.location.href = "/grand-plus-admin.php"
      throw new Error("Authentification requise.")
    }

    if (!response.ok || data.success === false) {
      throw new Error(
        data.message ||
          `Erreur serveur (${response.status}).`,
      )
    }

    return data
  }

  async function loadContents() {
    try {
      setLoading(true)
      setError("")

      const response = await fetch(
        "/social-api.php",
        {
          credentials: "same-origin",
        },
      )

      if (response.status === 401) {
        window.location.href = "/grand-plus-admin.php"
        return
      }

      const data = await response.json()

      if (!data.success) {
        throw new Error(
          data.message ||
            "Impossible de charger les contenus.",
        )
      }

      setContents(
        Array.isArray(data.contents)
          ? data.contents
          : [],
      )
    } catch (err) {
      const message =
        err instanceof Error
          ? err.message
          : "Impossible de charger les contenus."

      setError(message)
    } finally {
      setLoading(false)
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
    setScheduledAt("")
    setImage("")
    setImages([])
    setVideo("")
    setError("")
  }

  function loadContent(content: SocialContent) {
    setSelected(content)
    setType(content.type)
    setTopic(content.topic || "")
    setObjective(
      content.objective ||
        "Gagner en visibilité",
    )
    setCaption(content.caption || "")
    setSlides(
      Array.isArray(content.slides)
        ? content.slides
        : [],
    )
    setScript(content.script || "")
    setScheduledAt(
      content.scheduledAt
        ? new Date(content.scheduledAt)
            .toISOString()
            .slice(0, 16)
        : "",
    )
    setImage(content.image || "")
    setImages(
      Array.isArray(content.images)
        ? content.images
        : [],
    )
    setVideo(content.video || "")
    setError("")
  }

  async function generateContent() {
    if (!topic.trim()) {
      notify("Indique d'abord un sujet.")
      return
    }

    try {
      setLoading(true)
      setError("")

      const data = await apiRequest(
        "generate",
        {
          type,
          topic,
          objective,
        },
      )

      const generated =
        data.content || {}

      setCaption(
        generated.caption || "",
      )

      setSlides(
        Array.isArray(generated.slides)
          ? generated.slides
          : [],
      )

      setScript(
        generated.script || "",
      )

      notify(
        "Contenu généré par Gemini.",
      )
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
        "Indique un sujet ou génère d'abord une légende.",
      )
      return
    }

    try {
      setGeneratingVisual(true)
      setError("")

      if (type === "carousel") {
        const slideList =
          slides.length > 0
            ? slides
            : [caption]

        const generatedImages: string[] = []

        for (
          let index = 0;
          index < slideList.length;
          index += 1
        ) {
          const data =
            await apiRequest(
              "generate_visual",
              {
                type: "carousel",
                topic,
                caption:
                  slideList[index],
                slide:
                  index + 1,
                total:
                  slideList.length,
              },
            )

          if (data.url) {
            generatedImages.push(
              data.url,
            )
          }
        }

        setImages(generatedImages)

        notify(
          `${generatedImages.length} visuel${
            generatedImages.length > 1
              ? "s"
              : ""
          } généré${
            generatedImages.length > 1
              ? "s"
              : ""
          }.`,
        )

        return
      }

      const data =
        await apiRequest(
          "generate_visual",
          {
            type,
            topic,
            caption,
          },
        )

      if (data.url) {
        setImage(data.url)
      }

      notify("Visuel généré.")
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

      const content: SocialContent = {
        id:
          selected?.id ||
          "",
        type,
        topic,
        objective,
        caption,
        slides,
        script,
        scheduledAt: scheduledAt
          ? new Date(
              scheduledAt,
            ).toISOString()
          : undefined,
        status: scheduledAt
          ? "scheduled"
          : selected?.status ===
            "published"
          ? "published"
          : "draft",
        createdAt:
          selected?.createdAt ||
          new Date().toISOString(),
        updatedAt:
          new Date().toISOString(),
        image,
        images,
        video,
      }

      const data =
        await apiRequest(
          "save",
          {
            content,
          },
        )

      const saved =
        data.content

      if (saved) {
        setSelected(saved)

        setContents((current) => {
          const exists =
            current.some(
              (item) =>
                item.id === saved.id,
            )

          if (exists) {
            return current.map(
              (item) =>
                item.id === saved.id
                  ? saved
                  : item,
            )
          }

          return [
            saved,
            ...current,
          ]
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
    const confirmed =
      window.confirm(
        "Supprimer définitivement ce contenu ?",
      )

    if (!confirmed) {
      return
    }

    try {
      await apiRequest(
        "delete",
        {
          id: content.id,
        },
      )

      setContents((current) =>
        current.filter(
          (item) =>
            item.id !== content.id,
        ),
      )

      if (
        selected?.id ===
        content.id
      ) {
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

    if (
      type === "carousel" &&
      images.length === 0
    ) {
      notify(
        "Génère les visuels du carrousel avant de publier.",
      )
      return
    }

    if (
      type !== "carousel" &&
      type !== "reel" &&
      !image
    ) {
      notify(
        "Génère ou ajoute un visuel avant de publier.",
      )
      return
    }

    if (
      type === "reel" &&
      !video
    ) {
      notify(
        "Ajoute une vidéo pour publier le Reel.",
      )
      return
    }

    try {
      setPublishing(true)
      setError("")

      const data =
        await apiRequest(
          "publish",
          {
            id:
              selected?.id ||
              "",
            type,
            caption,
            image,
            images,
            video,
            slides,
            script,
          },
        )

      const mediaId =
        data.media_id ||
        data.instagramMediaId

      if (selected?.id) {
        setContents((current) =>
          current.map(
            (item) =>
              item.id ===
              selected.id
                ? {
                    ...item,
                    status:
                      "published",
                    updatedAt:
                      new Date().toISOString(),
                    instagramMediaId:
                      mediaId,
                  }
                : item,
          ),
        )

        setSelected((current) =>
          current
            ? {
                ...current,
                status:
                  "published",
                updatedAt:
                  new Date().toISOString(),
                instagramMediaId:
                  mediaId,
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
    if (slides.length >= 10) {
      notify(
        "Instagram autorise au maximum 10 slides.",
      )
      return
    }

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
      current.map(
        (slide, slideIndex) =>
          slideIndex === index
            ? value
            : slide,
      ),
    )
  }

  function removeSlide(
    index: number,
  ) {
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

  const selectedType =
    useMemo(
      () =>
        CONTENT_TYPES.find(
          (item) =>
            item.value === type,
        ),
      [type],
    )

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
            Crée, prépare, programme et publie les contenus sociaux de Vitrine+ depuis ton administration.
          </p>
        </div>

        <div className="flex gap-2">
          <button
            type="button"
            onClick={loadContents}
            className="inline-flex items-center justify-center gap-2 rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-bold text-white transition hover:bg-white/10"
          >
            <RefreshCw
              size={17}
              className={
                loading
                  ? "animate-spin"
                  : ""
              }
            />
            Actualiser
          </button>

          <button
            type="button"
            onClick={resetEditor}
            className="inline-flex items-center justify-center gap-2 rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-bold text-white transition hover:bg-white/10"
          >
            <Plus size={17} />
            Nouveau contenu
          </button>
        </div>
      </div>

      {error && (
        <div className="flex items-start justify-between gap-4 rounded-2xl border border-red-400/20 bg-red-500/10 px-4 py-3 text-sm text-red-200">
          <div>{error}</div>

          <button
            type="button"
            onClick={() =>
              setError("")
            }
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
              <span
                className={`rounded-full border px-3 py-1.5 text-xs font-bold ${statusClasses(
                  selected.status,
                )}`}
              >
                {statusLabel(
                  selected.status,
                )}
              </span>
            )}
          </div>

          <div>
            <label className="mb-3 block text-xs font-black uppercase tracking-[0.16em] text-white/45">
              Format
            </label>

            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
              {CONTENT_TYPES.map(
                (item) => {
                  const Icon =
                    item.icon
                  const active =
                    type ===
                    item.value

                  return (
                    <button
                      type="button"
                      key={
                        item.value
                      }
                      onClick={() =>
                        setType(
                          item.value,
                        )
                      }
                      className={`rounded-2xl border p-4 text-left transition ${
                        active
                          ? "border-[#C8A45D]/40 bg-[#C8A45D]/10"
                          : "border-white/10 bg-white/[0.02] hover:bg-white/5"
                      }`}
                    >
                      <Icon
                        size={20}
                        className={
                          active
                            ? "text-[#C8A45D]"
                            : "text-white/50"
                        }
                      />

                      <p className="mt-3 text-sm font-black text-white">
                        {item.label}
                      </p>

                      <p className="mt-1 text-xs leading-5 text-white/35">
                        {
                          item.description
                        }
                      </p>
                    </button>
                  )
                },
              )}
            </div>
          </div>

          <div className="mt-6 grid gap-5 md:grid-cols-2">
            <div>
              <label className="mb-2 block text-xs font-black uppercase tracking-[0.16em] text-white/45">
                Sujet
              </label>

              <input
                value={topic}
                onChange={(event) =>
                  setTopic(
                    event.target.value,
                  )
                }
                placeholder="Ex. 5 erreurs d'un site internet"
                className="w-full rounded-2xl border border-white/10 bg-black/30 px-4 py-3 text-sm text-white outline-none placeholder:text-white/20 focus:border-[#C8A45D]/40"
              />
            </div>

            <div>
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
                  className="w-full appearance-none rounded-2xl border border-white/10 bg-black/30 px-4 py-3 text-sm text-white outline-none focus:border-[#C8A45D]/40"
                >
                  {OBJECTIVES.map(
                    (item) => (
                      <option
                        key={item}
                        value={item}
                        className="bg-[#101010]"
                      >
                        {item}
                      </option>
                    ),
                  )}
                </select>

                <ChevronDown
                  size={17}
                  className="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-white/40"
                />
              </div>
            </div>
          </div>

          <div className="mt-6 flex flex-wrap gap-3">
            <button
              type="button"
              onClick={generateContent}
              disabled={loading}
              className="inline-flex items-center justify-center gap-2 rounded-xl bg-[#C8A45D] px-4 py-3 text-sm font-black text-black transition hover:bg-[#d7b76d] disabled:cursor-not-allowed disabled:opacity-50"
            >
              {loading ? (
                <Loader2
                  size={17}
                  className="animate-spin"
                />
              ) : (
                <WandSparkles
                  size={17}
                />
              )}

              Générer avec Gemini
            </button>

            <button
              type="button"
              onClick={generateVisual}
              disabled={
                generatingVisual
              }
              className="inline-flex items-center justify-center gap-2 rounded-xl border border-[#C8A45D]/30 bg-[#C8A45D]/10 px-4 py-3 text-sm font-black text-[#C8A45D] transition hover:bg-[#C8A45D]/15 disabled:cursor-not-allowed disabled:opacity-50"
            >
              {generatingVisual ? (
                <Loader2
                  size={17}
                  className="animate-spin"
                />
              ) : (
                <Sparkles
                  size={17}
                />
              )}

              Générer le visuel
            </button>
          </div>

          <div className="mt-6">
            <div className="mb-2 flex items-center justify-between">
              <label className="block text-xs font-black uppercase tracking-[0.16em] text-white/45">
                Légende Instagram
              </label>

              <button
                type="button"
                onClick={copyCaption}
                disabled={!caption}
                className="inline-flex items-center gap-1.5 text-xs font-bold text-white/40 hover:text-white disabled:opacity-30"
              >
                <Copy size={14} />
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
              rows={9}
              placeholder="La légende générée apparaîtra ici..."
              className="w-full resize-y rounded-2xl border border-white/10 bg-black/30 px-4 py-3 text-sm leading-6 text-white outline-none placeholder:text-white/20 focus:border-[#C8A45D]/40"
            />
          </div>

          {type ===
            "carousel" && (
            <div className="mt-6">
              <div className="mb-3 flex items-center justify-between">
                <label className="text-xs font-black uppercase tracking-[0.16em] text-white/45">
                  Slides du carrousel
                </label>

                <button
                  type="button"
                  onClick={
                    addSlide
                  }
                  disabled={
                    slides.length >=
                    10
                  }
                  className="inline-flex items-center gap-1.5 rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-xs font-bold text-white/70 hover:bg-white/10 disabled:opacity-30"
                >
                  <Plus
                    size={14}
                  />
                  Ajouter
                </button>
              </div>

              <div className="space-y-3">
                {slides.map(
                  (
                    slide,
                    index,
                  ) => (
                    <div
                      key={`${index}-${slide.slice(
                        0,
                        10,
                      )}`}
                      className="flex gap-3"
                    >
                      <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#C8A45D]/10 text-sm font-black text-[#C8A45D]">
                        {index + 1}
                      </div>

                      <textarea
                        value={slide}
                        onChange={(
                          event,
                        ) =>
                          updateSlide(
                            index,
                            event
                              .target
                              .value,
                          )
                        }
                        rows={3}
                        className="min-w-0 flex-1 resize-y rounded-xl border border-white/10 bg-black/30 px-3 py-2 text-sm text-white outline-none focus:border-[#C8A45D]/40"
                      />

                      <button
                        type="button"
                        onClick={() =>
                          removeSlide(
                            index,
                          )
                        }
                        className="self-start rounded-xl p-2 text-white/30 hover:bg-red-500/10 hover:text-red-300"
                      >
                        <Trash2
                          size={16}
                        />
                      </button>
                    </div>
                  ),
                )}

                {slides.length ===
                  0 && (
                  <div className="rounded-2xl border border-dashed border-white/10 px-4 py-8 text-center text-sm text-white/30">
                    Génère le contenu ou ajoute tes slides manuellement.
                  </div>
                )}
              </div>
            </div>
          )}

          {type === "reel" && (
            <div className="mt-6">
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
                rows={9}
                placeholder="Le script généré par Gemini apparaîtra ici..."
                className="w-full resize-y rounded-2xl border border-white/10 bg-black/30 px-4 py-3 text-sm leading-6 text-white outline-none placeholder:text-white/20 focus:border-[#C8A45D]/40"
              />
            </div>
          )}

          <div className="mt-6 grid gap-5 md:grid-cols-2">
            <div>
              <label className="mb-2 flex items-center gap-2 text-xs font-black uppercase tracking-[0.16em] text-white/45">
                <CalendarClock
                  size={14}
                />
                Programmation
              </label>

              <input
                type="datetime-local"
                value={scheduledAt}
                onChange={(event) =>
                  setScheduledAt(
                    event.target
                      .value,
                  )
                }
                className="w-full rounded-2xl border border-white/10 bg-black/30 px-4 py-3 text-sm text-white outline-none focus:border-[#C8A45D]/40"
              />
            </div>

            <div className="flex items-end">
              <div className="rounded-2xl border border-white/10 bg-white/[0.02] p-4 text-xs leading-5 text-white/35">
                {scheduledAt
                  ? `Publication prévue le ${formatDate(
                      new Date(
                        scheduledAt,
                      ).toISOString(),
                    )}`
                  : "Sans date : le contenu sera enregistré comme brouillon."}
              </div>
            </div>
          </div>

          <div className="mt-6 flex flex-wrap gap-3 border-t border-white/10 pt-6">
            <button
              type="button"
              onClick={
                saveContent
              }
              disabled={saving}
              className="inline-flex items-center justify-center gap-2 rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-black text-white transition hover:bg-white/10 disabled:opacity-50"
            >
              {saving ? (
                <Loader2
                  size={17}
                  className="animate-spin"
                />
              ) : (
                <Save
                  size={17}
                />
              )}
              Enregistrer
            </button>

            <button
              type="button"
              onClick={
                publishInstagram
              }
              disabled={
                publishing
              }
              className="inline-flex items-center justify-center gap-2 rounded-xl bg-[#C8A45D] px-5 py-3 text-sm font-black text-black transition hover:bg-[#d7b76d] disabled:cursor-not-allowed disabled:opacity-50"
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
                  <Send
                    size={17}
                  />
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

              {selectedType && (
                <div className="flex items-center gap-2 text-xs font-bold text-white/40">
                  <selectedType.icon
                    size={16}
                  />
                  {
                    selectedType.label
                  }
                </div>
              )}
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

              <div className="flex aspect-square items-center justify-center overflow-hidden bg-gradient-to-br from-[#171717] via-[#0b0b0b] to-[#1a1a1a]">
                {type ===
                  "carousel" &&
                images.length >
                  0 ? (
                  <img
                    src={images[0]}
                    alt=""
                    className="h-full w-full object-cover"
                  />
                ) : image ? (
                  <img
                    src={image}
                    alt=""
                    className="h-full w-full object-cover"
                  />
                ) : type ===
                  "reel" ? (
                  <div className="text-center">
                    <Film
                      size={36}
                      className="mx-auto text-[#C8A45D]"
                    />

                    <p className="mt-3 text-sm font-black text-white">
                      Reel Vitrine+
                    </p>
                  </div>
                ) : type ===
                  "story" ? (
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
                      La légende apparaîtra ici...
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
                <Sparkles
                  size={18}
                />
              </div>

              <div>
                <h3 className="text-sm font-black text-white">
                  Social Studio
                </h3>

                <p className="mt-1 text-xs leading-5 text-white/35">
                  Gemini génère les idées, légendes et scripts. Les visuels sont ensuite préparés pour Instagram.
                </p>
              </div>
            </div>
          </section>

          <section className="rounded-3xl border border-white/10 bg-[#101010] p-5">
            <div className="mb-4 flex items-center justify-between">
              <div>
                <p className="text-xs font-black uppercase tracking-[0.18em] text-white/35">
                  Bibliothèque
                </p>

                <h2 className="mt-1 text-lg font-black text-white">
                  Contenus
                </h2>
              </div>

              <MessageSquareText
                size={19}
                className="text-[#C8A45D]"
              />
            </div>

            <div className="space-y-2">
              {contents.length ===
                0 ? (
                <div className="rounded-2xl border border-dashed border-white/10 px-4 py-8 text-center text-sm text-white/30">
                  Aucun contenu enregistré.
                </div>
              ) : (
                contents.map(
                  (content) => (
                    <div
                      key={
                        content.id
                      }
                      className="group rounded-2xl border border-white/10 bg-white/[0.02] p-3 transition hover:bg-white/5"
                    >
                      <button
                        type="button"
                        onClick={() =>
                          loadContent(
                            content,
                          )
                        }
                        className="w-full text-left"
                      >
                        <div className="flex items-start gap-3">
                          <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#C8A45D]/10 text-[#C8A45D]">
                            {content.type ===
                            "carousel" ? (
                              <Copy
                                size={
                                  16
                                }
                              />
                            ) : content.type ===
                              "reel" ? (
                              <Film
                                size={
                                  16
                                }
                              />
                            ) : content.type ===
                              "story" ? (
                              <Instagram
                                size={
                                  16
                                }
                              />
                            ) : (
                              <ImageIcon
                                size={
                                  16
                                }
                              />
                            )}
                          </div>

                          <div className="min-w-0 flex-1">
                            <div className="flex items-center justify-between gap-2">
                              <p className="truncate text-xs font-black text-white">
                                {
                                  content.topic ||
                                  "Sans sujet"
                                }
                              </p>

                              <span
                                className={`shrink-0 rounded-full border px-2 py-1 text-[9px] font-bold ${statusClasses(
                                  content.status,
                                )}`}
                              >
                                {statusLabel(
                                  content.status,
                                )}
                              </span>
                            </div>

                            <p className="mt-1 line-clamp-2 text-[11px] leading-5 text-white/35">
                              {
                                content.caption
                              }
                            </p>

                            {content.scheduledAt && (
                              <div className="mt-2 flex items-center gap-1.5 text-[10px] text-blue-300/60">
                                <Clock3
                                  size={
                                    12
                                  }
                                />
                                {
                                  formatDate(
                                    content.scheduledAt,
                                  )
                                }
                              </div>
                            )}
                          </div>
                        </div>
                      </button>

                      <div className="mt-2 flex justify-end opacity-0 transition group-hover:opacity-100">
                        <button
                          type="button"
                          onClick={() =>
                            deleteContent(
                              content,
                            )
                          }
                          className="rounded-lg p-1.5 text-white/25 hover:bg-red-500/10 hover:text-red-300"
                        >
                          <Trash2
                            size={
                              14
                            }
                          />
                        </button>
                      </div>
                    </div>
                  ),
                )
              )}
            </div>
          </section>
        </aside>
      </div>
    </div>
  )
}
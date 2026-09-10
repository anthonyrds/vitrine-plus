import { useEffect, useMemo, useRef, useState } from "react"
import {
  CalendarClock,
  Check,
  ChevronDown,
  Copy,
  Film,
  Image as ImageIcon,
  Instagram,
  Loader2,
  Plus,
  RefreshCw,
  Save,
  Send,
  Sparkles,
  Trash2,
  Upload,
  WandSparkles,
  X,
  Play,
} from "lucide-react"

type ContentType = "post" | "carousel" | "reel" | "story"

type ContentStatus =
  | "draft"
  | "scheduled"
  | "published"
  | "scheduled_error"

type SocialContent = {
  id: string
  type: ContentType
  topic: string
  objective: string
  caption: string
  slides?: string[]
  script?: string
  mediaUrls?: string[]
  videoUrl?: string
  scheduledAt?: string
  status: ContentStatus
  createdAt: string
  updatedAt?: string
  instagram?: Record<string, unknown>
  publishError?: string
  title?: string
}

type SocialStudioProps = {
  onToast?: (message: string) => void
}

type GeneratedVideoResult = {
  blob: Blob
  extension: string
  mimeType: string
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
    description: "Post Instagram classique",
    icon: ImageIcon,
  },
  {
    value: "carousel",
    label: "Carrousel",
    description: "2 à 10 slides",
    icon: Copy,
  },
  {
    value: "reel",
    label: "Reel",
    description: "Vidéo verticale",
    icon: Film,
  },
  {
    value: "story",
    label: "Story",
    description: "Format vertical",
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

function statusLabel(status: ContentStatus) {
  if (status === "published") return "Publié"
  if (status === "scheduled") return "Programmé"
  if (status === "scheduled_error") return "Erreur de publication"

  return "Brouillon"
}

function statusClasses(status: ContentStatus) {
  if (status === "published") {
    return "bg-emerald-500/10 text-emerald-300 border-emerald-400/20"
  }

  if (status === "scheduled") {
    return "bg-blue-500/10 text-blue-300 border-blue-400/20"
  }

  if (status === "scheduled_error") {
    return "bg-red-500/10 text-red-300 border-red-400/20"
  }

  return "bg-white/5 text-white/60 border-white/10"
}

function getSupportedVideoMimeType() {
  if (
    typeof MediaRecorder !== "undefined" &&
    MediaRecorder.isTypeSupported("video/mp4;codecs=h264")
  ) {
    return "video/mp4;codecs=h264"
  }

  if (
    typeof MediaRecorder !== "undefined" &&
    MediaRecorder.isTypeSupported("video/mp4")
  ) {
    return "video/mp4"
  }

  if (
    typeof MediaRecorder !== "undefined" &&
    MediaRecorder.isTypeSupported("video/webm;codecs=vp9")
  ) {
    return "video/webm;codecs=vp9"
  }

  if (
    typeof MediaRecorder !== "undefined" &&
    MediaRecorder.isTypeSupported("video/webm;codecs=vp8")
  ) {
    return "video/webm;codecs=vp8"
  }

  if (
    typeof MediaRecorder !== "undefined" &&
    MediaRecorder.isTypeSupported("video/webm")
  ) {
    return "video/webm"
  }

  return ""
}

function extensionFromMimeType(mimeType: string) {
  if (mimeType.includes("mp4")) {
    return "mp4"
  }

  return "webm"
}

function sleep(milliseconds: number) {
  return new Promise<void>((resolve) => {
    window.setTimeout(resolve, milliseconds)
  })
}

function wrapCanvasText(
  context: CanvasRenderingContext2D,
  text: string,
  maxWidth: number,
) {
  const words = text.trim().split(/\s+/)
  const lines: string[] = []

  let currentLine = ""

  for (const word of words) {
    const candidate = currentLine
      ? `${currentLine} ${word}`
      : word

    const metrics = context.measureText(candidate)

    if (metrics.width > maxWidth && currentLine) {
      lines.push(currentLine)
      currentLine = word
    } else {
      currentLine = candidate
    }
  }

  if (currentLine) {
    lines.push(currentLine)
  }

  return lines
}

function drawWrappedCanvasText(
  context: CanvasRenderingContext2D,
  text: string,
  x: number,
  y: number,
  maxWidth: number,
  lineHeight: number,
) {
  const paragraphs = text
    .split("\n")
    .map((value) => value.trim())
    .filter(Boolean)

  let currentY = y

  for (const paragraph of paragraphs) {
    const lines = wrapCanvasText(
      context,
      paragraph,
      maxWidth,
    )

    for (const line of lines) {
      context.fillText(line, x, currentY)
      currentY += lineHeight
    }

    currentY += lineHeight * 0.45
  }

  return currentY
}

async function loadImage(url: string) {
  const image = new Image()

  image.crossOrigin = "anonymous"

  await new Promise<void>((resolve, reject) => {
    image.onload = () => resolve()
    image.onerror = () =>
      reject(
        new Error(
          "Impossible de charger le visuel du Reel.",
        ),
      )

    image.src = url
  })

  return image
}

async function createReelVideo(
  imageUrl: string,
  scriptText: string,
): Promise<GeneratedVideoResult> {
  if (
    typeof document === "undefined" ||
    typeof MediaRecorder === "undefined"
  ) {
    throw new Error(
      "La génération vidéo n’est pas disponible dans ce navigateur.",
    )
  }

  const mimeType = getSupportedVideoMimeType()

  if (!mimeType) {
    throw new Error(
      "Ce navigateur ne permet pas de générer une vidéo compatible.",
    )
  }

  const image = await loadImage(imageUrl)

  const canvas = document.createElement("canvas")

  canvas.width = 1080
  canvas.height = 1920

  const context = canvas.getContext("2d")

  if (!context) {
    throw new Error(
      "Impossible d’initialiser le moteur vidéo.",
    )
  }

  const stream = canvas.captureStream(30)

  const chunks: BlobPart[] = []

  const recorder = new MediaRecorder(stream, {
    mimeType,
    videoBitsPerSecond: 6_000_000,
  })

  recorder.ondataavailable = (event) => {
    if (event.data && event.data.size > 0) {
      chunks.push(event.data)
    }
  }

  const duration = 12_000

  const recorderStopped = new Promise<void>(
    (resolve) => {
      recorder.onstop = () => resolve()
    },
  )

  recorder.start(250)

  const start = performance.now()

  while (performance.now() - start < duration) {
    const elapsed = performance.now() - start
    const progress = Math.min(
      elapsed / duration,
      1,
    )

    context.clearRect(
      0,
      0,
      canvas.width,
      canvas.height,
    )

    /*
     * Fond noir premium.
     */
    context.fillStyle = "#080808"

    context.fillRect(
      0,
      0,
      canvas.width,
      canvas.height,
    )

    /*
     * Image centrée en conservant son ratio.
     */
    const imageRatio =
      image.width / image.height

    const canvasRatio =
      canvas.width / canvas.height

    let drawWidth = canvas.width
    let drawHeight = canvas.height

    if (imageRatio > canvasRatio) {
      drawHeight =
        canvas.width / imageRatio
    } else {
      drawWidth =
        canvas.height * imageRatio
    }

    const imageX =
      (canvas.width - drawWidth) / 2

    const imageY =
      (canvas.height - drawHeight) / 2

    const zoom =
      1 +
      Math.sin(progress * Math.PI) * 0.035

    const zoomWidth =
      drawWidth * zoom

    const zoomHeight =
      drawHeight * zoom

    const zoomX =
      (canvas.width - zoomWidth) / 2

    const zoomY =
      (canvas.height - zoomHeight) / 2

    context.save()

    context.globalAlpha = 0.96

    context.drawImage(
      image,
      zoomX,
      zoomY,
      zoomWidth,
      zoomHeight,
    )

    context.restore()

    /*
     * Overlay sombre pour garder le texte lisible.
     */
    const gradient =
      context.createLinearGradient(
        0,
        0,
        0,
        canvas.height,
      )

    gradient.addColorStop(
      0,
      "rgba(0,0,0,0.15)",
    )

    gradient.addColorStop(
      0.5,
      "rgba(0,0,0,0.15)",
    )

    gradient.addColorStop(
      1,
      "rgba(0,0,0,0.92)",
    )

    context.fillStyle = gradient

    context.fillRect(
      0,
      0,
      canvas.width,
      canvas.height,
    )

    /*
     * Barre supérieure Vitrine+.
     */
    context.fillStyle = "#C8A45D"

    context.fillRect(
      70,
      78,
      940,
      6,
    )

    context.font =
      "900 34px Arial, sans-serif"

    context.fillText(
      "VITRINE+",
      75,
      150,
    )

    /*
     * Script du Reel.
     */
    context.font =
      "800 52px Arial, sans-serif"

    context.fillStyle = "#FFFFFF"

    const safeScript =
      scriptText.trim() ||
      "Votre entreprise mérite une présence en ligne à la hauteur de vos ambitions."

    drawWrappedCanvasText(
      context,
      safeScript,
      80,
      1250,
      920,
      70,
    )

    /*
     * CTA.
     */
    context.font =
      "700 30px Arial, sans-serif"

    context.fillStyle = "#C8A45D"

    context.fillText(
      "Votre entreprise. En mieux.",
      80,
      1770,
    )

    context.font =
      "600 26px Arial, sans-serif"

    context.fillStyle = "#D0D0D0"

    context.fillText(
      "vitrineplus.fr",
      80,
      1820,
    )

    await sleep(33)
  }

  recorder.stop()

  await recorderStopped

  stream.getTracks().forEach(
    (track) => track.stop(),
  )

  const blob = new Blob(
    chunks,
    {
      type: mimeType,
    },
  )

  if (blob.size === 0) {
    throw new Error(
      "La vidéo générée est vide.",
    )
  }

  return {
    blob,
    extension:
      extensionFromMimeType(mimeType),
    mimeType,
  }
}

export default function SocialStudio({
  onToast,
}: SocialStudioProps) {
  const [contents, setContents] =
    useState<SocialContent[]>([])

  const [selected, setSelected] =
    useState<SocialContent | null>(null)

  const [type, setType] =
    useState<ContentType>("post")

  const [topic, setTopic] =
    useState("")

  const [objective, setObjective] =
    useState("Gagner en visibilité")

  const [caption, setCaption] =
    useState("")

  const [slides, setSlides] =
    useState<string[]>([])

  const [mediaUrls, setMediaUrls] =
    useState<string[]>([])

  const [videoUrl, setVideoUrl] =
    useState("")

  const [script, setScript] =
    useState("")

  const [scheduledAt, setScheduledAt] =
    useState("")

  const [loading, setLoading] =
    useState(false)

  const [publishing, setPublishing] =
    useState(false)

  const [saving, setSaving] =
    useState(false)

  const [uploading, setUploading] =
    useState(false)

  const [generatingReel, setGeneratingReel] =
    useState(false)

  const [loadingLibrary, setLoadingLibrary] =
    useState(false)

  const [error, setError] =
    useState("")

  const videoObjectUrlRef =
    useRef<string | null>(null)

  const notify = (message: string) => {
    if (onToast) {
      onToast(message)
    } else {
      window.alert(message)
    }
  }

  const selectedType = useMemo(
    () =>
      CONTENT_TYPES.find(
        (item) => item.value === type,
      ),
    [type],
  )

  async function apiRequest(
    action: string,
    payload: Record<string, unknown> = {},
  ) {
    const response = await fetch(
      "/social-api.php",
      {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type":
            "application/json",
        },
        body: JSON.stringify({
          action,
          ...payload,
        }),
      },
    )

    if (response.status === 401) {
      window.location.href =
        "/grand-plus-admin.php"

      throw new Error(
        "Session administrateur expirée.",
      )
    }

    const data =
      await response
        .json()
        .catch(() => null)

    if (
      !response.ok ||
      !data?.success
    ) {
      throw new Error(
        data?.message ||
          "Une erreur est survenue.",
      )
    }

    return data
  }

  async function loadContents() {
    try {
      setLoadingLibrary(true)

      const data =
        await apiRequest("list")

      setContents(
        Array.isArray(data.contents)
          ? data.contents
          : [],
      )
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Impossible de charger les contenus.",
      )
    } finally {
      setLoadingLibrary(false)
    }
  }

  useEffect(() => {
    void loadContents()

    return () => {
      if (
        videoObjectUrlRef.current
      ) {
        URL.revokeObjectURL(
          videoObjectUrlRef.current,
        )
      }
    }
  }, [])

  function clearGeneratedVideo() {
    if (
      videoObjectUrlRef.current
    ) {
      URL.revokeObjectURL(
        videoObjectUrlRef.current,
      )

      videoObjectUrlRef.current =
        null
    }

    setVideoUrl("")
  }

  function resetEditor() {
    setSelected(null)

    setType("post")

    setTopic("")

    setObjective(
      "Gagner en visibilité",
    )

    setCaption("")

    setSlides([])

    setMediaUrls([])

    clearGeneratedVideo()

    setScript("")

    setScheduledAt("")

    setError("")
  }

  function loadIntoEditor(
    content: SocialContent,
  ) {
    setSelected(content)

    setType(content.type)

    setTopic(content.topic || "")

    setObjective(
      content.objective ||
        "Gagner en visibilité",
    )

    setCaption(
      content.caption || "",
    )

    setSlides(
      Array.isArray(content.slides)
        ? content.slides
        : [],
    )

    setMediaUrls(
      Array.isArray(content.mediaUrls)
        ? content.mediaUrls
        : [],
    )

    if (
      videoObjectUrlRef.current
    ) {
      URL.revokeObjectURL(
        videoObjectUrlRef.current,
      )

      videoObjectUrlRef.current =
        null
    }

    setVideoUrl(
      content.videoUrl || "",
    )

    setScript(
      content.script || "",
    )

    setScheduledAt(
      content.scheduledAt || "",
    )

    setError(
      content.publishError || "",
    )

    window.scrollTo({
      top: 0,
      behavior: "smooth",
    })
  }

  async function generateContent() {
    if (!topic.trim()) {
      notify(
        "Indique d'abord le sujet du contenu.",
      )

      return
    }

    try {
      setLoading(true)

      setError("")

      const data =
        await apiRequest(
          "generate",
          {
            type,
            topic: topic.trim(),
            objective,
          },
        )

      const generated =
        data.content

      if (!generated) {
        throw new Error(
          "Le serveur n'a retourné aucun contenu.",
        )
      }

      setSelected(null)

      setCaption(
        generated.caption || "",
      )

      setSlides(
        Array.isArray(
          generated.slides,
        )
          ? generated.slides
          : [],
      )

      setScript(
        generated.script || "",
      )

      setMediaUrls([])

      clearGeneratedVideo()

      /*
       * Génération automatique des visuels.
       */
      const visual =
        await apiRequest(
          "generate_visual",
          {
            type,
            topic: topic.trim(),
            caption:
              generated.caption || "",
            slides:
              Array.isArray(
                generated.slides,
              )
                ? generated.slides
                : [],
          },
        )

      const generatedUrls =
        Array.isArray(visual.urls)
          ? visual.urls
          : []

      setMediaUrls(
        generatedUrls,
      )

      /*
       * Pour un Reel, on génère
       * automatiquement une vidéo
       * à partir du visuel.
       */
      if (
        type === "reel" &&
        generatedUrls[0]
      ) {
        await generateReelFromVisual(
          generatedUrls[0],
          generated.script || "",
        )
      } else {
        notify(
          "Contenu et visuels générés avec succès.",
        )
      }
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

  async function regenerateVisuals() {
    if (
      !caption.trim() &&
      !topic.trim()
    ) {
      notify(
        "Ajoute un sujet ou une légende avant de générer un visuel.",
      )

      return
    }

    try {
      setLoading(true)

      setError("")

      const data =
        await apiRequest(
          "generate_visual",
          {
            type,
            topic,
            caption,
            slides,
          },
        )

      const urls =
        Array.isArray(data.urls)
          ? data.urls
          : []

      setMediaUrls(urls)

      /*
       * Si c'est un Reel,
       * on régénère aussi automatiquement
       * la vidéo.
       */
      if (
        type === "reel" &&
        urls[0]
      ) {
        await generateReelFromVisual(
          urls[0],
          script,
        )
      } else {
        notify(
          "Visuel(s) régénéré(s).",
        )
      }
    } catch (err) {
      const message =
        err instanceof Error
          ? err.message
          : "Impossible de générer le visuel."

      setError(message)

      notify(message)
    } finally {
      setLoading(false)
    }
  }

  async function uploadVideoBlob(
    blob: Blob,
    extension: string,
  ) {
    const form =
      new FormData()

    const filename =
      `vitrine-reel-${Date.now()}.${extension}`

    form.append(
      "action",
      "upload_media",
    )

    form.append(
      "media",
      blob,
      filename,
    )

    const response =
      await fetch(
        "/social-api.php",
        {
          method: "POST",
          credentials:
            "same-origin",
          body: form,
        },
      )

    if (
      response.status === 401
    ) {
      window.location.href =
        "/grand-plus-admin.php"

      throw new Error(
        "Session administrateur expirée.",
      )
    }

    const data =
      await response
        .json()
        .catch(() => null)

    if (
      !response.ok ||
      !data?.success ||
      !data?.media?.url
    ) {
      throw new Error(
        data?.message ||
          "Impossible d'envoyer la vidéo au serveur.",
      )
    }

    return data.media.url as string
  }

  async function generateReelFromVisual(
    imageUrl: string,
    scriptText: string,
  ) {
    try {
      setGeneratingReel(true)

      setError("")

      notify(
        "Création automatique du Reel en cours…",
      )

      const generated =
        await createReelVideo(
          imageUrl,
          scriptText,
        )

      /*
       * Si le navigateur produit directement
       * du MP4, on l'envoie comme tel.
       *
       * Si le navigateur produit du WebM,
       * le serveur actuel ne pourra pas forcément
       * le publier comme Reel Instagram.
       */
      const publicUrl =
        await uploadVideoBlob(
          generated.blob,
          generated.extension,
        )

      setVideoUrl(
        publicUrl,
      )

      notify(
        generated.extension ===
          "mp4"
          ? "Reel MP4 généré et envoyé au serveur."
          : "Vidéo Reel générée. Le navigateur a produit du WebM ; une conversion MP4 sera nécessaire avant publication Instagram.",
      )
    } catch (err) {
      const message =
        err instanceof Error
          ? err.message
          : "Impossible de générer le Reel."

      setError(message)

      notify(message)

      throw err
    } finally {
      setGeneratingReel(false)
    }
  }

  async function uploadMedia(
    file: File,
  ) {
    try {
      setUploading(true)

      setError("")

      const form =
        new FormData()

      form.append(
        "action",
        "upload_media",
      )

      form.append(
        "media",
        file,
      )

      const response =
        await fetch(
          "/social-api.php",
          {
            method: "POST",
            credentials:
              "same-origin",
            body: form,
          },
        )

      if (
        response.status === 401
      ) {
        window.location.href =
          "/grand-plus-admin.php"

        throw new Error(
          "Session administrateur expirée.",
        )
      }

      const data =
        await response
          .json()
          .catch(() => null)

      if (
        !response.ok ||
        !data?.success
      ) {
        throw new Error(
          data?.message ||
            "Échec de l'envoi du média.",
        )
      }

      const media =
        data.media

      if (
        media.type ===
        "video"
      ) {
        clearGeneratedVideo()

        setVideoUrl(
          media.url,
        )

        notify(
          "Vidéo ajoutée.",
        )
      } else {
        setMediaUrls(
          (current) =>
            type ===
            "carousel"
              ? [
                  ...current,
                  media.url,
                ].slice(0, 10)
              : [media.url],
        )

        notify(
          "Visuel ajouté.",
        )

        /*
         * Pour un Reel,
         * une image importée peut servir
         * de base pour créer automatiquement
         * une vidéo.
         */
        if (
          type === "reel"
        ) {
          await generateReelFromVisual(
            media.url,
            script,
          )
        }
      }
    } catch (err) {
      const message =
        err instanceof Error
          ? err.message
          : "Impossible d'importer le média."

      setError(message)

      notify(message)
    } finally {
      setUploading(false)
    }
  }

  async function saveContent() {
    if (!caption.trim()) {
      notify(
        "La légende est vide.",
      )

      return
    }

    if (
      type === "reel" &&
      !videoUrl
    ) {
      notify(
        "Ajoute ou génère une vidéo MP4/MOV pour le Reel avant de l'enregistrer.",
      )

      return
    }

    if (
      (
        type === "post" ||
        type === "story"
      ) &&
      mediaUrls.length === 0
    ) {
      notify(
        "Ajoute ou génère un visuel avant d'enregistrer.",
      )

      return
    }

    if (
      type === "carousel" &&
      mediaUrls.length < 2
    ) {
      notify(
        "Un carrousel doit contenir au moins 2 visuels.",
      )

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
        mediaUrls,
        videoUrl,
        scheduledAt:
          scheduledAt ||
          undefined,
        status: scheduledAt
          ? "scheduled"
          : "draft",
      }

      const data =
        await apiRequest(
          "save",
          {
            content,
          },
        )

      const saved =
        data.content as SocialContent

      setSelected(saved)

      setContents(
        (current) =>
          current.some(
            (item) =>
              item.id ===
              saved.id,
          )
            ? current.map(
                (item) =>
                  item.id ===
                  saved.id
                    ? saved
                    : item,
              )
            : [
                saved,
                ...current,
              ],
      )

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

  async function publishInstagram() {
    if (!caption.trim()) {
      notify(
        "La légende est vide.",
      )

      return
    }

    if (
      type === "reel" &&
      !videoUrl
    ) {
      notify(
        "Ajoute ou génère une vidéo MP4/MOV pour le Reel.",
      )

      return
    }

    if (
      type === "carousel" &&
      mediaUrls.length < 2
    ) {
      notify(
        "Le carrousel nécessite au moins 2 visuels.",
      )

      return
    }

    if (
      (
        type === "post" ||
        type === "story"
      ) &&
      mediaUrls.length < 1
    ) {
      notify(
        "Ajoute ou génère un visuel.",
      )

      return
    }

    try {
      setPublishing(true)

      setError("")

      const content = {
        id:
          selected?.id ||
          "",
        type,
        topic,
        objective,
        caption,
        slides,
        script,
        mediaUrls,
        videoUrl,
      }

      const data =
        await apiRequest(
          "publish",
          {
            id:
              selected?.id ||
              "",
            content,
          },
        )

      const now =
        new Date().toISOString()

      if (
        selected?.id
      ) {
        setSelected(
          (current) =>
            current
              ? {
                  ...current,
                  status:
                    "published",
                  updatedAt:
                    now,
                  instagram:
                    data.result,
                }
              : current,
        )

        setContents(
          (current) =>
            current.map(
              (item) =>
                item.id ===
                selected.id
                  ? {
                      ...item,
                      status:
                        "published",
                      updatedAt:
                        now,
                      instagram:
                        data.result,
                    }
                  : item,
            ),
        )
      }

      notify(
        data.message ||
          "Publication envoyée sur Instagram.",
      )

      await loadContents()
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

  async function deleteContent(
    content: SocialContent,
  ) {
    if (
      !window.confirm(
        "Supprimer définitivement ce contenu ?",
      )
    ) {
      return
    }

    try {
      await apiRequest(
        "delete",
        {
          id: content.id,
        },
      )

      setContents(
        (current) =>
          current.filter(
            (item) =>
              item.id !==
              content.id,
          ),
      )

      if (
        selected?.id ===
        content.id
      ) {
        resetEditor()
      }

      notify(
        "Contenu supprimé.",
      )
    } catch (err) {
      notify(
        err instanceof Error
          ? err.message
          : "Impossible de supprimer le contenu.",
      )
    }
  }

  function addSlide() {
    if (
      slides.length >= 10
    ) {
      notify(
        "Instagram limite les carrousels à 10 éléments.",
      )

      return
    }

    setSlides(
      (current) => [
        ...current,
        "",
      ],
    )
  }

  function removeSlide(
    index: number,
  ) {
    setSlides(
      (current) =>
        current.filter(
          (_, i) =>
            i !== index,
        ),
    )

    setMediaUrls(
      (current) =>
        current.filter(
          (_, i) =>
            i !== index,
        ),
    )
  }

  function removeMedia(
    index: number,
  ) {
    setMediaUrls(
      (current) =>
        current.filter(
          (_, i) =>
            i !== index,
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

      notify(
        "Légende copiée.",
      )
    } catch {
      notify(
        "Impossible de copier la légende.",
      )
    }
  }

  const reelGenerationBusy =
    generatingReel ||
    loading

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

          <p className="mt-1 max-w-3xl text-sm leading-6 text-white/50">
            Crée, génère, programme et publie les contenus Instagram de Vitrine+ depuis une seule interface.
          </p>
        </div>

        <div className="flex gap-2">
          <button
            type="button"
            onClick={() =>
              void loadContents()
            }
            className="inline-flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-bold text-white hover:bg-white/10"
          >
            <RefreshCw
              size={16}
              className={
                loadingLibrary
                  ? "animate-spin"
                  : ""
              }
            />

            Actualiser
          </button>

          <button
            type="button"
            onClick={
              resetEditor
            }
            className="inline-flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-bold text-white hover:bg-white/10"
          >
            <Plus size={17} />

            Nouveau contenu
          </button>
        </div>
      </div>

      {error && (
        <div className="flex items-start justify-between gap-4 rounded-2xl border border-red-400/20 bg-red-500/10 px-4 py-3 text-sm text-red-200">
          <div>
            {error}
          </div>

          <button
            type="button"
            onClick={() =>
              setError("")
            }
          >
            <X size={16} />
          </button>
        </div>
      )}

      <div className="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(340px,0.65fr)]">
        <section className="rounded-3xl border border-white/10 bg-[#101010] p-5 shadow-2xl shadow-black/20 lg:p-6">
          <div className="mb-6 flex items-center justify-between">
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
                    key={
                      item.value
                    }
                    type="button"
                    onClick={() => {
                      setType(
                        item.value,
                      )

                      if (
                        item.value !==
                        "reel"
                      ) {
                        clearGeneratedVideo()
                      }
                    }}
                    className={`rounded-2xl border p-4 text-left transition ${
                      active
                        ? "border-[#C8A45D]/50 bg-[#C8A45D]/10"
                        : "border-white/10 bg-black/20 hover:bg-white/5"
                    }`}
                  >
                    <Icon
                      size={18}
                      className={
                        active
                          ? "text-[#C8A45D]"
                          : "text-white/45"
                      }
                    />

                    <div className="mt-3 text-sm font-black text-white">
                      {
                        item.label
                      }
                    </div>

                    <div className="mt-1 text-[11px] leading-4 text-white/35">
                      {
                        item.description
                      }
                    </div>
                  </button>
                )
              },
            )}
          </div>

          <div className="mt-6">
            <label className="mb-2 block text-xs font-black uppercase tracking-[0.16em] text-white/45">
              Sujet
            </label>

            <textarea
              value={topic}
              onChange={(event) =>
                setTopic(
                  event.target
                    .value,
                )
              }
              placeholder="Exemple : pourquoi un site professionnel change la perception d'une entreprise..."
              rows={4}
              className="w-full resize-none rounded-2xl border border-white/10 bg-black/30 px-4 py-3 text-sm leading-6 text-white outline-none placeholder:text-white/20 focus:border-[#C8A45D]/50"
            />
          </div>

          <div className="mt-5">
            <label className="mb-2 block text-xs font-black uppercase tracking-[0.16em] text-white/45">
              Objectif
            </label>

            <div className="relative">
              <select
                value={
                  objective
                }
                onChange={(
                  event,
                ) =>
                  setObjective(
                    event.target
                      .value,
                  )
                }
                className="w-full appearance-none rounded-2xl border border-white/10 bg-black/30 px-4 py-3 pr-10 text-sm text-white outline-none focus:border-[#C8A45D]/50"
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
                className="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-white/35"
              />
            </div>
          </div>

          <button
            type="button"
            onClick={() =>
              void generateContent()
            }
            disabled={
              loading ||
              generatingReel
            }
            className="mt-5 flex w-full items-center justify-center gap-2 rounded-2xl bg-[#C8A45D] px-5 py-3.5 text-sm font-black text-black hover:brightness-110 disabled:opacity-50"
          >
            {reelGenerationBusy ? (
              <>
                <Loader2
                  size={18}
                  className="animate-spin"
                />

                {generatingReel
                  ? "Création du Reel..."
                  : "Génération complète..."}
              </>
            ) : (
              <>
                <WandSparkles
                  size={18}
                />

                Générer contenu + visuels
              </>
            )}
          </button>

          <div className="mt-7">
            <div className="mb-2 flex items-center justify-between">
              <label className="text-xs font-black uppercase tracking-[0.16em] text-white/45">
                Légende Instagram
              </label>

              <button
                type="button"
                onClick={() =>
                  void copyCaption()
                }
                disabled={!caption}
                className="inline-flex items-center gap-1.5 text-xs font-bold text-white/40 hover:text-white disabled:opacity-30"
              >
                <Copy
                  size={13}
                />

                Copier
              </button>
            </div>

            <textarea
              value={caption}
              onChange={(
                event,
              ) =>
                setCaption(
                  event.target
                    .value,
                )
              }
              rows={11}
              placeholder="La légende générée apparaîtra ici..."
              className="w-full resize-y rounded-2xl border border-white/10 bg-black/30 px-4 py-3 text-sm leading-6 text-white outline-none placeholder:text-white/20 focus:border-[#C8A45D]/50"
            />

            <div className="mt-2 flex justify-between text-[11px] text-white/25">
              <span>
                {caption.length}{" "}
                caractères
              </span>

              <span>
                Instagram · Vitrine+
              </span>
            </div>
          </div>

          {type ===
            "carousel" && (
            <div className="mt-7">
              <div className="mb-3 flex items-center justify-between">
                <label className="text-xs font-black uppercase tracking-[0.16em] text-white/45">
                  Slides
                </label>

                <button
                  type="button"
                  onClick={
                    addSlide
                  }
                  className="inline-flex items-center gap-1.5 rounded-lg border border-white/10 bg-white/5 px-2.5 py-1.5 text-xs font-bold text-white/60 hover:bg-white/10"
                >
                  <Plus
                    size={13}
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
                      key={
                        index
                      }
                      className="rounded-2xl border border-white/10 bg-black/20 p-3"
                    >
                      <div className="mb-2 flex items-center justify-between">
                        <span className="text-xs font-black text-[#C8A45D]">
                          Slide{" "}
                          {index +
                            1}
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
                          <Trash2
                            size={
                              14
                            }
                          />
                        </button>
                      </div>

                      <textarea
                        value={
                          slide
                        }
                        onChange={(
                          event,
                        ) =>
                          setSlides(
                            (
                              current,
                            ) =>
                              current.map(
                                (
                                  value,
                                  i,
                                ) =>
                                  i ===
                                  index
                                    ? event
                                        .target
                                        .value
                                    : value,
                              ),
                          )
                        }
                        rows={3}
                        className="w-full resize-none rounded-xl border border-white/10 bg-black/30 px-3 py-2 text-sm leading-5 text-white outline-none focus:border-[#C8A45D]/40"
                      />
                    </div>
                  ),
                )}

                {slides.length ===
                  0 && (
                  <div className="rounded-2xl border border-dashed border-white/10 px-4 py-8 text-center text-sm text-white/30">
                    Les slides générées apparaîtront ici.
                  </div>
                )}
              </div>
            </div>
          )}

          {type ===
            "reel" && (
            <div className="mt-7 space-y-4">
              <div className="rounded-2xl border border-white/10 bg-white/[0.025] p-4">
                <label className="mb-2 block text-xs font-black uppercase tracking-[0.16em] text-white/45">
                  Script du Reel
                </label>

                <textarea
                  value={script}
                  onChange={(
                    event,
                  ) =>
                    setScript(
                      event.target
                        .value,
                    )
                  }
                  rows={10}
                  placeholder="Script généré par l’IA..."
                  className="w-full resize-y rounded-xl border border-white/10 bg-black/30 px-3 py-3 text-sm leading-6 text-white outline-none placeholder:text-white/20 focus:border-[#C8A45D]/50"
                />

                <p className="mt-2 text-[11px] leading-5 text-white/30">
                  Le Social Studio peut créer automatiquement une vidéo verticale à partir du visuel et du script. Pour une publication Instagram fiable, la vidéo finale doit être en MP4/MOV accepté par Instagram.
                </p>
              </div>

              {generatingReel && (
                <div className="flex items-center gap-3 rounded-2xl border border-[#C8A45D]/20 bg-[#C8A45D]/5 px-4 py-4 text-sm text-white/70">
                  <Loader2
                    size={18}
                    className="animate-spin text-[#C8A45D]"
                  />

                  Création de la vidéo du Reel…
                </div>
              )}

              {videoUrl && (
                <div className="rounded-2xl border border-emerald-400/20 bg-emerald-500/5 px-4 py-3">
                  <div className="flex items-center gap-2 text-sm font-bold text-emerald-300">
                    <Check
                      size={17}
                    />

                    Vidéo du Reel prête
                  </div>
                </div>
              )}
            </div>
          )}

          <div className="mt-7 rounded-2xl border border-white/10 bg-white/[0.025] p-4">
            <div className="flex items-start gap-3">
              <div className="rounded-xl bg-[#C8A45D]/10 p-2 text-[#C8A45D]">
                <CalendarClock
                  size={18}
                />
              </div>

              <div>
                <div className="text-sm font-black text-white">
                  Programmer
                </div>

                <div className="mt-1 text-xs leading-5 text-white/35">
                  Le serveur publiera automatiquement à cette date/heure si le cron Vitrine+ est actif.
                </div>
              </div>
            </div>

            <input
              type="datetime-local"
              value={
                scheduledAt
              }
              onChange={(
                event,
              ) =>
                setScheduledAt(
                  event.target
                    .value,
                )
              }
              className="mt-4 w-full rounded-xl border border-white/10 bg-black/30 px-3 py-3 text-sm text-white outline-none focus:border-[#C8A45D]/50"
            />

            {scheduledAt && (
              <button
                type="button"
                onClick={() =>
                  setScheduledAt(
                    "",
                  )
                }
                className="mt-2 text-xs font-bold text-white/35 hover:text-white"
              >
                Annuler la programmation
              </button>
            )}
          </div>

          <div className="mt-6 flex flex-wrap gap-3">
            <label className="inline-flex cursor-pointer items-center gap-2 rounded-2xl border border-white/10 bg-white/5 px-4 py-3.5 text-sm font-black text-white hover:bg-white/10">
              <Upload
                size={17}
              />

              {uploading
                ? "Envoi..."
                : type ===
                    "reel"
                  ? "Importer une vidéo"
                  : "Importer un visuel"}

              <input
                type="file"
                accept={
                  type ===
                  "reel"
                    ? "video/mp4,video/quicktime,image/jpeg,image/jpg"
                    : "image/jpeg,image/jpg"
                }
                className="hidden"
                disabled={
                  uploading ||
                  generatingReel
                }
                onChange={(
                  event,
                ) => {
                  const file =
                    event
                      .target
                      .files?.[0]

                  if (file) {
                    void uploadMedia(
                      file,
                    )
                  }

                  event.currentTarget.value =
                    ""
                }}
              />
            </label>

            <button
              type="button"
              onClick={() =>
                void regenerateVisuals()
              }
              disabled={
                loading ||
                generatingReel
              }
              className="inline-flex items-center gap-2 rounded-2xl border border-white/10 bg-white/5 px-4 py-3.5 text-sm font-black text-white hover:bg-white/10 disabled:opacity-40"
            >
              <Sparkles
                size={17}
              />

              Régénérer visuel(s)
            </button>

            {type ===
              "reel" &&
              mediaUrls[0] &&
              !videoUrl &&
              !generatingReel && (
                <button
                  type="button"
                  onClick={() =>
                    void generateReelFromVisual(
                      mediaUrls[0],
                      script,
                    )
                  }
                  className="inline-flex items-center gap-2 rounded-2xl border border-[#C8A45D]/30 bg-[#C8A45D]/10 px-4 py-3.5 text-sm font-black text-[#C8A45D] hover:bg-[#C8A45D]/20"
                >
                  <Film
                    size={17}
                  />

                  Créer le Reel
                </button>
              )}
          </div>

          <div className="mt-6 grid gap-3 sm:grid-cols-2">
            <button
              type="button"
              onClick={() =>
                void saveContent()
              }
              disabled={
                saving ||
                !caption.trim() ||
                generatingReel
              }
              className="inline-flex items-center justify-center gap-2 rounded-2xl border border-white/10 bg-white/5 px-4 py-3.5 text-sm font-black text-white hover:bg-white/10 disabled:opacity-40"
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

              {scheduledAt
                ? "Programmer"
                : "Enregistrer"}
            </button>

            <button
              type="button"
              onClick={() =>
                void publishInstagram()
              }
              disabled={
                publishing ||
                !caption.trim() ||
                generatingReel
              }
              className="inline-flex items-center justify-center gap-2 rounded-2xl bg-[#C8A45D] px-4 py-3.5 text-sm font-black text-black hover:brightness-110 disabled:opacity-40"
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
          <section className="rounded-3xl border border-white/10 bg-[#101010] p-5">
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

            <div
              className={`overflow-hidden rounded-2xl border border-white/10 bg-black ${
                type === "story" ||
                type === "reel"
                  ? "mx-auto max-w-[280px]"
                  : ""
              }`}
            >
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

              <div
                className={`${
                  type ===
                    "story" ||
                  type ===
                    "reel"
                    ? "aspect-[9/16]"
                    : "aspect-square"
                } flex items-center justify-center overflow-hidden bg-[#080808]`}
              >
                {type ===
                  "reel" &&
                videoUrl ? (
                  <video
                    src={
                      videoUrl
                    }
                    controls
                    playsInline
                    className="h-full w-full object-cover"
                  />
                ) : mediaUrls[0] ? (
                  <img
                    src={
                      mediaUrls[0]
                    }
                    alt="Aperçu Vitrine+"
                    className="h-full w-full object-cover"
                  />
                ) : (
                  <div className="px-8 text-center text-white/20">
                    {type ===
                    "reel" ? (
                      <Play
                        size={40}
                        className="mx-auto"
                      />
                    ) : (
                      <ImageIcon
                        size={40}
                        className="mx-auto"
                      />
                    )}

                    <p className="mt-3 text-sm">
                      {
                        selectedType?.label ||
                        "Publication"
                      }
                    </p>
                  </div>
                )}
              </div>

              <div className="p-4">
                <div className="mb-3 flex items-center gap-4 text-white">
                  <span>
                    ♡
                  </span>

                  <span>
                    ◯
                  </span>

                  <span>
                    ➤
                  </span>

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
          </section>

          {type ===
            "carousel" &&
            mediaUrls.length >
              0 && (
              <section className="rounded-3xl border border-white/10 bg-[#101010] p-5">
                <div className="mb-4 flex items-center justify-between">
                  <h3 className="text-sm font-black text-white">
                    Visuels du carrousel
                  </h3>

                  <span className="text-xs text-white/35">
                    {
                      mediaUrls.length
                    }
                    /10
                  </span>
                </div>

                <div className="grid grid-cols-2 gap-3">
                  {mediaUrls.map(
                    (
                      url,
                      index,
                    ) => (
                      <div
                        key={
                          url +
                          index
                        }
                        className="relative overflow-hidden rounded-xl border border-white/10"
                      >
                        <img
                          src={url}
                          alt={`Slide ${
                            index +
                            1
                          }`}
                          className="aspect-square w-full object-cover"
                        />

                        <button
                          type="button"
                          onClick={() =>
                            removeMedia(
                              index,
                            )
                          }
                          className="absolute right-2 top-2 rounded-lg bg-black/70 p-1.5 text-white/70 hover:text-red-300"
                        >
                          <Trash2
                            size={
                              13
                            }
                          />
                        </button>
                      </div>
                    ),
                  )}
                </div>
              </section>
            )}

          {type ===
            "reel" &&
            videoUrl && (
              <section className="rounded-3xl border border-white/10 bg-[#101010] p-5">
                <div className="mb-4 flex items-center justify-between">
                  <h3 className="text-sm font-black text-white">
                    Vidéo du Reel
                  </h3>

                  <Film
                    size={18}
                    className="text-[#C8A45D]"
                  />
                </div>

                <video
                  src={videoUrl}
                  controls
                  playsInline
                  className="mx-auto max-h-[520px] rounded-2xl"
                />

                <div className="mt-3 flex items-center gap-2 text-[11px] text-white/30">
                  <Check
                    size={13}
                    className="text-emerald-400"
                  />

                  Vidéo hébergée sur Vitrine+
                </div>
              </section>
            )}

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

              <span className="text-xs text-white/30">
                {
                  contents.length
                }
              </span>
            </div>

            {contents.length ===
            0 ? (
              <div className="py-8 text-center text-sm text-white/25">
                Aucun contenu enregistré.
              </div>
            ) : (
              <div className="max-h-[520px] space-y-2 overflow-auto pr-1">
                {contents.map(
                  (
                    content,
                  ) => (
                    <div
                      key={
                        content.id
                      }
                      className="group rounded-2xl border border-white/10 bg-black/20 p-3"
                    >
                      <div className="flex items-start gap-3">
                        <button
                          type="button"
                          onClick={() =>
                            loadIntoEditor(
                              content,
                            )
                          }
                          className="min-w-0 flex-1 text-left"
                        >
                          <div className="flex items-center gap-2">
                            <span
                              className={`rounded-full border px-2 py-1 text-[10px] font-black ${statusClasses(
                                content.status,
                              )}`}
                            >
                              {statusLabel(
                                content.status,
                              )}
                            </span>

                            <span className="text-[10px] uppercase text-white/25">
                              {
                                content.type
                              }
                            </span>
                          </div>

                          <div className="mt-2 truncate text-sm font-bold text-white">
                            {content.title ||
                              content.topic ||
                              "Sans titre"}
                          </div>

                          <div className="mt-1 text-[11px] text-white/30">
                            {content.scheduledAt
                              ? formatDate(
                                  content.scheduledAt,
                                )
                              : formatDate(
                                  content.updatedAt ||
                                    content.createdAt,
                                )}
                          </div>
                        </button>

                        <button
                          type="button"
                          onClick={() =>
                            void deleteContent(
                              content,
                            )
                          }
                          className="rounded-lg p-2 text-white/20 hover:bg-red-500/10 hover:text-red-300"
                        >
                          <Trash2
                            size={
                              14
                            }
                          />
                        </button>
                      </div>

                      {content.status ===
                        "scheduled_error" &&
                        content.publishError && (
                          <div className="mt-2 rounded-xl bg-red-500/5 p-2 text-[11px] leading-4 text-red-300">
                            {
                              content.publishError
                            }
                          </div>
                        )}
                    </div>
                  ),
                )}
              </div>
            )}
          </section>
        </aside>
      </div>

      <div className="flex items-center gap-2 text-[11px] text-white/25">
        <Check size={13} />

        Social Studio Vitrine+ · Instagram Content Publishing · génération IA Gemini sans appel payant.
      </div>
    </div>
  )
}
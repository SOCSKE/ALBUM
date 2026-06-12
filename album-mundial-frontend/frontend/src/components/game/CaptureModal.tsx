'use client'

import { useEffect, useRef, useState } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import { Camera, X, RotateCcw, Users, CheckCircle, AlertCircle, Loader2 } from 'lucide-react'
import { useAlbumStore } from '@/store/albumStore'
import { apiClient } from '@/lib/api'

interface Props {
  open: boolean
  onClose: () => void
  eventSlug: string
}

type Step = 'select_player' | 'camera' | 'preview' | 'uploading' | 'result'

export default function CaptureModal({ open, onClose, eventSlug }: Props) {
  const [step, setStep]               = useState<Step>('select_player')
  const [selectedPlayerId, setSelected] = useState<string | null>(null)
  const [capturedPhoto, setCaptured]  = useState<Blob | null>(null)
  const [previewUrl, setPreviewUrl]   = useState<string | null>(null)
  const [result, setResult]           = useState<'success' | 'review' | 'error' | null>(null)
  const [errorMsg, setErrorMsg]       = useState('')
  const [players, setPlayers]         = useState<any[]>([])
  const [search, setSearch]           = useState('')

  const videoRef  = useRef<HTMLVideoElement>(null)
  const canvasRef = useRef<HTMLCanvasElement>(null)
  const streamRef = useRef<MediaStream | null>(null)

  const { refreshAlbum } = useAlbumStore()

  // Cargar jugadores disponibles
  useEffect(() => {
    if (open) {
      apiClient.get(`/game/${eventSlug}/players?missing=true`)
        .then(res => setPlayers(res.data.data))
        .catch(() => {})
    }
  }, [open, eventSlug])

  // Iniciar cámara
  useEffect(() => {
    if (step === 'camera') startCamera()
    else stopCamera()
  }, [step])

  // Cleanup al cerrar
  useEffect(() => {
    if (! open) {
      stopCamera()
      reset()
    }
  }, [open])

  async function startCamera() {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'environment', width: 1280, height: 720 }
      })
      streamRef.current = stream
      if (videoRef.current) {
        videoRef.current.srcObject = stream
        await videoRef.current.play()
      }
    } catch {
      setErrorMsg('No se pudo acceder a la cámara.')
      setStep('result')
      setResult('error')
    }
  }

  function stopCamera() {
    streamRef.current?.getTracks().forEach(t => t.stop())
    streamRef.current = null
  }

  function capturePhoto() {
    const video  = videoRef.current
    const canvas = canvasRef.current
    if (! video || ! canvas) return

    canvas.width  = video.videoWidth
    canvas.height = video.videoHeight
    canvas.getContext('2d')!.drawImage(video, 0, 0)

    canvas.toBlob(blob => {
      if (blob) {
        setCaptured(blob)
        setPreviewUrl(URL.createObjectURL(blob))
        setStep('preview')
      }
    }, 'image/jpeg', 0.85)
  }

  async function submitCapture() {
    if (! capturedPhoto || ! selectedPlayerId) return
    setStep('uploading')

    const formData = new FormData()
    formData.append('subject_id', selectedPlayerId)
    formData.append('photo', capturedPhoto, 'capture.jpg')
    formData.append('platform', 'web')

    try {
      const res = await apiClient.post(`/game/${eventSlug}/cards/capture`, formData)
      const status = res.data.status as 'approved' | 'pending_review'
      setResult(status === 'approved' ? 'success' : 'review')
      refreshAlbum(eventSlug)
    } catch (err: any) {
      setErrorMsg(err.response?.data?.message ?? 'Ocurrió un error al procesar la foto.')
      setResult('error')
    }

    setStep('result')
  }

  function reset() {
    setStep('select_player')
    setSelected(null)
    setCaptured(null)
    setPreviewUrl(null)
    setResult(null)
    setErrorMsg('')
    setSearch('')
  }

  const filteredPlayers = players.filter(p =>
    `${p.first_name} ${p.last_name}`.toLowerCase().includes(search.toLowerCase())
  )

  if (! open) return null

  return (
    <AnimatePresence>
      <motion.div
        className="fixed inset-0 z-50 bg-black/90 backdrop-blur-sm flex flex-col"
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        exit={{ opacity: 0 }}
      >
        {/* Header */}
        <div className="flex items-center justify-between p-4 border-b border-white/10">
          <h2 className="font-bold text-lg">
            {step === 'select_player' && 'Seleccionar jugador'}
            {step === 'camera'        && 'Tomar foto'}
            {step === 'preview'       && 'Confirmar foto'}
            {step === 'uploading'     && 'Procesando...'}
            {step === 'result'        && 'Resultado'}
          </h2>
          <button onClick={onClose} className="text-white/40 hover:text-white transition">
            <X size={22} />
          </button>
        </div>

        {/* Content */}
        <div className="flex-1 overflow-y-auto p-4">

          {/* STEP 1: SELECT PLAYER */}
          {step === 'select_player' && (
            <div className="space-y-4">
              <p className="text-white/50 text-sm">
                Elige el jugador del que tomarás la foto:
              </p>
              <input
                type="text"
                placeholder="Buscar jugador..."
                value={search}
                onChange={e => setSearch(e.target.value)}
                className="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-3 text-sm placeholder:text-white/30 focus:outline-none focus:border-[var(--event-color)]"
              />
              <div className="space-y-2">
                {filteredPlayers.map(p => (
                  <button
                    key={p.id}
                    onClick={() => {
                      setSelected(p.id)
                      setStep('camera')
                    }}
                    className={`
                      w-full flex items-center gap-3 p-3 rounded-xl border transition
                      ${selectedPlayerId === p.id
                        ? 'border-[var(--event-color)] bg-[var(--event-color)]/20'
                        : 'border-white/10 bg-white/5 hover:bg-white/10'}
                    `}
                  >
                    {p.card_url_webp ? (
                      <img src={p.card_url_webp} alt="" className="h-10 w-10 rounded-lg object-cover" />
                    ) : (
                      <div className="h-10 w-10 rounded-lg bg-white/10 flex items-center justify-center">
                        <Users size={18} className="text-white/30" />
                      </div>
                    )}
                    <div className="text-left">
                      <p className="font-medium text-sm">{p.first_name} {p.last_name}</p>
                      <p className="text-white/40 text-xs">#{String(p.player_number).padStart(3,'0')} · {p.team?.country?.name}</p>
                    </div>
                  </button>
                ))}
                {filteredPlayers.length === 0 && (
                  <div className="text-center text-white/30 py-8 text-sm">
                    No hay jugadores disponibles para capturar
                  </div>
                )}
              </div>
            </div>
          )}

          {/* STEP 2: CAMERA */}
          {step === 'camera' && (
            <div className="space-y-4">
              <div className="relative rounded-2xl overflow-hidden bg-black aspect-video">
                <video ref={videoRef} autoPlay muted playsInline className="w-full h-full object-cover" />
                {/* Overlay guía */}
                <div className="absolute inset-0 border-2 border-white/20 rounded-2xl pointer-events-none" />
                <div className="absolute bottom-4 left-4 right-4 text-center">
                  <p className="text-white/60 text-xs bg-black/40 rounded-full px-3 py-1 inline-block">
                    Asegúrate de que la cara del jugador sea visible
                  </p>
                </div>
              </div>
              <canvas ref={canvasRef} className="hidden" />
              <button
                onClick={capturePhoto}
                className="w-full flex items-center justify-center gap-2 bg-[var(--event-color)] text-white font-bold py-4 rounded-2xl text-lg shadow-lg shadow-[var(--event-color)]/30 active:scale-95 transition"
              >
                <Camera size={24} />
                Capturar foto
              </button>
              <button onClick={() => setStep('select_player')} className="w-full text-white/40 text-sm text-center py-2">
                ← Cambiar jugador
              </button>
            </div>
          )}

          {/* STEP 3: PREVIEW */}
          {step === 'preview' && previewUrl && (
            <div className="space-y-4">
              <div className="rounded-2xl overflow-hidden">
                <img src={previewUrl} alt="preview" className="w-full object-cover" />
              </div>
              <div className="flex gap-3">
                <button
                  onClick={() => setStep('camera')}
                  className="flex-1 flex items-center justify-center gap-2 border border-white/20 py-3 rounded-xl text-sm"
                >
                  <RotateCcw size={16} />
                  Repetir
                </button>
                <button
                  onClick={submitCapture}
                  className="flex-1 bg-[var(--event-color)] py-3 rounded-xl text-sm font-bold"
                >
                  Confirmar y enviar
                </button>
              </div>
            </div>
          )}

          {/* STEP 4: UPLOADING */}
          {step === 'uploading' && (
            <div className="flex flex-col items-center justify-center py-20 gap-4">
              <Loader2 size={40} className="animate-spin text-[var(--event-color)]" />
              <p className="text-white/60">Procesando reconocimiento facial...</p>
            </div>
          )}

          {/* STEP 5: RESULT */}
          {step === 'result' && (
            <div className="flex flex-col items-center justify-center py-12 gap-6 text-center">
              {result === 'success' && (
                <>
                  <CheckCircle size={60} className="text-green-400" />
                  <div>
                    <h3 className="text-xl font-bold">¡Cromo desbloqueado!</h3>
                    <p className="text-white/50 text-sm mt-1">El cromo se está generando y aparecerá en tu álbum en segundos.</p>
                  </div>
                  <button onClick={onClose} className="btn-primary">Ver mi álbum</button>
                </>
              )}
              {result === 'review' && (
                <>
                  <AlertCircle size={60} className="text-yellow-400" />
                  <div>
                    <h3 className="text-xl font-bold">Foto en revisión</h3>
                    <p className="text-white/50 text-sm mt-1">La coincidencia facial fue insuficiente. Un moderador revisará tu foto manualmente.</p>
                  </div>
                  <button onClick={onClose} className="btn-secondary">Entendido</button>
                </>
              )}
              {result === 'error' && (
                <>
                  <AlertCircle size={60} className="text-red-400" />
                  <div>
                    <h3 className="text-xl font-bold">Foto rechazada</h3>
                    <p className="text-white/50 text-sm mt-1">{errorMsg}</p>
                  </div>
                  <button onClick={reset} className="btn-secondary">Intentar de nuevo</button>
                </>
              )}
            </div>
          )}
        </div>
      </motion.div>
    </AnimatePresence>
  )
}

'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { motion } from 'framer-motion'
import { Users, DollarSign, Calendar, Image, Palette, ArrowRight, CheckCircle } from 'lucide-react'
import { apiClient } from '@/lib/api'

const schema = z.object({
  name:           z.string().min(3, 'Nombre muy corto').max(100),
  description:    z.string().optional(),
  starts_at:      z.string().min(1, 'Selecciona fecha de inicio'),
  max_players:    z.coerce.number().min(25).max(500),
  duration_hours: z.coerce.number().min(1).max(168).default(24),
  primary_color:  z.string().regex(/^#[0-9A-Fa-f]{6}$/).default('#1a56db'),
})

type FormData = z.infer<typeof schema>

const PLAYER_BLOCKS = [25, 50, 75, 100, 125, 150, 200, 250, 300, 400, 500]

function calculatePrice(maxPlayers: number) {
  const blocks = Math.ceil(maxPlayers / 25)
  return { blocks, total: blocks * 5 }
}

export default function CreateEventPage() {
  const router = useRouter()
  const [logo, setLogo]       = useState<File | null>(null)
  const [logoUrl, setLogoUrl] = useState<string | null>(null)
  const [step, setStep]       = useState<1 | 2 | 3>(1)
  const [isSubmitting, setSubmitting] = useState(false)
  const [createdEvent, setCreatedEvent] = useState<any>(null)

  const { register, handleSubmit, watch, formState: { errors } } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: { max_players: 25, duration_hours: 24, primary_color: '#1a56db' },
  })

  const watchedPlayers = watch('max_players')
  const pricing = calculatePrice(watchedPlayers || 25)

  function handleLogoChange(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0]
    if (file) {
      setLogo(file)
      setLogoUrl(URL.createObjectURL(file))
    }
  }

  async function onSubmit(data: FormData) {
    setSubmitting(true)
    try {
      const formData = new FormData()
      Object.entries(data).forEach(([k, v]) => formData.append(k, String(v)))
      if (logo) formData.append('logo', logo)

      const res = await apiClient.post('/organizer/events', formData)
      setCreatedEvent(res.data.event)
      setStep(3)
    } catch (err: any) {
      console.error(err)
    } finally {
      setSubmitting(false)
    }
  }

  async function proceedToPayment() {
    if (! createdEvent) return
    const res = await apiClient.post(`/organizer/events/${createdEvent.id}/checkout`)
    window.location.href = res.data.checkout_url
  }

  return (
    <div className="min-h-screen bg-gray-950 text-white">
      <div className="max-w-2xl mx-auto px-4 py-12">

        {/* Header */}
        <div className="mb-10">
          <h1 className="text-3xl font-black">Crear Evento</h1>
          <p className="text-white/50 mt-1">Configura tu álbum digital mundialista</p>
        </div>

        {/* Step indicator */}
        <div className="flex items-center gap-3 mb-8">
          {[1, 2, 3].map(s => (
            <div key={s} className="flex items-center gap-2">
              <div className={`
                w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold transition
                ${step >= s ? 'bg-blue-600' : 'bg-white/10 text-white/30'}
              `}>
                {step > s ? <CheckCircle size={16} /> : s}
              </div>
              {s < 3 && <div className={`h-px w-12 ${step > s ? 'bg-blue-600' : 'bg-white/10'}`} />}
            </div>
          ))}
          <span className="text-white/40 text-sm ml-2">
            {step === 1 && 'Configurar evento'}
            {step === 2 && 'Revisar y pagar'}
            {step === 3 && '¡Listo!'}
          </span>
        </div>

        {/* ── STEP 1: FORM ─── */}
        {step === 1 && (
          <motion.form
            initial={{ opacity: 0, y: 16 }}
            animate={{ opacity: 1, y: 0 }}
            onSubmit={handleSubmit(() => setStep(2))}
            className="space-y-6"
          >
            {/* Logo */}
            <div className="flex items-center gap-4">
              <label className="cursor-pointer">
                <div className="w-20 h-20 rounded-2xl bg-white/5 border-2 border-dashed border-white/20 hover:border-white/40 transition flex items-center justify-center overflow-hidden">
                  {logoUrl
                    ? <img src={logoUrl} alt="logo" className="w-full h-full object-cover" />
                    : <Image size={24} className="text-white/30" />
                  }
                </div>
                <input type="file" accept="image/*" onChange={handleLogoChange} className="hidden" />
              </label>
              <div>
                <p className="text-sm font-medium">Logo del evento</p>
                <p className="text-white/40 text-xs">PNG, JPG o SVG. Máx 2MB</p>
              </div>
            </div>

            {/* Nombre */}
            <div>
              <label className="block text-sm font-medium mb-2">Nombre del evento *</label>
              <input
                {...register('name')}
                placeholder="Ej: World Cup Familia García 2026"
                className="input-field"
              />
              {errors.name && <p className="text-red-400 text-xs mt-1">{errors.name.message}</p>}
            </div>

            {/* Descripción */}
            <div>
              <label className="block text-sm font-medium mb-2">Descripción</label>
              <textarea
                {...register('description')}
                rows={3}
                placeholder="Describe tu evento..."
                className="input-field resize-none"
              />
            </div>

            {/* Fecha de inicio */}
            <div>
              <label className="block text-sm font-medium mb-2 flex items-center gap-2">
                <Calendar size={16} /> Fecha y hora de inicio *
              </label>
              <input
                {...register('starts_at')}
                type="datetime-local"
                className="input-field"
              />
              {errors.starts_at && <p className="text-red-400 text-xs mt-1">{errors.starts_at.message}</p>}
            </div>

            {/* Duración */}
            <div>
              <label className="block text-sm font-medium mb-2">Duración del evento</label>
              <select {...register('duration_hours')} className="input-field">
                <option value={12}>12 horas</option>
                <option value={24}>24 horas (recomendado)</option>
                <option value={48}>48 horas</option>
                <option value={72}>3 días</option>
                <option value={168}>1 semana</option>
              </select>
            </div>

            {/* Jugadores */}
            <div>
              <label className="block text-sm font-medium mb-2 flex items-center gap-2">
                <Users size={16} /> Máximo de jugadores *
              </label>
              <div className="grid grid-cols-4 gap-2 sm:grid-cols-6">
                {PLAYER_BLOCKS.map(n => (
                  <label key={n} className="cursor-pointer">
                    <input
                      {...register('max_players')}
                      type="radio"
                      value={n}
                      className="sr-only peer"
                    />
                    <div className={`
                      text-center py-2 rounded-xl border text-sm font-medium transition
                      peer-checked:border-blue-500 peer-checked:bg-blue-500/20 peer-checked:text-white
                      border-white/10 text-white/50 hover:border-white/30
                    `}>
                      {n}
                    </div>
                  </label>
                ))}
              </div>
            </div>

            {/* Color */}
            <div>
              <label className="block text-sm font-medium mb-2 flex items-center gap-2">
                <Palette size={16} /> Color principal
              </label>
              <div className="flex items-center gap-3">
                <input
                  {...register('primary_color')}
                  type="color"
                  className="h-10 w-16 rounded-lg cursor-pointer bg-transparent"
                />
                <input
                  {...register('primary_color')}
                  type="text"
                  placeholder="#1a56db"
                  className="input-field flex-1"
                />
              </div>
            </div>

            {/* Precio calculado */}
            <div className="rounded-2xl bg-blue-500/10 border border-blue-500/30 p-5 flex items-center justify-between">
              <div>
                <p className="text-white/60 text-sm">Costo estimado</p>
                <p className="text-2xl font-black">${pricing.total} USD</p>
                <p className="text-white/40 text-xs mt-1">
                  {pricing.blocks} bloque{pricing.blocks > 1 ? 's' : ''} × $5 · hasta {watchedPlayers} jugadores
                </p>
              </div>
              <DollarSign size={40} className="text-blue-400 opacity-50" />
            </div>

            <button type="submit" className="btn-primary w-full flex items-center justify-center gap-2">
              Continuar
              <ArrowRight size={18} />
            </button>
          </motion.form>
        )}

        {/* ── STEP 2: REVIEW ─── */}
        {step === 2 && (
          <motion.div
            initial={{ opacity: 0, y: 16 }}
            animate={{ opacity: 1, y: 0 }}
            className="space-y-6"
          >
            <div className="rounded-2xl border border-white/10 p-6 space-y-4">
              <h3 className="font-bold text-lg">Resumen del evento</h3>
              <div className="space-y-3 text-sm">
                <Row label="Nombre" value={watch('name')} />
                <Row label="Jugadores" value={`${watch('max_players')} máx`} />
                <Row label="Duración" value={`${watch('duration_hours')}h`} />
              </div>
            </div>

            <div className="rounded-2xl bg-green-500/10 border border-green-500/30 p-5 space-y-3">
              <h3 className="font-bold">Desglose de precio</h3>
              <div className="space-y-1 text-sm">
                {Array.from({ length: pricing.blocks }).map((_, i) => (
                  <div key={i} className="flex justify-between text-white/60">
                    <span>Bloque {i+1}: jugadores {i*25+1}–{(i+1)*25}</span>
                    <span>$5.00</span>
                  </div>
                ))}
              </div>
              <div className="border-t border-white/10 pt-3 flex justify-between font-bold text-lg">
                <span>Total</span>
                <span>${pricing.total}.00 USD</span>
              </div>
            </div>

            <div className="flex gap-3">
              <button onClick={() => setStep(1)} className="flex-1 btn-secondary">
                Editar
              </button>
              <button
                onClick={handleSubmit(onSubmit)}
                disabled={isSubmitting}
                className="flex-1 btn-primary"
              >
                {isSubmitting ? 'Creando...' : 'Crear y pagar'}
              </button>
            </div>
          </motion.div>
        )}

        {/* ── STEP 3: SUCCESS ─── */}
        {step === 3 && createdEvent && (
          <motion.div
            initial={{ opacity: 0, scale: 0.95 }}
            animate={{ opacity: 1, scale: 1 }}
            className="text-center space-y-6 py-8"
          >
            <CheckCircle size={64} className="mx-auto text-green-400" />
            <div>
              <h2 className="text-2xl font-bold">¡Evento creado!</h2>
              <p className="text-white/50 mt-1">Ahora procede al pago para activarlo</p>
            </div>
            <div className="bg-white/5 rounded-2xl p-4">
              <p className="text-white/40 text-xs mb-1">Enlace del evento</p>
              <code className="text-blue-400 text-sm break-all">
                {process.env.NEXT_PUBLIC_APP_URL}/game/{createdEvent.slug}
              </code>
            </div>
            <button onClick={proceedToPayment} className="btn-primary w-full flex items-center justify-center gap-2">
              <DollarSign size={18} />
              Ir a pagar (${pricing.total} USD)
            </button>
          </motion.div>
        )}
      </div>
    </div>
  )
}

function Row({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex justify-between">
      <span className="text-white/50">{label}</span>
      <span className="font-medium">{value}</span>
    </div>
  )
}

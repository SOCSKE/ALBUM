'use client'

import { useEffect, useState } from 'react'
import { useParams } from 'next/navigation'
import { motion, AnimatePresence } from 'framer-motion'
import { Camera, Trophy, Users, BookOpen, ArrowRightLeft, Star } from 'lucide-react'
import AlbumGrid from '@/components/game/AlbumGrid'
import CaptureModal from '@/components/game/CaptureModal'
import RankingPanel from '@/components/game/RankingPanel'
import TeamPanel from '@/components/game/TeamPanel'
import PlayerProfile from '@/components/game/PlayerProfile'
import { usePlayerStore } from '@/store/playerStore'
import { useAlbumStore } from '@/store/albumStore'
import ProgressRing from '@/components/ui/ProgressRing'
import type { Tab } from '@/types/game'

const TABS: { id: Tab; label: string; icon: React.ReactNode }[] = [
  { id: 'album',    label: 'Mi Álbum',   icon: <BookOpen size={20} /> },
  { id: 'capture',  label: 'Capturar',   icon: <Camera size={20} /> },
  { id: 'teams',    label: 'Equipos',    icon: <Users size={20} /> },
  { id: 'rankings', label: 'Rankings',   icon: <Trophy size={20} /> },
  { id: 'trades',   label: 'Intercambio',icon: <ArrowRightLeft size={20} /> },
]

export default function GamePage() {
  const params         = useParams<{ eventSlug: string }>()
  const [activeTab, setActiveTab]     = useState<Tab>('album')
  const [captureOpen, setCaptureOpen] = useState(false)

  const { player, loadPlayer }  = usePlayerStore()
  const { progress, loadAlbum } = useAlbumStore()

  useEffect(() => {
    loadPlayer()
    loadAlbum(params.eventSlug)
  }, [params.eventSlug])

  if (! player) return <LoadingScreen />

  return (
    <div
      className="min-h-screen bg-game-bg text-white"
      style={{ '--event-color': player.event?.primary_color ?? '#1a56db' } as React.CSSProperties}
    >
      {/* ── HEADER ─────────────────────────────────────────── */}
      <header className="sticky top-0 z-40 bg-black/70 backdrop-blur-md border-b border-white/10">
        <div className="max-w-2xl mx-auto px-4 py-3 flex items-center justify-between">
          <div className="flex items-center gap-3">
            {player.event?.logo_url && (
              <img src={player.event.logo_url} alt="logo" className="h-8 w-8 rounded-full object-cover" />
            )}
            <div>
              <p className="text-xs text-white/50 uppercase tracking-wider">Álbum Digital</p>
              <h1 className="text-sm font-bold leading-none">{player.event?.name}</h1>
            </div>
          </div>

          <button
            onClick={() => setActiveTab('profile' as Tab)}
            className="flex items-center gap-2 bg-white/10 hover:bg-white/20 transition rounded-full px-3 py-1.5"
          >
            {player.card_url_webp ? (
              <img src={player.card_url_webp} alt="mi cromo" className="h-7 w-7 rounded-full object-cover border border-[var(--event-color)]" />
            ) : (
              <div className="h-7 w-7 rounded-full bg-[var(--event-color)] flex items-center justify-center text-xs font-bold">
                {player.first_name[0]}
              </div>
            )}
            <ProgressRing progress={progress} size={28} strokeWidth={3} />
          </button>
        </div>
      </header>

      {/* ── PROGRESS BAR ───────────────────────────────────── */}
      <div className="h-1 bg-white/10">
        <motion.div
          className="h-full bg-[var(--event-color)]"
          initial={{ width: 0 }}
          animate={{ width: `${progress}%` }}
          transition={{ duration: 1, ease: 'easeOut' }}
        />
      </div>

      {/* ── MAIN CONTENT ───────────────────────────────────── */}
      <main className="max-w-2xl mx-auto px-4 pb-24 pt-4">
        <AnimatePresence mode="wait">
          <motion.div
            key={activeTab}
            initial={{ opacity: 0, y: 12 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -12 }}
            transition={{ duration: 0.2 }}
          >
            {activeTab === 'album'    && <AlbumGrid eventSlug={params.eventSlug} />}
            {activeTab === 'capture'  && (
              <CapturePrompt
                onCapture={() => setCaptureOpen(true)}
                progress={progress}
              />
            )}
            {activeTab === 'teams'    && <TeamPanel eventSlug={params.eventSlug} />}
            {activeTab === 'rankings' && <RankingPanel eventSlug={params.eventSlug} />}
            {activeTab === 'trades'   && <TradesPanel eventSlug={params.eventSlug} />}
          </motion.div>
        </AnimatePresence>
      </main>

      {/* ── BOTTOM NAV ─────────────────────────────────────── */}
      <nav className="fixed bottom-0 left-0 right-0 z-40 bg-black/80 backdrop-blur-md border-t border-white/10 safe-bottom">
        <div className="max-w-2xl mx-auto flex">
          {TABS.map((tab) => (
            <button
              key={tab.id}
              onClick={() => tab.id === 'capture' ? setCaptureOpen(true) : setActiveTab(tab.id)}
              className={`
                flex-1 flex flex-col items-center gap-1 py-3 text-xs transition-colors
                ${activeTab === tab.id
                  ? 'text-[var(--event-color)]'
                  : 'text-white/40 hover:text-white/70'}
                ${tab.id === 'capture'
                  ? 'relative'
                  : ''}
              `}
            >
              {tab.id === 'capture' ? (
                <span className="absolute -top-5 bg-[var(--event-color)] rounded-full p-3 shadow-lg shadow-[var(--event-color)]/40">
                  <Camera size={22} />
                </span>
              ) : (
                tab.icon
              )}
              {tab.id !== 'capture' && (
                <span className="mt-0.5">{tab.label}</span>
              )}
            </button>
          ))}
        </div>
      </nav>

      {/* ── CAPTURE MODAL ──────────────────────────────────── */}
      <CaptureModal
        open={captureOpen}
        onClose={() => setCaptureOpen(false)}
        eventSlug={params.eventSlug}
      />
    </div>
  )
}

// ── Sub-componentes simples ──────────────────────────────────

function CapturePrompt({ onCapture, progress }: { onCapture: () => void; progress: number }) {
  return (
    <div className="text-center py-12 space-y-6">
      <div className="inline-flex items-center justify-center w-24 h-24 rounded-full bg-white/5 border border-white/10">
        <Camera size={40} className="text-white/50" />
      </div>
      <div>
        <h2 className="text-xl font-bold">Captura un cromo</h2>
        <p className="text-white/50 text-sm mt-1">
          Tómate una foto con otro jugador para desbloquear su cromo
        </p>
      </div>
      <div className="text-4xl font-black text-[var(--event-color)]">{progress}%</div>
      <p className="text-white/40 text-xs">de tu álbum completado</p>
      <button
        onClick={onCapture}
        className="btn-primary flex items-center gap-2 mx-auto"
      >
        <Camera size={18} />
        Tomar foto ahora
      </button>
    </div>
  )
}

function TradesPanel({ eventSlug }: { eventSlug: string }) {
  return (
    <div className="text-center py-12 text-white/40">
      <ArrowRightLeft size={40} className="mx-auto mb-3" />
      <p>Intercambio de cromos próximamente</p>
    </div>
  )
}

function LoadingScreen() {
  return (
    <div className="min-h-screen bg-[#0d0d1a] flex items-center justify-center">
      <div className="text-center space-y-4">
        <Star size={40} className="mx-auto text-yellow-400 animate-pulse" />
        <p className="text-white/50">Cargando tu álbum...</p>
      </div>
    </div>
  )
}

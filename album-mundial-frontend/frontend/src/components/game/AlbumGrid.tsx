'use client'

import { useEffect, useState } from 'react'
import { motion } from 'framer-motion'
import { Lock } from 'lucide-react'
import { useAlbumStore } from '@/store/albumStore'
import type { AlbumCard, Player } from '@/types/game'

interface Props {
  eventSlug: string
}

export default function AlbumGrid({ eventSlug }: Props) {
  const { cards, allPlayers, loadAlbum, isLoading } = useAlbumStore()

  useEffect(() => {
    loadAlbum(eventSlug)
  }, [eventSlug])

  const collectedIds = new Set(cards.map(c => c.subject_id))

  if (isLoading) return <AlbumGridSkeleton />

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-lg font-bold">Mi Álbum</h2>
          <p className="text-white/50 text-sm">
            {cards.length} / {allPlayers.length} cromos
          </p>
        </div>
        <div className="text-right">
          <span className="text-2xl font-black text-[var(--event-color)]">
            {Math.round((cards.length / Math.max(allPlayers.length, 1)) * 100)}%
          </span>
        </div>
      </div>

      {/* Progress bar */}
      <div className="h-2 bg-white/10 rounded-full overflow-hidden">
        <motion.div
          className="h-full bg-gradient-to-r from-[var(--event-color)] to-yellow-400 rounded-full"
          initial={{ width: 0 }}
          animate={{ width: `${(cards.length / Math.max(allPlayers.length, 1)) * 100}%` }}
          transition={{ duration: 0.8 }}
        />
      </div>

      {/* Cards Grid */}
      <div className="grid grid-cols-3 gap-2 sm:grid-cols-4">
        {allPlayers.map((player, i) => {
          const collected = collectedIds.has(player.id)
          const card = cards.find(c => c.subject_id === player.id)

          return (
            <motion.div
              key={player.id}
              initial={{ opacity: 0, scale: 0.8 }}
              animate={{ opacity: 1, scale: 1 }}
              transition={{ delay: i * 0.02 }}
            >
              <PlayerCard
                player={player}
                collected={collected}
                card={card}
              />
            </motion.div>
          )
        })}
      </div>
    </div>
  )
}

function PlayerCard({
  player,
  collected,
  card,
}: {
  player: Player
  collected: boolean
  card?: AlbumCard
}) {
  const [flipped, setFlipped] = useState(false)

  return (
    <div
      className="aspect-[3/4] cursor-pointer"
      onClick={() => collected && setFlipped(f => !f)}
    >
      {collected && card ? (
        <motion.div
          className="relative w-full h-full rounded-xl overflow-hidden shadow-lg"
          animate={{ rotateY: flipped ? 180 : 0 }}
          transition={{ duration: 0.4 }}
          style={{ transformStyle: 'preserve-3d' }}
        >
          {/* FRONT */}
          <div
            className="absolute inset-0 rounded-xl overflow-hidden"
            style={{ backfaceVisibility: 'hidden' }}
          >
            <img
              src={card.card_url_webp ?? card.photo_url}
              alt={player.first_name}
              className="w-full h-full object-cover"
            />
            {/* Number badge */}
            <span className="absolute top-1 right-1 bg-black/60 text-white text-[10px] font-bold px-1 rounded">
              #{String(player.player_number).padStart(3, '0')}
            </span>
          </div>

          {/* BACK (info) */}
          <div
            className="absolute inset-0 bg-gradient-to-br from-[var(--event-color)] to-black/80 rounded-xl p-2 flex flex-col justify-end"
            style={{ backfaceVisibility: 'hidden', transform: 'rotateY(180deg)' }}
          >
            <p className="text-white font-bold text-xs leading-tight">
              {player.first_name} {player.last_name}
            </p>
            <p className="text-white/60 text-[10px]">{player.team?.country?.name}</p>
          </div>
        </motion.div>
      ) : (
        /* LOCKED card */
        <div className="w-full h-full rounded-xl bg-white/5 border border-white/10 flex flex-col items-center justify-center gap-2">
          <Lock size={18} className="text-white/20" />
          <span className="text-white/20 text-[10px] font-bold">
            #{String(player.player_number).padStart(3, '0')}
          </span>
        </div>
      )}
    </div>
  )
}

function AlbumGridSkeleton() {
  return (
    <div className="grid grid-cols-3 gap-2 sm:grid-cols-4">
      {Array.from({ length: 12 }).map((_, i) => (
        <div
          key={i}
          className="aspect-[3/4] rounded-xl bg-white/5 animate-pulse"
        />
      ))}
    </div>
  )
}

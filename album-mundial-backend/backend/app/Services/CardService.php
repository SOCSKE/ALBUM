<?php

namespace App\Services;

use App\Models\AlbumCard;
use App\Models\Player;
use App\Models\Tenant;
use App\Jobs\GenerateCardJob;
use App\Jobs\SendNotificationJob;
use App\Services\FaceMatchService;
use App\Services\PhotoFraudService;
use App\Services\StorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CardService
{
    public function __construct(
        private FaceMatchService  $faceMatch,
        private PhotoFraudService $fraudCheck,
        private StorageService    $storage,
    ) {}

    /**
     * Capturar foto y generar cromo.
     * Retorna el cromo creado o lanza excepción.
     */
    public function capture(
        Player       $owner,
        string       $subjectId,
        UploadedFile $photo,
        string       $ipAddress,
        array        $deviceInfo = []
    ): AlbumCard {
        $tenant  = Tenant::findOrFail($owner->tenant_id);
        $subject = Player::where('id', $subjectId)
                         ->where('tenant_id', $owner->tenant_id)
                         ->firstOrFail();

        // 1. Validar que no exista ya ese cromo
        $existing = AlbumCard::where('owner_id', $owner->id)
                             ->where('subject_id', $subject->id)
                             ->where('tenant_id', $owner->tenant_id)
                             ->where('face_match_status', '!=', 'rejected')
                             ->exists();
        if ($existing) {
            throw new \DomainException('Ya tienes este cromo en tu álbum.');
        }

        // 2. Verificar anti-fraude (hash perceptual, EXIF, manipulación)
        $fraudResult = $this->fraudCheck->analyze($photo, $owner, $tenant);
        if ($fraudResult->isBlocked) {
            $this->logFraud($owner, $fraudResult, $ipAddress);
            throw new \DomainException("Foto rechazada: {$fraudResult->reason}");
        }

        // 3. Reconocimiento facial: comparar foto vs selfie del sujeto
        $faceScore = $this->faceMatch->compare($subject->selfie_url, $photo);

        $threshold = (float) config('app.face_match_threshold', 80.0);
        $status    = $faceScore >= $threshold ? 'approved' : 'pending_review';

        DB::beginTransaction();
        try {
            // 4. Subir foto al almacenamiento
            $photoPath = $this->storage->uploadPhoto(
                $photo,
                "events/{$tenant->slug}/photos/{$owner->id}"
            );

            // 5. Crear registro del cromo
            $card = AlbumCard::create([
                'id'                => Str::uuid(),
                'tenant_id'         => $tenant->id,
                'owner_id'          => $owner->id,
                'subject_id'        => $subject->id,
                'photo_url'         => $photoPath,
                'face_match_score'  => $faceScore,
                'face_match_status' => $status,
                'fraud_flags'       => $fraudResult->flags,
                'ip_address'        => $ipAddress,
                'device_info'       => $deviceInfo,
            ]);

            DB::commit();

            // 6. Disparar job asíncrono para generar imagen del cromo
            if ($status === 'approved') {
                GenerateCardJob::dispatch($card->id);
                SendNotificationJob::dispatch($owner->id, 'Player', 'card_unlocked', [
                    'subject_name'  => $subject->first_name . ' ' . $subject->last_name,
                    'album_progress'=> $owner->fresh()->album_progress,
                ]);
            }

            Log::info('Card captured', [
                'card_id'    => $card->id,
                'owner'      => $owner->id,
                'subject'    => $subject->id,
                'score'      => $faceScore,
                'status'     => $status,
                'tenant'     => $tenant->slug,
            ]);

            return $card;

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Card capture failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Log de intento de fraude detectado.
     */
    private function logFraud(Player $player, object $fraudResult, string $ip): void
    {
        DB::table('photo_fraud_log')->insert([
            'tenant_id'   => $player->tenant_id,
            'player_id'   => $player->id,
            'fraud_type'  => $fraudResult->type,
            'photo_hash'  => $fraudResult->photoHash,
            'face_score'  => $fraudResult->faceScore ?? null,
            'ip_address'  => $ip,
            'created_at'  => now(),
        ]);
    }
}

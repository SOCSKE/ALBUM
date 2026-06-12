<?php

namespace App\Jobs;

use App\Models\AlbumCard;
use App\Models\Player;
use App\Services\StorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class GenerateCardJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;

    public function __construct(
        private readonly string $cardId
    ) {}

    public function handle(StorageService $storage): void
    {
        $card    = AlbumCard::findOrFail($this->cardId);
        $owner   = Player::findOrFail($card->owner_id);
        $subject = Player::findOrFail($card->subject_id);

        // Cargar imágenes base
        $manager    = new ImageManager(new Driver());
        $photoImage = $manager->read(
            $storage->getStream($card->photo_url)
        );

        // Redimensionar foto del jugador a 300x300
        $photoImage->cover(300, 300);

        // ================================================
        // CANVAS DEL CROMO (460x620 px - proporción estándar FIFA)
        // ================================================
        $canvas = $manager->create(460, 620);

        // Fondo degradado usando color del equipo
        $team    = $subject->team;
        $country = $team?->country;
        $bgColor = $country?->primary_color ?? '#1a1a2e';
        $bgColor2= $country?->secondary_color ?? '#16213e';

        // Fondo
        $canvas->fill($bgColor);

        // Banda inferior decorativa
        $canvas->drawRectangle(0, 480, function($draw) use ($bgColor2) {
            $draw->size(460, 140);
            $draw->background($bgColor2);
        });

        // Foto del jugador (centrada, con marco)
        $canvas->place($photoImage->toWebp(), 'top-center', 0, 40);

        // Escudo del país
        if ($country?->shield_url) {
            $shield = $manager->read($storage->getStream($country->shield_url));
            $shield->scale(width: 60);
            $canvas->place($shield, 'top-left', 20, 20);
        }

        // Número del jugador (estilo álbum Panini)
        $canvas->text(
            (string) str_pad($subject->player_number, 3, '0', STR_PAD_LEFT),
            430, 30,
            function($font) {
                $font->size(28);
                $font->color('#ffffff');
                $font->align('right');
                $font->valign('top');
                $font->bold();
            }
        );

        // Nombre del jugador
        $canvas->text(
            strtoupper($subject->first_name),
            230, 490,
            function($font) {
                $font->size(22);
                $font->color('#ffffff');
                $font->align('center');
                $font->bold();
            }
        );
        $canvas->text(
            strtoupper($subject->last_name),
            230, 520,
            function($font) {
                $font->size(18);
                $font->color('#ffffff');
                $font->align('center');
            }
        );

        // País
        $canvas->text(
            strtoupper($country?->name ?? ''),
            230, 548,
            function($font) {
                $font->size(13);
                $font->color('#ffffff');
                $font->align('center');
            }
        );

        // QR Code
        $qrData = json_encode([
            'id'     => $subject->id,
            'slug'   => $subject->tenant->slug,
            'number' => $subject->player_number,
        ]);
        $qrImage = $this->generateQr($qrData);
        $canvas->place(
            $manager->read($qrImage)->scale(width: 70),
            'bottom-right', 10, 10
        );

        // ================================================
        // GUARDAR PNG y WEBP
        // ================================================
        $pngPath  = "events/{$owner->tenant->slug}/cards/{$card->id}.png";
        $webpPath = "events/{$owner->tenant->slug}/cards/{$card->id}.webp";

        $storage->put($pngPath,  $canvas->toPng()->toString());
        $storage->put($webpPath, $canvas->toWebp()->toString());

        $card->update([
            'card_url_png'  => $storage->url($pngPath),
            'card_url_webp' => $storage->url($webpPath),
        ]);

        // Actualizar también el cromo del propio sujeto si no lo tiene
        if (! $subject->card_url_png) {
            $subject->update([
                'card_url_png'  => $storage->url($pngPath),
                'card_url_webp' => $storage->url($webpPath),
            ]);
        }

        Log::info("Card generated: {$card->id}");
    }

    private function generateQr(string $data): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(70),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        return $writer->writeString($data);
    }

    public function failed(\Throwable $e): void
    {
        Log::error("GenerateCardJob failed for card {$this->cardId}: " . $e->getMessage());
    }
}

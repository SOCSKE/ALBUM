<?php

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use App\Http\Requests\Player\CaptureCardRequest;
use App\Http\Resources\AlbumCardResource;
use App\Services\CardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CardController extends Controller
{
    public function __construct(private CardService $cardService) {}

    /**
     * GET /game/{slug}/cards
     * Listar cromos del jugador autenticado.
     */
    public function index(Request $request): JsonResponse
    {
        $player = Auth::guard('players')->user();

        $cards = $player->ownedCards()
            ->with('subject:id,first_name,last_name,player_number,card_url_webp')
            ->where('face_match_status', 'approved')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'data'  => AlbumCardResource::collection($cards->items()),
            'meta'  => [
                'total'          => $cards->total(),
                'album_progress' => $player->album_progress,
                'page'           => $cards->currentPage(),
                'last_page'      => $cards->lastPage(),
            ],
        ]);
    }

    /**
     * GET /game/{slug}/cards/{id}
     */
    public function show(string $eventSlug, string $id): JsonResponse
    {
        $player = Auth::guard('players')->user();

        $card = $player->ownedCards()
            ->with('subject.team.country')
            ->where('id', $id)
            ->firstOrFail();

        return response()->json(new AlbumCardResource($card));
    }

    /**
     * POST /game/{slug}/cards/capture
     * Subir foto para generar nuevo cromo.
     */
    public function capture(CaptureCardRequest $request, string $eventSlug): JsonResponse
    {
        $player = Auth::guard('players')->user();

        $card = $this->cardService->capture(
            owner:      $player,
            subjectId:  $request->input('subject_id'),
            photo:      $request->file('photo'),
            ipAddress:  $request->ip(),
            deviceInfo: [
                'user_agent' => $request->userAgent(),
                'platform'   => $request->input('platform'),
            ]
        );

        $message = $card->face_match_status === 'approved'
            ? '¡Cromo desbloqueado exitosamente!'
            : 'Foto enviada para revisión manual.';

        return response()->json([
            'message' => $message,
            'card'    => new AlbumCardResource($card),
            'status'  => $card->face_match_status,
        ], 201);
    }

    /**
     * GET /game/{slug}/cards/{id}/download
     * Descargar cromo en PNG o WEBP.
     */
    public function download(Request $request, string $eventSlug, string $id): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $player = Auth::guard('players')->user();

        $card = $player->ownedCards()
            ->where('id', $id)
            ->where('face_match_status', 'approved')
            ->firstOrFail();

        $format = $request->query('format', 'webp');
        $url    = $format === 'png' ? $card->card_url_png : $card->card_url_webp;

        $subject  = $card->subject;
        $filename = "cromo-{$subject->player_number}-{$subject->last_name}.{$format}";

        return response()->streamDownload(function () use ($url) {
            echo file_get_contents($url);
        }, $filename, [
            'Content-Type'        => "image/{$format}",
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}

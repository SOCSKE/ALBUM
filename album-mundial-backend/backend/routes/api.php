<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PlayerAuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\CountryController;
use App\Http\Controllers\Admin\PaymentGatewayController;
use App\Http\Controllers\Admin\GlobalStatsController;
use App\Http\Controllers\Organizer\EventController;
use App\Http\Controllers\Organizer\PlayerManageController;
use App\Http\Controllers\Organizer\TeamController as OrganizerTeamController;
use App\Http\Controllers\Organizer\ReportController;
use App\Http\Controllers\Player\AlbumController;
use App\Http\Controllers\Player\CardController;
use App\Http\Controllers\Player\TradeController;
use App\Http\Controllers\Player\RankingController;
use App\Http\Controllers\Player\ProfileController;
use App\Http\Controllers\Payment\WebhookController;
use App\Http\Controllers\Payment\CheckoutController;
use App\Http\Controllers\Public\EventPublicController;

// ============================================================
// WEBHOOKS DE PAGO (sin auth, verificación por firma)
// ============================================================
Route::prefix('webhooks')->group(function () {
    Route::post('/stripe',   [WebhookController::class, 'stripe']);
    Route::post('/paypal',   [WebhookController::class, 'paypal']);
    Route::post('/kushki',   [WebhookController::class, 'kushki']);
    Route::post('/payphone', [WebhookController::class, 'payphone']);
});

// ============================================================
// ENDPOINTS PÚBLICOS
// ============================================================
Route::prefix('public')->group(function () {
    Route::get('/events/{slug}', [EventPublicController::class, 'show']);
    Route::get('/events/{slug}/teams', [EventPublicController::class, 'teams']);
});

// ============================================================
// AUTH - SUPERADMIN / ORGANIZADORES
// ============================================================
Route::prefix('auth')->group(function () {
    Route::post('/login',          [AuthController::class, 'login']);
    Route::post('/register',       [AuthController::class, 'register']);
    Route::post('/refresh',        [AuthController::class, 'refresh']);
    Route::post('/forgot-password',[AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    Route::middleware('auth:api')->group(function () {
        Route::post('/logout',     [AuthController::class, 'logout']);
        Route::get('/me',          [AuthController::class, 'me']);
        Route::post('/2fa/enable', [AuthController::class, 'enable2FA']);
        Route::post('/2fa/verify', [AuthController::class, 'verify2FA']);
    });
});

// AUTH - JUGADORES (por tenant/evento)
Route::prefix('player-auth/{eventSlug}')->group(function () {
    Route::post('/login',    [PlayerAuthController::class, 'login']);
    Route::post('/register', [PlayerAuthController::class, 'register']);
    Route::post('/refresh',  [PlayerAuthController::class, 'refresh']);

    Route::middleware('auth:players')->group(function () {
        Route::post('/logout', [PlayerAuthController::class, 'logout']);
        Route::get('/me',      [PlayerAuthController::class, 'me']);
    });
});

// ============================================================
// SUPERADMIN
// ============================================================
Route::prefix('admin')
    ->middleware(['auth:api', 'role:superadmin'])
    ->group(function () {

    // Dashboard global
    Route::get('/dashboard',        [DashboardController::class, 'index']);
    Route::get('/stats',            [GlobalStatsController::class, 'index']);

    // Eventos (tenants)
    Route::apiResource('tenants',   TenantController::class);
    Route::post('/tenants/{id}/suspend', [TenantController::class, 'suspend']);
    Route::post('/tenants/{id}/activate',[TenantController::class, 'activate']);

    // Países
    Route::apiResource('countries', CountryController::class);
    Route::post('/countries/{id}/toggle', [CountryController::class, 'toggle']);

    // Pasarelas de pago
    Route::get('/payment-gateways',           [PaymentGatewayController::class, 'index']);
    Route::put('/payment-gateways/{gateway}', [PaymentGatewayController::class, 'update']);
    Route::post('/payment-gateways/{gateway}/activate', [PaymentGatewayController::class, 'activate']);

    // Pagos globales
    Route::get('/payments',         [\App\Http\Controllers\Admin\PaymentController::class, 'index']);
    Route::get('/payments/{id}',    [\App\Http\Controllers\Admin\PaymentController::class, 'show']);

    // Revisión manual de cromos
    Route::get('/cards/pending-review', [\App\Http\Controllers\Admin\CardReviewController::class, 'index']);
    Route::post('/cards/{id}/approve',  [\App\Http\Controllers\Admin\CardReviewController::class, 'approve']);
    Route::post('/cards/{id}/reject',   [\App\Http\Controllers\Admin\CardReviewController::class, 'reject']);
});

// ============================================================
// ORGANIZADOR
// ============================================================
Route::prefix('organizer')
    ->middleware(['auth:api', 'role:organizer'])
    ->group(function () {

    // Eventos propios
    Route::get('/events',            [EventController::class, 'index']);
    Route::post('/events',           [EventController::class, 'store']);
    Route::get('/events/{id}',       [EventController::class, 'show']);
    Route::put('/events/{id}',       [EventController::class, 'update']);
    Route::post('/events/{id}/price',[EventController::class, 'calculatePrice']);

    // Pago del evento
    Route::post('/events/{id}/checkout',  [CheckoutController::class, 'initiate']);
    Route::get ('/events/{id}/payment',   [CheckoutController::class, 'status']);

    // Jugadores del evento
    Route::get ('/events/{id}/players',           [PlayerManageController::class, 'index']);
    Route::delete('/events/{id}/players/{playerId}',[PlayerManageController::class, 'remove']);
    Route::post('/events/{id}/players/{playerId}/assign-team', [PlayerManageController::class, 'assignTeam']);

    // Equipos del evento
    Route::get ('/events/{id}/teams',          [OrganizerTeamController::class, 'index']);
    Route::post('/events/{id}/teams',          [OrganizerTeamController::class, 'store']);
    Route::put ('/events/{id}/teams/{teamId}', [OrganizerTeamController::class, 'update']);
    Route::post('/events/{id}/teams/auto-assign', [OrganizerTeamController::class, 'autoAssign']);

    // Reportes y exportaciones
    Route::get('/events/{id}/reports/players',    [ReportController::class, 'players']);
    Route::get('/events/{id}/reports/albums',     [ReportController::class, 'albums']);
    Route::get('/events/{id}/reports/teams',      [ReportController::class, 'teams']);
    Route::get('/events/{id}/export/pdf',         [ReportController::class, 'exportPdf']);
    Route::get('/events/{id}/export/excel',       [ReportController::class, 'exportExcel']);
    Route::get('/events/{id}/export/csv',         [ReportController::class, 'exportCsv']);
});

// ============================================================
// JUGADOR (por evento/tenant)
// ============================================================
Route::prefix('game/{eventSlug}')
    ->middleware(['auth:players', 'tenant.active'])
    ->group(function () {

    // Perfil
    Route::get ('/profile',         [ProfileController::class, 'show']);
    Route::put ('/profile',         [ProfileController::class, 'update']);
    Route::post('/profile/selfie',  [ProfileController::class, 'updateSelfie']);

    // Álbum personal
    Route::get('/album',                    [AlbumController::class, 'index']);
    Route::get('/album/team',               [AlbumController::class, 'myTeam']);
    Route::get('/album/global',             [AlbumController::class, 'global']);
    Route::get('/album/progress',           [AlbumController::class, 'progress']);

    // Cromos
    Route::get ('/cards',                   [CardController::class, 'index']);
    Route::get ('/cards/{id}',              [CardController::class, 'show']);
    Route::post('/cards/capture',           [CardController::class, 'capture']); // Subir foto y generar cromo
    Route::get ('/cards/{id}/download',     [CardController::class, 'download']);

    // Intercambio de cromos
    Route::get ('/trades',                  [TradeController::class, 'index']);
    Route::post('/trades',                  [TradeController::class, 'create']);
    Route::post('/trades/{id}/accept',      [TradeController::class, 'accept']);
    Route::post('/trades/{id}/reject',      [TradeController::class, 'reject']);
    Route::post('/trades/{id}/cancel',      [TradeController::class, 'cancel']);

    // Rankings
    Route::get('/rankings/individual',      [RankingController::class, 'individual']);
    Route::get('/rankings/teams',           [RankingController::class, 'teams']);
    Route::get('/rankings/speed',           [RankingController::class, 'speed']);
    Route::get('/rankings/general',         [RankingController::class, 'general']);
    Route::get('/rankings/my-position',     [RankingController::class, 'myPosition']);

    // Jugadores del evento (para buscar con quién tomarse foto)
    Route::get('/players',                  [\App\Http\Controllers\Player\PlayersController::class, 'index']);
    Route::get('/players/{playerId}/card',  [\App\Http\Controllers\Player\PlayersController::class, 'card']);
});

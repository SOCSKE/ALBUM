<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de Multi-Tenancy.
 *
 * Resuelve el tenant (evento) desde el slug en la URL
 * y lo inyecta en el request y en el contexto de la DB.
 * Aplica a TODAS las rutas de jugadores: /game/{eventSlug}/...
 */
class TenantMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('eventSlug');

        if (! $slug) {
            return response()->json(['message' => 'Evento no especificado.'], 400);
        }

        $tenant = Tenant::where('slug', $slug)->first();

        if (! $tenant) {
            return response()->json(['message' => 'Evento no encontrado.'], 404);
        }

        // Compartir el tenant en todo el ciclo del request
        app()->instance('current_tenant', $tenant);
        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }
}

/**
 * Middleware adicional: valida que el evento esté activo y no vencido.
 */
class TenantActiveMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->attributes->get('tenant')
            ?? app('current_tenant');

        if (! $tenant) {
            return response()->json(['message' => 'Evento no encontrado.'], 404);
        }

        if ($tenant->status === 'suspended') {
            return response()->json(['message' => 'Este evento ha sido suspendido.'], 403);
        }

        if ($tenant->status === 'pending') {
            return response()->json(['message' => 'Este evento aún no ha sido activado. El pago puede estar pendiente.'], 403);
        }

        if ($tenant->status === 'finished' || now()->isAfter($tenant->ends_at)) {
            return response()->json(['message' => 'Este evento ha finalizado.'], 410);
        }

        return $next($request);
    }
}

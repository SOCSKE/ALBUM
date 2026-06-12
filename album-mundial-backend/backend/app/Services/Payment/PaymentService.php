<?php

namespace App\Services\Payment;

use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Payment\Gateways\StripeGateway;
use App\Services\Payment\Gateways\PaypalGateway;
use App\Services\Payment\Gateways\KushkiGateway;
use App\Services\Payment\Gateways\PayphoneGateway;
use App\Services\Payment\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    /**
     * Calcular el costo del evento según cantidad de jugadores.
     * $5 por cada bloque de 25 jugadores.
     */
    public static function calculatePrice(int $maxPlayers): array
    {
        $pricePerBlock  = (float) config('app.price_per_block', 5.00);
        $playersPerBlock = (int)  config('app.players_per_block', 25);

        $blocks    = ceil($maxPlayers / $playersPerBlock);
        $totalUsd  = $blocks * $pricePerBlock;

        return [
            'max_players'    => $maxPlayers,
            'blocks'         => $blocks,
            'price_per_block'=> $pricePerBlock,
            'total_usd'      => $totalUsd,
            'breakdown'      => array_map(
                fn($i) => [
                    'block'      => $i,
                    'from'       => ($i - 1) * $playersPerBlock + 1,
                    'to'         => $i * $playersPerBlock,
                    'price'      => $pricePerBlock,
                ],
                range(1, $blocks)
            ),
        ];
    }

    /**
     * Iniciar proceso de pago para un evento.
     */
    public function initiateCheckout(Tenant $tenant, User $organizer): array
    {
        $pricing = self::calculatePrice($tenant->max_players);
        $gateway = $this->resolveGateway();

        $payment = Payment::create([
            'id'           => Str::uuid(),
            'tenant_id'    => $tenant->id,
            'user_id'      => $organizer->id,
            'gateway'      => $gateway->name(),
            'amount_usd'   => $pricing['total_usd'],
            'player_blocks'=> $pricing['blocks'],
            'max_players'  => $tenant->max_players,
            'status'       => 'pending',
        ]);

        $checkoutData = $gateway->createCheckout([
            'payment_id'   => $payment->id,
            'amount'       => $pricing['total_usd'],
            'currency'     => 'USD',
            'description'  => "Evento {$tenant->name} - {$tenant->max_players} jugadores",
            'customer_email'=> $organizer->email,
            'success_url'  => config('app.frontend_url') . "/organizer/events/{$tenant->id}/success",
            'cancel_url'   => config('app.frontend_url') . "/organizer/events/{$tenant->id}/cancel",
            'metadata'     => [
                'payment_id' => $payment->id,
                'tenant_id'  => $tenant->id,
                'tenant_slug'=> $tenant->slug,
            ],
        ]);

        $payment->update(['gateway_tx_id' => $checkoutData['transaction_id'] ?? null]);

        return [
            'payment_id'   => $payment->id,
            'checkout_url' => $checkoutData['checkout_url'],
            'amount'       => $pricing['total_usd'],
            'gateway'      => $gateway->name(),
        ];
    }

    /**
     * Confirmar pago por webhook.
     */
    public function confirmPayment(string $gatewayName, array $webhookData): void
    {
        $gateway = $this->resolveGatewayByName($gatewayName);
        $verified = $gateway->verifyWebhook($webhookData);

        if (! $verified) {
            throw new \DomainException("Webhook verification failed for {$gatewayName}");
        }

        $txId    = $gateway->extractTransactionId($webhookData);
        $payment = Payment::where('gateway_tx_id', $txId)->firstOrFail();

        DB::transaction(function () use ($payment, $webhookData) {
            $payment->update([
                'status'          => 'completed',
                'webhook_payload' => $webhookData,
                'paid_at'         => now(),
            ]);
            // El trigger MySQL activa el evento automáticamente
        });
    }

    /**
     * Resolver la pasarela activa desde base de datos.
     */
    private function resolveGateway(): PaymentGatewayInterface
    {
        $activeGateway = DB::table('payment_gateways_config')
            ->where('is_active', 1)
            ->first();

        if (! $activeGateway) {
            throw new \RuntimeException('No hay pasarela de pago activa configurada.');
        }

        return $this->resolveGatewayByName($activeGateway->gateway);
    }

    private function resolveGatewayByName(string $name): PaymentGatewayInterface
    {
        return match ($name) {
            'stripe'   => app(StripeGateway::class),
            'paypal'   => app(PaypalGateway::class),
            'kushki'   => app(KushkiGateway::class),
            'payphone' => app(PayphoneGateway::class),
            default    => throw new \InvalidArgumentException("Gateway desconocido: {$name}"),
        };
    }
}

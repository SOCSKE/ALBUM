<?php

namespace App\Services\Payment\Contracts;

interface PaymentGatewayInterface
{
    /**
     * Nombre de la pasarela (stripe, paypal, kushki, payphone)
     */
    public function name(): string;

    /**
     * Crear sesión/enlace de pago.
     * Retorna ['checkout_url' => '...', 'transaction_id' => '...']
     */
    public function createCheckout(array $params): array;

    /**
     * Verificar firma/autenticidad del webhook.
     */
    public function verifyWebhook(array $payload): bool;

    /**
     * Extraer el transaction ID del payload del webhook.
     */
    public function extractTransactionId(array $payload): string;

    /**
     * Verificar el estado de una transacción directamente en la pasarela.
     */
    public function queryTransaction(string $transactionId): array;
}

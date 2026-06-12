<?php

namespace App\Services\Payment\Gateways;

use App\Services\Payment\Contracts\PaymentGatewayInterface;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Webhook;

class StripeGateway implements PaymentGatewayInterface
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret_key'));
    }

    public function name(): string
    {
        return 'stripe';
    }

    public function createCheckout(array $params): array
    {
        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items'           => [[
                'price_data' => [
                    'currency'     => 'usd',
                    'unit_amount'  => (int) ($params['amount'] * 100), // centavos
                    'product_data' => ['name' => $params['description']],
                ],
                'quantity' => 1,
            ]],
            'mode'                 => 'payment',
            'success_url'          => $params['success_url'] . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'           => $params['cancel_url'],
            'customer_email'       => $params['customer_email'],
            'metadata'             => $params['metadata'],
        ]);

        return [
            'checkout_url'   => $session->url,
            'transaction_id' => $session->id,
        ];
    }

    public function verifyWebhook(array $payload): bool
    {
        $signature = request()->header('Stripe-Signature');
        $secret    = config('services.stripe.webhook_secret');

        try {
            Webhook::constructEvent(
                request()->getContent(),
                $signature,
                $secret
            );
            return true;
        } catch (\Exception) {
            return false;
        }
    }

    public function extractTransactionId(array $payload): string
    {
        return $payload['data']['object']['id'] ?? '';
    }

    public function queryTransaction(string $transactionId): array
    {
        $session = Session::retrieve($transactionId);

        return [
            'id'     => $session->id,
            'status' => $session->payment_status === 'paid' ? 'completed' : 'pending',
            'amount' => $session->amount_total / 100,
        ];
    }
}

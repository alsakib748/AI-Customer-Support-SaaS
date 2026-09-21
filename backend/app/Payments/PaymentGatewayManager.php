<?php

namespace App\Payments;

use App\Payments\Contracts\PaymentGateway;
use App\Payments\Exceptions\PaymentGatewayException;
use App\Payments\PayPal\PayPalGateway;
use App\Payments\Stripe\StripeGateway;

class PaymentGatewayManager
{
    /** @var array<string, PaymentGateway> */
    protected array $resolved = [];

    public function driver(?string $provider = null): PaymentGateway
    {
        $provider = $provider ?: config('payment.default', 'stripe');

        if (isset($this->resolved[$provider])) {
            return $this->resolved[$provider];
        }

        $gateway = match ($provider) {
            'stripe' => app(StripeGateway::class),
            'paypal' => app(PayPalGateway::class),
            default  => throw new PaymentGatewayException("Unsupported payment provider: {$provider}"),
        };

        return $this->resolved[$provider] = $gateway;
    }

    public function has(string $provider): bool
    {
        return in_array($provider, ['stripe', 'paypal'], true);
    }

    /**
     * @return array<int, array{key: string, name: string, available: bool}>
     */
    public function availableProviders(): array
    {
        return [
            [
                'key'       => 'stripe',
                'name'      => 'Stripe',
                'available' => !empty(config('payment.providers.stripe.secret')),
            ],
            [
                'key'       => 'paypal',
                'name'      => 'PayPal',
                'available' => !empty(config('payment.providers.paypal.client_id'))
                    && !empty(config('payment.providers.paypal.client_secret')),
            ],
        ];
    }
}

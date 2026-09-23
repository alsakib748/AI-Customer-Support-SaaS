<?php

namespace App\Services\Billing;

use App\Payments\Contracts\PaymentGateway;
use InvalidArgumentException;

class PaymentGatewayManager
{
    /** @var array<string, PaymentGateway> */
    protected array $gateways = [];

    public function register(string $name, PaymentGateway $gateway): self
    {
        $this->gateways[$name] = $gateway;
        return $this;
    }

    public function gateway(?string $name = null): PaymentGateway
    {
        $name = $name ?: config('billing.default_gateway', 'stripe');

        if (!isset($this->gateways[$name])) {
            throw new InvalidArgumentException("Payment gateway [{$name}] is not registered or enabled.");
        }

        return $this->gateways[$name];
    }

    public function driver(?string $name = null): PaymentGateway
    {
        return $this->gateway($name);
    }

    public function hasGateway(string $name): bool
    {
        return isset($this->gateways[$name]);
    }

    public function getEnabledGateways(): array
    {
        return array_keys($this->gateways);
    }
}

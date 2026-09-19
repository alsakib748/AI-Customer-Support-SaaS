<?php

namespace App\Services\Billing;

use App\Contracts\PaymentGatewayInterface;
use InvalidArgumentException;

class PaymentGatewayManager
{
    /** @var array<string, PaymentGatewayInterface> */
    protected array $gateways = [];

    public function register(string $name, PaymentGatewayInterface $gateway): self
    {
        $this->gateways[$name] = $gateway;
        return $this;
    }

    public function gateway(?string $name = null): PaymentGatewayInterface
    {
        $name = $name ?: config('billing.default_gateway', 'stripe');

        if (!isset($this->gateways[$name])) {
            throw new InvalidArgumentException("Payment gateway [{$name}] is not registered or enabled.");
        }

        return $this->gateways[$name];
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

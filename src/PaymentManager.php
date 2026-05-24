<?php

declare(strict_types=1);

namespace Softscholar\Payment;

use InvalidArgumentException;
use Softscholar\Payment\Contracts\PaymentInterface;
use Softscholar\Payment\Services\Gateways\Bkash\Bkash;
use Softscholar\Payment\Services\Gateways\Nagad\Nagad;

/**
 * @method \Softscholar\Payment\Services\Gateways\Nagad\Nagad nagad()
 * @method \Softscholar\Payment\Services\Gateways\Bkash\Bkash bkash()
 */
class PaymentManager
{
    protected array $resolved = [];

    public function gateway(?string $name = null): PaymentInterface
    {
        $name = $name ?? config('spayment.default');

        if (! isset($this->resolved[$name])) {
            $this->resolved[$name] = $this->resolve($name);
        }

        return $this->resolved[$name];
    }

    protected function resolve(string $name): PaymentInterface
    {
        $config = config("spayment.gateways.{$name}");

        if (! $config) {
            throw new InvalidArgumentException("Gateway [{$name}] is not configured.");
        }

        return match ($name) {
            'nagad' => new Nagad($config),
            'bkash' => new Bkash($config),
            default => throw new InvalidArgumentException("Gateway [{$name}] is not supported."),
        };
    }

    /**
     * Dynamically retrieve a gateway instance or forward calls to the default gateway.
     */
    public function __call(string $method, array $parameters)
    {
        if (in_array(strtolower($method), ['nagad', 'bkash'])) {
            return $this->gateway(strtolower($method));
        }

        return $this->gateway()->$method(...$parameters);
    }
}

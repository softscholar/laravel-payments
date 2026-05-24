<?php

namespace Softscholar\Payment\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Softscholar\Payment\Contracts\PaymentInterface gateway(?string $name = null)
 * @method static \Softscholar\Payment\Services\Gateways\Nagad\Nagad nagad()
 * @method static \Softscholar\Payment\Services\Gateways\Bkash\Bkash bkash()
 * @method static string pay(array $data)
 * @method static string checkout(array $data, string $checkoutType = 'regular')
 * @method static mixed refund(array $data = [])
 * @method static mixed cancel(array $data = [])
 * @method static mixed verify(string $tnxId)
 *
 * @see \Softscholar\Payment\PaymentManager
 */
class Payment extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'spayment';
    }
}

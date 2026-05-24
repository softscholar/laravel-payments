<?php

namespace Softscholar\Payment\Contracts;

interface PaymentInterface
{
    public function pay(array $data);

    public function checkout(array $data, string $checkoutType = 'regular'): string;

    public function refund(array $data = []);

    public function cancel(array $data = []);

    public function verify(string $tnxId);
}

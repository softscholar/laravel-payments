<?php

namespace Softscholar\Payment\Contracts;

interface PaymentInterface
{
    public function pay(array $data);

    public function refund();

    public function cancel();

    public function verify(string $tnxId);
}

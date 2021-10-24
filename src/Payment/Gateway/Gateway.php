<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Payment\Gateway;


use Baraja\Shop\Order\Entity\Order;
use Baraja\Shop\Order\Payment\OrderPaymentProvider;

interface Gateway extends OrderPaymentProvider
{
	public function pay(Order $order): GatewayResponse;
}

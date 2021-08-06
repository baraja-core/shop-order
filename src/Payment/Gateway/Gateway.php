<?php

declare(strict_types=1);

namespace Baraja\Shop\Order\Payment\Gateway;


use Baraja\Shop\Order\Entity\Order;

interface Gateway
{
	public function pay(Order $order): GatewayResponse;
}

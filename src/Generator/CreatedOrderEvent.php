<?php

declare(strict_types=1);

namespace Baraja\Shop\Order;


use Baraja\Shop\Order\Entity\Order;

interface CreatedOrderEvent
{
	public function process(Order $order): void;
}

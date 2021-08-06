<?php

declare(strict_types=1);

namespace Baraja\Shop\Order;


interface OrderManagerAccessor
{
	public function get(): OrderManager;
}
